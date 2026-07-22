# Semitexa Media

Image-first media asset management with async variant generation, tenant-aware quotas, and CDN-ready delivery URLs.

## Purpose

Manages media assets from upload through processing to delivery. Provides canonical asset records, metadata extraction (EXIF, dimensions), named collections, async variant generation (thumbnails, WebP, quality presets), and per-tenant quota tracking.

## Role in Semitexa

Depends on Core, ORM, Storage, and Tenancy. Uses the Scheduler for async variant generation. Storage provides the underlying driver (local or S3/MinIO), while ORM persists asset records and quota usage.

## Key Features

- `ImagickImageProcessor` for image inspection and transformation
- Async variant generation via Scheduler workers
- Named collections for logical asset grouping
- WebP/JPEG quality presets
- Per-tenant quota tracking and enforcement
- CDN-ready delivery URL generation
- ORM-backed asset records (`MediaAssetResource`, `MediaVariantResource`, `MediaQuotaUsageResource`)
- CLI commands: `media:work`, `media:import`, `media:drain`, `media:regenerate`, `media:failed-variants`, `media:quota:recalculate`
- Bulk backfill: `media:import <dir> -c <collection> --tenant <id>` ingests pre-existing files (legacy CMS uploads) with sha256 dedup, `--dry-run`, `--ext` filter, and resumable `--limit` batches
- Broker-less generation: `media:drain` claims queued variants straight from the database and transforms them inline — no queue broker needed; `media:import --sync` does the same right after ingest

## Notes

Requires the `ext-imagick` PHP extension. Variant generation runs asynchronously via dedicated workers to avoid blocking request handling; broker-less setups can use `media:drain` / `media:import --sync` instead.

On Alpine images, ImageMagick format coders ship as separate packages — install `imagemagick-webp imagemagick-jpeg imagemagick-heic` alongside `imagemagick-dev`, otherwise webp/jpeg variant generation fails with "Unable to set image format" (the Semitexa scaffold Dockerfile does this since 2026-07-22).
