<?php

declare(strict_types=1);

namespace Semitexa\Media\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Media\Application\Service\ImagickImageProcessor;
use Semitexa\Media\Application\Service\MediaTransformationService;
use Semitexa\Media\Application\Service\VariantStoragePathBuilder;
use Semitexa\Media\Domain\Enum\MediaKind;
use Semitexa\Media\Domain\Enum\MediaVisibility;
use Semitexa\Media\Domain\Enum\OutputFormat;
use Semitexa\Media\Domain\Enum\ResizeMode;
use Semitexa\Media\Domain\Model\ImageTransformPreset;
use Semitexa\Media\Domain\Model\MediaCollection;
use Semitexa\Media\Domain\Model\MediaVariant;
use Semitexa\Storage\Driver\LocalDriver;

/**
 * A variant actually generated: real Imagick, real local storage, the real
 * transformation service (tk-parity-proof-gaps). Until this test the media
 * suite proved leases, paths and the pixel-flood guard — never that a
 * thumbnail comes out the size, shape and format its preset asks for.
 */
final class MediaVariantGenerationTest extends TestCase
{
    private string $root;

    private LocalDriver $storage;

    protected function setUp(): void
    {
        if (!class_exists(\Imagick::class)) {
            self::markTestSkipped('Imagick extension is not installed.');
        }
        $this->root = sys_get_temp_dir() . '/semitexa-media-variants-' . bin2hex(random_bytes(4));
        mkdir($this->root, 0o755, true);
        $this->storage = new LocalDriver($this->root);
    }

