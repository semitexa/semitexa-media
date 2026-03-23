# semitexa-media

Image-first media asset management for Semitexa. Provides canonical asset records, metadata extraction, named collections, async variant generation, WebP/JPEG quality presets, tenant-aware quotas, and CDN-ready delivery URLs.

## Requirements

- PHP 8.4+
- `ext-imagick` (Imagick PHP extension)
- `semitexa/storage`
- `semitexa/orm`
- `semitexa/tenancy`

## Getting Started

See `docs/GETTING_STARTED.md` for installation and integration guidance.

## Worker

Run the dedicated media variant worker:

```bash
php bin/semitexa media:work
php bin/semitexa media:work rabbitmq media
```

## Operations

```bash
# Regenerate variants for one asset
php bin/semitexa media:regenerate <asset-id>

# Regenerate one variant key
php bin/semitexa media:regenerate <asset-id> --variant=thumb

# Regenerate all assets in a collection
php bin/semitexa media:regenerate --collection=avatars --tenant=<tenant-id>

# List failed variants
php bin/semitexa media:failed-variants

# Recalculate quota usage
php bin/semitexa media:quota:recalculate <tenant-id> --bucket=default
```
