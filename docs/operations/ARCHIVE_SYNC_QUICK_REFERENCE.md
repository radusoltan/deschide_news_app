# Archive Sync Quick Reference

## Quick Commands

### First Time Sync (All Archives)

```bash
# Navigate to backend
cd /var/www/deschide_news_app/apps/backend

# Preview what will be synced (DRY RUN)
symfony console app:archive:sync-images --source=all --dry-run

# Execute full sync with stats
symfony console app:archive:sync-images --source=all --verbose-rsync --stats
```

**Estimated Time**: 40-80 minutes for initial sync (195K files, ~21GB)

### Daily Incremental Sync

```bash
# Quick sync of new/changed files only
symfony console app:archive:sync-images --source=all
```

**Estimated Time**: 2-5 minutes (if few changes)

### Weekly Verification

```bash
# Sync with checksum verification
symfony console app:archive:sync-images --source=all --verify
```

**Estimated Time**: Longer (reads all files for checksums)

## Available Tools

### 1. Symfony Command (Recommended)

```bash
symfony console app:archive:sync-images [options]
```

**Options**:
- `--source=alpha|beta|all` - Which source to sync [default: all]
- `--dry-run` - Preview without copying
- `--verify` - Use checksums for integrity
- `--verbose-rsync` - Show detailed progress
- `--stats` - Show detailed statistics

### 2. Bash Script (Alternative)

```bash
/var/www/deschide_news_app/scripts/sync-archive-images.sh [options]
```

**Options**:
- `--source=alpha|beta|all` - Which source (required)
- `--dry-run` - Preview mode
- `--verbose` - Detailed output
- `--stats` - Statistics

## Archive Sources

| Source | Location | Files | Size | Target |
|--------|----------|-------|------|--------|
| **Alpha** | `/mnt/d/ext-hdd/alpha/` | 155,323 | 18GB | `public/uploads/images/alpha/` |
| **Beta** | `/mnt/d/ext-hdd/beta/images/` | 40,526 | 3.3GB | `public/uploads/images/beta/` |

## Workflow

### Standard Workflow

```bash
# 1. Preview sync
symfony console app:archive:sync-images --source=alpha --dry-run

# 2. Execute sync
symfony console app:archive:sync-images --source=alpha

# 3. Verify results
find /var/www/deschide_news_app/apps/backend/public/uploads/images/alpha -type f | wc -l

# 4. Test CDN access
curl -I http://127.0.0.1:8082/uploads/images/alpha/cms-image-000000001.jpg
```

### Automation (Cron)

```bash
# Edit crontab
crontab -e

# Add nightly sync at 3 AM
0 3 * * * cd /var/www/deschide_news_app/apps/backend && /usr/local/bin/symfony console app:archive:sync-images --source=all >> /var/log/archive-sync.log 2>&1
```

## Troubleshooting

### rsync not installed

```bash
sudo apt-get install rsync
```

### External HDD not mounted

```bash
# Check mount
ls -la /mnt/d/ext-hdd/

# Verify files exist
ls -la /mnt/d/ext-hdd/alpha/ | head -20
ls -la /mnt/d/ext-hdd/beta/images/ | head -20
```

### Permission issues

```bash
# Fix target permissions
sudo chown -R $USER:$USER /var/www/deschide_news_app/apps/backend/public/uploads/images/
chmod -R 755 /var/www/deschide_news_app/apps/backend/public/uploads/images/
```

### Check disk space

```bash
# Available space
df -h /var/www/deschide_news_app/apps/backend/public/uploads/

# Need at least 25GB free for full sync
```

## File Examples

### Alpha (Newscoop) Naming

```
cms-image-000000001.jpg
cms-image-000000002.png
cms-image-000000003.jpg
```

### Beta Naming (Hash-based)

```
128b2c3bfa5b707366d05786a86eed135d74d141.jpg
16b78b104bb4b96488eaefaf7864e25e27ae6ba3.jpg
```

## CDN Access After Sync

```bash
# Alpha images
http://127.0.0.1:8082/uploads/images/alpha/cms-image-000000001.jpg

# Beta images
http://127.0.0.1:8082/uploads/images/beta/128b2c3bfa5b707366d05786a86eed135d74d141.jpg
```

## Key Features

- ✅ **Incremental**: Only copies new/changed files
- ✅ **Safe**: Source files preserved (COPY not MOVE)
- ✅ **Fast**: rsync optimizations
- ✅ **Reliable**: Preserves timestamps and permissions
- ✅ **Monitored**: Progress tracking and statistics
- ✅ **Verifiable**: Optional checksum validation

## Performance Tips

1. **First sync**: Run during off-peak hours (takes 40-80 min)
2. **USB port**: Use USB 3.0 if available for faster transfer
3. **Disk I/O**: Close other applications using disk
4. **Verification**: Only use `--verify` weekly (slower)

## Documentation

- **User Guide**: `/var/www/deschide_news_app/apps/backend/ARCHIVE_IMAGES_SYNC_GUIDE.md`
- **Implementation**: `/var/www/deschide_news_app/docs/ARCHIVE_SYNC_IMPLEMENTATION.md`
- **Bash Script**: `/var/www/deschide_news_app/scripts/sync-archive-images.sh`
- **Symfony Command**: `src/Command/Archive/SyncArchiveImagesCommand.php`

## Next Steps After Sync

```bash
# 1. Link articles to images
symfony console app:archive:link-article-images

# 2. Generate thumbnails
symfony console app:import:generate-thumbnails

# 3. Index images in Elasticsearch
symfony console app:elasticsearch:index-images
```

## Support

For issues or questions, see:
- Full documentation in `ARCHIVE_IMAGES_SYNC_GUIDE.md`
- Implementation details in `docs/ARCHIVE_SYNC_IMPLEMENTATION.md`
