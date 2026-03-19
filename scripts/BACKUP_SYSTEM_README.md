# PostgreSQL Backup System Documentation

## Overview

Automated backup system for the Deschide News database with daily backups, automatic cleanup, and comprehensive verification.

## Files

| File | Location | Purpose |
|------|----------|---------|
| **Backup Script** | `/var/www/deschide_news_app/scripts/backup-database.sh` | Main backup script |
| **Cron Configuration** | `/var/www/deschide_news_app/scripts/cron/backup-database.cron` | Automated scheduling |
| **Backup Directory** | `/var/www/deschide_news_app/backups/` | Storage for backups |
| **Backup Log** | `/var/www/deschide_news_app/backups/backup.log` | Execution logs |

## Database Configuration

| Parameter | Value |
|-----------|-------|
| Database Name | `deschide` |
| Database User | `deschide_admin` |
| Database Host | `127.0.0.1` |
| Database Port | `6432` (PgBouncer) |
| Backup Format | PostgreSQL custom format + gzip |
| Retention Period | 7 days |

## Features

✅ **Pre-flight Checks**
- Database connectivity verification
- PostgreSQL client tools validation
- Directory permissions check

✅ **Backup Creation**
- PostgreSQL custom format (`pg_dump -Fc`)
- Additional gzip compression
- Timestamped filenames

✅ **Verification**
- File existence check
- File size validation
- Minimum size threshold (1KB)
- Compression ratio calculation

✅ **Database Statistics**
- Table count
- Row counts (articles, categories, authors, images)
- Database size
- Backup size with compression ratio

✅ **Automatic Cleanup**
- Removes backups older than 7 days
- Configurable retention period
- Reports deleted count

✅ **Color-Coded Output**
- Info messages (blue)
- Success messages (green)
- Warning messages (yellow)
- Error messages (red)

## Usage

### Manual Backup

Run the backup script manually:

```bash
# Run backup
/var/www/deschide_news_app/scripts/backup-database.sh

# Check backup log
tail -f /var/www/deschide_news_app/backups/backup.log
```

### Automated Backups (Cron)

**Install cron job:**

```bash
# Copy cron configuration
sudo cp /var/www/deschide_news_app/scripts/cron/backup-database.cron /etc/cron.d/deschide-db-backup

# Set correct permissions
sudo chmod 644 /etc/cron.d/deschide-db-backup

# Verify cron job
sudo cat /etc/cron.d/deschide-db-backup
```

**Default Schedule:** Daily at 2:00 AM

**Alternative Schedules:**

Edit `/etc/cron.d/deschide-db-backup` and uncomment one of these:

```bash
# Every 6 hours (high-traffic sites)
0 */6 * * * radu /var/www/deschide_news_app/scripts/backup-database.sh >> /var/www/deschide_news_app/backups/backup.log 2>&1

# Every hour (critical data)
0 * * * * radu /var/www/deschide_news_app/scripts/backup-database.sh >> /var/www/deschide_news_app/backups/backup.log 2>&1

# Twice daily (2 AM and 2 PM)
0 2,14 * * * radu /var/www/deschide_news_app/scripts/backup-database.sh >> /var/www/deschide_news_app/backups/backup.log 2>&1
```

**Monitor Cron Logs:**

```bash
# System cron log
sudo tail -f /var/log/syslog | grep CRON

# Backup execution log
tail -f /var/www/deschide_news_app/backups/backup.log
```

## Restore from Backup

### Step-by-Step Restore Process

**1. Stop the application:**

```bash
cd /var/www/deschide_news_app/apps/backend
symfony server:stop
```

**2. List available backups:**

```bash
ls -lht /var/www/deschide_news_app/backups/deschide_*.sql.gz
```

**3. Choose backup to restore:**

```bash
# Example backup file
BACKUP_FILE="/var/www/deschide_news_app/backups/deschide_20251220_134912.sql.gz"
```

**4. Drop existing database:**

⚠️ **CAUTION:** This will permanently delete all current data!

```bash
PGPASSWORD="iIzmHACi7+W+yq9NFRT2FeadUPAmgEna" \
dropdb -h 127.0.0.1 -p 6432 -U deschide_admin deschide
```

**5. Create new database:**

```bash
PGPASSWORD="iIzmHACi7+W+yq9NFRT2FeadUPAmgEna" \
createdb -h 127.0.0.1 -p 6432 -U deschide_admin deschide
```

**6. Restore backup:**

```bash
gunzip -c "$BACKUP_FILE" | PGPASSWORD="iIzmHACi7+W+yq9NFRT2FeadUPAmgEna" \
pg_restore -h 127.0.0.1 -p 6432 -U deschide_admin -d deschide
```

**7. Verify restoration:**

```bash
PGPASSWORD="iIzmHACi7+W+yq9NFRT2FeadUPAmgEna" \
psql -h 127.0.0.1 -p 6432 -U deschide_admin -d deschide -c "\dt"
```

