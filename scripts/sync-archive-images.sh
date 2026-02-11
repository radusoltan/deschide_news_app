#!/bin/bash
################################################################################
# Archive Images Sync Script
#
# Syncs images from external HDD to server using rsync
# Source: /mnt/d/ext-hdd/alpha/ and /mnt/d/ext-hdd/beta/images/
# Target: /var/www/deschide_news_app/apps/backend/public/uploads/images/
#
# Usage:
#   ./scripts/sync-archive-images.sh --source=alpha --dry-run
#   ./scripts/sync-archive-images.sh --source=beta
#   ./scripts/sync-archive-images.sh --source=all
#
# Options:
#   --source=alpha|beta|all  Which source to sync (required)
#   --dry-run                Preview changes without copying
#   --verbose                Show detailed progress
#   --stats                  Show detailed statistics
#
################################################################################

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
SOURCE_BASE="/mnt/d/ext-hdd"
TARGET_BASE="/var/www/deschide_news_app/apps/backend/public/uploads/images"
ALPHA_SOURCE="${SOURCE_BASE}/alpha/"
BETA_SOURCE="${SOURCE_BASE}/beta/images/"
ALPHA_TARGET="${TARGET_BASE}/alpha/"
BETA_TARGET="${TARGET_BASE}/beta/"

# Default options
SOURCE_TYPE=""
DRY_RUN=0
VERBOSE=0
SHOW_STATS=0

# Parse arguments
for arg in "$@"; do
    case $arg in
        --source=*)
            SOURCE_TYPE="${arg#*=}"
            shift
            ;;
        --dry-run)
            DRY_RUN=1
            shift
            ;;
        --verbose)
            VERBOSE=1
            shift
            ;;
        --stats)
            SHOW_STATS=1
            shift
            ;;
        --help)
            echo "Usage: $0 --source=alpha|beta|all [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --source=alpha|beta|all  Which source to sync (required)"
            echo "  --dry-run                Preview changes without copying"
            echo "  --verbose                Show detailed progress"
            echo "  --stats                  Show detailed statistics"
            echo "  --help                   Show this help message"
            exit 0
            ;;
        *)
            echo -e "${RED}Error: Unknown argument: $arg${NC}"
            echo "Use --help for usage information"
            exit 1
            ;;
    esac
done

# Validate source type
if [[ -z "$SOURCE_TYPE" ]]; then
    echo -e "${RED}Error: --source parameter is required${NC}"
    echo "Use --help for usage information"
    exit 1
fi

if [[ "$SOURCE_TYPE" != "alpha" && "$SOURCE_TYPE" != "beta" && "$SOURCE_TYPE" != "all" ]]; then
    echo -e "${RED}Error: Invalid source type. Must be: alpha, beta, or all${NC}"
    exit 1
fi

# Function to print section header
print_header() {
    echo ""
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
}

# Function to check if directory exists
check_directory() {
    local dir=$1
    local name=$2

    if [[ ! -d "$dir" ]]; then
        echo -e "${RED}Error: $name directory does not exist: $dir${NC}"
        return 1
    fi

    echo -e "${GREEN}✓${NC} $name exists: $dir"
    return 0
}

