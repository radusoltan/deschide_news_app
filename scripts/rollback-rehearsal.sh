#!/usr/bin/env bash
# T57.05 — Rollback rehearsal (Flavor A: dev-safe simulation).
#
# Simulates each step of the T57.02b revert procedure documented in
# 20_Architecture/Operations/Local_Staging_Runbook.md §"Revert procedure"
# using temp/dry-run equivalents. Times each step, verifies required
# backups exist, and produces a timings CSV plus console report.
#
# Guarantees:
#   - NO real staging resources are mutated.
#   - NO dev resources are mutated.
#   - Temp DB (deschide_rehearsal_<ts>) is created then dropped during run.
#   - Supervisor / Mercure / Nginx commands run as read-only status/lint
#     queries whose wall-time approximates the real mutation's latency.
#   - ES is probed via HEAD against a guaranteed-nonexistent prefix
#     (rehearsal_nonexistent_*). No DELETE is ever issued.
#
# Usage:
#   ./scripts/rollback-rehearsal.sh
#
# Exit codes:
#   0 — rehearsal complete (budget PASS or FAIL is reported, not errored)
#   1 — setup/cleanup failure before or after rehearsal

set -euo pipefail

# --- Resolve repo root (script lives in <repo>/scripts/) ---
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$REPO_ROOT"

# --- Identifiers and paths ---
TS="$(date +%Y%m%d-%H%M%S)"
REHEARSAL_ID="rehearsal-$TS"
ARTIFACT_DIR="/tmp/rollback-$REHEARSAL_ID"
TIMINGS_CSV="$ARTIFACT_DIR/timings.csv"
WARNINGS_LOG="$ARTIFACT_DIR/warnings.log"

TEMP_DB="deschide_rehearsal_$(date +%s)"
TEMP_SUPERVISOR_CONF="/tmp/supervisor-rehearsal-$TS.conf"
TEMP_NGINX_CONF="/tmp/nginx-rehearsal-$TS.conf"

mkdir -p "$ARTIFACT_DIR"
: > "$WARNINGS_LOG"
echo "step,description,ms" > "$TIMINGS_CSV"

# --- Helpers ---
step_start() {
    STEP_NUM="$1"
    STEP_DESC="$2"
    STEP_START_NS="$(date +%s%N)"
    printf '[STEP %2s] START — %s\n' "$STEP_NUM" "$STEP_DESC"
}

step_end() {
    local now
    now="$(date +%s%N)"
    local elapsed_ms=$(( (now - STEP_START_NS) / 1000000 ))
    printf '[STEP %2s] END   — %6d ms\n' "$STEP_NUM" "$elapsed_ms"
    echo "$STEP_NUM,\"$STEP_DESC\",$elapsed_ms" >> "$TIMINGS_CSV"
}

warn() {
    echo "  WARNING: $*"
    echo "[STEP $STEP_NUM] $*" >> "$WARNINGS_LOG"
}

# --- Extract creds from .env.local (dev backend) ---
ENV_FILE="$REPO_ROOT/apps/backend/.env.local"
if [ ! -f "$ENV_FILE" ]; then
    echo "FATAL: $ENV_FILE not found — cannot resolve DB/ES credentials" >&2
    exit 1
fi

DB_PASS="$(grep -E '^DATABASE_URL=' "$ENV_FILE" | head -1 | sed -E 's|^DATABASE_URL="?postgresql://[^:]+:([^@]+)@.*|\1|')"
ES_USER="$(grep -E '^ELASTICSEARCH_USER=' "$ENV_FILE" | head -1 | cut -d= -f2- | tr -d '"')"
ES_PASS="$(grep -E '^ELASTICSEARCH_PASSWORD=' "$ENV_FILE" | head -1 | cut -d= -f2- | tr -d '"')"

if [ -z "$DB_PASS" ] || [ -z "$ES_USER" ] || [ -z "$ES_PASS" ]; then
    echo "FATAL: could not parse credentials from $ENV_FILE" >&2
    exit 1
fi

# --- SETUP ---
echo "=== SETUP ($REHEARSAL_ID) ==="

# Create temp DB via direct Postgres (bypasses pgbouncer by design)
PGPASSWORD="$DB_PASS" psql -h 127.0.0.1 -p 5432 -U deschide_user -d postgres \
    -c "CREATE DATABASE $TEMP_DB;" >/dev/null
echo "  temp DB created: $TEMP_DB"

cat > "$TEMP_SUPERVISOR_CONF" <<EOF
[program:rehearsal-placeholder-$TS]
command=/bin/true
autostart=false
EOF
echo "  temp supervisor conf: $TEMP_SUPERVISOR_CONF"

cat > "$TEMP_NGINX_CONF" <<EOF
server { listen 127.0.0.1:65535; server_name rehearsal-$TS.invalid; return 404; }
EOF
echo "  temp nginx conf: $TEMP_NGINX_CONF"

echo ""

