#!/bin/bash
set -e

# Configuration
DB_NAME="deschide"
DB_USER="deschide_admin"
DB_HOST="127.0.0.1"
BACKUP_DIR="/var/www/deschide_news_app/backups/postgresql"
DATE=$(date +%Y%m%d_%H%M%S)
DAY_OF_WEEK=$(date +%u)
DAY_OF_MONTH=$(date +%d)

# Create backup directories if not exist
mkdir -p "$BACKUP_DIR"/{daily,weekly,monthly}

echo "=========================================="
echo "Backup started at $(date)"
echo "=========================================="

# Daily backup (pg_dump with custom format for compression)
DAILY_BACKUP="$BACKUP_DIR/daily/${DB_NAME}_${DATE}.dump"
echo "Creating daily backup: $DAILY_BACKUP"
PGPASSWORD="${PGPASSWORD:-iIzmHACi7+W+yq9NFRT2FeadUPAmgEna}" pg_dump -h "$DB_HOST" -U "$DB_USER" -Fc "$DB_NAME" > "$DAILY_BACKUP"

# Verify backup
if [ -f "$DAILY_BACKUP" ] && [ -s "$DAILY_BACKUP" ]; then
    echo "Daily backup created successfully: $(du -h $DAILY_BACKUP | cut -f1)"
else
    echo "ERROR: Daily backup failed!"
    exit 1
fi

# Weekly backup (Sunday = 7)
if [ "$DAY_OF_WEEK" -eq 7 ]; then
    WEEKLY_BACKUP="$BACKUP_DIR/weekly/${DB_NAME}_week_$(date +%Y%W).dump"
    cp "$DAILY_BACKUP" "$WEEKLY_BACKUP"
    echo "Weekly backup created: $WEEKLY_BACKUP"
fi

# Monthly backup (1st of month)
if [ "$DAY_OF_MONTH" -eq "01" ]; then
    MONTHLY_BACKUP="$BACKUP_DIR/monthly/${DB_NAME}_$(date +%Y%m).dump"
    cp "$DAILY_BACKUP" "$MONTHLY_BACKUP"
    echo "Monthly backup created: $MONTHLY_BACKUP"
fi

# Cleanup old backups
echo "Cleaning up old backups..."
find "$BACKUP_DIR/daily" -name "*.dump" -mtime +7 -delete 2>/dev/null || true
find "$BACKUP_DIR/weekly" -name "*.dump" -mtime +30 -delete 2>/dev/null || true
find "$BACKUP_DIR/monthly" -name "*.dump" -mtime +365 -delete 2>/dev/null || true

echo "Backup completed at $(date)"
echo "=========================================="
