#!/bin/bash
# scripts/smoke-check.sh
# Quick smoke check script for deployment validation
# Tests infrastructure services, backend API, and frontend pages

# Don't exit on first error - we want to run all tests
set +e

# Configuration
BACKEND_URL="${BACKEND_URL:-http://127.0.0.1:8081}"
FRONTEND_URL="${FRONTEND_URL:-http://localhost:3005}"
TIMEOUT=10

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Counters
PASSED=0
FAILED=0
WARNINGS=0

# Print header
echo -e "${BLUE}╔════════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║         Deschide News App - Smoke Check Tests                 ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "Backend URL:  ${BLUE}${BACKEND_URL}${NC}"
echo -e "Frontend URL: ${BLUE}${FRONTEND_URL}${NC}"
echo -e "Timeout:      ${BLUE}${TIMEOUT}s${NC}"
echo ""

# Function to test HTTP endpoint
test_endpoint() {
    local name="$1"
    local url="$2"
    local expected_status="${3:-200}"
    local headers="${4:-}"
    local follow_redirects="${5:-false}"

    printf "%-50s" "$name"

    local curl_opts="-s -o /dev/null -w %{http_code} --max-time $TIMEOUT"

    if [ "$follow_redirects" = "true" ]; then
        curl_opts="$curl_opts -L"
    fi

    if [ -n "$headers" ]; then
        response=$(curl $curl_opts -H "$headers" "$url" 2>/dev/null || echo "000")
    else
        response=$(curl $curl_opts "$url" 2>/dev/null || echo "000")
    fi

    if [ "$response" = "$expected_status" ]; then
        echo -e "${GREEN}✓ PASS${NC} (${response})"
        ((PASSED++))
        return 0
    elif [ "$response" = "000" ]; then
        echo -e "${RED}✗ FAIL${NC} (Connection failed)"
        ((FAILED++))
        return 1
    else
        echo -e "${RED}✗ FAIL${NC} (Expected: ${expected_status}, Got: ${response})"
        ((FAILED++))
        return 1
    fi
}

# Function to test service
test_service() {
    local name="$1"
    local command="$2"
    local critical="${3:-false}"

    printf "%-50s" "$name"

    if eval "$command" > /dev/null 2>&1; then
        echo -e "${GREEN}✓ PASS${NC}"
        ((PASSED++))
        return 0
    else
        if [ "$critical" = "true" ]; then
            echo -e "${RED}✗ FAIL${NC} (Critical)"
            ((FAILED++))
            return 1
        else
            echo -e "${YELLOW}⚠ WARNING${NC} (Non-critical)"
            ((WARNINGS++))
            return 0
        fi
    fi
}

# Function to test JSON response contains expected data
test_json_endpoint() {
    local name="$1"
    local url="$2"
    local search_pattern="$3"

    printf "%-50s" "$name"

    response=$(curl -s --max-time "$TIMEOUT" "$url" 2>/dev/null || echo "")

    if [ -z "$response" ]; then
        echo -e "${RED}✗ FAIL${NC} (No response)"
        ((FAILED++))
        return 1
    fi

    if echo "$response" | grep -q "$search_pattern"; then
        echo -e "${GREEN}✓ PASS${NC}"
        ((PASSED++))
        return 0
    else
        echo -e "${RED}✗ FAIL${NC} (Pattern not found)"
        ((FAILED++))
        return 1
    fi
}

# ============================================================================
# 1. INFRASTRUCTURE SERVICES
# ============================================================================
echo -e "${BLUE}[1/4] Infrastructure Services${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

test_service "PostgreSQL (pg_isready)" "pg_isready -h localhost -p 5432 -U postgres" "true"
test_service "Redis (PING)" "redis-cli -p 6379 -n 1 PING | grep -q PONG" "true"
test_service "Elasticsearch (cluster health)" "curl -s --max-time 5 -k https://localhost:9200/_cluster/health | grep -q '\"status\"'" "false"

echo ""

# ============================================================================
# 2. BACKEND API ENDPOINTS
# ============================================================================
echo -e "${BLUE}[2/4] Backend API Endpoints${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Core API endpoints (public endpoints)
test_json_endpoint "API Articles (has data)" "${BACKEND_URL}/api/articles" "@context"
test_json_endpoint "API Categories (has data)" "${BACKEND_URL}/api/categories" "@context"
test_json_endpoint "API Authors (has data)" "${BACKEND_URL}/api/authors" "@context"
test_endpoint "API Images (public access)" "${BACKEND_URL}/api/images" 200
test_endpoint "API Thumbnails (public access)" "${BACKEND_URL}/api/thumbnails" 200
test_endpoint "API Thumbnail Profiles" "${BACKEND_URL}/api/thumbnail_profiles" 200

echo ""

# ============================================================================
# 3. BACKEND MULTILINGUAL (Accept-Language headers)
# ============================================================================
echo -e "${BLUE}[3/4] Backend Multilingual Support${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

test_endpoint "Articles (Romanian)" "${BACKEND_URL}/api/articles" 200 "Accept-Language: ro"
test_endpoint "Articles (English)" "${BACKEND_URL}/api/articles" 200 "Accept-Language: en"
test_endpoint "Articles (Russian)" "${BACKEND_URL}/api/articles" 200 "Accept-Language: ru"

echo ""

# ============================================================================
# 4. FRONTEND PAGES
# ============================================================================
echo -e "${BLUE}[4/4] Frontend Pages${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

test_endpoint "Homepage (Romanian)" "${FRONTEND_URL}/ro" 200 "" "true"
test_endpoint "Homepage (English)" "${FRONTEND_URL}/en" 200 "" "true"
test_endpoint "Homepage (Russian)" "${FRONTEND_URL}/ru" 200 "" "true"
test_endpoint "Login Page (Romanian)" "${FRONTEND_URL}/ro/login" 200 "" "true"
test_endpoint "Archive Page (Romanian)" "${FRONTEND_URL}/ro/archive" 200 "" "true"

echo ""

# ============================================================================
# SUMMARY
# ============================================================================
echo -e "${BLUE}╔════════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║                        Test Summary                            ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════════╝${NC}"
echo ""

TOTAL=$((PASSED + FAILED + WARNINGS))

echo -e "Total Tests:     ${BLUE}${TOTAL}${NC}"
echo -e "Passed:          ${GREEN}${PASSED}${NC}"
echo -e "Failed:          ${RED}${FAILED}${NC}"
echo -e "Warnings:        ${YELLOW}${WARNINGS}${NC}"

echo ""

# Exit code logic
if [ "$FAILED" -gt 0 ]; then
    echo -e "${RED}✗ SMOKE CHECK FAILED${NC}"
    echo -e "${RED}Some critical tests failed. Please check the application.${NC}"
    exit 1
elif [ "$WARNINGS" -gt 0 ]; then
    echo -e "${YELLOW}⚠ SMOKE CHECK PASSED WITH WARNINGS${NC}"
    echo -e "${YELLOW}Some non-critical services are unavailable.${NC}"
    exit 0
else
    echo -e "${GREEN}✓ ALL SMOKE CHECKS PASSED${NC}"
    echo -e "${GREEN}Application is healthy and ready!${NC}"
    exit 0
fi
