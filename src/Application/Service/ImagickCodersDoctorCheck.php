<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Service;

use Semitexa\Core\Attribute\AsDoctorCheck;
use Semitexa\Core\Contract\DoctorCheckInterface;
use Semitexa\Core\Support\DoctorResult;

/**
 * Alpine ships ImageMagick format coders as separate packages; with only
 * imagemagick-dev installed, Imagick reports no WEBP/JPEG support and every
 * variant transform dies with "Unable to set image format" — invisible until
 * the first real transform. This check makes it a one-line diagnosis.
 */
#[AsDoctorCheck(name: 'media.imagick-coders', package: 'semitexa/media')]
final class ImagickCodersDoctorCheck implements DoctorCheckInterface
{
    private const REQUIRED_FORMATS = ['WEBP', 'JPEG', 'PNG'];

    public function run(): DoctorResult
    {
        if (!class_exists(\Imagick::class)) {
            return DoctorResult::fail(
                'ext-imagick is not installed — semitexa/media cannot generate variants.',
                'Rebuild the app image from the current Semitexa scaffold Dockerfile (pecl install imagick).',
            );
        }

        $missing = [];
        foreach (self::REQUIRED_FORMATS as $format) {
            if (\Imagick::queryFormats($format) === []) {
                $missing[] = $format;
            }
        }

        if ($missing !== []) {
            return DoctorResult::fail(
                'Imagick lacks format coder(s): ' . implode(', ', $missing)
                . ' — variant transforms will fail with "Unable to set image format".',
                'On Alpine: apk add imagemagick-webp imagemagick-jpeg imagemagick-heic '
                . '(baked into the Semitexa scaffold Dockerfile since 2026-07-22), then restart the container.',
            );
        }

        $version = \Imagick::getVersion()['versionString'] ?? 'unknown version';

        return DoctorResult::pass(implode('/', self::REQUIRED_FORMATS) . " coders present ({$version}).");
    }
}
