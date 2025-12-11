#!/bin/bash
# scripts/run-load-tests.sh
# k6 Load Test Runner for Deschide News App
#
# This script provides an interactive menu for running various load tests
# against the Deschide News backend and frontend applications.
#
# Usage:
#   ./scripts/run-load-tests.sh              # Interactive menu
#   ./scripts/run-load-tests.sh load         # Run load test
#   ./scripts/run-load-tests.sh stress       # Run stress test
#   ./scripts/run-load-tests.sh soak         # Run soak test
#   ./scripts/run-load-tests.sh spike        # Run spike test
#   ./scripts/run-load-tests.sh api          # Run API-only test
#   ./scripts/run-load-tests.sh quick        # Run quick validation test

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

# Configuration
BACKEND_URL="${BACKEND_URL:-http://127.0.0.1:8081}"
FRONTEND_URL="${FRONTEND_URL:-http://localhost:3005}"
PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
K6_DIR="$PROJECT_ROOT/k6"
RESULTS_DIR="$K6_DIR/results"
SCRIPTS_DIR="$PROJECT_ROOT/scripts"

# Create results directory if it doesn't exist
mkdir -p "$RESULTS_DIR"

# ==============================================================================
# Helper Functions
# ==============================================================================

# Print colored output
print_header() {
    echo ""
    echo -e "${BLUE}╔════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${BLUE}║${NC}           Deschide News App - Load Test Runner                 ${BLUE}║${NC}"
    echo -e "${BLUE}╚════════════════════════════════════════════════════════════════╝${NC}"
    echo ""
}

print_section() {
    echo -e "${CYAN}■${NC} $1"
}

print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_info() {
    echo -e "${YELLOW}ℹ${NC} $1"
}

# Check if k6 is installed
check_k6() {
    if ! command -v k6 &> /dev/null; then
        print_error "k6 is not installed"
        echo ""
        echo "Install k6 with:"
        echo ""
        echo "  Ubuntu/Debian:"
        echo "  $ sudo gpg -k"
        echo "  $ sudo gpg --no-default-keyring --keyring /usr/share/keyrings/k6-archive-keyring.gpg --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D69"
        echo '  $ echo "deb [signed-by=/usr/share/keyrings/k6-archive-keyring.gpg] https://dl.k6.io/deb stable main" | sudo tee /etc/apt/sources.list.d/k6.list'
        echo "  $ sudo apt-get update && sudo apt-get install k6"
        echo ""
        echo "  macOS (Homebrew):"
        echo "  $ brew install k6"
        echo ""
        echo "  Or with snap:"
        echo "  $ sudo snap install k6"
        exit 1
    fi
    print_success "k6 is installed: $(k6 version)"
}

# Check if backend is running
check_backend() {
    if ! curl -s "$BACKEND_URL/api" > /dev/null 2>&1; then
        print_error "Backend is not accessible at $BACKEND_URL"
        echo ""
        echo "Start the backend with:"
        echo "  $ cd /var/www/deschide_news_app/apps/backend"
        echo "  $ symfony serve -d --port=8081"
        return 1
    fi
    print_success "Backend is running at $BACKEND_URL"
    return 0
}

# Check if frontend is running
check_frontend() {
    if ! curl -s "$FRONTEND_URL" > /dev/null 2>&1; then
        print_error "Frontend is not accessible at $FRONTEND_URL"
        echo ""
        echo "Start the frontend with:"
        echo "  $ cd /var/www/deschide_news_app/apps/frontend"
        echo "  $ pnpm dev"
        return 1
    fi
    print_success "Frontend is running at $FRONTEND_URL"
    return 0
}

# Check if test files exist
check_test_files() {
    local test_file="$1"
    if [ ! -f "$test_file" ]; then
        print_error "Test file not found: $test_file"
        echo ""
        echo "Available test files in $K6_DIR:"
        ls -la "$K6_DIR" 2>/dev/null | grep -E "\.js$" | awk '{print "  - " $NF}'
        return 1
    fi
}

# Show main menu
show_menu() {
    print_header
    echo "Configuration:"
    echo "  Backend URL:  ${CYAN}$BACKEND_URL${NC}"
    echo "  Frontend URL: ${CYAN}$FRONTEND_URL${NC}"
    echo ""
    echo "Available Tests:"
    echo ""
    echo "  ${YELLOW}1${NC})  Load Test       - Gradual ramp-up to standard load"
    echo "      Users: 50-100 | Duration: ~10 minutes"
    echo ""
    echo "  ${YELLOW}2${NC})  Stress Test     - Push system to find breaking point"
    echo "      Users: up to 500 | Duration: ~17 minutes"
    echo ""
    echo "  ${YELLOW}3${NC})  Soak Test       - Endurance test for memory leaks"
    echo "      Users: 50 | Duration: ~65 minutes"
    echo ""
    echo "  ${YELLOW}4${NC})  Spike Test      - Sudden traffic burst scenario"
    echo "      Users: up to 300 | Duration: ~5 minutes"
    echo ""
    echo "  ${YELLOW}5${NC})  API Only        - Test API endpoints in isolation"
    echo "      Users: 50-100 | Duration: ~5 minutes"
    echo ""
    echo "  ${YELLOW}6${NC})  Quick Test      - Fast validation test"
    echo "      Users: 10 | Duration: ~2 minutes"
    echo ""
    echo "  ${YELLOW}0${NC})  Exit"
    echo ""
}

