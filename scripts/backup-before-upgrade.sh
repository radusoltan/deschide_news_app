#!/bin/bash

###############################################################################
# Pre-Upgrade Backup Script
# Purpose: Create comprehensive backup before Symfony upgrade
# Usage: ./scripts/backup-before-upgrade.sh [phase]
###############################################################################

set -e

PHASE="${1:-phase1}"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="${BACKUP_DIR:-/var/backups/deschide_news_app}"
PROJECT_DIR="/var/www/deschide_news_app"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}======================================"
echo "Pre-Upgrade Backup Script"
echo "======================================"
echo -e "${NC}"
echo "Phase: $PHASE"
echo "Timestamp: $TIMESTAMP"
echo "Backup Directory: $BACKUP_DIR"
echo ""

# Create backup directory if it doesn't exist
if [ ! -d "$BACKUP_DIR" ]; then
    echo -e "${YELLOW}Creating backup directory: $BACKUP_DIR${NC}"
    mkdir -p "$BACKUP_DIR"
fi

BACKUP_PATH="$BACKUP_DIR/backup_${PHASE}_${TIMESTAMP}"
mkdir -p "$BACKUP_PATH"

echo -e "${GREEN}✓ Backup directory created: $BACKUP_PATH${NC}"
echo ""

# Function to check command success
check_success() {
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ $1${NC}"
    else
        echo -e "${RED}✗ $1 FAILED${NC}"
        exit 1
    fi
}

# 1. Backup Git State
echo -e "${BLUE}[1/8] Backing up Git state...${NC}"
cd "$PROJECT_DIR"

git rev-parse HEAD > "$BACKUP_PATH/git_commit.txt"
git branch --show-current > "$BACKUP_PATH/git_branch.txt"
git status > "$BACKUP_PATH/git_status.txt"
git diff > "$BACKUP_PATH/git_diff.patch" 2>/dev/null || true

check_success "Git state backed up"
echo ""

# 2. Backup Backend Code
echo -e "${BLUE}[2/8] Backing up backend code...${NC}"
cd "$PROJECT_DIR/apps/backend"

tar -czf "$BACKUP_PATH/backend_code.tar.gz" \
    --exclude='var/cache' \
    --exclude='var/log' \
    --exclude='vendor' \
    --exclude='node_modules' \
    . 2>/dev/null

check_success "Backend code backed up ($(du -h "$BACKUP_PATH/backend_code.tar.gz" | cut -f1))"
echo ""

# 3. Backup Composer Files
echo -e "${BLUE}[3/8] Backing up Composer files...${NC}"

cp composer.json "$BACKUP_PATH/composer.json"
cp composer.lock "$BACKUP_PATH/composer.lock"

if [ -f "symfony.lock" ]; then
    cp symfony.lock "$BACKUP_PATH/symfony.lock"
fi

check_success "Composer files backed up"
echo ""

# 4. Backup Configuration Files
echo -e "${BLUE}[4/8] Backing up configuration files...${NC}"

mkdir -p "$BACKUP_PATH/config"
cp -r config/* "$BACKUP_PATH/config/" 2>/dev/null || true

if [ -f ".env" ]; then
    cp .env "$BACKUP_PATH/.env"
fi
if [ -f ".env.local" ]; then
    cp .env.local "$BACKUP_PATH/.env.local"
fi

check_success "Configuration files backed up"
echo ""

# 5. Backup Database
echo -e "${BLUE}[5/8] Backing up PostgreSQL database...${NC}"

# Read database credentials from .env
if [ -f ".env" ]; then
    export $(grep -v '^#' .env | xargs)
fi

# Extract database connection details
DB_URL="${DATABASE_URL:-}"

if [ -n "$DB_URL" ]; then
    # Parse DATABASE_URL
    # Format: postgresql://user:pass@host:port/dbname
    DB_NAME=$(echo "$DB_URL" | sed -n 's/.*\/\([^?]*\).*/\1/p')
    DB_USER=$(echo "$DB_URL" | sed -n 's/.*:\/\/\([^:]*\):.*/\1/p')
    DB_HOST=$(echo "$DB_URL" | sed -n 's/.*@\([^:\/]*\).*/\1/p')
    
    if [ -n "$DB_NAME" ]; then
        echo "Database: $DB_NAME"
        echo "User: $DB_USER"
        echo "Host: $DB_HOST"
        
        # Backup database
        pg_dump -U "$DB_USER" -h "$DB_HOST" "$DB_NAME" \
            > "$BACKUP_PATH/database_$DB_NAME.sql" 2>/dev/null || \
        PGPASSWORD="$(echo "$DB_URL" | sed -n 's/.*:\([^@]*\)@.*/\1/p')" \
            pg_dump -U "$DB_USER" -h "$DB_HOST" "$DB_NAME" \
            > "$BACKUP_PATH/database_$DB_NAME.sql"
        
        check_success "Database backed up ($(du -h "$BACKUP_PATH/database_$DB_NAME.sql" | cut -f1))"
        
        # Compress database backup
        gzip "$BACKUP_PATH/database_$DB_NAME.sql"
        check_success "Database backup compressed"
    else
        echo -e "${YELLOW}⚠ Could not parse database name from DATABASE_URL${NC}"
    fi
