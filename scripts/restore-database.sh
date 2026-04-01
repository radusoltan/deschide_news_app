#!/bin/bash
set -e

# ============================================================================
# PostgreSQL Database Restore Script for Deschide News
# ============================================================================
# Description: Restores the deschide database from a backup file
# Usage: ./restore-database.sh <backup_file.sql.gz>
# ============================================================================

# Configuration
DB_NAME="deschide"
DB_USER="deschide_admin"
DB_HOST="127.0.0.1"
DB_PORT="6432"
BACKEND_DIR="/var/www/deschide_news_app/apps/backend"

# Database password (MUST be set in environment - no hardcoded defaults)
if [ -z "${PGPASSWORD:-}" ]; then
    echo -e "${RED}[ERROR]${NC} PGPASSWORD environment variable is not set."
    echo -e "${RED}[ERROR]${NC} Set it before running: export PGPASSWORD='your_password'"
    exit 1
fi
export PGPASSWORD

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
echo "PostgreSQL Database Restore Script"
echo "Database: $DB_NAME"
echo "Started at: $(date)"
echo "=============================================="
echo ""

# Check if backup file argument is provided
if [ -z "$1" ]; then
    log_error "No backup file specified"
    echo ""
    echo "Usage: $0 <backup_file.sql.gz>"
    echo ""
    echo "Example:"
    echo "  $0 /var/www/deschide_news_app/backups/deschide_20251220_134912.sql.gz"
    echo ""
    echo "Available backups:"
    ls -lht /var/www/deschide_news_app/backups/deschide_*.sql.gz 2>/dev/null | head -10 | \
        awk '{printf "  %s %s  %-8s  %s\n", $6, $7, $5, $9}' || echo "  No backups found"
    echo ""
    exit 1
fi

BACKUP_FILE="$1"

# Check if backup file exists
if [ ! -f "$BACKUP_FILE" ]; then
    log_error "Backup file not found: $BACKUP_FILE"
    exit 1
fi

# Check if backup file is readable
if [ ! -r "$BACKUP_FILE" ]; then
    log_error "Backup file not readable: $BACKUP_FILE"
    exit 1
fi

# Get backup file size
BACKUP_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
log_info "Backup file: $BACKUP_FILE ($BACKUP_SIZE)"

# Check if pg_restore is available
if ! command -v pg_restore &> /dev/null; then
    log_error "pg_restore command not found. Please install PostgreSQL client tools."
    exit 1
fi

# Check if gunzip is available
if ! command -v gunzip &> /dev/null; then
    log_error "gunzip command not found. Please install gzip."
    exit 1
fi

# ============================================================================
# Confirmation
# ============================================================================

echo ""
log_warning "⚠️  WARNING: This will PERMANENTLY DELETE the current database!"
echo ""
echo "Current database: $DB_NAME"
echo "Backup file: $BACKUP_FILE"
echo "Backup size: $BACKUP_SIZE"
echo ""

# Check if running interactively
if [ -t 0 ]; then
    read -p "Are you sure you want to proceed? (yes/no): " CONFIRM
    echo ""

    if [ "$CONFIRM" != "yes" ]; then
        log_info "Restore cancelled by user"
        exit 0
    fi
else
    log_warning "Running in non-interactive mode. Proceeding with restore..."
fi

# ============================================================================
# Restore Process
# ============================================================================

log_info "Starting database restore process..."

# Step 1: Stop the application
log_info "Stopping Symfony application..."
if [ -d "$BACKEND_DIR" ]; then
    cd "$BACKEND_DIR"
    if symfony server:status &> /dev/null; then
        symfony server:stop
        log_success "Application stopped"
    else
        log_info "Application was not running"
    fi
else
    log_warning "Backend directory not found: $BACKEND_DIR"
fi

# Step 2: Drop existing database
log_info "Dropping existing database: $DB_NAME"
if PGPASSWORD="$PGPASSWORD" dropdb -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" "$DB_NAME" 2>/dev/null; then
    log_success "Database dropped successfully"
else
    log_warning "Database drop failed (database may not exist)"
fi

# Step 3: Create new database
log_info "Creating new database: $DB_NAME"
if PGPASSWORD="$PGPASSWORD" createdb -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" "$DB_NAME"; then
    log_success "Database created successfully"
