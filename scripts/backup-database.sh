#!/bin/bash
set -e

# ============================================================================
# PostgreSQL Backup Script for Deschide News Database
# ============================================================================
# Description: Creates compressed backups of the deschide_news database
# Usage: ./backup-database.sh
# Schedule: Run daily via cron (recommended: 2 AM)
# ============================================================================

# Configuration
DB_NAME="deschide"
DB_USER="deschide_admin"
DB_HOST="127.0.0.1"
DB_PORT="6432"
BACKUP_DIR="/var/www/deschide_news_app/backups"
DATE=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=7

# Database password (from environment or default)
export PGPASSWORD="${PGPASSWORD:-iIzmHACi7+W+yq9NFRT2FeadUPAmgEna}"

# Color codes for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# ============================================================================
# Functions
# ============================================================================

log_info() {
    echo -e "${BLUE}[INFO]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1"
}

# ============================================================================
# Pre-flight Checks
# ============================================================================

echo "=============================================="
echo "PostgreSQL Backup Script"
echo "Database: $DB_NAME"
echo "Started at: $(date)"
echo "=============================================="

# Check if pg_dump is available
if ! command -v pg_dump &> /dev/null; then
    log_error "pg_dump command not found. Please install PostgreSQL client tools."
    exit 1
fi

# Check database connectivity
log_info "Testing database connection..."
if ! PGPASSWORD="$PGPASSWORD" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -c "SELECT 1;" &> /dev/null; then
    log_error "Cannot connect to database. Please check credentials and connection."
    exit 1
fi
log_success "Database connection OK"

# Create backup directory if not exists
if [ ! -d "$BACKUP_DIR" ]; then
    log_info "Creating backup directory: $BACKUP_DIR"
    mkdir -p "$BACKUP_DIR"
fi

# ============================================================================
# Backup Execution
# ============================================================================

BACKUP_FILE="$BACKUP_DIR/${DB_NAME}_${DATE}.sql.gz"

log_info "Starting backup to: $BACKUP_FILE"
log_info "Using pg_dump with custom format (-Fc) and gzip compression..."

# Execute backup with error handling
if PGPASSWORD="$PGPASSWORD" pg_dump -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -Fc "$DB_NAME" | gzip > "$BACKUP_FILE"; then
    log_success "Backup created successfully"
else
    log_error "Backup failed with exit code $?"
    exit 1
fi

# ============================================================================
# Backup Verification
# ============================================================================

log_info "Verifying backup integrity..."

if [ ! -f "$BACKUP_FILE" ]; then
    log_error "Backup file not found: $BACKUP_FILE"
    exit 1
fi

if [ ! -s "$BACKUP_FILE" ]; then
    log_error "Backup file is empty: $BACKUP_FILE"
    exit 1
fi

# Get file size
SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
SIZE_BYTES=$(stat -f%z "$BACKUP_FILE" 2>/dev/null || stat -c%s "$BACKUP_FILE" 2>/dev/null)

# Minimum size check (should be at least 1KB for valid database)
if [ "$SIZE_BYTES" -lt 1024 ]; then
    log_error "Backup file suspiciously small ($SIZE). Possible corruption."
    exit 1
fi

log_success "Backup verified: $BACKUP_FILE ($SIZE)"

# ============================================================================
# Get Database Statistics
# ============================================================================

log_info "Database statistics:"

# Get table count and row counts
log_info "Fetching database statistics..."