# Run a load test
run_test() {
    local test_file="$1"
    local test_name="$2"
    local description="$3"
    local timestamp=$(date +%Y%m%d_%H%M%S)
    local result_file="$RESULTS_DIR/${test_name}_${timestamp}.json"
    local log_file="$RESULTS_DIR/${test_name}_${timestamp}.log"

    echo ""
    print_section "$description"
    echo "Test file:  $test_file"
    echo "Results:    $result_file"
    echo "Log file:   $log_file"
    echo ""

    # Run the k6 test
    if BACKEND_URL="$BACKEND_URL" FRONTEND_URL="$FRONTEND_URL" \
        k6 run "$test_file" --out json="$result_file" 2>&1 | tee "$log_file"; then

        echo ""
        print_success "Test completed successfully!"
        echo ""
        print_section "Test Results Summary"

        # Extract key metrics from the log
        grep -E "(http_reqs|http_req_duration|http_req_failed|vus_max)" "$log_file" | tail -10 || true

        echo ""
        print_info "Full results available at:"
        echo "  JSON: $result_file"
        echo "  LOG:  $log_file"
        echo ""
    else
        print_error "Test failed! Check the log file for details:"
        echo "  $log_file"
        return 1
    fi
}

# Run quick validation test
run_quick_test() {
    local timestamp=$(date +%Y%m%d_%H%M%S)
    local log_file="$RESULTS_DIR/quick_validation_${timestamp}.log"

    echo ""
    print_section "Quick Validation Test"
    echo "Running with 10 VUs for 2 minutes..."
    echo "Log file: $log_file"
    echo ""

    # Create a minimal inline k6 script if no test files exist
    if [ ! -f "$K6_DIR/load-test.js" ]; then
        print_info "Creating inline test (no k6 test files found in $K6_DIR)"
        k6 run --vus 10 --duration 2m \
            -e BACKEND_URL="$BACKEND_URL" \
            -e FRONTEND_URL="$FRONTEND_URL" \
            --summary-export="$RESULTS_DIR/quick_validation_${timestamp}.json" \
            - 2>&1 | tee "$log_file" << 'EOF'
import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  vus: 10,
  duration: '2m',
};

export default function() {
  let backend = __ENV.BACKEND_URL || 'http://127.0.0.1:8081';

  let res = http.get(backend + '/api/articles?itemsPerPage=10');
  check(res, {
    'status is 200': (r) => r.status === 200,
    'response time < 1000ms': (r) => r.timings.duration < 1000,
  });
  sleep(1);
}
EOF
    else
        BACKEND_URL="$BACKEND_URL" FRONTEND_URL="$FRONTEND_URL" \
            k6 run "$K6_DIR/load-test.js" --vus 10 --duration 2m 2>&1 | tee "$log_file"
    fi

    echo ""
    print_success "Quick test completed!"
    echo "Results: $log_file"
}

# Display test files status
show_test_status() {
    echo ""
    print_section "Available Test Files"

    if [ ! -d "$K6_DIR" ]; then
        print_error "k6 directory not found at $K6_DIR"
        echo ""
        echo "Create k6 tests with:"
        echo "  $ mkdir -p $K6_DIR/scenarios"
        echo "  $ mkdir -p $K6_DIR/results"
        return 1
    fi

    if [ ! -f "$K6_DIR/load-test.js" ] && \
       [ ! -f "$K6_DIR/stress-test.js" ] && \
       [ ! -f "$K6_DIR/soak-test.js" ] && \
       [ ! -f "$K6_DIR/scenarios/spike-test.js" ] && \
       [ ! -f "$K6_DIR/scenarios/api-endpoints.js" ]; then

        print_error "No test files found in $K6_DIR"
        echo ""
        echo "Please create k6 test files in:"
        echo "  - $K6_DIR/load-test.js"
        echo "  - $K6_DIR/stress-test.js"
        echo "  - $K6_DIR/soak-test.js"
        echo "  - $K6_DIR/scenarios/spike-test.js"
        echo "  - $K6_DIR/scenarios/api-endpoints.js"
        return 1
    fi

    echo ""
    [ -f "$K6_DIR/load-test.js" ] && print_success "load-test.js" || print_error "load-test.js (missing)"
    [ -f "$K6_DIR/stress-test.js" ] && print_success "stress-test.js" || print_error "stress-test.js (missing)"
    [ -f "$K6_DIR/soak-test.js" ] && print_success "soak-test.js" || print_error "soak-test.js (missing)"
    [ -f "$K6_DIR/scenarios/spike-test.js" ] && print_success "scenarios/spike-test.js" || print_error "scenarios/spike-test.js (missing)"
    [ -f "$K6_DIR/scenarios/api-endpoints.js" ] && print_success "scenarios/api-endpoints.js" || print_error "scenarios/api-endpoints.js (missing)"

    echo ""
}

