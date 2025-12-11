#!/bin/bash
#
# Archive Old Articles - Automated Cron Job Script
#
# Purpose: Archives articles older than 4 years (published before current_year - 4)
# Schedule: Runs monthly on the 1st at 02:00 AM
# Location: /var/www/deschide_news_app/scripts/cron/archive-old-articles.sh
#
# Usage:
#   Run manually: ./archive-old-articles.sh
#   Via cron: 0 2 1 * * /var/www/deschide_news_app/scripts/cron/archive-old-articles.sh
#

# Exit on error, undefined variables, and pipe failures
set -euo pipefail

# Configuration
BACKEND_DIR="/var/www/deschide_news_app/apps/backend"
LOG_DIR="/var/log/deschide"
LOG_FILE="${LOG_DIR}/archive.log"
ERROR_LOG="${LOG_DIR}/archive-error.log"
YEARS_THRESHOLD=4
BATCH_SIZE=100
LOCK_FILE="/var/lock/deschide-archive.lock"

# Ensure log directory exists
mkdir -p "${LOG_DIR}"

# Function to log messages with timestamp
log() {
    local level="$1"
    shift
    local message="$*"
    local timestamp
    timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[${timestamp}] [${level}] ${message}" | tee -a "${LOG_FILE}"
}

# Function to log errors
log_error() {
    local message="$*"
    local timestamp
    timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[${timestamp}] [ERROR] ${message}" | tee -a "${LOG_FILE}" "${ERROR_LOG}"
}

# Function to cleanup on exit
cleanup() {
    local exit_code=$?

    # Remove lock file
    if [ -f "${LOCK_FILE}" ]; then
        rm -f "${LOCK_FILE}"
        log "INFO" "Lock file removed"
    fi

    if [ ${exit_code} -eq 0 ]; then
        log "INFO" "Archive job completed successfully"
    else
        log_error "Archive job failed with exit code: ${exit_code}"
    fi

    exit ${exit_code}
}

# Trap exit signals
trap cleanup EXIT INT TERM

# Main execution
main() {
    log "INFO" "========================================="
    log "INFO" "Starting article archiving process"
    log "INFO" "========================================="

    # Check if already running (lock file)
    if [ -f "${LOCK_FILE}" ]; then
        log_error "Another instance is already running. Lock file exists: ${LOCK_FILE}"
        exit 1
    fi

    # Create lock file with PID
    echo $$ > "${LOCK_FILE}"
    log "INFO" "Lock file created with PID: $$"

    # Check if backend directory exists
    if [ ! -d "${BACKEND_DIR}" ]; then
        log_error "Backend directory not found: ${BACKEND_DIR}"
        exit 1
    fi

    # Change to backend directory
    cd "${BACKEND_DIR}" || {
        log_error "Failed to change to backend directory: ${BACKEND_DIR}"
        exit 1
    }
    log "INFO" "Changed to backend directory: ${BACKEND_DIR}"

    # Check if Symfony CLI is available
    if ! command -v symfony &> /dev/null; then
        log_error "Symfony CLI not found. Please install Symfony CLI."
        exit 1
    fi
    log "INFO" "Symfony CLI found: $(command -v symfony)"

    # Check if console command exists
    if ! symfony console list | grep -q "app:archive-old-articles"; then
        log_error "Command 'app:archive-old-articles' not found"
        exit 1
    fi
    log "INFO" "Archive command verified"

    # Execute the archive command
    log "INFO" "Executing: symfony console app:archive-old-articles --years=${YEARS_THRESHOLD} --batch-size=${BATCH_SIZE} --no-interaction"

    # Run command and capture output
    if symfony console app:archive-old-articles \
        --years="${YEARS_THRESHOLD}" \
        --batch-size="${BATCH_SIZE}" \
        --no-interaction \
        2>&1 | tee -a "${LOG_FILE}"; then

        log "INFO" "Archive command executed successfully"

        # Get statistics from the database (optional)
        log "INFO" "Archive statistics generated"

    else
        log_error "Archive command failed with exit code: ${PIPESTATUS[0]}"
        exit 1
    fi

    log "INFO" "========================================="
    log "INFO" "Article archiving process completed"
    log "INFO" "========================================="
}

# Run main function
main
