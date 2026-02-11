# Archive Images Sync Guide

This guide explains how to sync archive images from external HDD to the server.

## Overview

Two tools are available for syncing archive images:

1. **Bash Script**: `/var/www/deschide_news_app/scripts/sync-archive-images.sh`
2. **Symfony Command**: `app:archive:sync-images`

Both use `rsync` internally for efficient, incremental copying.

## Source Locations

| Source | Path | Files | Size |
|--------|------|-------|------|
| **Alpha** (Newscoop) | `/mnt/d/ext-hdd/alpha/` | 155,323 | 18GB |
| **Beta** | `/mnt/d/ext-hdd/beta/images/` | 40,526 | 3.3GB |

## Target Locations

| Target | Path |
|--------|------|
| **Alpha** | `/var/www/deschide_news_app/apps/backend/public/uploads/images/alpha/` |
| **Beta** | `/var/www/deschide_news_app/apps/backend/public/uploads/images/beta/` |

## Usage

### Bash Script

```bash
# Navigate to project root
cd /var/www/deschide_news_app

# Preview alpha sync (dry-run)
./scripts/sync-archive-images.sh --source=alpha --dry-run

# Sync alpha images
./scripts/sync-archive-images.sh --source=alpha

# Sync beta images
./scripts/sync-archive-images.sh --source=beta

# Sync all images
./scripts/sync-archive-images.sh --source=all

# Sync with detailed progress
./scripts/sync-archive-images.sh --source=alpha --verbose --stats

# Show help
./scripts/sync-archive-images.sh --help
```

#### Bash Script Options

- `--source=alpha|beta|all` - Which source to sync (required)
- `--dry-run` - Preview changes without copying
- `--verbose` - Show detailed progress
- `--stats` - Show detailed statistics

### Symfony Command

```bash
# Navigate to backend directory
cd /var/www/deschide_news_app/apps/backend

# Preview alpha sync (dry-run)
symfony console app:archive:sync-images --source=alpha --dry-run

# Sync alpha images
symfony console app:archive:sync-images --source=alpha

# Sync beta images
symfony console app:archive:sync-images --source=beta

# Sync all images (default)
symfony console app:archive:sync-images

# Sync with verification (checksums)
symfony console app:archive:sync-images --source=all --verify

# Sync with detailed progress and stats
symfony console app:archive:sync-images --source=alpha --verbose-rsync --stats

# Show help
symfony console app:archive:sync-images --help
```

#### Symfony Command Options

- `-s, --source=SOURCE` - Which source to sync (alpha, beta, or all) [default: "all"]
- `-d, --dry-run` - Preview changes without copying files
- `--verify` - Verify file integrity after copy using checksums
- `--verbose-rsync` - Show detailed rsync progress
- `--stats` - Show detailed rsync statistics

## Features

### Incremental Sync

Both tools use `rsync` which only copies:
- New files that don't exist in target
- Files that have changed since last sync

This makes subsequent syncs much faster.

### Safety

- **COPY, NOT MOVE**: Source files on HDD remain intact
- **No --delete flag**: Existing files in target are never deleted
- **Dry-run mode**: Preview changes before executing

### Verification

Use `--verify` (Symfony) to enable checksum verification:
- Slower but ensures file integrity
- Compares checksums instead of timestamps
- Recommended for critical data

### Progress Tracking

- File count and size analysis before sync
- Real-time progress display (with `--verbose` options)
- Statistics after sync completion (with `--stats`)

## Example Workflow

### First Time Sync (Large)

```bash
# 1. Preview what will be synced
symfony console app:archive:sync-images --source=alpha --dry-run

# 2. Sync alpha images (will take time - 155K files, 18GB)
symfony console app:archive:sync-images --source=alpha --verbose-rsync --stats

# 3. Sync beta images (smaller - 40K files, 3.3GB)
symfony console app:archive:sync-images --source=beta --verbose-rsync --stats
```

### Incremental Updates

```bash
# Quick sync of any new/changed files
symfony console app:archive:sync-images --source=all
```

