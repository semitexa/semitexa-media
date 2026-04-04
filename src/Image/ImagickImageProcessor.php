<?php

declare(strict_types=1);

namespace Semitexa\Media\Image;

use Semitexa\Core\Attribute\SatisfiesServiceContract;
use Semitexa\Media\Contract\ImageProcessorInterface;
use Semitexa\Media\Domain\Exception\MediaProcessingException;
use Semitexa\Media\Enum\ResizeMode;
use Semitexa\Media\Value\ImageMetadata;
use Semitexa\Media\Value\ImageTransformPreset;

#[SatisfiesServiceContract(of: ImageProcessorInterface::class)]
final class ImagickImageProcessor implements ImageProcessorInterface
{
    public function isAvailable(): bool
    {
        return class_exists(\Imagick::class);
    }

    public function inspect(string $bytes): ImageMetadata
    {
        $imagick = $this->createImagick($bytes);

        try {
            $width       = $imagick->getImageWidth();
            $height      = $imagick->getImageHeight();
            $mimeType    = $this->resolveImagickMimeType($imagick->getImageFormat());
            $orientation = $this->resolveOrientation($imagick);
            $sha256      = hash('sha256', $bytes);

            return new ImageMetadata(
                width:       $width,
                height:      $height,
                mimeType:    $mimeType,
                sha256:      $sha256,
                byteSize:    strlen($bytes),
                orientation: $orientation,
            );
        } catch (MediaProcessingException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new MediaProcessingException("Imagick inspect failed: {$e->getMessage()}", $e);
        } finally {
            $imagick->clear();
        }
    }

    public function transform(string $bytes, ImageTransformPreset $preset): string
    {
        $imagick = $this->createImagick($bytes);

        try {
            // Auto-orient based on EXIF before any resize
            $imagick->autoOrient();

            $this->applyResize($imagick, $preset);

            if ($preset->stripMetadata) {
                $imagick->stripImage();
            }

            $result = $this->encode($imagick, $preset);
        } catch (MediaProcessingException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new MediaProcessingException("Imagick transform failed: {$e->getMessage()}", $e);
        } finally {
            $imagick->clear();
        }

        return $result;
    }

    private function applyResize(\Imagick $imagick, ImageTransformPreset $preset): void
    {
        $srcWidth  = $imagick->getImageWidth();
        $srcHeight = $imagick->getImageHeight();
        $dstWidth  = $preset->width  ?? $srcWidth;
        $dstHeight = $preset->height ?? $srcHeight;

        if ($srcWidth === $dstWidth && $srcHeight === $dstHeight) {
            return;
        }

        match ($preset->mode) {
            ResizeMode::Fit     => $this->resizeFit($imagick, $dstWidth, $dstHeight),
            ResizeMode::Cover   => $this->resizeCover($imagick, $dstWidth, $dstHeight),
            ResizeMode::Contain => $this->resizeContain($imagick, $dstWidth, $dstHeight, $preset->backgroundFill),
        };
    }

    private function resizeFit(\Imagick $imagick, int $width, int $height): void
    {
        $imagick->thumbnailImage($width, $height, true);
    }

    private function resizeCover(\Imagick $imagick, int $width, int $height): void
    {
        $srcWidth  = $imagick->getImageWidth();
        $srcHeight = $imagick->getImageHeight();

        $scaleX = $width  / $srcWidth;
        $scaleY = $height / $srcHeight;
        $scale  = max($scaleX, $scaleY);

        $scaledWidth  = (int) round($srcWidth  * $scale);
        $scaledHeight = (int) round($srcHeight * $scale);

        $imagick->resizeImage($scaledWidth, $scaledHeight, \Imagick::FILTER_LANCZOS, 1);

        $offsetX = (int) round(($scaledWidth  - $width)  / 2);
        $offsetY = (int) round(($scaledHeight - $height) / 2);

        $imagick->cropImage($width, $height, $offsetX, $offsetY);
        $imagick->setImagePage($width, $height, 0, 0);
    }

    private function resizeContain(\Imagick $imagick, int $width, int $height, ?string $backgroundFill): void
    {
        $imagick->thumbnailImage($width, $height, true);

        $actualWidth  = $imagick->getImageWidth();
        $actualHeight = $imagick->getImageHeight();

        if ($actualWidth === $width && $actualHeight === $height) {
            return;
        }

        $background = $backgroundFill ?? '#ffffff';
        $canvas     = new \Imagick();
        $canvas->newImage($width, $height, new \ImagickPixel($background));
        $canvas->setImageFormat($imagick->getImageFormat());

        $offsetX = (int) round(($width  - $actualWidth)  / 2);
        $offsetY = (int) round(($height - $actualHeight) / 2);

        $canvas->compositeImage($imagick, \Imagick::COMPOSITE_OVER, $offsetX, $offsetY);

        $imagick->clear();
        $imagick->addImage($canvas);
        $canvas->clear();
    }

    private function encode(\Imagick $imagick, ImageTransformPreset $preset): string
    {
        $format  = strtoupper($preset->format->value === 'jpeg' ? 'JPEG' : strtoupper($preset->format->value));
        $quality = $preset->quality;

        $imagick->setImageFormat($format);
        $imagick->setImageCompressionQuality($quality);

        return $imagick->getImageBlob();
    }

    private function resolveImagickMimeType(string $format): string
    {
        return match (strtolower($format)) {
            'jpeg', 'jpg' => 'image/jpeg',
            'png'         => 'image/png',
            'webp'        => 'image/webp',
            'gif'         => 'image/gif',
            'bmp'         => 'image/bmp',
            'tiff', 'tif' => 'image/tiff',
            default       => 'image/' . strtolower($format),
        };
    }

    private function resolveOrientation(\Imagick $imagick): ?string
    {
        try {
            $orientation = $imagick->getImageOrientation();

            return match ($orientation) {
                \Imagick::ORIENTATION_TOPLEFT     => 'landscape',
                \Imagick::ORIENTATION_LEFTTOP,
                \Imagick::ORIENTATION_RIGHTTOP,
                \Imagick::ORIENTATION_LEFTBOTTOM  => 'portrait',
                default                           => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }

    private function createImagick(string $bytes): \Imagick
    {
        if (!$this->isAvailable()) {
            throw new MediaProcessingException('Imagick extension is not available.');
        }

        if ($bytes === '') {
            throw new MediaProcessingException('Cannot process empty image bytes.');
        }

        try {
            $imagick = new \Imagick();
            $imagick->readImageBlob($bytes);

            return $imagick;
        } catch (\ImagickException $e) {
            throw new MediaProcessingException("Failed to read image: {$e->getMessage()}", $e);
        }
    }
}
