# Archive Sync Implementation

## Overview

This document describes the implementation of archive image sync from external HDD to the Deschide News App server, including lessons from the previous sync_arhiva Laravel application.

## Created Tools

### 1. Bash Script: `sync-archive-images.sh`

**Location**: `/var/www/deschide_news_app/scripts/sync-archive-images.sh`

**Purpose**: Standalone shell script for syncing images using rsync

**Features**:
- ✅ Incremental sync (only new/changed files)
- ✅ Dry-run mode for preview
- ✅ Progress tracking and statistics
- ✅ Multi-source support (alpha/beta/all)
- ✅ Safety: COPY not MOVE (source files preserved)
- ✅ Colored output for better readability
- ✅ File counting and size analysis
- ✅ Error handling and validation

**Usage**:
```bash
cd /var/www/deschide_news_app

# Preview sync
./scripts/sync-archive-images.sh --source=alpha --dry-run

# Execute sync
./scripts/sync-archive-images.sh --source=all --verbose --stats
```

### 2. Symfony Command: `app:archive:sync-images`

**Location**: `/var/www/deschide_news_app/apps/backend/src/Command/Archive/SyncArchiveImagesCommand.php`

**Purpose**: Symfony console command wrapping rsync functionality with additional features

**Features**:
- ✅ All bash script features plus:
- ✅ Checksum verification (--verify flag)
- ✅ SymfonyStyle formatted output
- ✅ Symfony Process component integration
- ✅ Better error handling with exceptions
- ✅ Integration with Symfony ecosystem
- ✅ Can be called from other Symfony code

**Usage**:
```bash
cd /var/www/deschide_news_app/apps/backend

# Preview sync
symfony console app:archive:sync-images --source=alpha --dry-run

# Execute sync with verification
symfony console app:archive:sync-images --source=all --verify --stats
```

## Data Sources

### Alpha Archive (Newscoop Legacy)

```
Source:  /mnt/d/ext-hdd/alpha/
Target:  /var/www/deschide_news_app/apps/backend/public/uploads/images/alpha/
Files:   155,323
Size:    ~18 GB
Format:  JPG, PNG (mostly)
Naming:  cms-image-000000001.jpg, cms-image-000000002.png, etc.
```

**Characteristics**:
- Legacy Newscoop CMS images
- Sequential naming pattern
- Mix of JPEG and PNG
- High volume (155K+ files)

### Beta Archive

```
Source:  /mnt/d/ext-hdd/beta/images/
Target:  /var/www/deschide_news_app/apps/backend/public/uploads/images/beta/
Files:   40,526
Size:    ~3.3 GB
Format:  JPG, PNG
Naming:  Hash-based (e.g., 128b2c3bfa5b707366d05786a86eed135d74d141.jpg)
```

**Characteristics**:
- Content-addressable storage (hash-based names)
- Smaller volume than Alpha
- Modern approach with hash identifiers

## Sync Strategy

### Phase 1: Initial Bulk Sync

**Objective**: Copy all archive images to server

**Method**: rsync with archive mode
```bash
rsync -av /source/ /target/
```

**Flags**:
- `-a` (archive): Preserves timestamps, permissions, symbolic links
- `-v` (verbose): Shows progress

**Estimated Time**:
- Alpha: 30-60 minutes
- Beta: 10-20 minutes
- **Total**: ~40-80 minutes (first run)

### Phase 2: Incremental Updates

**Objective**: Sync only new or changed files

**Method**: Same rsync command (automatically incremental)

**Performance**:
- Only transfers files that:
  - Don't exist in target
  - Have different modification times
  - Have different sizes

**Estimated Time**: 2-5 minutes (if few changes)

### Phase 3: Verification (Optional)

**Objective**: Ensure file integrity

**Method**: rsync with checksum
```bash
rsync -av --checksum /source/ /target/
```

**Trade-off**:
- **Slower**: Reads every file to compute checksums
- **Safer**: Guarantees byte-for-byte accuracy
- **Use case**: Critical data, post-hardware failure

## Best Practices from sync_arhiva (Inferred)

Based on Laravel architecture and common sync patterns:

### 1. Database Tracking

**Recommendation**: Create a `sync_logs` table to track operations