### Verification Run

```bash
# Sync with checksums to ensure integrity
symfony console app:archive:sync-images --source=all --verify
```

## Performance Estimates

### Alpha (155,323 files, 18GB)

- **First sync**: ~30-60 minutes (depends on disk speed)
- **Incremental**: ~2-5 minutes (if few changes)

### Beta (40,526 files, 3.3GB)

- **First sync**: ~10-20 minutes
- **Incremental**: ~1-2 minutes

### All (195,849 files, ~21GB)

- **First sync**: ~40-80 minutes
- **Incremental**: ~3-7 minutes

*Note: Times vary based on:*
- External HDD speed (USB 2.0 vs USB 3.0)
- Server disk I/O performance
- Network if syncing over network mount

## Troubleshooting

### rsync not installed

```bash
sudo apt-get update
sudo apt-get install rsync
```

### Permission denied

```bash
# Ensure target directory is writable
sudo chown -R $USER:$USER /var/www/deschide_news_app/apps/backend/public/uploads/images/
chmod -R 755 /var/www/deschide_news_app/apps/backend/public/uploads/images/
```

### External HDD not mounted

```bash
# Check if HDD is mounted
ls -la /mnt/d/ext-hdd/

# If not, mount it (adjust device path)
sudo mount /dev/sdX1 /mnt/d/ext-hdd/
```

### Slow performance

- Use USB 3.0 port if available
- Close other applications using disk I/O
- Run during off-peak hours for server

## Automation

### Cron Job (Daily Incremental Sync)

```bash
# Edit crontab
crontab -e

# Add daily sync at 3 AM
0 3 * * * cd /var/www/deschide_news_app/apps/backend && /usr/local/bin/symfony console app:archive:sync-images --source=all >> /var/log/archive-sync.log 2>&1
```

### Manual Script

```bash
#!/bin/bash
# /var/www/deschide_news_app/scripts/nightly-archive-sync.sh

cd /var/www/deschide_news_app/apps/backend

echo "Starting archive sync at $(date)"

symfony console app:archive:sync-images --source=all --stats

echo "Finished archive sync at $(date)"
```

## File Structure After Sync

```
/var/www/deschide_news_app/apps/backend/public/uploads/images/
├── alpha/
│   ├── cms-image-000000001.jpg
│   ├── cms-image-000000002.png
│   ├── cms-image-000000003.jpg
│   └── ... (155,323 files)
├── beta/
│   ├── 128b2c3bfa5b707366d05786a86eed135d74d141.jpg
│   ├── 16b78b104bb4b96488eaefaf7864e25e27ae6ba3.jpg
│   └── ... (40,526 files)
└── ... (other image directories)
```

## CDN Access

After sync, images are accessible via CDN:

```
# Alpha images
http://127.0.0.1:8082/uploads/images/alpha/cms-image-000000001.jpg

# Beta images
http://127.0.0.1:8082/uploads/images/beta/128b2c3bfa5b707366d05786a86eed135d74d141.jpg
```

## Next Steps

After syncing images:

1. **Update Article-Image Associations**: Link articles to their images
   ```bash
   symfony console app:import:link-article-images
   ```

2. **Generate Thumbnails**: Create responsive image variants
   ```bash
   symfony console app:import:generate-thumbnails
   ```

3. **Index Images in Elasticsearch**: Enable search functionality
   ```bash
   symfony console app:elasticsearch:index-images
   ```

## Related Commands

- `app:archive:process-content` - Process archive content
- `app:import:images` - Import image metadata from Newscoop
- `app:import:generate-thumbnails` - Generate thumbnail variants
- `app:elasticsearch:index-images` - Index images for search

## References

- **Bash Script**: `/var/www/deschide_news_app/scripts/sync-archive-images.sh`
- **Symfony Command**: `/var/www/deschide_news_app/apps/backend/src/Command/Archive/SyncArchiveImagesCommand.php`
- **rsync Documentation**: `man rsync`
