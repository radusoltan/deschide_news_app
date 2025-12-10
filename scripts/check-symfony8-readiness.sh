#!/bin/bash

###############################################################################
# Symfony 8 Upgrade Readiness Check Script
# Purpose: Verify system is ready for Symfony 8 upgrade
# Usage: ./scripts/check-symfony8-readiness.sh
###############################################################################

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Counters
PASSED=0
FAILED=0
WARNINGS=0

echo "======================================"
echo "Symfony 8 Upgrade Readiness Check"
echo "======================================"
echo ""

# Function to check and report
check() {
    local name="$1"
    local command="$2"
    local expected="$3"
    local level="${4:-CRITICAL}"  # CRITICAL, WARNING, INFO
    
    echo -n "Checking $name... "
    
    if eval "$command" > /dev/null 2>&1; then
        if [ -n "$expected" ]; then
            result=$(eval "$command" 2>/dev/null)
            if echo "$result" | grep -q "$expected"; then
                echo -e "${GREEN}✓ PASS${NC}"
                ((PASSED++))
                return 0
            else
                echo -e "${RED}✗ FAIL${NC} (Got: $result, Expected: $expected)"
                if [ "$level" = "CRITICAL" ]; then
                    ((FAILED++))
                else
                    ((WARNINGS++))
                fi
                return 1
            fi
        else
            echo -e "${GREEN}✓ PASS${NC}"
            ((PASSED++))
            return 0
        fi
    else
        echo -e "${RED}✗ FAIL${NC}"
        if [ "$level" = "CRITICAL" ]; then
            ((FAILED++))
        else
            ((WARNINGS++))
        fi
        return 1
    fi
}

echo "=== PHP Version Checks ==="
check "PHP installed" "which php" "" "CRITICAL"
check "PHP version >= 8.2" "php -v | head -1" "PHP 8\.[2-9]" "CRITICAL"

echo ""
echo "=== PHP Extensions ==="
check "ext-ctype" "php -m | grep -i ctype" "" "CRITICAL"
check "ext-iconv" "php -m | grep -i iconv" "" "CRITICAL"
check "ext-pgsql" "php -m | grep -i pgsql" "" "CRITICAL"
check "ext-redis" "php -m | grep -i redis" "" "CRITICAL"
check "ext-curl" "php -m | grep -i curl" "" "CRITICAL"
check "ext-mbstring" "php -m | grep -i mbstring" "" "CRITICAL"
check "ext-xml" "php -m | grep -i xml" "" "CRITICAL"
check "ext-zip" "php -m | grep -i zip" "" "CRITICAL"
check "ext-intl" "php -m | grep -i intl" "" "CRITICAL"

echo ""
echo "=== Composer ==="
check "Composer installed" "which composer" "" "CRITICAL"
check "Composer version >= 2.0" "composer --version" "Composer version 2\." "CRITICAL"

echo ""
echo "=== Current Symfony Installation ==="
if [ -f "bin/console" ]; then
    check "Symfony console available" "test -f bin/console" "" "CRITICAL"
    check "Symfony version 7.x" "php bin/console --version" "Symfony 7\." "CRITICAL"
else
    echo -e "${RED}✗ bin/console not found${NC}"
    ((FAILED++))
fi

echo ""
echo "=== Dependencies ==="
if [ -f "composer.json" ]; then
    check "composer.json exists" "test -f composer.json" "" "CRITICAL"
    check "composer.lock exists" "test -f composer.lock" "" "CRITICAL"
    
    # Check critical dependencies
    if grep -q "api-platform/symfony" composer.json; then
        API_VERSION=$(grep "api-platform/symfony" composer.json | grep -oP '\^[0-9]+\.[0-9]+')
        if echo "$API_VERSION" | grep -q "^4\."; then
            echo -e "API Platform version ${GREEN}✓ $API_VERSION (Compatible)${NC}"
            ((PASSED++))
        else
            echo -e "API Platform version ${YELLOW}⚠ $API_VERSION (Needs upgrade to 4.x)${NC}"
            ((WARNINGS++))
        fi
    fi
    
    if grep -q "doctrine/orm" composer.json; then
        DOCTRINE_VERSION=$(grep "doctrine/orm" composer.json | grep -oP '\^[0-9]+\.[0-9]+')
        if echo "$DOCTRINE_VERSION" | grep -q "^3\."; then
            echo -e "Doctrine ORM version ${GREEN}✓ $DOCTRINE_VERSION (Compatible)${NC}"
            ((PASSED++))
        else
            echo -e "Doctrine ORM version ${YELLOW}⚠ $DOCTRINE_VERSION (Needs upgrade to 3.x)${NC}"
            ((WARNINGS++))
        fi
    fi
    
    if grep -q "doctrine/dbal" composer.json; then
        DBAL_VERSION=$(grep "doctrine/dbal" composer.json | grep -oP '\^[0-9]+\.[0-9]+')
        if echo "$DBAL_VERSION" | grep -q "^3\."; then
            echo -e "Doctrine DBAL version ${YELLOW}⚠ $DBAL_VERSION (Needs upgrade to 4.x for Symfony 8)${NC}"
            ((WARNINGS++))
        elif echo "$DBAL_VERSION" | grep -q "^4\."; then
            echo -e "Doctrine DBAL version ${GREEN}✓ $DBAL_VERSION (Compatible)${NC}"
            ((PASSED++))
        fi
    fi