# Ensure cleanup even on early exit
cleanup() {
    local rc=$?
    # Drop temp DB if still present
    PGPASSWORD="$DB_PASS" psql -h 127.0.0.1 -p 5432 -U deschide_user -d postgres \
        -c "DROP DATABASE IF EXISTS $TEMP_DB;" >/dev/null 2>&1 || true
    rm -f "$TEMP_SUPERVISOR_CONF" "$TEMP_NGINX_CONF" 2>/dev/null || true
    exit "$rc"
}
trap cleanup EXIT

# --- REHEARSAL ---
echo "=== REHEARSAL ==="

# STEP 1 — Stop all staging programs
# Real: sudo supervisorctl stop staging:   (blocks until children down, ~5s sleep)
# Dry:  status query + count, similar IPC round-trip
step_start 1 "Stop all staging programs"
STAGING_COUNT="$(sudo supervisorctl status 2>/dev/null | grep -cE '^staging:' || true)"
if [ "$STAGING_COUNT" -eq 0 ]; then
    warn "no staging:* programs registered with supervisor (runbook step 1 would be no-op)"
fi
step_end 1 "Stop all staging programs"

# STEP 2 — Remove supervisor configs
# Real: sudo rm /etc/supervisor/conf.d/*-staging.conf + staging-group.conf ; reread ; update
# Dry:  verify files would be found, count them, rm the temp placeholder, then sudo reread
step_start 2 "Remove supervisor configs"
SV_CONFS="$(sudo find /etc/supervisor/conf.d/ -maxdepth 1 -type f \
    \( -name '*-staging.conf' -o -name 'staging-group.conf' \) 2>/dev/null | wc -l)"
if [ "$SV_CONFS" -eq 0 ]; then
    warn "no *-staging.conf or staging-group.conf under /etc/supervisor/conf.d/ — runbook step 2 would be no-op"
fi
rm -f "$TEMP_SUPERVISOR_CONF"
sudo supervisorctl reread >/dev/null 2>&1 || true
step_end 2 "Remove supervisor configs"

# STEP 3 — Drop staging DB
# Real: sudo -u postgres psql -c "DROP DATABASE deschide_staging;"
# Dry:  drop our temp DB instead (same SQL path)
step_start 3 "Drop staging DB"
PGPASSWORD="$DB_PASS" psql -h 127.0.0.1 -p 5432 -U deschide_user -d postgres \
    -c "DROP DATABASE $TEMP_DB;" >/dev/null
step_end 3 "Drop staging DB"

# STEP 4 — Revert pgbouncer
# Real: cp .pre-t57-02b.bak back + systemctl reload pgbouncer
# Dry:  confirm backups exist; copy them into ARTIFACT_DIR (not /etc/); skip reload
step_start 4 "Revert pgbouncer"
PGB_USERLIST_BAK="/etc/pgbouncer/userlist.txt.pre-t57-02b.bak"
PGB_INI_BAK="/etc/pgbouncer/pgbouncer.ini.pre-t57-02b.bak"
if sudo test -f "$PGB_USERLIST_BAK" && sudo test -f "$PGB_INI_BAK"; then
    sudo cp "$PGB_USERLIST_BAK" "$ARTIFACT_DIR/userlist-rehearsal.txt"
    sudo cp "$PGB_INI_BAK"      "$ARTIFACT_DIR/pgbouncer-rehearsal.ini"
    # Skip systemctl reload — would impact dev + prod pgbouncer users
else
    warn "pgbouncer pre-t57-02b backups missing — REAL REVERT WOULD FAIL"
fi
step_end 4 "Revert pgbouncer"

# STEP 5 — Delete staging ES indices
# Real: curl -X DELETE .../staging_deschide_*
# Dry:  HEAD against rehearsal_nonexistent_* — probes auth + connectivity, no delete
step_start 5 "Delete staging ES indices"
ES_PROBE_HTTP="$(curl -sk -u "$ES_USER:$ES_PASS" -o /dev/null -w '%{http_code}' \
    -I "https://localhost:9200/rehearsal_nonexistent_${TS}_x" 2>/dev/null || echo "000")"
if [ "$ES_PROBE_HTTP" != "404" ] && [ "$ES_PROBE_HTTP" != "200" ]; then
    warn "ES probe returned HTTP $ES_PROBE_HTTP (expected 404/200) — auth or connectivity concern"
fi
step_end 5 "Delete staging ES indices"

# STEP 6 — Revert Mercure Caddyfile
# Real: cp .pre-t57-02b.bak back + supervisorctl restart mercure (3s)
# Dry:  confirm backup; copy to ARTIFACT; status-query mercure (no restart)
step_start 6 "Revert Mercure Caddyfile"
MERCURE_BAK="/etc/mercure/Caddyfile.pre-t57-02b.bak"
if sudo test -f "$MERCURE_BAK"; then
    sudo cp "$MERCURE_BAK" "$ARTIFACT_DIR/Caddyfile-rehearsal"
    sudo supervisorctl status mercure >/dev/null 2>&1 || true
