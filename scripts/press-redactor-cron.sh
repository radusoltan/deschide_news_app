#!/bin/bash
# Press Redactor — Cron runner
# Rulează agentul email-press-redactor autonom, fără confirmare
# Cron: */10 * * * * /var/www/deschide_news_app/scripts/press-redactor-cron.sh

set -euo pipefail

PROJECT_DIR="/var/www/deschide_news_app"
LOG_DIR="$PROJECT_DIR/var/log"
LOG_FILE="$LOG_DIR/press-redactor.log"
LOCK_FILE="/tmp/press-redactor.lock"
MAX_BUDGET="0.50"  # max $0.50 per run

# Ensure log dir exists
mkdir -p "$LOG_DIR"

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

echo "[$(date -Iseconds)] START press-redactor run" >> "$LOG_FILE"

cd "$PROJECT_DIR"

# Run agent in non-interactive print mode with explicit tool allowlist
claude -p \
  --agent email-press-redactor \
  --allowedTools "mcp__zoho-mail__list_emails,mcp__zoho-mail__get_email_content,mcp__zoho-mail__search_emails,mcp__zoho-mail__mark_as_read,mcp__zoho-mail__move_to_folder,mcp__zoho-mail__list_folders,Bash(curl:*),Read" \
  --max-budget-usd "$MAX_BUDGET" \
  --output-format json \
  "Procesează email-urile NOI (necitite) de presă din inbox-ul Zoho Mail. Surse: IPN (newsfeed@ipn.md), Guvern (presa@gov.md). Doar limba română. Creează articole via API cu status 'new'. Marchează email-urile procesate ca citite. Raportează rezultatele." \
  >> "$LOG_FILE" 2>&1

EXIT_CODE=$?
echo "[$(date -Iseconds)] END press-redactor run (exit: $EXIT_CODE)" >> "$LOG_FILE"
echo "---" >> "$LOG_FILE"