```sql
CREATE TABLE sync_logs (
    id SERIAL PRIMARY KEY,
    source VARCHAR(50) NOT NULL,           -- 'alpha' or 'beta'
    operation VARCHAR(20) NOT NULL,        -- 'sync', 'verify'
    files_total INTEGER,
    files_copied INTEGER,
    files_skipped INTEGER,
    files_failed INTEGER,
    size_total BIGINT,                     -- bytes
    size_transferred BIGINT,               -- bytes
    started_at TIMESTAMP NOT NULL,
    completed_at TIMESTAMP,
    status VARCHAR(20) DEFAULT 'running',  -- 'running', 'success', 'failed'
    error_message TEXT,
    metadata JSON,                         -- rsync stats, errors, etc.
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Usage**:
```php
// In SyncArchiveImagesCommand.php - add logging
$logId = $this->createSyncLog($sourceType);
// ... perform sync ...
$this->updateSyncLog($logId, $stats, 'success');
```

### 2. File Registry

**Recommendation**: Track individual files for deduplication

```sql
CREATE TABLE archive_files (
    id SERIAL PRIMARY KEY,
    source VARCHAR(50) NOT NULL,           -- 'alpha', 'beta'
    filename VARCHAR(255) NOT NULL,
    relative_path VARCHAR(500) NOT NULL,
    absolute_path VARCHAR(1000) NOT NULL,
    file_hash VARCHAR(64),                 -- SHA256 hash
    size BIGINT NOT NULL,                  -- bytes
    mime_type VARCHAR(100),
    width INTEGER,                         -- for images
    height INTEGER,                        -- for images
    synced_at TIMESTAMP,
    checksum_verified_at TIMESTAMP,
    is_linked_to_article BOOLEAN DEFAULT FALSE,
    article_id INTEGER REFERENCES articles(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(source, relative_path)
);

CREATE INDEX idx_archive_files_source ON archive_files(source);
CREATE INDEX idx_archive_files_hash ON archive_files(file_hash);
CREATE INDEX idx_archive_files_article ON archive_files(article_id);
```

**Benefits**:
- Track which files are synced
- Detect duplicates across sources
- Link files to articles
- Support cleanup operations

### 3. Progress Monitoring

**Recommendation**: Use Symfony Messenger for async sync with progress updates

```php
// Message class
class SyncArchiveImagesMessage
{
    public function __construct(
        public readonly string $source,
        public readonly bool $verify = false,
    ) {}
}

// Handler
class SyncArchiveImagesHandler implements MessageHandlerInterface
{
    public function __invoke(SyncArchiveImagesMessage $message): void
    {
        // Execute sync
        // Publish progress to Mercure
        $this->mercure->publish(
            new Update(
                'deschide_news/sync/progress',
                json_encode(['percent' => $percent, 'files' => $filesProcessed])
            )
        );
    }
}
```

### 4. Web Interface (Laravel Pattern)

From sync_arhiva Laravel structure, likely features:

**Dashboard**:
- Sync status overview
- Recent sync operations
- File statistics
- Error logs

**API Endpoints** (equivalent Symfony routes):
```php
// routes/api.php
Route::post('/api/sync/start', [SyncController::class, 'start']);
Route::get('/api/sync/status/{id}', [SyncController::class, 'status']);
Route::post('/api/sync/verify', [SyncController::class, 'verify']);
Route::get('/api/sync/logs', [SyncController::class, 'logs']);
```

**Frontend** (Blade → Twig equivalent):
- Real-time progress bar
- File statistics
- Error handling UI

## Implementation Roadmap

### Phase 1: Basic Sync ✅ COMPLETED

- [x] Bash script with rsync
- [x] Symfony command wrapper
- [x] Dry-run capability
- [x] Progress tracking
- [x] Documentation

### Phase 2: Database Integration 🔄 RECOMMENDED

**Tasks**:
1. Create `sync_logs` migration
2. Create `archive_files` migration
3. Update `SyncArchiveImagesCommand` to log operations
4. Create `ArchiveFile` entity
5. Implement file registry population

**Entities to Create**:

```php
// src/Entity/SyncLog.php
#[ORM\Entity]
class SyncLog
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private string $source;

    #[ORM\Column(length: 20)]
    private string $operation;

    #[ORM\Column(nullable: true)]
    private ?int $filesTotal = null;

    #[ORM\Column(nullable: true)]
    private ?int $filesCopied = null;

    #[ORM\Column(nullable: true)]
    private ?int $filesSkipped = null;

    #[ORM\Column(type: 'bigint', nullable: true)]
    private ?int $sizeTotal = null;

    #[ORM\Column]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(length: 20)]
    private string $status = 'running';

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = null;

    // Getters and setters...
}
```

```php
// src/Entity/ArchiveFile.php
#[ORM\Entity]
class ArchiveFile
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private string $source;

    #[ORM\Column(length: 255)]
    private string $filename;

    #[ORM\Column(length: 500)]
    private string $relativePath;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $fileHash = null;

    #[ORM\Column(type: 'bigint')]
    private int $size;

    #[ORM\Column(nullable: true)]
    private ?int $width = null;

    #[ORM\Column(nullable: true)]
    private ?int $height = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $syncedAt = null;

    #[ORM\ManyToOne(targetEntity: Article::class)]
    private ?Article $article = null;

    // Getters and setters...
}
```

### Phase 3: Web Dashboard 📋 OPTIONAL

**Tasks**:
1. Create admin controller for sync management
2. Create Twig templates for dashboard
3. Implement API endpoints
4. Add Mercure real-time updates
5. Create frontend components

**Routes**:
```yaml
# config/routes/admin.yaml
admin_sync_dashboard:
    path: /admin/sync
    controller: App\Controller\Admin\SyncController::dashboard

