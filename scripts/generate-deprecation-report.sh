#!/bin/bash

###############################################################################
# Deprecation Report Generator
# Purpose: Generate comprehensive deprecation report for Symfony upgrade
# Usage: ./scripts/generate-deprecation-report.sh [output-file]
###############################################################################

set -e

OUTPUT_FILE="${1:-docs/planning/deprecations_inventory.md}"
TEMP_FILE=$(mktemp)

echo "======================================"
echo "Generating Deprecation Report"
echo "======================================"
echo ""

# Function to count occurrences of a pattern in files
count_pattern() {
    local pattern="$1"
    local path="${2:-.}"
    grep -r "$pattern" "$path" --include="*.php" 2>/dev/null | wc -l || echo "0"
}

# Function to find files with pattern
find_files_with_pattern() {
    local pattern="$1"
    local path="${2:-.}"
    grep -r "$pattern" "$path" --include="*.php" -l 2>/dev/null || echo ""
}

echo "Analyzing codebase for deprecated patterns..."
echo ""

# Start building the report
cat > "$TEMP_FILE" << 'EOF'
# Symfony Deprecations Inventory

**Generated:** $(date)  
**Project:** Deschide News App  
**Current Version:** $(php bin/console --version)

---

## Executive Summary

| Category | Count | Priority |
|----------|-------|----------|
EOF

# Run PHPUnit deprecation scan
echo "Running PHPUnit deprecation scan..."
DEPRECATION_COUNT=$(SYMFONY_DEPRECATIONS_HELPER=weak vendor/bin/phpunit 2>&1 | grep -oP 'Remaining deprecation notices \(\K[0-9]+' || echo "0")

echo "Found $DEPRECATION_COUNT deprecations from PHPUnit"
echo ""

# Check for common deprecated patterns
echo "Scanning for specific deprecated patterns..."

# Pattern 1: TaggedIterator
TAGGED_ITERATOR_COUNT=$(count_pattern "#\[TaggedIterator\(" "src/")
TAGGED_ITERATOR_FILES=$(find_files_with_pattern "#\[TaggedIterator\(" "src/")

echo "  - TaggedIterator: $TAGGED_ITERATOR_COUNT occurrences"

# Pattern 2: TaggedLocator
TAGGED_LOCATOR_COUNT=$(count_pattern "#\[TaggedLocator\(" "src/")
TAGGED_LOCATOR_FILES=$(find_files_with_pattern "#\[TaggedLocator\(" "src/")

echo "  - TaggedLocator: $TAGGED_LOCATOR_COUNT occurrences"

# Pattern 3: Request::get()
REQUEST_GET_COUNT=$(count_pattern '\$request->get\(' "src/")
REQUEST_GET_FILES=$(find_files_with_pattern '\$request->get\(' "src/")

echo "  - Request::get(): $REQUEST_GET_COUNT occurrences"

# Pattern 4: Application::add()
APP_ADD_COUNT=$(count_pattern '->add\(new [A-Z].*Command' "src/")
APP_ADD_FILES=$(find_files_with_pattern '->add\(new [A-Z].*Command' "src/")

echo "  - Application::add(): $APP_ADD_COUNT occurrences"

echo ""
echo "Generating detailed report..."

# Build the full report
cat > "$OUTPUT_FILE" << EOF
# Symfony Deprecations Inventory

**Generated:** $(date '+%Y-%m-%d %H:%M:%S')  
**Project:** Deschide News App  
**Current Version:** $(php bin/console --version 2>/dev/null || echo "Unknown")  
**PHP Version:** $(php -v | head -1)

---

## Executive Summary

| Category | Count | Priority | Effort |
|----------|-------|----------|--------|
| PHPUnit Deprecations | $DEPRECATION_COUNT | Critical | High |
| TaggedIterator | $TAGGED_ITERATOR_COUNT | Critical | Medium |
| TaggedLocator | $TAGGED_LOCATOR_COUNT | Critical | Medium |
| Request::get() | $REQUEST_GET_COUNT | Critical | Low |
| Application::add() | $APP_ADD_COUNT | Critical | Low |

**Total Issues:** $((DEPRECATION_COUNT + TAGGED_ITERATOR_COUNT + TAGGED_LOCATOR_COUNT + REQUEST_GET_COUNT + APP_ADD_COUNT))

**Estimated Effort:** $(if [ $((DEPRECATION_COUNT + TAGGED_ITERATOR_COUNT + TAGGED_LOCATOR_COUNT + REQUEST_GET_COUNT + APP_ADD_COUNT)) -lt 20 ]; then echo "1-2 days"; elif [ $((DEPRECATION_COUNT + TAGGED_ITERATOR_COUNT + TAGGED_LOCATOR_COUNT + REQUEST_GET_COUNT + APP_ADD_COUNT)) -lt 50 ]; then echo "3-5 days"; else echo "1-2 weeks"; fi)

---

## Deprecation Categories

### 1. PHPUnit Deprecations (Priority: Critical)

**Count:** $DEPRECATION_COUNT  
**Effort:** High  
**Status:** 🔴 Not Started