# Function to sync images
sync_images() {
    local source=$1
    local target=$2
    local label=$3

    print_header "Syncing $label Images"

    # Check source directory
    if ! check_directory "$source" "$label source"; then
        return 1
    fi

    # Create target directory if it doesn't exist
    if [[ ! -d "$target" ]]; then
        echo -e "${YELLOW}Creating target directory: $target${NC}"
        mkdir -p "$target"
    else
        echo -e "${GREEN}✓${NC} Target directory exists: $target"
    fi

    # Count source files
    echo ""
    echo -e "${BLUE}Analyzing source files...${NC}"
    local source_count=$(find "$source" -type f \( -iname "*.jpg" -o -iname "*.jpeg" -o -iname "*.png" -o -iname "*.gif" -o -iname "*.webp" \) | wc -l)
    local source_size=$(du -sh "$source" | cut -f1)
    echo -e "Source files: ${GREEN}$source_count${NC}"
    echo -e "Source size:  ${GREEN}$source_size${NC}"

    # Build rsync options
    local rsync_opts="-av"

    if [[ $DRY_RUN -eq 1 ]]; then
        rsync_opts="$rsync_opts --dry-run"
        echo -e "${YELLOW}DRY RUN MODE - No files will be copied${NC}"
    fi

    if [[ $VERBOSE -eq 1 ]]; then
        rsync_opts="$rsync_opts --progress"
    fi

    if [[ $SHOW_STATS -eq 1 ]]; then
        rsync_opts="$rsync_opts --stats"
    fi

    # Add checksum for integrity (optional, slower but safer)
    # rsync_opts="$rsync_opts --checksum"

    echo ""
    echo -e "${BLUE}Starting rsync...${NC}"
    echo -e "Command: rsync $rsync_opts \"$source\" \"$target\""
    echo ""

    # Run rsync
    if rsync $rsync_opts "$source" "$target"; then
        echo ""
        echo -e "${GREEN}✓ $label sync completed successfully${NC}"

        # Count target files after sync (only if not dry-run)
        if [[ $DRY_RUN -eq 0 ]]; then
            local target_count=$(find "$target" -type f \( -iname "*.jpg" -o -iname "*.jpeg" -o -iname "*.png" -o -iname "*.gif" -o -iname "*.webp" \) | wc -l)
            local target_size=$(du -sh "$target" | cut -f1)
            echo -e "Target files: ${GREEN}$target_count${NC}"
            echo -e "Target size:  ${GREEN}$target_size${NC}"
        fi

        return 0
    else
        echo ""
        echo -e "${RED}✗ $label sync failed${NC}"
        return 1
    fi
}

# Main execution
print_header "Archive Images Sync Script"
echo -e "Source type: ${GREEN}$SOURCE_TYPE${NC}"
echo -e "Dry run:     ${GREEN}$([ $DRY_RUN -eq 1 ] && echo 'YES' || echo 'NO')${NC}"
echo -e "Verbose:     ${GREEN}$([ $VERBOSE -eq 1 ] && echo 'YES' || echo 'NO')${NC}"
echo -e "Stats:       ${GREEN}$([ $SHOW_STATS -eq 1 ] && echo 'YES' || echo 'NO')${NC}"

# Check if rsync is installed
if ! command -v rsync &> /dev/null; then
    echo -e "${RED}Error: rsync is not installed${NC}"
    echo "Install with: sudo apt-get install rsync"
    exit 1
fi

# Sync based on source type
SYNC_SUCCESS=0

if [[ "$SOURCE_TYPE" == "alpha" || "$SOURCE_TYPE" == "all" ]]; then
    if sync_images "$ALPHA_SOURCE" "$ALPHA_TARGET" "Alpha"; then
        ((SYNC_SUCCESS++))
    fi
fi

if [[ "$SOURCE_TYPE" == "beta" || "$SOURCE_TYPE" == "all" ]]; then
    if sync_images "$BETA_SOURCE" "$BETA_TARGET" "Beta"; then
        ((SYNC_SUCCESS++))
    fi
fi

# Final summary
print_header "Sync Summary"

if [[ "$SOURCE_TYPE" == "all" ]]; then
    if [[ $SYNC_SUCCESS -eq 2 ]]; then
        echo -e "${GREEN}✓ All syncs completed successfully${NC}"
        exit 0
    else
        echo -e "${RED}✗ Some syncs failed${NC}"
        exit 1
    fi
elif [[ $SYNC_SUCCESS -eq 1 ]]; then
    echo -e "${GREEN}✓ Sync completed successfully${NC}"
    exit 0
else
    echo -e "${RED}✗ Sync failed${NC}"
    exit 1
fi