else
    echo -e "${RED}✗ composer.json not found${NC}"
    ((FAILED++))
fi

echo ""
echo "=== Database ==="
check "PostgreSQL client installed" "which psql" "" "CRITICAL"

echo ""
echo "=== Testing Tools ==="
check "PHPUnit available" "test -f vendor/bin/phpunit" "" "WARNING"
check "PHPStan available" "test -f vendor/bin/phpstan" "" "WARNING"
check "PHP CS Fixer available" "test -f vendor/bin/php-cs-fixer" "" "WARNING"

echo ""
echo "=== Deprecations Check ==="
if [ -f "vendor/bin/phpunit" ]; then
    echo "Running deprecation scan (this may take a while)..."
    DEPRECATIONS=$(SYMFONY_DEPRECATIONS_HELPER=weak vendor/bin/phpunit 2>&1 | grep -oP 'Remaining deprecation notices \(\K[0-9]+' || echo "0")
    if [ "$DEPRECATIONS" = "0" ]; then
        echo -e "Deprecations: ${GREEN}✓ 0 (Ready for Symfony 8)${NC}"
        ((PASSED++))
    else
        echo -e "Deprecations: ${YELLOW}⚠ $DEPRECATIONS (Must fix before Symfony 8 upgrade)${NC}"
        echo "  Run: SYMFONY_DEPRECATIONS_HELPER=weak vendor/bin/phpunit > deprecations.txt"
        ((WARNINGS++))
    fi
else
    echo -e "${YELLOW}⚠ Cannot check deprecations (PHPUnit not available)${NC}"
    ((WARNINGS++))
fi

echo ""
echo "=== Environment ==="
check "Environment variables loaded" "test -f .env" "" "CRITICAL"
check "var/cache directory writable" "test -w var/cache" "" "CRITICAL"
check "var/log directory writable" "test -w var/log" "" "CRITICAL"

echo ""
echo "=== Git Status ==="
check "Git repository" "git rev-parse --git-dir" "" "CRITICAL"

if git diff-index --quiet HEAD -- 2>/dev/null; then
    echo -e "Working directory: ${GREEN}✓ Clean${NC}"
    ((PASSED++))
else
    echo -e "Working directory: ${YELLOW}⚠ Has uncommitted changes${NC}"
    echo "  Consider committing or stashing changes before upgrade"
    ((WARNINGS++))
fi

echo ""
echo "=== Backup Status ==="
BACKUP_DIR="/var/backups"
if [ -d "$BACKUP_DIR" ] && [ -w "$BACKUP_DIR" ]; then
    echo -e "Backup directory: ${GREEN}✓ $BACKUP_DIR is writable${NC}"
    ((PASSED++))
else
    echo -e "Backup directory: ${YELLOW}⚠ $BACKUP_DIR not available${NC}"
    echo "  Ensure backup location is configured"
    ((WARNINGS++))
fi

echo ""
echo "======================================"
echo "Summary"
echo "======================================"
echo -e "✓ Passed:   ${GREEN}$PASSED${NC}"
echo -e "✗ Failed:   ${RED}$FAILED${NC}"
echo -e "⚠ Warnings: ${YELLOW}$WARNINGS${NC}"
echo ""

if [ $FAILED -gt 0 ]; then
    echo -e "${RED}❌ CRITICAL ISSUES FOUND${NC}"
    echo "System is NOT ready for Symfony 8 upgrade."
    echo "Please fix all critical issues before proceeding."
    exit 1
elif [ $WARNINGS -gt 0 ]; then
    echo -e "${YELLOW}⚠️  WARNINGS DETECTED${NC}"
    echo "System is mostly ready, but address warnings for optimal upgrade."
    echo "Review the warnings above before proceeding."
    exit 2
else
    echo -e "${GREEN}✅ ALL CHECKS PASSED${NC}"
    echo "System is ready for Symfony 8 upgrade!"
    echo ""
    echo "Next steps:"
    echo "1. Review: docs/planning/SYMFONY_8_UPGRADE_PLAN.md"
    echo "2. Create upgrade branch: git checkout -b upgrade/symfony-7.4-preparation"
    echo "3. Start Phase 1: Symfony 7.4 upgrade"
    exit 0
fi