TABLE_COUNT=$(PGPASSWORD="$PGPASSWORD" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -t -c "
    SELECT COUNT(DISTINCT table_name)
    FROM information_schema.tables
    WHERE table_schema = 'public' AND table_type = 'BASE TABLE';
" 2>/dev/null | xargs)

ARTICLE_COUNT=$(PGPASSWORD="$PGPASSWORD" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -t -c "SELECT COUNT(*) FROM article;" 2>/dev/null | xargs)
CATEGORY_COUNT=$(PGPASSWORD="$PGPASSWORD" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -t -c "SELECT COUNT(*) FROM category;" 2>/dev/null | xargs)
AUTHOR_COUNT=$(PGPASSWORD="$PGPASSWORD" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -t -c "SELECT COUNT(*) FROM author;" 2>/dev/null | xargs)
IMAGE_COUNT=$(PGPASSWORD="$PGPASSWORD" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -t -c "SELECT COUNT(*) FROM image;" 2>/dev/null | xargs)

echo "  Tables: $TABLE_COUNT"
echo "  Articles: ${ARTICLE_COUNT:-0}"
echo "  Categories: ${CATEGORY_COUNT:-0}"
echo "  Authors: ${AUTHOR_COUNT:-0}"
echo "  Images: ${IMAGE_COUNT:-0}"

# Get database size
DB_SIZE=$(PGPASSWORD="$PGPASSWORD" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -t -c "
    SELECT pg_size_pretty(pg_database_size('$DB_NAME'));
" 2>/dev/null | xargs)

log_info "Database size: $DB_SIZE"
log_info "Backup size: $SIZE"

# Calculate compression ratio if bc is available
if command -v bc &> /dev/null; then
    DB_SIZE_BYTES=$(PGPASSWORD="$PGPASSWORD" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -t -c "SELECT pg_database_size('$DB_NAME');" 2>/dev/null | xargs)
    if [ -n "$DB_SIZE_BYTES" ] && [ "$DB_SIZE_BYTES" -gt 0 ]; then
        COMPRESSION_RATIO=$(echo "scale=1; 100 - (100 * $SIZE_BYTES / $DB_SIZE_BYTES)" | bc 2>/dev/null || echo "N/A")
        log_info "Compression ratio: ~${COMPRESSION_RATIO}%"
    fi
fi

# ============================================================================
# Cleanup Old Backups
# ============================================================================

log_info "Cleaning up backups older than $RETENTION_DAYS days..."

DELETED_COUNT=$(find "$BACKUP_DIR" -name "${DB_NAME}_*.sql.gz" -mtime +$RETENTION_DAYS -type f | wc -l | xargs)

if [ "$DELETED_COUNT" -gt 0 ]; then
    find "$BACKUP_DIR" -name "${DB_NAME}_*.sql.gz" -mtime +$RETENTION_DAYS -type f -delete
    log_success "Deleted $DELETED_COUNT old backup(s)"
else
    log_info "No old backups to delete"
fi

# ============================================================================
# Summary
# ============================================================================

echo ""
echo "=============================================="
log_success "Backup completed successfully!"
echo "=============================================="
echo ""
log_info "Current backups in $BACKUP_DIR:"
echo ""

if ls "$BACKUP_DIR"/${DB_NAME}_*.sql.gz 1> /dev/null 2>&1; then
    ls -lht "$BACKUP_DIR"/${DB_NAME}_*.sql.gz | head -10 | awk '{printf "  %s %s  %-8s  %s\n", $6, $7, $5, $9}'

    TOTAL_BACKUPS=$(ls -1 "$BACKUP_DIR"/${DB_NAME}_*.sql.gz | wc -l | xargs)
    TOTAL_SIZE=$(du -sh "$BACKUP_DIR" | cut -f1)

    echo ""
    log_info "Total backups: $TOTAL_BACKUPS"
    log_info "Total backup directory size: $TOTAL_SIZE"
else
    log_warning "No backups found in $BACKUP_DIR"
fi

echo ""
echo "=============================================="
echo "Completed at: $(date)"
echo "=============================================="

# ============================================================================
# Restore Instructions
# ============================================================================

cat << 'EOF'

📝 RESTORE INSTRUCTIONS:

To restore from this backup:

1. Stop the application:
   cd /var/www/deschide_news_app/apps/backend
   symfony server:stop

2. Drop existing database (CAUTION!):
   PGPASSWORD="iIzmHACi7+W+yq9NFRT2FeadUPAmgEna" \
   dropdb -h 127.0.0.1 -p 6432 -U deschide_admin deschide

3. Create new database:
   PGPASSWORD="iIzmHACi7+W+yq9NFRT2FeadUPAmgEna" \
   createdb -h 127.0.0.1 -p 6432 -U deschide_admin deschide

4. Restore backup:
   gunzip -c BACKUP_FILE.sql.gz | PGPASSWORD="iIzmHACi7+W+yq9NFRT2FeadUPAmgEna" \
   pg_restore -h 127.0.0.1 -p 6432 -U deschide_admin -d deschide

5. Restart the application:
   cd /var/www/deschide_news_app/apps/backend
   symfony serve -d --port=8081

EOF

exit 0