admin_sync_start:
    path: /admin/sync/start
    controller: App\Controller\Admin\SyncController::start
    methods: [POST]

admin_sync_logs:
    path: /admin/sync/logs
    controller: App\Controller\Admin\SyncController::logs
    methods: [GET]
```

### Phase 4: Automation 🤖 RECOMMENDED

**Cron Setup**:
```bash
# Nightly incremental sync
0 3 * * * cd /var/www/deschide_news_app/apps/backend && /usr/local/bin/symfony console app:archive:sync-images --source=all >> /var/log/archive-sync.log 2>&1

# Weekly verification
0 4 * * 0 cd /var/www/deschide_news_app/apps/backend && /usr/local/bin/symfony console app:archive:sync-images --source=all --verify >> /var/log/archive-verify.log 2>&1
```

**Monitoring**:
```bash
# Create monitoring script
#!/bin/bash
# scripts/monitor-sync.sh

LAST_LOG=$(tail -1 /var/log/archive-sync.log)
if echo "$LAST_LOG" | grep -q "ERROR"; then
    # Send alert via email or Slack
    echo "Sync error detected: $LAST_LOG" | mail -s "Archive Sync Alert" admin@deschide.md
fi
```

## Integration with Article Import

### Current Import Flow

```
Newscoop MySQL → Symfony Import Commands → Article entities
```

### Enhanced Flow with Archive Files

```
1. Sync Archive Images
   ↓
2. Register Files in Database (archive_files table)
   ↓
3. Import Articles from Newscoop
   ↓
4. Link Articles to Archive Images
   ↓
5. Generate Thumbnails
   ↓
6. Index in Elasticsearch
```

### Article-Image Linking Command

**Create**: `app:archive:link-article-images`

```php
// src/Command/Archive/LinkArticleImagesCommand.php

