# Cron Job Setup Guide - Article Archiving

This guide provides complete instructions for setting up automated article archiving using cron jobs on the Deschide News platform.

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [Prerequisites](#prerequisites)
3. [Installation](#installation)
4. [Configuration](#configuration)
5. [Verification](#verification)
6. [Log Management](#log-management)
7. [Troubleshooting](#troubleshooting)
8. [Manual Execution](#manual-execution)
9. [Monitoring](#monitoring)

---

## 🎯 Overview

### Purpose

The automated article archiving system moves old published articles to archived status to:
- Keep the main article database focused on recent content
- Improve query performance on active articles
- Maintain historical content in an archived state
- Reduce index size for search engines

### How It Works

- **Command**: `app:archive-old-articles`
- **Frequency**: Monthly (1st day at 02:00 AM)
- **Threshold**: Articles older than 4 years
- **Batch Processing**: 100 articles per batch to avoid memory issues
- **Archive Reason**: `OLD_CONTENT` (enum value)

### What Gets Archived

Articles that meet ALL these criteria:
- Status: `PUBLISHED`
- Published date: More than 4 years ago (e.g., before 2021 if current year is 2025)

**Note**: Drafts, scheduled, and already-archived articles are NOT affected.

---

## 🔧 Prerequisites

### System Requirements

1. **Symfony CLI** installed and accessible:
   ```bash
   symfony --version
   ```

2. **Sufficient permissions** to:
   - Execute scripts in `/var/www/deschide_news_app/scripts/cron/`
   - Write to `/var/log/deschide/`
   - Create lock files in `/var/lock/`

3. **Database access** configured in backend `.env.local`:
   ```bash
   DATABASE_URL="postgresql://deschide_admin:password@127.0.0.1:5432/deschide?..."
   ```

4. **Cron service** running:
   ```bash
   systemctl status cron
   ```

### File Structure

```
/var/www/deschide_news_app/
├── scripts/
│   └── cron/
│       └── archive-old-articles.sh    # ← Cron script
├── apps/
│   └── backend/
│       └── src/
│           └── Command/
│               └── ArchiveOldArticlesCommand.php  # ← Symfony command
└── docs/
    └── CRON_SETUP.md                  # ← This file
```

---

## 📥 Installation

### Step 1: Verify Script Exists

Check that the script is present and executable:

```bash
# Navigate to project root
cd /var/www/deschide_news_app

# Check script exists
ls -lh scripts/cron/archive-old-articles.sh

# Expected output:
# -rwxr-xr-x 1 user group 4.2K Nov 30 12:00 archive-old-articles.sh
```

### Step 2: Make Script Executable (if needed)

```bash
chmod +x scripts/cron/archive-old-articles.sh
```

### Step 3: Test Manual Execution

**IMPORTANT**: Test the script manually before adding to cron:

```bash
# Run the script manually
/var/www/deschide_news_app/scripts/cron/archive-old-articles.sh

# Check the log output
tail -f /var/log/deschide/archive.log
```

Expected output in log:
```
[2025-11-30 14:35:00] [INFO] =========================================
[2025-11-30 14:35:00] [INFO] Starting article archiving process
[2025-11-30 14:35:00] [INFO] =========================================
[2025-11-30 14:35:01] [INFO] Lock file created with PID: 12345
[2025-11-30 14:35:01] [INFO] Changed to backend directory: /var/www/deschide_news_app/apps/backend
[2025-11-30 14:35:02] [INFO] Symfony CLI found: /usr/local/bin/symfony
[2025-11-30 14:35:02] [INFO] Archive command verified
[2025-11-30 14:35:03] [INFO] Executing: symfony console app:archive-old-articles --years=4 --batch-size=100 --no-interaction
...
[2025-11-30 14:35:10] [INFO] Archive command executed successfully
[2025-11-30 14:35:10] [INFO] =========================================
[2025-11-30 14:35:10] [INFO] Article archiving process completed
[2025-11-30 14:35:10] [INFO] =========================================
```

### Step 4: Add Cron Job

#### Option A: Using crontab (Recommended)

```bash
# Edit crontab for current user
crontab -e

# Add this line (runs monthly on the 1st at 02:00 AM):
0 2 1 * * /var/www/deschide_news_app/scripts/cron/archive-old-articles.sh

# Save and exit (Ctrl+X, then Y, then Enter in nano)
```

#### Option B: Using crontab for specific user

```bash
# Edit crontab for www-data user (if PHP-FPM runs as www-data)
sudo crontab -u www-data -e

# Add the cron entry
0 2 1 * * /var/www/deschide_news_app/scripts/cron/archive-old-articles.sh
```

#### Option C: Using /etc/cron.d/ (system-wide)

```bash
# Create a cron file
sudo nano /etc/cron.d/deschide-archive

# Add this content:
# Archive old articles on the 1st day of each month at 02:00 AM
0 2 1 * * root /var/www/deschide_news_app/scripts/cron/archive-old-articles.sh

# Save and exit

# Set correct permissions
sudo chmod 644 /etc/cron.d/deschide-archive

# Restart cron service (optional, usually auto-detected)
sudo systemctl restart cron
```

### Step 5: Verify Cron Entry

```bash
# List current user's cron jobs
crontab -l

# Or for specific user
sudo crontab -u www-data -l

# Or check system-wide cron files
cat /etc/cron.d/deschide-archive
```

---

## ⚙️ Configuration

### Script Configuration Variables

Edit the script to customize behavior:

```bash
nano /var/www/deschide_news_app/scripts/cron/archive-old-articles.sh
```

**Configurable variables** (lines 18-24):

```bash
BACKEND_DIR="/var/www/deschide_news_app/apps/backend"  # Backend location
LOG_DIR="/var/log/deschide"                            # Log directory
LOG_FILE="${LOG_DIR}/archive.log"                      # Main log file
ERROR_LOG="${LOG_DIR}/archive-error.log"               # Error log
YEARS_THRESHOLD=4                                       # Age threshold (years)
BATCH_SIZE=100                                          # Articles per batch
LOCK_FILE="/var/lock/deschide-archive.lock"            # Lock file path
```

### Cron Schedule Format

The cron format is: `minute hour day month weekday command`

**Current schedule**: `0 2 1 * *`
- **Minute**: 0 (at the top of the hour)
- **Hour**: 2 (02:00 AM)
- **Day**: 1 (first day of the month)
- **Month**: * (every month)
- **Weekday**: * (any day of the week)

**Alternative schedules**:

```bash
# Weekly on Sunday at 03:00 AM
0 3 * * 0 /var/www/deschide_news_app/scripts/cron/archive-old-articles.sh

# Quarterly (1st day of Jan, Apr, Jul, Oct at 02:00 AM)
0 2 1 1,4,7,10 * /var/www/deschide_news_app/scripts/cron/archive-old-articles.sh

# Daily at 02:00 AM (not recommended for archiving)
0 2 * * * /var/www/deschide_news_app/scripts/cron/archive-old-articles.sh
```

---

## ✅ Verification

### Step 1: Check Cron Status

```bash
# Verify cron service is running
systemctl status cron

# Expected: active (running)
```

### Step 2: Check Cron Logs

System cron logs (varies by distribution):

```bash
# Ubuntu/Debian
grep CRON /var/log/syslog | tail -20

# CentOS/RHEL
grep CRON /var/log/cron | tail -20

# Look for entries like:
# Nov 30 02:00:01 hostname CRON[12345]: (root) CMD (/var/www/deschide_news_app/scripts/cron/archive-old-articles.sh)
```

### Step 3: Check Application Logs

```bash
# Check archive log
tail -f /var/log/deschide/archive.log

# Check error log
tail -f /var/log/deschide/archive-error.log

# Check last 50 lines
tail -50 /var/log/deschide/archive.log
```

### Step 4: Verify Database Changes

After the cron runs, verify articles were archived:

```bash
cd /var/www/deschide_news_app/apps/backend

# Check archived articles count
symfony console dbal:run-sql "SELECT COUNT(*) FROM article WHERE status = 'archived'"

# See recently archived articles
symfony console dbal:run-sql "SELECT id, title, published_at, archived_at, archive_reason FROM article WHERE status = 'archived' ORDER BY archived_at DESC LIMIT 10"
```

### Step 5: Test Email Notifications (Optional)

If you configure email notifications, test with:

```bash
# Add to crontab:
0 2 1 * * /var/www/deschide_news_app/scripts/cron/archive-old-articles.sh 2>&1 | mail -s "Archive Report" admin@deschide.md
```

---

## 📊 Log Management

### Log Files

| File | Purpose | Retention |
|------|---------|-----------|
| `/var/log/deschide/archive.log` | All events (info, errors) | Rotate monthly |
| `/var/log/deschide/archive-error.log` | Errors only | Rotate monthly |

### Log Rotation Setup

Create a logrotate configuration:

```bash
# Create logrotate config file
sudo nano /etc/logrotate.d/deschide-archive
```

**Content**:

```
/var/log/deschide/archive.log
/var/log/deschide/archive-error.log
{
    monthly
    rotate 12
    compress
    delaycompress
    notifempty
    missingok
    create 0644 root root
    sharedscripts
    postrotate
        # Optional: restart services if needed
    endscript
}
```

**Save and test**:

```bash
# Test logrotate config
sudo logrotate -d /etc/logrotate.d/deschide-archive

# Force rotation (for testing)
sudo logrotate -f /etc/logrotate.d/deschide-archive

# Verify rotated files
ls -lh /var/log/deschide/
```

Expected output:
```
archive.log
archive.log.1.gz
archive.log.2.gz
archive-error.log
archive-error.log.1.gz
```

### Manual Log Cleanup

```bash
# Archive old logs (manual)
cd /var/log/deschide
gzip archive.log.old
mv archive.log.old.gz /var/backups/deschide/

# Keep only last 30 days
find /var/log/deschide/ -name "archive*.log*" -mtime +30 -delete
```

---

## 🐛 Troubleshooting

### Issue 1: Cron Job Not Running

**Symptoms**:
- No entries in `/var/log/syslog`
- No log files created

**Solutions**:

1. **Check cron service**:
   ```bash
   systemctl status cron
   sudo systemctl start cron
   ```

2. **Verify crontab syntax**:
   ```bash
   crontab -l
   # Ensure no syntax errors
   ```

3. **Check script permissions**:
   ```bash
   ls -l /var/www/deschide_news_app/scripts/cron/archive-old-articles.sh
   # Should show: -rwxr-xr-x (executable)
   ```

4. **Use absolute paths**:
   - Cron has limited `$PATH`
   - Always use full paths in crontab

### Issue 2: Script Exits with Errors

**Symptoms**:
- Exit code 1 in logs
- Error messages in `archive-error.log`

**Solutions**:

1. **Check Symfony CLI**:
   ```bash
   which symfony
   # If not found, install: wget https://get.symfony.com/cli/installer -O - | bash
   ```

2. **Verify backend directory**:
   ```bash
   ls /var/www/deschide_news_app/apps/backend
   ```

3. **Check database connection**:
   ```bash
   cd /var/www/deschide_news_app/apps/backend
   symfony console doctrine:query:sql "SELECT 1"
   ```

4. **Test command manually**:
   ```bash
   cd /var/www/deschide_news_app/apps/backend
   symfony console app:archive-old-articles --dry-run
   ```

### Issue 3: Lock File Stuck

**Symptoms**:
- Error: "Another instance is already running"
- Script won't execute

**Solutions**:

```bash
# Check if process is actually running
ps aux | grep archive-old-articles

# If not running, remove stale lock file
sudo rm -f /var/lock/deschide-archive.lock

# Retry script
/var/www/deschide_news_app/scripts/cron/archive-old-articles.sh
```

### Issue 4: Permission Denied

**Symptoms**:
- Cannot create log directory
- Cannot write to log files

**Solutions**:

```bash
# Create log directory with correct permissions
sudo mkdir -p /var/log/deschide
sudo chown $USER:$USER /var/log/deschide
sudo chmod 755 /var/log/deschide

# Or run as root (not recommended for security)
sudo /var/www/deschide_news_app/scripts/cron/archive-old-articles.sh
```

### Issue 5: No Articles Archived

**Symptoms**:
- Script runs successfully
- But 0 articles archived

**Possible Reasons**:

1. **No articles old enough**:
   ```bash
   # Check oldest published article
   cd /var/www/deschide_news_app/apps/backend
   symfony console dbal:run-sql "SELECT MIN(published_at) FROM article WHERE status = 'published'"
   ```

2. **All eligible articles already archived**:
   ```bash
   # Check archived count
   symfony console dbal:run-sql "SELECT COUNT(*) FROM article WHERE status = 'archived'"
   ```

3. **Date threshold issue**:
   - Verify `YEARS_THRESHOLD` in script (default: 4)
   - Adjust if needed

### Issue 6: Cron Output Not Logged

**Symptoms**:
- Cron runs but no output in logs
- Missing execution details

**Solutions**:

```bash
# Redirect cron output explicitly
0 2 1 * * /var/www/deschide_news_app/scripts/cron/archive-old-articles.sh >> /var/log/deschide/cron-output.log 2>&1

# Or email output
0 2 1 * * /var/www/deschide_news_app/scripts/cron/archive-old-articles.sh 2>&1 | mail -s "Archive Job" admin@deschide.md
```

---

## 🔧 Manual Execution

### Run with Default Settings

```bash
# Execute script directly
/var/www/deschide_news_app/scripts/cron/archive-old-articles.sh
```

### Run with Custom Parameters

Edit the script or run the Symfony command directly:

```bash
cd /var/www/deschide_news_app/apps/backend

# Dry-run (no changes)
symfony console app:archive-old-articles --dry-run

# Custom years threshold (archive articles older than 3 years)
symfony console app:archive-old-articles --years=3

# Smaller batch size
symfony console app:archive-old-articles --batch-size=50

# Combine options
symfony console app:archive-old-articles --years=5 --batch-size=200 --no-interaction
```

### Debug Mode

```bash
# Run with verbose output
cd /var/www/deschide_news_app/apps/backend
symfony console app:archive-old-articles -vvv
```

### Force Execution (Ignore Lock)

```bash
# Remove lock file and run
rm -f /var/lock/deschide-archive.lock
/var/www/deschide_news_app/scripts/cron/archive-old-articles.sh
```

---

## 📈 Monitoring

### Check Execution History

```bash
# Last 10 executions
grep "Starting article archiving process" /var/log/deschide/archive.log | tail -10

# Count successful executions
grep "completed successfully" /var/log/deschide/archive.log | wc -l

# Count failures
grep "failed with exit code" /var/log/deschide/archive-error.log | wc -l
```

### Performance Metrics

```bash
# Average execution time (from logs)
grep "Archive job completed" /var/log/deschide/archive.log | tail -20

# Articles archived per month
grep "Successfully archived" /var/log/deschide/archive.log
```

### Alerts Setup (Optional)

Create a monitoring script to alert on failures:

```bash
#!/bin/bash
# Check if archive job failed in last 24 hours

if grep -q "failed with exit code" /var/log/deschide/archive-error.log; then
    echo "Archive job failed! Check logs." | mail -s "ALERT: Archive Job Failed" admin@deschide.md
fi
```

### Database Statistics

```bash
cd /var/www/deschide_news_app/apps/backend

# Article status breakdown
symfony console dbal:run-sql "
SELECT status, COUNT(*) as count
FROM article
GROUP BY status
ORDER BY count DESC
"

# Archive statistics by year
symfony console dbal:run-sql "
SELECT
    EXTRACT(YEAR FROM published_at) as year,
    COUNT(*) as archived_count
FROM article
WHERE status = 'archived'
GROUP BY year
ORDER BY year DESC
"

# Recently archived articles
symfony console dbal:run-sql "
SELECT id, title, published_at, archived_at
FROM article
WHERE status = 'archived'
ORDER BY archived_at DESC
LIMIT 20
"
```

---

## 📝 Best Practices

### 1. Regular Monitoring

- Check logs weekly: `tail -100 /var/log/deschide/archive.log`
- Monitor execution time trends
- Verify archive counts match expectations

### 2. Backup Before Archiving

**Recommended**: Schedule database backups before the archive job runs:

```bash
# Crontab entry (backup at 01:30 AM, archive at 02:00 AM)
30 1 1 * * /var/www/deschide_news_app/scripts/backup-db.sh
0 2 1 * * /var/www/deschide_news_app/scripts/cron/archive-old-articles.sh
```

### 3. Test in Staging First

Before enabling in production:
1. Run with `--dry-run` to preview changes
2. Test on staging environment
3. Verify results
4. Deploy to production

### 4. Log Retention

- Keep logs for at least 12 months (1 year)
- Compress old logs to save space
- Archive critical logs to backup storage

### 5. Email Notifications

Configure email for cron failures:

```bash
# Add MAILTO to crontab
MAILTO=admin@deschide.md
0 2 1 * * /var/www/deschide_news_app/scripts/cron/archive-old-articles.sh
```

---

## 🔗 Related Documentation

- **Command Documentation**: `apps/backend/src/Command/ArchiveOldArticlesCommand.php`
- **Database Backup Guide**: `docs/DATABASE_BACKUP_GUIDE.md`
- **Backend CLAUDE.md**: `apps/backend/CLAUDE.md`
- **Main Project CLAUDE.md**: `CLAUDE.md`

---

## 📞 Support

For issues or questions:
1. Check logs: `/var/log/deschide/archive.log`
2. Review this documentation
3. Test manually: `symfony console app:archive-old-articles --dry-run`
4. Contact development team

---

**Last Updated**: November 30, 2025
**Version**: 1.0
**Maintainer**: Deschide Development Team
