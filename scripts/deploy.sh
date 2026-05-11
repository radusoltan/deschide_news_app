#!/usr/bin/env bash
# =============================================================================
# Deschide News — Production Deployment Script
# =============================================================================
# Usage:
#   ./scripts/deploy.sh                  # Full deployment
#   ./scripts/deploy.sh --dry-run        # Print commands without executing
#   ./scripts/deploy.sh --skip-backup    # Skip database backup step
#   ./scripts/deploy.sh --backend-only   # Deploy backend only
#   ./scripts/deploy.sh --frontend-only  # Deploy frontend only
#   ./scripts/deploy.sh --confirm-flush  # Flush Redis DB 1 before migrations
#                                        # (required when deploying entity-removal
#                                        # migrations, e.g. ADR-019 cluster drop)
# =============================================================================

set -euo pipefail

# =============================================================================
# Configuration
# =============================================================================

ROOT_DIR="/var/www/deschide_news_app"
BACKEND_DIR="$ROOT_DIR/apps/backend"
FRONTEND_DIR="$ROOT_DIR/apps/frontend"
SCRIPTS_DIR="$ROOT_DIR/scripts"
BACKUP_DIR="$ROOT_DIR/backups"
LOG_FILE="$ROOT_DIR/deploy.log"

BACKEND_URL="${BACKEND_URL:-http://127.0.0.1:8081}"
FRONTEND_URL="${FRONTEND_URL:-http://localhost:3005}"
GIT_BRANCH="${GIT_BRANCH:-main}"

# PHP-FPM service name (adjust for your server)
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.4-fpm}"

# Minimum disk space required (in MB)
MIN_DISK_SPACE_MB=500

# Smoke check timeout (seconds)
SMOKE_TIMEOUT=15

# =============================================================================
# Parse Arguments
# =============================================================================

DRY_RUN=false
SKIP_BACKUP=false
BACKEND_ONLY=false
FRONTEND_ONLY=false
CONFIRM_FLUSH=false

for arg in "$@"; do
    case "$arg" in
        --dry-run)
            DRY_RUN=true
            ;;
        --skip-backup)
            SKIP_BACKUP=true
            ;;
        --backend-only)
            BACKEND_ONLY=true
            ;;
        --frontend-only)
            FRONTEND_ONLY=true
            ;;
        --confirm-flush)
            CONFIRM_FLUSH=true
            ;;
        --help|-h)
            echo "Usage: $0 [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --dry-run         Print commands without executing"
            echo "  --skip-backup     Skip database backup step"
            echo "  --backend-only    Deploy backend (Symfony) only"
            echo "  --frontend-only   Deploy frontend (Next.js) only"
            echo "  --confirm-flush   Flush Redis DB 1 before migrations (see"
            echo "                    DEPLOY_README.md 'Redis pre-flight' section)"
            echo "  --help, -h        Show this help message"
            echo ""
            echo "Environment variables:"
            echo "  GIT_BRANCH          Branch to deploy (default: main)"
            echo "  PHP_FPM_SERVICE     PHP-FPM service name (default: php8.4-fpm)"
            echo "  BACKEND_URL         Backend URL for smoke check (default: http://127.0.0.1:8081)"
            echo "  FRONTEND_URL        Frontend URL for smoke check (default: http://localhost:3005)"
            echo "  REDIS_HOST          Redis host for FLUSHDB (default: 127.0.0.1)"
            echo "  REDIS_PORT          Redis port for FLUSHDB (default: 6379)"
            echo "  REDIS_CACHE_DB      Redis DB index to flush (default: 1)"
            exit 0
            ;;
        *)
            echo "Unknown option: $arg"
            echo "Use --help for usage information."
            exit 1
            ;;
    esac
done

# =============================================================================
# Colors & Logging
# =============================================================================

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

DEPLOY_START=$(date +%s)
DEPLOY_TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

log_info()    { echo -e "${BLUE}[INFO]${NC}    $(date '+%H:%M:%S') $1"; }
log_success() { echo -e "${GREEN}[OK]${NC}      $(date '+%H:%M:%S') $1"; }
log_warning() { echo -e "${YELLOW}[WARN]${NC}    $(date '+%H:%M:%S') $1"; }
log_error()   { echo -e "${RED}[ERROR]${NC}   $(date '+%H:%M:%S') $1"; }
log_step()    { echo -e "${CYAN}${BOLD}[STEP]${NC}    $(date '+%H:%M:%S') $1"; }
log_dry()     { echo -e "${YELLOW}[DRY-RUN]${NC} $1"; }