else
    echo -e "${YELLOW}⚠ DATABASE_URL not found in .env${NC}"
fi
echo ""

# 6. Backup Vendor Directory (optional, commented out by default)
# echo -e "${BLUE}[6/8] Backing up vendor directory...${NC}"
# echo -e "${YELLOW}⚠ Skipping vendor backup (can be restored with composer install)${NC}"
# echo "To include vendor backup, uncomment relevant lines in script"
# echo ""

# 7. System Information
echo -e "${BLUE}[6/8] Collecting system information...${NC}"

{
    echo "=== System Information ==="
    echo "Timestamp: $(date)"
    echo "Hostname: $(hostname)"
    echo "OS: $(uname -a)"
    echo ""
    echo "=== PHP Information ==="
    php -v
    echo ""
    echo "=== PHP Extensions ==="
    php -m
    echo ""
    echo "=== Composer Version ==="
    composer --version
    echo ""
    echo "=== Symfony Version ==="
    php bin/console --version 2>/dev/null || echo "N/A"
    echo ""
    echo "=== Database Version ==="
    psql --version 2>/dev/null || echo "N/A"
    echo ""
    echo "=== Disk Space ==="
    df -h "$PROJECT_DIR"
    echo ""
    echo "=== Memory ==="
    free -h
} > "$BACKUP_PATH/system_info.txt"

check_success "System information collected"
echo ""

# 8. Backup Frontend (if exists)
echo -e "${BLUE}[7/8] Checking frontend...${NC}"

if [ -d "$PROJECT_DIR/apps/frontend" ]; then
    echo "Frontend directory found, backing up..."
    cd "$PROJECT_DIR/apps/frontend"
    
    tar -czf "$BACKUP_PATH/frontend_code.tar.gz" \
        --exclude='node_modules' \
        --exclude='.next' \
        . 2>/dev/null
    
    if [ -f "package.json" ]; then
        cp package.json "$BACKUP_PATH/frontend_package.json"
    fi
    if [ -f "package-lock.json" ]; then
        cp package-lock.json "$BACKUP_PATH/frontend_package-lock.json"
    fi
    
    check_success "Frontend backed up ($(du -h "$BACKUP_PATH/frontend_code.tar.gz" | cut -f1))"
else
    echo -e "${YELLOW}⚠ No frontend directory found${NC}"
fi
echo ""

# 9. Create Backup Manifest
echo -e "${BLUE}[8/8] Creating backup manifest...${NC}"

cat > "$BACKUP_PATH/MANIFEST.txt" << EOF
Symfony Upgrade Backup Manifest
================================

Backup Details:
- Phase: $PHASE
- Timestamp: $TIMESTAMP
- Created: $(date '+%Y-%m-%d %H:%M:%S')
- Hostname: $(hostname)

Git Information:
- Commit: $(cat "$BACKUP_PATH/git_commit.txt")
- Branch: $(cat "$BACKUP_PATH/git_branch.txt")

Symfony Version:
$(php bin/console --version 2>/dev/null || echo "N/A")