# Pre-flight checks
preflight_checks() {
    echo ""
    print_section "Running Pre-flight Checks"

    check_k6
    echo ""

    # Check backend (not mandatory, will warn)
    if ! check_backend; then
        echo ""
        print_error "Backend is not running. Some tests may fail."
        read -p "Continue anyway? (y/n) " -n 1 -r
        echo ""
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 1
        fi
    fi

    echo ""
    show_test_status
}

# ==============================================================================
# Main Script Logic
# ==============================================================================

# Handle command-line arguments
if [ "$1" != "" ] && [ "$1" != "menu" ]; then
    # Handle help first, before checks
    if [ "$1" = "--help" ] || [ "$1" = "-h" ] || [ "$1" = "help" ]; then
        cat << 'HELP'
Load Test Runner for Deschide News App

USAGE:
  ./scripts/run-load-tests.sh                # Interactive menu
  ./scripts/run-load-tests.sh [test-type]   # Run specific test

TEST TYPES:
  load                                       # Standard load test (10 min)
  stress                                     # Stress test - find breaking point (17 min)
  soak                                       # Soak test - endurance (65 min)
  spike                                      # Spike test - sudden burst (5 min)
  api                                        # API-only test (5 min)
  quick                                      # Quick validation (2 min)

ENVIRONMENT VARIABLES:
  BACKEND_URL                                # Backend URL (default: http://127.0.0.1:8081)
  FRONTEND_URL                               # Frontend URL (default: http://localhost:3005)

EXAMPLES:
  ./scripts/run-load-tests.sh                # Show menu
  ./scripts/run-load-tests.sh load           # Run load test
  BACKEND_URL=http://api.example.com \
    ./scripts/run-load-tests.sh stress       # Run stress test with custom URL

RESULTS:
  All test results are saved to: k6/results/
  - JSON files for detailed analysis
  - Log files for quick reference

HELP
        exit 0
    fi

    check_k6
    show_test_status
    echo ""

    case $1 in
        load)
            run_test "$K6_DIR/load-test.js" "load-test" "Running Load Test"
            ;;
        stress)
            run_test "$K6_DIR/stress-test.js" "stress-test" "Running Stress Test"
            ;;
        soak)
            run_test "$K6_DIR/soak-test.js" "soak-test" "Running Soak Test"
            ;;
        spike)
            run_test "$K6_DIR/scenarios/spike-test.js" "spike-test" "Running Spike Test"
            ;;
        api)
            run_test "$K6_DIR/scenarios/api-endpoints.js" "api-only" "Running API-Only Test"
            ;;
        quick)
            run_quick_test
            ;;
        *)
            print_error "Unknown test type: $1"
            echo ""
            echo "Available tests: load, stress, soak, spike, api, quick"
            echo ""
            echo "Run with --help for more information"
            exit 1
            ;;
    esac
    exit 0
fi

# Interactive menu mode
preflight_checks

while true; do
    show_menu
    read -p "Enter your choice [0-6]: " choice
    echo ""

    case $choice in
        1)
            if [ -f "$K6_DIR/load-test.js" ]; then
                run_test "$K6_DIR/load-test.js" "load-test" "Running Load Test"
            else
                print_error "load-test.js not found"
            fi
            ;;
        2)
            if [ -f "$K6_DIR/stress-test.js" ]; then
                run_test "$K6_DIR/stress-test.js" "stress-test" "Running Stress Test"
            else
                print_error "stress-test.js not found"
            fi
            ;;
        3)
            if [ -f "$K6_DIR/soak-test.js" ]; then
                print_info "This test will run for approximately 65 minutes."
                read -p "Continue? (y/n) " -n 1 -r
                echo ""
                if [[ $REPLY =~ ^[Yy]$ ]]; then
                    run_test "$K6_DIR/soak-test.js" "soak-test" "Running Soak Test"
                fi
            else
                print_error "soak-test.js not found"
            fi
            ;;
        4)
            if [ -f "$K6_DIR/scenarios/spike-test.js" ]; then
                run_test "$K6_DIR/scenarios/spike-test.js" "spike-test" "Running Spike Test"
            else
                print_error "scenarios/spike-test.js not found"
            fi
            ;;
        5)
            if [ -f "$K6_DIR/scenarios/api-endpoints.js" ]; then
                run_test "$K6_DIR/scenarios/api-endpoints.js" "api-only" "Running API-Only Test"
            else
                print_error "scenarios/api-endpoints.js not found"
            fi
            ;;
        6)
            run_quick_test
            ;;
        0)
            echo -e "${GREEN}Goodbye!${NC}"
            exit 0
            ;;
        *)
            print_error "Invalid option. Please select 0-6."
            ;;
    esac

    echo ""
    read -p "Press Enter to continue..."
done