Run the following command to see detailed deprecation output:

\`\`\`bash
SYMFONY_DEPRECATIONS_HELPER=weak vendor/bin/phpunit > deprecations_detail.txt 2>&1
cat deprecations_detail.txt | grep -A 5 "Remaining deprecation notices"
\`\`\`

**Next Steps:**
1. Generate detailed deprecation output
2. Categorize each deprecation by component
3. Create fix plan for each category
4. Assign to team members

---

### 2. TaggedIterator → AutowireIterator (Priority: Critical)

**Count:** $TAGGED_ITERATOR_COUNT occurrences  
**Effort:** Medium (15-30 min per file)  
**Status:** 🔴 Not Started

**Breaking Change:** Symfony 8.0 removes \`TaggedIterator\` attribute in favor of \`AutowireIterator\`.

**Affected Files:**
EOF

if [ -n "$TAGGED_ITERATOR_FILES" ]; then
    echo "$TAGGED_ITERATOR_FILES" | while read -r file; do
        echo "- \`$file\`" >> "$OUTPUT_FILE"
    done
else
    echo "- None found ✅" >> "$OUTPUT_FILE"
fi

cat >> "$OUTPUT_FILE" << 'EOF'

**Migration Pattern:**

```php
// BEFORE (Symfony 7.x)
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class MyService {
    public function __construct(
        #[TaggedIterator('app.my_tag')]
        private iterable $items
    ) {}
}

// AFTER (Symfony 8.0)
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class MyService {
    public function __construct(
        #[AutowireIterator('app.my_tag')]
        private iterable $items
    ) {}
}
```

**Tasks:**
EOF

if [ -n "$TAGGED_ITERATOR_FILES" ]; then
    echo "$TAGGED_ITERATOR_FILES" | while read -r file; do
        echo "- [ ] Fix \`$file\`" >> "$OUTPUT_FILE"
    done
else
    echo "- [x] No files to fix ✅" >> "$OUTPUT_FILE"
fi

cat >> "$OUTPUT_FILE" << EOF

---

### 3. TaggedLocator → AutowireLocator (Priority: Critical)

**Count:** $TAGGED_LOCATOR_COUNT occurrences  
**Effort:** Medium (15-30 min per file)  
**Status:** 🔴 Not Started

**Breaking Change:** Symfony 8.0 removes \`TaggedLocator\` attribute in favor of \`AutowireLocator\`.

**Affected Files:**
EOF

if [ -n "$TAGGED_LOCATOR_FILES" ]; then
    echo "$TAGGED_LOCATOR_FILES" | while read -r file; do
        echo "- \`$file\`" >> "$OUTPUT_FILE"
    done
else
    echo "- None found ✅" >> "$OUTPUT_FILE"
fi

cat >> "$OUTPUT_FILE" << 'EOF'

**Migration Pattern:**

```php
// BEFORE
use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;

class MyService {
    public function __construct(
        #[TaggedLocator('app.my_tag')]
        private ServiceLocatorInterface $locator
    ) {}
}

// AFTER
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;

class MyService {
    public function __construct(
        #[AutowireLocator('app.my_tag')]
        private ServiceLocatorInterface $locator
    ) {}
}
```

**Tasks:**
EOF

if [ -n "$TAGGED_LOCATOR_FILES" ]; then
    echo "$TAGGED_LOCATOR_FILES" | while read -r file; do
        echo "- [ ] Fix \`$file\`" >> "$OUTPUT_FILE"
    done
else
    echo "- [x] No files to fix ✅" >> "$OUTPUT_FILE"
fi

cat >> "$OUTPUT_FILE" << EOF

---

### 4. Request::get() Removal (Priority: Critical)

**Count:** $REQUEST_GET_COUNT occurrences  
**Effort:** Low (5-10 min per file)  
**Status:** 🔴 Not Started

**Breaking Change:** Symfony 8.0 removes the \`Request::get()\` convenience method.

**Affected Files:**
EOF

if [ -n "$REQUEST_GET_FILES" ]; then
    echo "$REQUEST_GET_FILES" | while read -r file; do
        echo "- \`$file\`" >> "$OUTPUT_FILE"
    done
else
    echo "- None found ✅" >> "$OUTPUT_FILE"
fi

cat >> "$OUTPUT_FILE" << 'EOF'

**Migration Pattern:**

```php
// BEFORE
$id = $request->get('id');
$page = $request->get('page', 1);

