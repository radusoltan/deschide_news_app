# PostgreSQL Backup Configuration Guide

**Database**: deschide
**User**: deschide_admin
**Backup Location**: `/var/www/deschide_news_app/backups/postgresql/`
**Script Location**: `/var/www/deschide_news_app/scripts/backup-db.sh`

---

## Backup Strategy

### Backup Types

| Type | Frequency | Retention | Location |
|------|-----------|-----------|----------|
| **Daily** | Every day | 7 days | `backups/postgresql/daily/` |
| **Weekly** | Every Sunday | 30 days | `backups/postgresql/weekly/` |
| **Monthly** | 1st of month | 365 days | `backups/postgresql/monthly/` |

### Backup Format

- **Format**: PostgreSQL Custom Format (`pg_dump -Fc`)
- **Compression**: gzip (automatic with custom format)
- **Average Size**: ~430 KB (compressed)
- **Naming**: `deschide_YYYYMMDD_HHMMSS.dump`

---

## Manual Backup

### Run Backup Script

```bash
cd /var/www/deschide_news_app
./scripts/backup-db.sh
```

**Expected Output**:
```
==========================================
Backup started at Sat Nov 29 13:17:47 EET 2025
==========================================
Creating daily backup: /var/www/deschide_news_app/backups/postgresql/daily/deschide_20251129_131747.dump
Daily backup created successfully: 432K
Cleaning up old backups...
Backup completed at Sat Nov 29 13:17:48 EET 2025
==========================================
```

### Verify Backup

```bash
# List backup contents
pg_restore --list /var/www/deschide_news_app/backups/postgresql/daily/deschide_20251129_131747.dump

# Check backup file
ls -lh /var/www/deschide_news_app/backups/postgresql/daily/
```

---

## Automated Backup (Production)

### Cron Job Configuration

For production environments, schedule automated backups using cron:

```bash
# Edit crontab
crontab -e

# Add this line (runs daily at 2:00 AM)
0 2 * * * PGPASSWORD=YOUR_DB_PASSWORD /var/www/deschide_news_app/scripts/backup-db.sh >> /var/log/deschide_backup.log 2>&1
```

**Important**: Replace `YOUR_DB_PASSWORD` with the actual database password from `.env.local`.

### Alternative: systemd Timer (Recommended for Production)

Create systemd service and timer files:

**Service File**: `/etc/systemd/system/deschide-backup.service`
```ini
[Unit]
Description=Deschide News PostgreSQL Backup
After=postgresql.service

[Service]
Type=oneshot
User=www-data
Environment="PGPASSWORD=YOUR_DB_PASSWORD"
ExecStart=/var/www/deschide_news_app/scripts/backup-db.sh
StandardOutput=journal
StandardError=journal
```

**Timer File**: `/etc/systemd/system/deschide-backup.timer`
```ini
[Unit]
Description=Daily Deschide News PostgreSQL Backup
Requires=deschide-backup.service

[Timer]
OnCalendar=daily
OnCalendar=02:00
Persistent=true

[Install]
WantedBy=timers.target
```

**Enable and Start**:
```bash
sudo systemctl daemon-reload
sudo systemctl enable deschide-backup.timer
sudo systemctl start deschide-backup.timer

# Check timer status
sudo systemctl list-timers deschide-backup.timer

# Check logs
sudo journalctl -u deschide-backup.service
```

---

## Restore Procedures

### Quick Restore (Replace Existing Database)

**WARNING**: This will replace the existing database with the backup!

```bash
# Set password
export PGPASSWORD=YOUR_DB_PASSWORD

# Stop application (to close connections)
cd /var/www/deschide_news_app/apps/backend
symfony server:stop

# Drop and recreate database
psql -h 127.0.0.1 -U deschide_admin -d postgres -c "DROP DATABASE deschide;"
psql -h 127.0.0.1 -U deschide_admin -d postgres -c "CREATE DATABASE deschide;"

# Restore backup
BACKUP_FILE=/var/www/deschide_news_app/backups/postgresql/daily/deschide_20251129_131747.dump
pg_restore -h 127.0.0.1 -U deschide_admin -d deschide "$BACKUP_FILE"

# Verify restoration
psql -h 127.0.0.1 -U deschide_admin -d deschide -c "SELECT COUNT(*) FROM articles;"
psql -h 127.0.0.1 -U deschide_admin -d deschide -c "SELECT COUNT(*) FROM categories;"

# Restart application
symfony server:start -d
```

### Test Restore (Non-destructive)

```bash
# Set password
export PGPASSWORD=YOUR_DB_PASSWORD

# Create test database
psql -h 127.0.0.1 -U deschide_admin -d postgres -c "CREATE DATABASE deschide_restore_test;"

# Restore to test database
BACKUP_FILE=/var/www/deschide_news_app/backups/postgresql/daily/deschide_20251129_131747.dump
pg_restore -h 127.0.0.1 -U deschide_admin -d deschide_restore_test "$BACKUP_FILE"

# Verify data
psql -h 127.0.0.1 -U deschide_admin -d deschide_restore_test -c "SELECT COUNT(*) FROM articles;"

# Clean up (when done)
psql -h 127.0.0.1 -U deschide_admin -d postgres -c "DROP DATABASE deschide_restore_test;"
```

### Selective Restore (Specific Tables)