PHP Version:
$(php -v | head -1)

Backup Contents:
$(ls -lh "$BACKUP_PATH" | tail -n +2)

Total Backup Size:
$(du -sh "$BACKUP_PATH" | cut -f1)

Restoration Instructions:
=========================

To restore this backup:

1. Stop services:
   sudo systemctl stop php-fpm nginx

2. Restore code:
   cd /var/www/deschide_news_app/apps/backend
   tar -xzf $BACKUP_PATH/backend_code.tar.gz

3. Restore composer files:
   cp $BACKUP_PATH/composer.json composer.json
   cp $BACKUP_PATH/composer.lock composer.lock
   composer install --no-dev --optimize-autoloader

4. Restore database:
   gunzip < $BACKUP_PATH/database_*.sql.gz | psql -U DB_USER -h DB_HOST DB_NAME

5. Restore configuration:
   cp $BACKUP_PATH/config/* config/
   cp $BACKUP_PATH/.env .env

6. Clear caches:
   rm -rf var/cache/*
   php bin/console cache:clear --env=prod

7. Restart services:
   sudo systemctl start php-fpm nginx

8. Verify:
   php bin/console --version
   curl -I https://deschide.md/api/articles

EOF

check_success "Backup manifest created"
echo ""

# Create a quick restore script
cat > "$BACKUP_PATH/restore.sh" << 'RESTORE_EOF'
#!/bin/bash

###############################################################################
# Quick Restore Script
# Generated automatically by backup-before-upgrade.sh
###############################################################################

set -e

BACKUP_PATH="$(dirname "$0")"
PROJECT_DIR="/var/www/deschide_news_app"

echo "======================================"
echo "Restoring from backup"
echo "======================================"
echo ""
echo "Backup Path: $BACKUP_PATH"
echo "Project Path: $PROJECT_DIR"
echo ""

read -p "This will overwrite current files. Continue? (yes/no): " CONFIRM

if [ "$CONFIRM" != "yes" ]; then
    echo "Restore cancelled."
    exit 1
fi

echo ""
echo "Stopping services..."
sudo systemctl stop php-fpm nginx || true

echo "Restoring backend code..."
cd "$PROJECT_DIR/apps/backend"
tar -xzf "$BACKUP_PATH/backend_code.tar.gz"

echo "Restoring composer files..."
cp "$BACKUP_PATH/composer.json" composer.json
cp "$BACKUP_PATH/composer.lock" composer.lock

echo "Running composer install..."
composer install --no-dev --optimize-autoloader

echo "Restoring database..."
DB_BACKUP=$(ls "$BACKUP_PATH"/database_*.sql.gz | head -1)
if [ -n "$DB_BACKUP" ]; then
    # You'll need to provide DB credentials
    echo "Database backup found: $DB_BACKUP"
    echo "Restore with: gunzip < $DB_BACKUP | psql -U USER -h HOST DATABASE"
else
    echo "No database backup found"
fi

echo "Clearing caches..."
rm -rf var/cache/*
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

echo "Starting services..."
sudo systemctl start php-fpm nginx

echo ""
echo "======================================"
echo "Restore complete!"
echo "======================================"
echo ""
echo "Verify with:"
echo "  php bin/console --version"
echo "  curl -I https://deschide.md/api/articles"

RESTORE_EOF

chmod +x "$BACKUP_PATH/restore.sh"

echo -e "${GREEN}======================================"
echo "Backup Complete!"
echo "======================================${NC}"
echo ""
echo -e "${GREEN}✓ All components backed up successfully${NC}"
echo ""
echo "Backup Location: $BACKUP_PATH"
echo "Backup Size: $(du -sh "$BACKUP_PATH" | cut -f1)"
echo ""
echo "Backup Contents:"
ls -lh "$BACKUP_PATH"
echo ""
echo "To restore this backup:"
echo "  $BACKUP_PATH/restore.sh"
echo ""
echo "Or manually follow instructions in:"
echo "  $BACKUP_PATH/MANIFEST.txt"
echo ""
echo -e "${BLUE}======================================"
echo "You can now proceed with the upgrade"
echo "======================================${NC}"