#[AsCommand(name: 'app:archive:link-article-images')]
class LinkArticleImagesCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Query Newscoop: SELECT IdArticle, IdImage FROM ArticleImages
        // Find corresponding ArchiveFile by matching patterns
        // Create ArticleImage entity
        // Link to Article

        $io->success('Linked X articles to Y archive images');
        return Command::SUCCESS;
    }
}
```

## Performance Optimization

### 1. Parallel Processing

For very large syncs, consider splitting by subdirectory:

```bash
# In sync script, detect subdirectories
for dir in /mnt/d/ext-hdd/alpha/*/; do
    rsync -av "$dir" /target/alpha/ &
done
wait  # Wait for all parallel processes
```

### 2. Bandwidth Limiting

If syncing over network:

```bash
rsync -av --bwlimit=10000 /source/ /target/  # 10 MB/s limit
```

### 3. Compression

For slow network connections:

```bash
rsync -avz /source/ /target/  # -z enables compression
```

### 4. Exclude Cache Files

```bash
rsync -av --exclude='cache/' --exclude='*.tmp' /source/ /target/
```

## Disaster Recovery

### Backup Before Sync

```bash
# Create snapshot
cd /var/www/deschide_news_app/apps/backend/public/uploads
tar -czf images-backup-$(date +%Y%m%d).tar.gz images/
```

### Rollback Procedure

```bash
# Restore from backup
cd /var/www/deschide_news_app/apps/backend/public/uploads
rm -rf images/
tar -xzf images-backup-YYYYMMDD.tar.gz
```

### External HDD Failure

- Archive images remain on original HDD (COPY not MOVE)
- Server has full copy after sync
- Can rebuild HDD from server if needed

## Security Considerations

### 1. File Permissions

```bash
# Set proper permissions after sync
find /var/www/deschide_news_app/apps/backend/public/uploads/images -type f -exec chmod 644 {} \;
find /var/www/deschide_news_app/apps/backend/public/uploads/images -type d -exec chmod 755 {} \;
```

### 2. Access Control

```nginx
# Nginx config for CDN server
location /uploads/images/ {
    alias /var/www/deschide_news_app/apps/backend/public/uploads/images/;

    # Rate limiting
    limit_req zone=images burst=20;

    # Prevent directory listing
    autoindex off;

    # Only allow image files
    location ~* \.(jpg|jpeg|png|gif|webp)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Deny all other files
    location ~ /\. {
        deny all;
    }
}
```

### 3. Malware Scanning

```bash
# Install ClamAV
sudo apt-get install clamav clamav-daemon

# Scan after sync
clamscan -r /var/www/deschide_news_app/apps/backend/public/uploads/images/
```

## Monitoring & Alerts

### Disk Space Monitoring

```bash
# Check available space
df -h /var/www/deschide_news_app/apps/backend/public/uploads/

# Alert if < 10GB free
AVAILABLE=$(df --output=avail /var/www/deschide_news_app | tail -1)
if [ "$AVAILABLE" -lt 10485760 ]; then
    echo "Low disk space!" | mail -s "Disk Alert" admin@deschide.md
fi
```

### Sync Health Check

```bash
# Compare file counts
ALPHA_SOURCE=$(find /mnt/d/ext-hdd/alpha -type f | wc -l)
ALPHA_TARGET=$(find /var/www/deschide_news_app/apps/backend/public/uploads/images/alpha -type f | wc -l)

if [ "$ALPHA_SOURCE" -ne "$ALPHA_TARGET" ]; then
    echo "File count mismatch: Source=$ALPHA_SOURCE, Target=$ALPHA_TARGET"
fi
```

## Next Steps

### Immediate (Week 1)

1. **Run initial sync**:
   ```bash
   symfony console app:archive:sync-images --source=all --stats
   ```

2. **Verify results**:
   ```bash
   # Check file counts
   find /var/www/deschide_news_app/apps/backend/public/uploads/images/alpha -type f | wc -l
   find /var/www/deschide_news_app/apps/backend/public/uploads/images/beta -type f | wc -l
   ```

3. **Test CDN access**:
   ```bash
   curl -I http://127.0.0.1:8082/uploads/images/alpha/cms-image-000000001.jpg
   ```

### Short-term (Week 2-3)

1. Create `SyncLog` and `ArchiveFile` entities
2. Update command to populate database
3. Implement article-image linking
4. Set up automated nightly sync

### Long-term (Month 2+)

1. Build admin dashboard
2. Implement real-time progress (Mercure)
3. Add advanced search/filtering
4. Create sync analytics and reporting

## References

- **Bash Script**: `/var/www/deschide_news_app/scripts/sync-archive-images.sh`
- **Symfony Command**: `/var/www/deschide_news_app/apps/backend/src/Command/Archive/SyncArchiveImagesCommand.php`
- **User Guide**: `/var/www/deschide_news_app/apps/backend/ARCHIVE_IMAGES_SYNC_GUIDE.md`
- **rsync Manual**: `man rsync`
- **Previous Implementation**: https://github.com/radusoltan/sync_arhiva (Laravel, reference only)