# Execute or print (dry-run aware)
run_cmd() {
    if [ "$DRY_RUN" = true ]; then
        log_dry "$*"
    else
        "$@"
    fi
}

# Execute shell command string (for piped commands)
run_shell() {
    if [ "$DRY_RUN" = true ]; then
        log_dry "$*"
    else
        eval "$@"
    fi
}

# =============================================================================
# Rollback State
# =============================================================================

BACKEND_GIT_SHA=""
BACKUP_FILE=""
ROLLBACK_NEEDED=false

cleanup_on_failure() {
    if [ "$DRY_RUN" = true ]; then
        return
    fi

    echo ""
    log_error "Deployment FAILED. Initiating rollback..."
    echo ""

    # Rollback backend git state
    if [ -n "$BACKEND_GIT_SHA" ]; then
        log_info "Rolling back git to $BACKEND_GIT_SHA ..."
        cd "$ROOT_DIR"
        git reset --hard "$BACKEND_GIT_SHA" 2>/dev/null || true
        log_info "Git rollback complete."
    fi

    # Restore database if backup was taken
    if [ -n "$BACKUP_FILE" ] && [ -f "$BACKUP_FILE" ]; then
        log_info "Database backup available at: $BACKUP_FILE"
        log_info "To restore: $SCRIPTS_DIR/restore-database.sh $BACKUP_FILE"
    fi

    # Restart services to ensure consistency
    log_info "Restarting PHP-FPM..."
    sudo systemctl restart "$PHP_FPM_SERVICE" 2>/dev/null || true

    log_info "Restarting PM2 frontend..."
    cd "$FRONTEND_DIR" && pm2 reload ecosystem.config.js --env production 2>/dev/null || true

    echo ""
    log_error "Rollback complete. Please verify the application state manually."
    echo ""
}

trap 'if [ "$?" -ne 0 ] && [ "$DRY_RUN" = false ]; then cleanup_on_failure; fi' EXIT

# =============================================================================
# Header
# =============================================================================

echo ""
echo -e "${BLUE}============================================================================${NC}"
echo -e "${BLUE}   DESCHIDE NEWS — PRODUCTION DEPLOYMENT${NC}"
echo -e "${BLUE}============================================================================${NC}"
echo ""
echo -e "  Timestamp:    ${BOLD}$DEPLOY_TIMESTAMP${NC}"
echo -e "  Branch:       ${BOLD}$GIT_BRANCH${NC}"
echo -e "  Dry Run:      ${BOLD}$DRY_RUN${NC}"
echo -e "  Skip Backup:  ${BOLD}$SKIP_BACKUP${NC}"
echo -e "  Backend Only: ${BOLD}$BACKEND_ONLY${NC}"
echo -e "  Frontend Only:${BOLD}$FRONTEND_ONLY${NC}"
echo -e "  Confirm Flush:${BOLD}$CONFIRM_FLUSH${NC}"
echo ""
echo -e "${BLUE}----------------------------------------------------------------------------${NC}"

# =============================================================================
# STEP 1: Pre-Deploy Checks
# =============================================================================

log_step "[1/6] Pre-deploy checks"

# Check disk space
AVAILABLE_MB=$(df -m "$ROOT_DIR" | awk 'NR==2 {print $4}')
if [ "$AVAILABLE_MB" -lt "$MIN_DISK_SPACE_MB" ]; then
    log_error "Insufficient disk space: ${AVAILABLE_MB}MB available, ${MIN_DISK_SPACE_MB}MB required."
    exit 1
fi
log_success "Disk space: ${AVAILABLE_MB}MB available"

# Check required services
check_service() {
    local name="$1"
    local check_cmd="$2"
    local critical="${3:-true}"

    if [ "$DRY_RUN" = true ]; then
        log_dry "Check service: $name"
        return 0
    fi

    if eval "$check_cmd" > /dev/null 2>&1; then
        log_success "$name is running"
        return 0
    else
        if [ "$critical" = "true" ]; then
            log_error "$name is NOT running (critical)"
            return 1
        else
            log_warning "$name is NOT running (non-critical, continuing)"
            return 0
        fi
    fi
}