else
    log_error "Failed to create database"
    exit 1
fi

# Step 4: Restore backup
log_info "Restoring backup (this may take several minutes)..."
if gunzip -c "$BACKUP_FILE" | PGPASSWORD="$PGPASSWORD" pg_restore -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" 2>&1 | tee /tmp/restore.log; then
    log_success "Backup restored successfully"
else
    # pg_restore may return non-zero even for successful restore due to warnings
    if grep -q "ERROR" /tmp/restore.log; then
        log_error "Restore completed with errors. Check /tmp/restore.log for details"
    else
        log_warning "Restore completed with warnings (this is often normal)"
    fi
fi

# Step 5: Verify restoration
log_info "Verifying database restoration..."

TABLE_COUNT=$(PGPASSWORD="$PGPASSWORD" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -t -c "
    SELECT COUNT(DISTINCT table_name)
    FROM information_schema.tables
    WHERE table_schema = 'public' AND table_type = 'BASE TABLE';
" 2>/dev/null | xargs)

if [ -n "$TABLE_COUNT" ] && [ "$TABLE_COUNT" -gt 0 ]; then
    log_success "Database verification: $TABLE_COUNT tables found"

    # Get row counts for main tables
    ARTICLE_COUNT=$(PGPASSWORD="$PGPASSWORD" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -t -c "SELECT COUNT(*) FROM article;" 2>/dev/null | xargs)
    CATEGORY_COUNT=$(PGPASSWORD="$PGPASSWORD" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -t -c "SELECT COUNT(*) FROM category;" 2>/dev/null | xargs)
    AUTHOR_COUNT=$(PGPASSWORD="$PGPASSWORD" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -t -c "SELECT COUNT(*) FROM author;" 2>/dev/null | xargs)
    IMAGE_COUNT=$(PGPASSWORD="$PGPASSWORD" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -t -c "SELECT COUNT(*) FROM image;" 2>/dev/null | xargs)

    echo ""
    log_info "Database statistics:"
    echo "  Tables: $TABLE_COUNT"
    echo "  Articles: ${ARTICLE_COUNT:-0}"
    echo "  Categories: ${CATEGORY_COUNT:-0}"
    echo "  Authors: ${AUTHOR_COUNT:-0}"
    echo "  Images: ${IMAGE_COUNT:-0}"
else
    log_error "Database verification failed: no tables found"
    exit 1
fi

# Step 6: Restart the application
log_info "Restarting Symfony application..."
if [ -d "$BACKEND_DIR" ]; then
    cd "$BACKEND_DIR"
    if symfony serve -d --port=8081; then
        log_success "Application started successfully"

        # Wait for server to be ready
        sleep 2

        # Check server status
        if symfony server:status &> /dev/null; then
            log_success "Application is running"
        else
            log_warning "Application may not be running correctly"
        fi
    else
        log_error "Failed to start application"
    fi
else
    log_warning "Backend directory not found: $BACKEND_DIR"
fi

# Step 7: Test the application
log_info "Testing application..."

# Test API endpoint
if curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8081/api | grep -q "200"; then
    log_success "API endpoint responding correctly"
else
    log_warning "API endpoint may not be responding correctly"
fi

# ============================================================================
# Summary
# ============================================================================

echo ""
echo "=============================================="
log_success "Database restore completed!"
echo "=============================================="
echo ""
log_info "Restore summary:"
echo "  Backup file: $BACKUP_FILE"
echo "  Database: $DB_NAME"
echo "  Tables restored: $TABLE_COUNT"
echo "  Application status: Running on port 8081"
echo ""
echo "=============================================="
echo "Completed at: $(date)"
echo "=============================================="
echo ""

# ============================================================================
# Next Steps
# ============================================================================

cat << 'EOF'
📝 NEXT STEPS:

1. Verify the application in your browser:
   http://localhost:3005

2. Test the API:
   curl http://127.0.0.1:8081/api

3. Check database content:
   cd /var/www/deschide_news_app/apps/backend
   symfony console doctrine:query:sql "SELECT COUNT(*) FROM article"

4. Monitor application logs:
   symfony server:log

5. If you encounter issues:
   - Check logs: /var/log/postgresql/
   - Review restore log: /tmp/restore.log
   - Contact system administrator

EOF

exit 0
