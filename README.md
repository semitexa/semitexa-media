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
- CLI commands: `media:work`, `media:regenerate`, `media:failed-variants`, `media:quota:recalculate`

## Notes

Requires the `ext-imagick` PHP extension. Variant generation runs asynchronously via dedicated workers to avoid blocking request handling.