    protected function tearDown(): void
    {
        if (isset($this->root) && is_dir($this->root)) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($this->root);
        }
    }

    #[Test]
    public function a_thumbnail_is_written_to_storage_at_its_preset_size_and_format(): void
    {
        $this->storage->put('originals/photo.png', self::image(200, 400), 'image/png');

        $result = $this->service()->generateVariant('originals/photo.png', 'a-1', 't-1', $this->variant('thumb'), $this->collection(
            new ImageTransformPreset('thumb', ResizeMode::Fit, 100, 100, OutputFormat::Webp, 80),
        ));

        self::assertTrue($result->success, (string) $result->errorMessage);
        self::assertSame('media/t-1/gallery/a-1/thumb.webp', $result->storagePath);
        self::assertSame('image/webp', $result->mimeType);
        $stored = $this->storage->get('media/t-1/gallery/a-1/thumb.webp');
        self::assertNotNull($stored);
        self::assertSame(strlen($stored), $result->byteSize);
        self::assertSame(['WEBP', 50, 100], self::describe($stored), 'fit keeps the 1:2 shape inside 100x100');
        self::assertSame([50, 100], [$result->actualWidth, $result->actualHeight], 'what was made, not what was asked for');
    }

    #[Test]
    public function cover_fills_the_box_and_crops_the_overflow(): void
    {
        $out = $this->processor()->transform(self::image(400, 200), new ImageTransformPreset('square', ResizeMode::Cover, 100, 100, OutputFormat::Png, 90));

        self::assertSame(['PNG', 100, 100], self::describe($out));
    }

    #[Test]
    public function contain_letterboxes_onto_the_background_fill(): void
    {
        $out = $this->processor()->transform(self::image(400, 200, 'red'), new ImageTransformPreset('box', ResizeMode::Contain, 100, 100, OutputFormat::Png, 90, backgroundFill: '#0000ff'));

        self::assertSame(['PNG', 100, 100], self::describe($out));
        self::assertSame('blue', self::colourAt($out, 50, 5), 'above the picture: the fill');
        self::assertSame('red', self::colourAt($out, 50, 50), 'in the middle: the picture');
    }

    #[Test]
    public function an_exif_rotated_photo_comes_out_upright(): void
    {
        // A camera held upright stores a landscape frame and EXIF "rotate 90°".
        // Imagick does not write the orientation into a JPEG it encodes, so
        // the EXIF segment is spliced in by hand: one IFD entry, 0x0112 = 6.
        $photo = new \Imagick();
        $photo->newImage(40, 20, 'white');
        $photo->setImageFormat('jpeg');
        $jpeg = $photo->getImageBlob();
        $exif = "Exif\0\0II*\0" . pack('V', 8) . pack('v', 1) . pack('vvVvv', 0x0112, 3, 1, \Imagick::ORIENTATION_RIGHTTOP, 0) . pack('V', 0);
        $rotated = substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif . substr($jpeg, 2);

        $out = $this->processor()->transform($rotated, new ImageTransformPreset('full', ResizeMode::Fit, null, null, OutputFormat::Jpeg, 85));

        self::assertSame(['JPEG', 20, 40], self::describe($out));
    }

    #[Test]
    public function a_transparent_png_turned_into_a_jpeg_gets_the_fill_not_black(): void
    {
        $logo = new \Imagick();
        $logo->newImage(40, 40, new \ImagickPixel('transparent'));
        $logo->setImageFormat('png');

        $out = $this->processor()->transform($logo->getImageBlob(), new ImageTransformPreset('card', ResizeMode::Fit, 20, 20, OutputFormat::Jpeg, 85));

        self::assertSame('white', self::colourAt($out, 10, 10), 'JPEG has no alpha: transparency must become the fill');
    }

    #[Test]
    public function a_missing_original_and_an_unknown_preset_fail_with_a_reason_and_write_nothing(): void
    {
        $collection = $this->collection(new ImageTransformPreset('thumb', ResizeMode::Fit, 100, 100, OutputFormat::Webp, 80));

        $missing = $this->service()->generateVariant('originals/none.png', 'a-1', 't-1', $this->variant('thumb'), $collection);
        $unknown = $this->service()->generateVariant('originals/none.png', 'a-1', 't-1', $this->variant('poster'), $collection);

        self::assertSame([false, 'original_not_found'], [$missing->success, $missing->errorCode]);
        self::assertSame([false, 'preset_not_found'], [$unknown->success, $unknown->errorCode]);
        self::assertFalse($this->storage->exists('media/t-1/gallery/a-1/thumb.webp'));
    }

    #[Test]
    public function an_original_that_is_not_an_image_is_a_processing_error(): void
    {
        $this->storage->put('originals/notes.png', 'this is not a png', 'image/png');

        $result = $this->service()->generateVariant('originals/notes.png', 'a-1', 't-1', $this->variant('thumb'), $this->collection(
            new ImageTransformPreset('thumb', ResizeMode::Fit, 100, 100, OutputFormat::Webp, 80),
        ));

        self::assertSame([false, 'processing_error'], [$result->success, $result->errorCode]);
    }

    private function processor(): ImagickImageProcessor
    {
        return new ImagickImageProcessor();
    }

    private function service(): MediaTransformationService
    {
        $service = new MediaTransformationService();
        foreach (['imageProcessor' => $this->processor(), 'pathBuilder' => new VariantStoragePathBuilder(), 'storage' => $this->storage] as $property => $value) {
            (new \ReflectionProperty(MediaTransformationService::class, $property))->setValue($service, $value);
        }

        return $service;
    }

    private function collection(ImageTransformPreset ...$presets): MediaCollection
    {
        return new MediaCollection('gallery', MediaKind::Image, MediaVisibility::Public, 'default', ['image/png', 'image/jpeg'], $presets);
    }

    private function variant(string $key): MediaVariant
    {
        return new MediaVariant(id: 'v-' . $key, tenantId: 't-1', mediaAssetId: 'a-1', variantKey: $key);
    }

    private static function image(int $width, int $height, string $colour = 'gray'): string
    {
        $image = new \Imagick();
        $image->newImage($width, $height, new \ImagickPixel($colour));
        $image->setImageFormat('png');

        return $image->getImageBlob();
    }

    /** @return array{string, int, int} format, width, height — read back from the bytes */
    private static function describe(string $bytes): array
    {
        $image = new \Imagick();
        $image->readImageBlob($bytes);

        return [$image->getImageFormat(), $image->getImageWidth(), $image->getImageHeight()];
    }

    private static function colourAt(string $bytes, int $x, int $y): string
    {
        $image = new \Imagick();
        $image->readImageBlob($bytes);
        $rgb = $image->getImagePixelColor($x, $y)->getColor();

        return match (true) {
            $rgb['r'] > 200 && $rgb['g'] > 200 && $rgb['b'] > 200 => 'white',
            $rgb['r'] < 50 && $rgb['g'] < 50 && $rgb['b'] < 50 => 'black',
            $rgb['r'] > 200 && $rgb['g'] < 50 && $rgb['b'] < 50 => 'red',
            $rgb['b'] > 200 && $rgb['r'] < 50 && $rgb['g'] < 50 => 'blue',
            default => sprintf('rgb(%d,%d,%d)', $rgb['r'], $rgb['g'], $rgb['b']),
        };
    }
}