else
    warn "Mercure Caddyfile pre-t57-02b backup missing — REAL REVERT WOULD FAIL"
fi
step_end 6 "Revert Mercure Caddyfile"

# STEP 7 — Remove Nginx vhosts
# Real: rm sites-enabled/* sites-available/* + nginx -t + nginx -s reload
# Dry:  rm temp conf; nginx -t on live config (validates syntax, no reload)
step_start 7 "Remove Nginx vhosts"
STAGING_VHOSTS="$(sudo find /etc/nginx/sites-enabled /etc/nginx/sites-available \
    -maxdepth 1 \( -name 'staging.news-app.local' -o -name 'api.staging.news-app.local' \) 2>/dev/null | wc -l)"
if [ "$STAGING_VHOSTS" -eq 0 ]; then
    warn "no staging nginx vhosts found — runbook step 7 would be no-op"
fi
rm -f "$TEMP_NGINX_CONF"
sudo nginx -t >/dev/null 2>&1
step_end 7 "Remove Nginx vhosts"

# STEP 8 — Remove JWT staging keypair
# Real: rm -rf apps/backend/config/jwt/staging/
# Dry:  stat the dir
step_start 8 "Remove JWT staging keypair"
JWT_STAGING_DIR="$REPO_ROOT/apps/backend/config/jwt/staging"
if [ -d "$JWT_STAGING_DIR" ]; then
    ls -la "$JWT_STAGING_DIR" >/dev/null
else
    warn "JWT staging dir absent — runbook step 8 would be no-op (or provisioning incomplete)"
fi
step_end 8 "Remove JWT staging keypair"

# STEP 9 — Remove .env.staging.local files
# Real: rm apps/backend/.env.staging.local apps/frontend/.env.staging.local
# Dry:  stat both
step_start 9 "Remove .env.staging.local files"
ENV_BACKEND="$REPO_ROOT/apps/backend/.env.staging.local"
ENV_FRONTEND="$REPO_ROOT/apps/frontend/.env.staging.local"
MISSING=0
[ -f "$ENV_BACKEND" ]  || { warn "missing $ENV_BACKEND";  MISSING=$((MISSING+1)); }
[ -f "$ENV_FRONTEND" ] || { warn "missing $ENV_FRONTEND"; MISSING=$((MISSING+1)); }
if [ "$MISSING" -eq 0 ]; then
    stat "$ENV_BACKEND" "$ENV_FRONTEND" >/dev/null
fi
step_end 9 "Remove .env.staging.local files"

# STEP 10 — git revert (documented, not simulated as a mutation)
# Real: git revert 4c5165a  (T57.02b feature commit)
# Dry:  verify the referenced commits exist in history, time the lookup
step_start 10 "Git revert T57.02b commits"
COMMIT_T57_02B="$(git rev-list --all --grep='T57.02b' --max-count=1 || true)"
COMMIT_MONOLOG="$(git rev-list --all --grep='monolog' --max-count=1 || true)"
if [ -z "$COMMIT_T57_02B" ]; then
    warn "no commit matching 'T57.02b' found in git history — runbook step 10 reference invalid"
fi
# The monolog hotfix is optional per runbook; track presence but don't warn if missing
step_end 10 "Git revert T57.02b commits"

# --- FINAL REPORT ---
echo ""
echo "=== TIMINGS ==="
column -t -s, "$TIMINGS_CSV"

TOTAL_MS="$(awk -F, 'NR>1 { sum += $3 } END { print sum+0 }' "$TIMINGS_CSV")"
TOTAL_SEC=$(( TOTAL_MS / 1000 ))
TOTAL_REM_MS=$(( TOTAL_MS % 1000 ))

echo ""
printf 'Total rehearsal time: %d.%03d s (%d ms)\n' "$TOTAL_SEC" "$TOTAL_REM_MS" "$TOTAL_MS"

# Budget: <120000 ms = <120s
if [ "$TOTAL_MS" -lt 120000 ]; then
    echo "BUDGET: PASS (< 120s)"
else
    echo "BUDGET: FAIL (>= 120s — investigate slow steps)"
fi

# Warning summary
WARN_COUNT="$(wc -l < "$WARNINGS_LOG" | tr -d ' ')"
echo ""
if [ "$WARN_COUNT" -eq 0 ]; then
    echo "WARNINGS: none — all prerequisites present"
else
    echo "WARNINGS: $WARN_COUNT"
    sed 's/^/  /' "$WARNINGS_LOG"
fi

echo ""
echo "Artifact dir: $ARTIFACT_DIR"
echo "  - timings.csv"
echo "  - warnings.log"

# Drop copied backups now that we've confirmed they exist + readable
rm -f "$ARTIFACT_DIR/userlist-rehearsal.txt" \
      "$ARTIFACT_DIR/pgbouncer-rehearsal.ini" \
      "$ARTIFACT_DIR/Caddyfile-rehearsal"