check_service "PostgreSQL" "pg_isready -h localhost -p 5432" "true"
check_service "Redis" "redis-cli -p 6379 -n 1 PING 2>/dev/null | grep -q PONG" "true"
check_service "RabbitMQ" "ss -tulpn 2>/dev/null | grep -q ':5672 '" "false"
check_service "Elasticsearch" "curl -sk --max-time 3 https://localhost:9200/_cluster/health | grep -q status" "false"

# Check required tools
for tool in git composer pnpm pm2 curl; do
    if command -v "$tool" &> /dev/null; then
        log_success "$tool found: $(command -v "$tool")"
    else
        log_error "$tool not found in PATH"
        exit 1
    fi
done

# Record current git SHA for rollback
BACKEND_GIT_SHA=$(cd "$ROOT_DIR" && git rev-parse HEAD)
log_info "Current commit: $BACKEND_GIT_SHA"

echo ""

# =============================================================================
# STEP 2: Database Backup
# =============================================================================

log_step "[2/6] Database backup"

if [ "$SKIP_BACKUP" = true ]; then
    log_warning "Database backup SKIPPED (--skip-backup)"
elif [ "$FRONTEND_ONLY" = true ]; then
    log_info "Skipping backup for frontend-only deploy"
else
    if [ -x "$SCRIPTS_DIR/backup-database.sh" ]; then
        log_info "Running database backup..."
        run_cmd "$SCRIPTS_DIR/backup-database.sh"
        if [ "$DRY_RUN" = false ]; then
            # Find the most recent backup for potential rollback
            BACKUP_FILE=$(ls -t "$BACKUP_DIR"/deschide_*.sql.gz 2>/dev/null | head -1)
            if [ -n "$BACKUP_FILE" ]; then
                log_success "Backup created: $BACKUP_FILE"
            else
                log_warning "Could not locate backup file"
            fi
        fi
    else
        log_warning "Backup script not found or not executable: $SCRIPTS_DIR/backup-database.sh"
        log_warning "Continuing without backup..."
    fi
fi

echo ""

# =============================================================================
# STEP 3: Pull Latest Code
# =============================================================================

log_step "[3/6] Pull latest code"

log_info "Pulling branch: $GIT_BRANCH"
run_cmd git -C "$ROOT_DIR" fetch origin
run_cmd git -C "$ROOT_DIR" pull origin "$GIT_BRANCH"

if [ "$DRY_RUN" = false ]; then
    NEW_SHA=$(cd "$ROOT_DIR" && git rev-parse HEAD)
    if [ "$NEW_SHA" = "$BACKEND_GIT_SHA" ]; then
        log_info "Already up to date ($NEW_SHA)"
    else
        COMMIT_COUNT=$(cd "$ROOT_DIR" && git rev-list "$BACKEND_GIT_SHA".."$NEW_SHA" --count 2>/dev/null || echo "?")
        log_success "Updated: $BACKEND_GIT_SHA -> $NEW_SHA ($COMMIT_COUNT new commits)"
    fi
fi

echo ""

# =============================================================================
# STEP 4: Backend Deploy (Symfony)
# =============================================================================