// AFTER - use specific parameter bags
$id = $request->attributes->get('id');    // Route parameters
$page = $request->query->get('page', 1);  // Query string
$title = $request->request->get('title'); // POST body
```

**Tasks:**
EOF

if [ -n "$REQUEST_GET_FILES" ]; then
    echo "$REQUEST_GET_FILES" | while read -r file; do
        echo "- [ ] Fix \`$file\`" >> "$OUTPUT_FILE"
    done
else
    echo "- [x] No files to fix ✅" >> "$OUTPUT_FILE"
fi

cat >> "$OUTPUT_FILE" << EOF

---

### 5. Application::add() → addCommand() (Priority: Critical)

**Count:** $APP_ADD_COUNT occurrences  
**Effort:** Low (2-5 min per file)  
**Status:** 🔴 Not Started

**Breaking Change:** Use \`addCommand()\` instead of \`add()\` for console commands.

**Affected Files:**
EOF

if [ -n "$APP_ADD_FILES" ]; then
    echo "$APP_ADD_FILES" | while read -r file; do
        echo "- \`$file\`" >> "$OUTPUT_FILE"
    done
else
    echo "- None found ✅" >> "$OUTPUT_FILE"
fi

cat >> "$OUTPUT_FILE" << 'EOF'

**Migration Pattern:**

```php
// BEFORE
$application->add(new MyCommand());

// AFTER
$application->addCommand(new MyCommand());
```

**Tasks:**
EOF

if [ -n "$APP_ADD_FILES" ]; then
    echo "$APP_ADD_FILES" | while read -r file; do
        echo "- [ ] Fix \`$file\`" >> "$OUTPUT_FILE"
    done
else
    echo "- [x] No files to fix ✅" >> "$OUTPUT_FILE"
fi

cat >> "$OUTPUT_FILE" << 'EOF'

---

## Additional Checks Required

### Configuration Files

- [ ] Review `config/packages/security.yaml` for OIDC token handler config
- [ ] Review `config/validator/*.yaml` for implicit constraint options
- [ ] Review `config/packages/framework.yaml` for deprecated options
- [ ] Review `config/packages/doctrine.yaml` for deprecated options

### Custom Code Patterns

- [ ] Check for deprecated Doctrine query methods
- [ ] Check for deprecated Form component methods
- [ ] Check for deprecated Validator constraints
- [ ] Check for deprecated Twig extensions
- [ ] Check for deprecated Event Dispatcher patterns

### Third-Party Bundles

- [ ] Verify Vich Uploader Bundle compatibility
- [ ] Verify Stof Doctrine Extensions compatibility
- [ ] Verify Gesdinet JWT Refresh Token Bundle compatibility
- [ ] Verify all other bundles in composer.json

---

## Verification Steps

After fixing all deprecations, verify with:

```bash
# 1. Run tests with deprecation tracking
SYMFONY_DEPRECATIONS_HELPER=weak vendor/bin/phpunit

# Expected output: "Remaining deprecation notices (0)"

# 2. Check container deprecations
php bin/console debug:container --deprecations

# Expected output: No deprecations found

# 3. Run PHPStan
vendor/bin/phpstan analyse

# Expected output: No errors

# 4. Clear caches
rm -rf var/cache/*
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

# 5. Run full test suite
vendor/bin/phpunit
```

---

## Progress Tracking

| Date | Developer | Category | Files Fixed | Status |
|------|-----------|----------|-------------|--------|
| YYYY-MM-DD | Name | TaggedIterator | X/Y | In Progress |
| | | | | |

---

## Notes

Add any important notes or observations here during the fixing process:

- 
- 
- 

---

## Sign-Off

**All deprecations fixed and verified:**

- [ ] Technical Lead: _____________ Date: _______
- [ ] Code Review Complete: _____________ Date: _______
- [ ] Tests Passing: _____________ Date: _______

**Ready for Phase 2 (Symfony 8.0 Upgrade):** ☐ YES / ☐ NO

---

*Generated by: generate-deprecation-report.sh*  
*Last Updated: $(date '+%Y-%m-%d %H:%M:%S')*
EOF

rm -f "$TEMP_FILE"

echo "======================================"
echo "Report generated successfully!"
echo "======================================"
echo ""
echo "Location: $OUTPUT_FILE"
echo ""
echo "Next steps:"
echo "1. Review the report: cat $OUTPUT_FILE"
echo "2. Prioritize fixes by category"
echo "3. Assign tasks to team members"
echo "4. Track progress in the report"
echo ""
echo "To regenerate later: ./scripts/generate-deprecation-report.sh"
EOF

chmod +x "$OUTPUT_FILE" 2>/dev/null || true

echo "======================================"
echo "Report generated successfully!"
echo "======================================"
echo ""
echo "Location: $OUTPUT_FILE"
echo ""
echo "Summary:"
echo "  - PHPUnit Deprecations: $DEPRECATION_COUNT"
echo "  - TaggedIterator: $TAGGED_ITERATOR_COUNT"
echo "  - TaggedLocator: $TAGGED_LOCATOR_COUNT"
echo "  - Request::get(): $REQUEST_GET_COUNT"
echo "  - Application::add(): $APP_ADD_COUNT"
echo ""
echo "Total Issues: $((DEPRECATION_COUNT + TAGGED_ITERATOR_COUNT + TAGGED_LOCATOR_COUNT + REQUEST_GET_COUNT + APP_ADD_COUNT))"
echo ""
echo "Next steps:"
echo "1. Review the report: cat $OUTPUT_FILE"
echo "2. Prioritize fixes by category"
echo "3. Track progress in the file"