**8. Restart the application:**

```bash
cd /var/www/deschide_news_app/apps/backend
symfony serve -d --port=8081
symfony server:status
```

**9. Test the application:**

```bash
# Check API
curl http://127.0.0.1:8081/api

# Check database connection
cd /var/www/deschide_news_app/apps/backend
symfony console doctrine:query:sql "SELECT COUNT(*) FROM article"
```

### Quick Restore Script

For convenience, you can create a restore script:

```bash
#!/bin/bash
set -e

BACKUP_FILE="$1"

if [ -z "$BACKUP_FILE" ]; then
    echo "Usage: $0 <backup_file.sql.gz>"
    exit 1
fi

if [ ! -f "$BACKUP_FILE" ]; then
    echo "Error: Backup file not found: $BACKUP_FILE"
    exit 1
fi

echo "⚠️  WARNING: This will DELETE the current database!"
echo "Backup file: $BACKUP_FILE"
read -p "Are you sure? (yes/no): " CONFIRM

if [ "$CONFIRM" != "yes" ]; then
    echo "Restore cancelled."
    exit 0
fi

cd /var/www/deschide_news_app/apps/backend
symfony server:stop

PGPASSWORD="iIzmHACi7+W+yq9NFRT2FeadUPAmgEna" \
dropdb -h 127.0.0.1 -p 6432 -U deschide_admin deschide

PGPASSWORD="iIzmHACi7+W+yq9NFRT2FeadUPAmgEna" \
createdb -h 127.0.0.1 -p 6432 -U deschide_admin deschide

gunzip -c "$BACKUP_FILE" | PGPASSWORD="iIzmHACi7+W+yq9NFRT2FeadUPAmgEna" \
pg_restore -h 127.0.0.1 -p 6432 -U deschide_admin -d deschide

symfony serve -d --port=8081

echo "✅ Restore completed successfully!"
```

Save as `/var/www/deschide_news_app/scripts/restore-database.sh` and make executable:

```bash
chmod +x /var/www/deschide_news_app/scripts/restore-database.sh
```

Usage:

```bash
/var/www/deschide_news_app/scripts/restore-database.sh /var/www/deschide_news_app/backups/deschide_20251220_134912.sql.gz
```

## Configuration

### Change Retention Period

Edit `/var/www/deschide_news_app/scripts/backup-database.sh`:

```bash
# Default: 7 days
RETENTION_DAYS=7

# Keep backups for 30 days
RETENTION_DAYS=30

# Keep backups for 90 days
RETENTION_DAYS=90
```

### Change Backup Directory

Edit `/var/www/deschide_news_app/scripts/backup-database.sh`:

```bash
# Default location
BACKUP_DIR="/var/www/deschide_news_app/backups"

# Use external storage (recommended for production)
BACKUP_DIR="/mnt/backup-storage/deschide"

# Create new directory if needed
mkdir -p "$BACKUP_DIR"
```

### Backup to Remote Storage

For production, consider backing up to remote storage:

**Amazon S3:**

```bash
# Install AWS CLI
sudo apt install awscli

# Configure AWS credentials
aws configure

# Add to backup script (after backup creation)
aws s3 cp "$BACKUP_FILE" s3://your-bucket/database-backups/
```

**Rsync to Remote Server:**

```bash
# Add to backup script (after backup creation)
rsync -avz "$BACKUP_FILE" backup-server:/backups/deschide/
```

## Monitoring

### Check Backup Status

```bash
# List recent backups
ls -lht /var/www/deschide_news_app/backups/deschide_*.sql.gz | head -10

# Check backup sizes
du -sh /var/www/deschide_news_app/backups/deschide_*.sql.gz

# View backup log
tail -50 /var/www/deschide_news_app/backups/backup.log
```

### Verify Backups

Test restore in a separate database:

```bash
# Create test database
PGPASSWORD="iIzmHACi7+W+yq9NFRT2FeadUPAmgEna" \
createdb -h 127.0.0.1 -p 6432 -U deschide_admin deschide_test

# Restore backup to test database
gunzip -c /var/www/deschide_news_app/backups/deschide_20251220_134912.sql.gz | \
PGPASSWORD="iIzmHACi7+W+yq9NFRT2FeadUPAmgEna" \
pg_restore -h 127.0.0.1 -p 6432 -U deschide_admin -d deschide_test

# Verify data
PGPASSWORD="iIzmHACi7+W+yq9NFRT2FeadUPAmgEna" \
psql -h 127.0.0.1 -p 6432 -U deschide_admin -d deschide_test -c "SELECT COUNT(*) FROM article"

# Drop test database
PGPASSWORD="iIzmHACi7+W+yq9NFRT2FeadUPAmgEna" \
dropdb -h 127.0.0.1 -p 6432 -U deschide_admin deschide_test
```

### Set Up Monitoring Alerts

**Email Notifications on Failure:**