if [ "$FRONTEND_ONLY" = false ]; then
    log_step "[4/6] Backend deployment (Symfony)"

    log_info "Installing PHP dependencies..."
    run_shell "cd '$BACKEND_DIR' && composer install --no-dev --optimize-autoloader --no-interaction --classmap-authoritative"

    # =========================================================================
    # Redis pre-flight (ADR-019 / Sprint 52 cluster-drop pattern)
    # =========================================================================
    # When a deploy includes migrations that drop or rename entities, the
    # Doctrine metadata cache in Redis (DB 1, prefix deschide_news:*) may
    # still hold class definitions for the removed entities. The first
    # request after deploy then explodes with a metadata-mapping error.
    #
    # Gate the FLUSHDB behind --confirm-flush so the operator opts in
    # explicitly. Without the flag, we warn but do not fail — many deploys
    # (frontend-only, hotfixes that don't touch entities) don't need it.
    #
    # See: scripts/DEPLOY_README.md § "Redis pre-flight" for when to opt in.
    REDIS_HOST_VALUE="${REDIS_HOST:-127.0.0.1}"
    REDIS_PORT_VALUE="${REDIS_PORT:-6379}"
    REDIS_CACHE_DB_VALUE="${REDIS_CACHE_DB:-1}"

    if [ "$CONFIRM_FLUSH" = true ]; then
        log_info "Flushing Redis cache (DB ${REDIS_CACHE_DB_VALUE} on ${REDIS_HOST_VALUE}:${REDIS_PORT_VALUE}) ..."
        if [ "$DRY_RUN" = true ]; then
            log_dry "redis-cli -h ${REDIS_HOST_VALUE} -p ${REDIS_PORT_VALUE} -n ${REDIS_CACHE_DB_VALUE} FLUSHDB"
        else
            if redis-cli -h "$REDIS_HOST_VALUE" -p "$REDIS_PORT_VALUE" -n "$REDIS_CACHE_DB_VALUE" FLUSHDB > /dev/null 2>&1; then
                log_success "Redis DB ${REDIS_CACHE_DB_VALUE} flushed"
            else
                log_error "Redis FLUSHDB failed (host=${REDIS_HOST_VALUE} port=${REDIS_PORT_VALUE} db=${REDIS_CACHE_DB_VALUE})"
                exit 1
            fi
        fi
    else
        log_warning "Redis pre-flight SKIPPED (no --confirm-flush)"
        log_warning "  Use --confirm-flush when deploying entity-removal migrations"
        log_warning "  (e.g. ADR-019 cluster drop). See DEPLOY_README.md § 'Redis pre-flight'."
    fi

    log_info "Running database migrations..."
    run_shell "cd '$BACKEND_DIR' && symfony console doctrine:migrations:migrate --no-interaction --allow-no-migration"

    # B5 — explicit pool clears to avoid stale entity metadata in Redis DB 1.
    # Generic cache:clear does not always purge Doctrine metadata pool nor
    # system pool — required when entity classes have changed (e.g. PR #21
    # dropped 15 entities + 13 cols).
    log_info "Clearing Doctrine + system cache pools..."
    run_shell "cd '$BACKEND_DIR' && symfony console cache:pool:clear cache.app --env=prod --no-debug"
    run_shell "cd '$BACKEND_DIR' && symfony console cache:pool:clear cache.system_clearer --env=prod --no-debug"

    log_info "Clearing production cache..."
    run_shell "cd '$BACKEND_DIR' && symfony console cache:clear --env=prod --no-debug"

    log_info "Warming up cache..."
    run_shell "cd '$BACKEND_DIR' && symfony console cache:warmup --env=prod --no-debug"

    # B3 — Reload supervisor configs so messenger-async.conf changes land in
    # the running daemon. Without this, edits to config/supervisor/*.conf are
    # ignored until supervisord is manually restarted.
    if command -v supervisorctl > /dev/null 2>&1; then
        log_info "Reloading supervisor configs..."
        if [ "$DRY_RUN" = true ]; then
            log_dry "sudo supervisorctl reread && sudo supervisorctl update"
        else
            if sudo supervisorctl reread > /tmp/supervisor-reread.log 2>&1; then
                log_success "supervisorctl reread OK"
            else
                log_error "supervisorctl reread failed (see /tmp/supervisor-reread.log)"
                exit 1
            fi
            if sudo supervisorctl update > /tmp/supervisor-update.log 2>&1; then
                log_success "supervisorctl update OK"
            else
                log_error "supervisorctl update failed (see /tmp/supervisor-update.log)"
                exit 1
            fi
        fi
    else
        log_warning "supervisorctl not found — skipping supervisor reload"
    fi

    log_info "Reloading PHP-FPM..."
    run_cmd sudo systemctl reload "$PHP_FPM_SERVICE"

    log_success "Backend deployment complete"
else
    log_info "[4/6] Backend deployment SKIPPED (--frontend-only)"
fi

echo ""

# =============================================================================
# STEP 5: Frontend Deploy (Next.js)
# =============================================================================

if [ "$BACKEND_ONLY" = false ]; then
    log_step "[5/6] Frontend deployment (Next.js)"

    log_info "Installing Node dependencies..."
    run_shell "cd '$FRONTEND_DIR' && pnpm install --frozen-lockfile"

    log_info "Building production bundle..."
    run_shell "cd '$FRONTEND_DIR' && pnpm build"

    log_info "Reloading PM2 processes..."
    run_shell "cd '$FRONTEND_DIR' && pm2 reload ecosystem.config.js --env production"

    log_success "Frontend deployment complete"
else
    log_info "[5/6] Frontend deployment SKIPPED (--backend-only)"
fi

echo ""

# =============================================================================
# STEP 5.5: Cache Invalidation (T60.10)
# =============================================================================
# Tag-based revalidation post-build. Without this, fetch-cache entries from
# previous build can serve stale data for up to 1h after deploy.
# Requires REVALIDATE_SECRET env var or REVALIDATE_SECRET= in frontend/.env.local.

