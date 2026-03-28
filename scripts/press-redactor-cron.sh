#!/bin/bash
# Press Email Fetcher — Cron runner
# Symfony command: zero AI cost, ~3 seconds per run
# Cron: */10 * * * * /var/www/deschide_news_app/scripts/press-redactor-cron.sh

set -euo pipefail

PROJECT_DIR="/var/www/deschide_news_app/apps/backend"
LOG_FILE="/var/www/deschide_news_app/var/log/press-fetcher.log"
LOCK_FILE="/tmp/press-fetcher.lock"

mkdir -p "$(dirname "$LOG_FILE")"

# Prevent overlapping runs
if [ -f "$LOCK_FILE" ]; then
  PID=$(cat "$LOCK_FILE" 2>/dev/null)
  if kill -0 "$PID" 2>/dev/null; then
    echo "[$(date -Iseconds)] SKIP — previous run still active (PID $PID)" >> "$LOG_FILE"
    exit 0
  fi
  rm -f "$LOCK_FILE"
fi

echo $$ > "$LOCK_FILE"
trap 'rm -f "$LOCK_FILE"' EXIT

echo "[$(date -Iseconds)] START press-fetcher" >> "$LOG_FILE"

cd "$PROJECT_DIR"
symfony console app:fetch-press-emails --limit=20 --no-interaction >> "$LOG_FILE" 2>&1

EXIT_CODE=$?
echo "[$(date -Iseconds)] END (exit: $EXIT_CODE)" >> "$LOG_FILE"
echo "---" >> "$LOG_FILE"
