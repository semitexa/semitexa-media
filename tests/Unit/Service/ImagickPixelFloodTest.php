<?php

declare(strict_types=1);

namespace Semitexa\Media\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Media\Application\Service\ImagickImageProcessor;
use Semitexa\Media\Domain\Exception\MediaProcessingException;

/**
 * A "pixel flood" file: a few dozen bytes of PNG that declare a huge size.
 * Decoding it would allocate the whole pixel buffer before any size check;
 * it must be refused from its header alone.
 */
final class ImagickPixelFloodTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(\Imagick::class)) {
            self::markTestSkipped('Imagick extension is not installed.');
        }
    }

    #[Test]
    public function an_image_declaring_too_many_pixels_is_refused_before_decoding(): void
    {
        // libpng itself refuses absurd widths, so the flood is scaled down to
        // a lowered limit: 200x200 declared against 10000 pixels allowed. The
        // IDAT is empty, so only a header-only check can produce this message.
        $processor = new ImagickImageProcessor();
        (new \ReflectionProperty($processor, 'maxPixels'))->setValue($processor, 10_000);

        $this->expectException(MediaProcessingException::class);
        $this->expectExceptionMessageMatches('/200x200 \\(40000 pixels.*10000-pixel limit/');

        $processor->inspect(self::pngDeclaring(200, 200));
    }

    #[Test]
    public function an_ordinary_image_is_still_inspected(): void
    {
        $image = new \Imagick();
        $image->newImage(32, 16, 'white');
        $image->setImageFormat('png');

        $metadata = (new ImagickImageProcessor())->inspect($image->getImageBlob());

        self::assertSame(32, $metadata->width);
        self::assertSame(16, $metadata->height);
    }

    private static function pngDeclaring(int $width, int $height): string
    {
        $chunk = static function (string $type, string $data): string {
            return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
        };

        return "\x89PNG\r\n\x1a\n"
            . $chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            . $chunk('IDAT', (string) gzcompress(''))
            . $chunk('IEND', '');
    }
}
