# Symfony Deprecations Inventory

**Generated:** 2025-12-10 13:13:52  
**Project:** Deschide News App  
**Current Version:** Symfony 7.3.7 (env: dev, debug: false)  
**PHP Version:** PHP 8.4.14 (cli) (built: Oct 27 2025 20:53:56) (NTS)

---

## Executive Summary

| Category | Count | Priority | Effort |
|----------|-------|----------|--------|
| PHPUnit Deprecations | 0 | Critical | High |
| TaggedIterator | 0 | Critical | Medium |
| TaggedLocator | 0 | Critical | Medium |
| Request::get() | 0 | Critical | Low |
| Application::add() | 0 | Critical | Low |

**Total Issues:** 0

**Estimated Effort:** 1-2 days

---

## Deprecation Categories

### 1. PHPUnit Deprecations (Priority: Critical)

**Count:** 0  
**Effort:** High  
**Status:** 🔴 Not Started

Run the following command to see detailed deprecation output:

```bash
SYMFONY_DEPRECATIONS_HELPER=weak vendor/bin/phpunit > deprecations_detail.txt 2>&1
cat deprecations_detail.txt | grep -A 5 "Remaining deprecation notices"
```

**Next Steps:**
1. Generate detailed deprecation output
2. Categorize each deprecation by component
3. Create fix plan for each category
4. Assign to team members

---

### 2. TaggedIterator → AutowireIterator (Priority: Critical)

**Count:** 0 occurrences  
**Effort:** Medium (15-30 min per file)  
**Status:** 🔴 Not Started

**Breaking Change:** Symfony 8.0 removes `TaggedIterator` attribute in favor of `AutowireIterator`.

**Affected Files:**
- None found ✅

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
- [x] No files to fix ✅

---

### 3. TaggedLocator → AutowireLocator (Priority: Critical)

**Count:** 0 occurrences  
**Effort:** Medium (15-30 min per file)  
**Status:** 🔴 Not Started

**Breaking Change:** Symfony 8.0 removes `TaggedLocator` attribute in favor of `AutowireLocator`.

**Affected Files:**
- None found ✅

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
- [x] No files to fix ✅

---

### 4. Request::get() Removal (Priority: Critical)

**Count:** 0 occurrences  
**Effort:** Low (5-10 min per file)  
**Status:** 🔴 Not Started

**Breaking Change:** Symfony 8.0 removes the `Request::get()` convenience method.

**Affected Files:**
- None found ✅

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
- [x] No files to fix ✅

---

### 5. Application::add() → addCommand() (Priority: Critical)

**Count:** 0 occurrences  
**Effort:** Low (2-5 min per file)  
**Status:** 🔴 Not Started

**Breaking Change:** Use `addCommand()` instead of `add()` for console commands.

**Affected Files:**
- None found ✅

**Migration Pattern:**

```php
// BEFORE
$application->add(new MyCommand());

// AFTER
$application->addCommand(new MyCommand());
```

**Tasks:**
- [x] No files to fix ✅

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