if [ "$BACKEND_ONLY" = false ]; then
    log_step "[5.5/6] Frontend cache invalidation"

    REVALIDATE_SECRET_VALUE="${REVALIDATE_SECRET:-}"
    if [ -z "$REVALIDATE_SECRET_VALUE" ] && [ -f "$FRONTEND_DIR/.env.local" ]; then
        REVALIDATE_SECRET_VALUE=$(grep -E '^REVALIDATE_SECRET=' "$FRONTEND_DIR/.env.local" | cut -d= -f2- | tr -d '"' | tr -d "'")
    fi

    if [ -z "$REVALIDATE_SECRET_VALUE" ]; then
        log_warning "REVALIDATE_SECRET not set — skipping tag-based invalidation"
        log_warning "Stale fetch cache may persist up to 1h post-deploy"
    else
        # Allow PM2 reload to settle before hitting the endpoint
        sleep 2
        REVALIDATE_PAYLOAD='{"tags":["categories","navigation","homepage","articles","tags","video-shows","important-articles","special-articles","authors"]}'
        if [ "$DRY_RUN" = true ]; then
            log_dry "curl POST $FRONTEND_URL/api/revalidate with tags payload"
        else
            HTTP_CODE=$(curl -sf -o /tmp/revalidate-response.json -w "%{http_code}" \
                --max-time 10 \
                -X POST "$FRONTEND_URL/api/revalidate" \
                -H "Content-Type: application/json" \
                -H "x-revalidate-secret: $REVALIDATE_SECRET_VALUE" \
                -d "$REVALIDATE_PAYLOAD" 2>/dev/null || echo "000")
            if [ "$HTTP_CODE" = "200" ]; then
                log_success "Cache invalidated (HTTP $HTTP_CODE)"
            elif [ "$HTTP_CODE" = "207" ]; then
                log_warning "Partial invalidation (HTTP 207) — see /tmp/revalidate-response.json"
            else
                log_error "Cache invalidation failed (HTTP $HTTP_CODE)"
                log_warning "Continuing deploy; manual revalidation may be required"
            fi
        fi
    fi
    echo ""
fi

# =============================================================================
# STEP 6: Post-Deploy Verification
# =============================================================================

log_step "[6/6] Post-deploy verification"

if [ "$DRY_RUN" = true ]; then
    log_dry "curl -sf --max-time $SMOKE_TIMEOUT $BACKEND_URL/api"
    log_dry "curl -sf --max-time $SMOKE_TIMEOUT $FRONTEND_URL/ro"
    log_dry "Trilingual slug probe (T60.10): for LOCALE in ro en ru; curl $FRONTEND_URL/\$LOCALE | grep -q /uncategorized/"
    log_dry "Run full smoke check: $SCRIPTS_DIR/smoke-check.sh"