```bash
# Set password
export PGPASSWORD=YOUR_DB_PASSWORD

# List available tables in backup
pg_restore --list backup.dump | grep "TABLE DATA"

# Restore specific table
BACKUP_FILE=/var/www/deschide_news_app/backups/postgresql/daily/deschide_20251129_131747.dump
pg_restore -h 127.0.0.1 -U deschide_admin -d deschide -t articles "$BACKUP_FILE"
```

---

## Backup Verification

### Test Results (2025-11-29)

**Backup Test**:
- ✅ Backup created successfully: 432 KB
- ✅ File format verified: PostgreSQL custom database dump v1.16-0
- ✅ TOC entries: 404 objects

**Restoration Test**:
- ✅ Test database created
- ✅ Backup restored without errors
- ✅ Data integrity verified:
  - Articles: 81 (matches original)
  - Categories: 9 (matches original)
  - Authors: 14 (matches original)

---

## PostgreSQL Configuration

### Current Settings

```sql
-- Archive mode (WAL archiving)
SHOW archive_mode;  -- Result: off

-- WAL level (Write-Ahead Logging)
SHOW wal_level;     -- Result: replica
```

### Notes

- **Development**: Current configuration (archive_mode=off) is sufficient for development
- **Production**: Consider enabling WAL archiving for point-in-time recovery (PITR)

---

## Production Recommendations

### 1. Enable WAL Archiving (Optional - Advanced)

For production environments requiring point-in-time recovery, enable WAL archiving:

**Edit** `/etc/postgresql/18/main/postgresql.conf` (requires root access):
```ini
wal_level = replica                    # Already set
archive_mode = on                      # Enable archiving
archive_command = 'cp %p /var/lib/postgresql/18/main/archive/%f'
archive_timeout = 300                  # Archive every 5 minutes
```

**Create archive directory**:
```bash
sudo mkdir -p /var/lib/postgresql/18/main/archive
sudo chown postgres:postgres /var/lib/postgresql/18/main/archive
sudo chmod 700 /var/lib/postgresql/18/main/archive
```

**Restart PostgreSQL**:
```bash
sudo systemctl restart postgresql
```

### 2. Off-site Backup Storage

For disaster recovery, copy backups to off-site location:

```bash
# rsync to remote server
rsync -avz /var/www/deschide_news_app/backups/ backup-server:/backups/deschide_news/

# Or use cloud storage (AWS S3, Google Cloud Storage, etc.)
aws s3 sync /var/www/deschide_news_app/backups/ s3://your-bucket/deschide-backups/
```

### 3. Monitoring

Add monitoring to track backup success/failure:

```bash
# Check last backup
ls -lth /var/www/deschide_news_app/backups/postgresql/daily/ | head -5

# Check backup age (should not be older than 24 hours)
find /var/www/deschide_news_app/backups/postgresql/daily/ -name "*.dump" -mtime +1

# Send alert if backup is older than 24 hours
if [ -n "$(find /var/www/deschide_news_app/backups/postgresql/daily/ -name '*.dump' -mtime +1)" ]; then
    echo "WARNING: Backup older than 24 hours!" | mail -s "Backup Alert" admin@example.com
fi
```

---

## Troubleshooting

### Authentication Failed

**Error**: `FATAL: password authentication failed for user "deschide_admin"`

**Solution**: Check password in `.env.local`:
```bash
cd /var/www/deschide_news_app/apps/backend
grep DATABASE_URL .env.local
```

Update password in backup script or use environment variable:
```bash
PGPASSWORD=correct_password ./scripts/backup-db.sh
```

### Permission Denied

**Error**: `cannot create directory '/var/backups/postgresql': Permission denied`

**Solution**: The backup script uses project directory instead. No action needed.

### Disk Space Issues

**Error**: `No space left on device`

**Solution**:
```bash
# Check disk space
df -h /var/www/deschide_news_app/backups/

# Manually clean old backups
find /var/www/deschide_news_app/backups/postgresql/daily/ -name "*.dump" -mtime +7 -delete
find /var/www/deschide_news_app/backups/postgresql/weekly/ -name "*.dump" -mtime +30 -delete
```

---

## Backup Script Details

**Location**: `/var/www/deschide_news_app/scripts/backup-db.sh`

**Features**:
- Daily backups with timestamp
- Automatic weekly backups (Sundays)
- Automatic monthly backups (1st of month)
- Automatic cleanup of old backups
- Backup verification
- Error handling

**Configuration**:
```bash
DB_NAME="deschide"
DB_USER="deschide_admin"
DB_HOST="127.0.0.1"
BACKUP_DIR="/var/www/deschide_news_app/backups/postgresql"
```

---

## Security Notes

### Password Management

**NEVER commit passwords to git!**

The backup script uses:
```bash
PGPASSWORD="${PGPASSWORD:-default_password}"
```

In production, provide password via environment variable:
```bash
export PGPASSWORD=your_secure_password
./scripts/backup-db.sh
```

Or use `.pgpass` file:
```bash
# Create ~/.pgpass
echo "127.0.0.1:5432:deschide:deschide_admin:your_password" > ~/.pgpass
chmod 600 ~/.pgpass
```

### Backup Security

Ensure backup files have restricted permissions:
```bash
chmod 700 /var/www/deschide_news_app/backups/postgresql/
chmod 600 /var/www/deschide_news_app/backups/postgresql/*/*.dump
```

---

## Contact

**Documentation**: `/var/www/deschide_news_app/docs/DATABASE_BACKUP_GUIDE.md`
**Last Updated**: 2025-11-29
**Status**: ✅ Tested and Verified