Edit `/var/www/deschide_news_app/scripts/backup-database.sh` and add at the end:

```bash
# Send email on failure (requires sendmail or similar)
if [ $? -ne 0 ]; then
    echo "Backup failed at $(date)" | mail -s "Database Backup Failed" admin@example.com
fi
```

**Prometheus/Grafana Integration:**

Create a metrics file that can be scraped by Prometheus:

```bash
# Add to backup script (after successful backup)
cat > /var/www/deschide_news_app/backups/metrics.prom << EOF
# HELP backup_last_success_timestamp Unix timestamp of last successful backup
# TYPE backup_last_success_timestamp gauge
backup_last_success_timestamp $(date +%s)

# HELP backup_size_bytes Size of last backup in bytes
# TYPE backup_size_bytes gauge
backup_size_bytes $SIZE_BYTES

# HELP backup_database_size_bytes Size of database in bytes
# TYPE backup_database_size_bytes gauge
backup_database_size_bytes $DB_SIZE_BYTES
EOF
```

## Troubleshooting

### Backup Fails: "Cannot connect to database"

**Check database is running:**

```bash
sudo systemctl status postgresql
```

**Check PgBouncer is running:**

```bash
sudo systemctl status pgbouncer
```

**Test connection manually:**

```bash
PGPASSWORD="iIzmHACi7+W+yq9NFRT2FeadUPAmgEna" \
psql -h 127.0.0.1 -p 6432 -U deschide_admin -d deschide -c "SELECT 1"
```

### Backup Fails: "Permission denied"

**Check directory permissions:**

```bash
ls -ld /var/www/deschide_news_app/backups/
```

**Fix permissions:**

```bash
sudo chown -R radu:radu /var/www/deschide_news_app/backups/
chmod 755 /var/www/deschide_news_app/backups/
```

### Backup File Too Large

**Check database size:**

```bash
PGPASSWORD="iIzmHACi7+W+yq9NFRT2FeadUPAmgEna" \
psql -h 127.0.0.1 -p 6432 -U deschide_admin -d deschide -c \
"SELECT pg_size_pretty(pg_database_size('deschide'))"
```

**Clean up old data:**

```bash
# Example: Delete old test data
cd /var/www/deschide_news_app/apps/backend
symfony console app:cleanup-old-test-data
```

**Use compression:**

The script already uses gzip compression. For additional compression, consider:

```bash
# Use xz compression (better compression, slower)
xz -9 backup.dump
```

### Restore Fails: "Database already exists"

**Force drop:**

```bash
PGPASSWORD="iIzmHACi7+W+yq9NFRT2FeadUPAmgEna" \
dropdb -h 127.0.0.1 -p 6432 -U deschide_admin --force deschide
```

### Out of Disk Space

**Check disk usage:**

```bash
df -h /var/www/deschide_news_app/backups/
```

**Clean up old backups manually:**

```bash
# Delete backups older than 30 days
find /var/www/deschide_news_app/backups/ -name "deschide_*.sql.gz" -mtime +30 -delete
```

**Move backups to external storage:**

```bash
# Move to external drive
sudo mv /var/www/deschide_news_app/backups/deschide_*.sql.gz /mnt/external-backup/
```

## Best Practices

### Production Recommendations

1. **Multiple Backup Locations**
   - Keep local backups for quick restore
   - Copy to remote storage for disaster recovery
   - Use S3, Google Cloud Storage, or similar

2. **Backup Verification**
   - Test restore monthly
   - Verify backup integrity automatically
   - Monitor backup size trends

3. **Security**
   - Encrypt backups for sensitive data
   - Restrict backup directory permissions
   - Use secure transfer protocols (SFTP, S3 with encryption)

4. **Monitoring**
   - Set up alerts for backup failures
   - Track backup size over time
   - Monitor disk space

5. **Documentation**
   - Document restore procedures
   - Test disaster recovery plan
   - Train team on restore process

### Backup Schedule Recommendations

| Site Traffic | Backup Frequency | Retention |
|--------------|------------------|-----------|
| Low (< 1K visits/day) | Daily | 7-14 days |
| Medium (1K-10K/day) | Daily + Weekly | 30 days + 12 weeks |
| High (10K-100K/day) | Every 6 hours | 7 days + 4 weeks + 12 months |
| Critical (> 100K/day) | Hourly + WAL archiving | 30 days + 52 weeks + yearly |

### Backup Testing Schedule

- **Monthly:** Restore to test database and verify
- **Quarterly:** Full disaster recovery drill
- **Yearly:** Review and update backup strategy

## Support

For issues or questions:

1. Check logs: `/var/www/deschide_news_app/backups/backup.log`
2. Review this documentation
3. Contact system administrator
4. See Database Engineer Agent: `.claude/agents/database-engineer.md`

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2025-12-20 | Initial implementation |

---

**Last Updated:** 2025-12-20
**Maintained By:** Database Engineering Team