else
    SMOKE_PASS=0
    SMOKE_FAIL=0

    # Backend smoke check
    if [ "$FRONTEND_ONLY" = false ]; then
        log_info "Checking backend API..."
        # Give PHP-FPM a moment to reload
        sleep 2
        HTTP_CODE=$(curl -sf -o /dev/null -w "%{http_code}" --max-time "$SMOKE_TIMEOUT" "$BACKEND_URL/api" 2>/dev/null || echo "000")
        if [ "$HTTP_CODE" = "200" ]; then
            log_success "Backend API responding (HTTP $HTTP_CODE)"
            ((SMOKE_PASS++))
        else
            log_error "Backend API NOT responding (HTTP $HTTP_CODE)"
            ((SMOKE_FAIL++))
        fi

        # Test articles endpoint
        HTTP_CODE=$(curl -sf -o /dev/null -w "%{http_code}" --max-time "$SMOKE_TIMEOUT" "$BACKEND_URL/api/articles" 2>/dev/null || echo "000")
        if [ "$HTTP_CODE" = "200" ]; then
            log_success "Articles API responding (HTTP $HTTP_CODE)"
            ((SMOKE_PASS++))
        else
            log_warning "Articles API returned HTTP $HTTP_CODE"
            ((SMOKE_FAIL++))
        fi
    fi

    # Frontend smoke check
    if [ "$BACKEND_ONLY" = false ]; then
        log_info "Checking frontend..."
        # Give PM2/Next.js a moment to start
        sleep 3
        HTTP_CODE=$(curl -sf -o /dev/null -w "%{http_code}" --max-time "$SMOKE_TIMEOUT" -L "$FRONTEND_URL" 2>/dev/null || echo "000")
        if [ "$HTTP_CODE" = "200" ]; then
            log_success "Frontend responding (HTTP $HTTP_CODE)"
            ((SMOKE_PASS++))
        else
            log_error "Frontend NOT responding (HTTP $HTTP_CODE)"
            ((SMOKE_FAIL++))
        fi

        # Check Romanian homepage
        HTTP_CODE=$(curl -sf -o /dev/null -w "%{http_code}" --max-time "$SMOKE_TIMEOUT" -L "$FRONTEND_URL/ro" 2>/dev/null || echo "000")
        if [ "$HTTP_CODE" = "200" ]; then
            log_success "Romanian homepage responding (HTTP $HTTP_CODE)"
            ((SMOKE_PASS++))
        else
            log_warning "Romanian homepage returned HTTP $HTTP_CODE"
            ((SMOKE_FAIL++))
        fi

        # T60.10: Verify trilingual category slugs are not stale
        # Detects post-T60.6 regression where cache freezes /uncategorized/
        # fallback slug after backend translates categories.
        for LOCALE in ro en ru; do
            LOC_HTTP=$(curl -sf -o /tmp/smoke-${LOCALE}.html -w "%{http_code}" \
                --max-time "$SMOKE_TIMEOUT" -L "$FRONTEND_URL/$LOCALE" 2>/dev/null || echo "000")
            if [ "$LOC_HTTP" = "200" ]; then
                if grep -q "/uncategorized/" /tmp/smoke-${LOCALE}.html; then
                    log_error "[$LOCALE] /uncategorized/ slug leaked — cache likely stale"
                    ((SMOKE_FAIL++))
                else
                    log_success "[$LOCALE] homepage clean of /uncategorized/"
                    ((SMOKE_PASS++))
                fi
            else
                log_warning "[$LOCALE] homepage HTTP $LOC_HTTP — cannot verify slugs"
                ((SMOKE_FAIL++))
            fi
        done
    fi

    echo ""
    log_info "Smoke check results: ${SMOKE_PASS} passed, ${SMOKE_FAIL} failed"

    if [ "$SMOKE_FAIL" -gt 0 ]; then
        log_error "Some smoke checks failed. Please investigate."
        log_info "Run full smoke check: $SCRIPTS_DIR/smoke-check.sh"
    fi
fi

# =============================================================================
# Deployment Summary
# =============================================================================

DEPLOY_END=$(date +%s)
DEPLOY_DURATION=$((DEPLOY_END - DEPLOY_START))
DEPLOY_MINUTES=$((DEPLOY_DURATION / 60))
DEPLOY_SECONDS=$((DEPLOY_DURATION % 60))

echo ""
echo -e "${BLUE}============================================================================${NC}"
echo -e "${BLUE}   DEPLOYMENT SUMMARY${NC}"
echo -e "${BLUE}============================================================================${NC}"
echo ""
echo -e "  Status:       ${GREEN}${BOLD}COMPLETE${NC}"
echo -e "  Timestamp:    $DEPLOY_TIMESTAMP"
echo -e "  Duration:     ${DEPLOY_MINUTES}m ${DEPLOY_SECONDS}s"
echo -e "  Branch:       $GIT_BRANCH"

if [ "$DRY_RUN" = false ]; then
    NEW_SHA=$(cd "$ROOT_DIR" && git rev-parse --short HEAD)
    echo -e "  Commit:       $NEW_SHA"
fi

echo -e "  Dry Run:      $DRY_RUN"

if [ -n "$BACKUP_FILE" ]; then
    echo -e "  Backup:       $BACKUP_FILE"
fi

echo ""
echo -e "${BLUE}  Backend:${NC}      $BACKEND_URL"
echo -e "${BLUE}  Frontend:${NC}     $FRONTEND_URL"
echo ""
echo -e "${BLUE}============================================================================${NC}"
echo ""

# Append to deploy log
if [ "$DRY_RUN" = false ]; then
    echo "[$DEPLOY_TIMESTAMP] Deployed branch=$GIT_BRANCH commit=$(cd "$ROOT_DIR" && git rev-parse --short HEAD) duration=${DEPLOY_MINUTES}m${DEPLOY_SECONDS}s" >> "$LOG_FILE" 2>/dev/null || true
fi

exit 0
