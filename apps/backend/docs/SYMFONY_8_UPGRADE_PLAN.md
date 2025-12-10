# Upgrade Backend to Symfony 8.0

## Goal Description
Upgrade the backend application from Symfony 7.3 to Symfony 8.0 to leverage the latest features, performance improvements, and long-term support. This involves updating PHP requirements to 8.4+, updating Symfony packages, and resolving compatibility issues.

## User Review Required
> [!IMPORTANT]
> **PHP 8.4 Requirement**: Symfony 8.0 requires PHP 8.4 or higher. Please ensure your production and CI/CD environments support PHP 8.4.
> The initial analysis shows the project is already documented as "PHP 8.4" in the user rules, but `composer.json` allows `>=8.2`. We will enforce `>=8.4`.

> [!WARNING]
> **Major Version Upgrade**: This is a major version upgrade which removes all deprecated code from Symfony 7.3/7.4. Any usage of deprecated code MUST be refactored.

## Proposed Changes

### Pre-Upgrade Preparation (Crucial)
> [!TIP]
> **Resolve Deprecations First**: The safest way to upgrade is to first ensure the application is deprecation-free on the latest minor version of Symfony 7.

1.  **Install PHPUnit Bridge**:
    ```bash
    composer require --dev symfony/phpunit-bridge
    ```
2.  **Run Tests to Find Deprecations**:
    ```bash
    SYMFONY_DEPRECATIONS_HELPER=max[total]=0 vendor/bin/phpunit
    ```
3.  **Fix Deprecations**: iterate until the test suite passes with 0 deprecations.

### Upgrade Execution
#### [MODIFY] [composer.json](file:///var/www/deschide_news_app/apps/backend/composer.json)
- Update `php` requirement to `>=8.4`.
- Update `extra.symfony.require` to `8.0.*`.
- Update all `symfony/*` packages to `^8.0`.
- Update third-party bundles (API Platform, specific bundles) to compatible versions.

#### Run Composer Update
```bash
composer update "symfony/*" --with-all-dependencies
```

#### Update Recipes
After upgrading packages, update configuration files to match new defaults:
```bash
composer recipes:update
```

#### Clear Cache Manually
Avoid `cache:clear` command initially as the container might be broken.
```bash
rm -rf var/cache/*
```

## Verification Plan

### Automated Tests
- **Dependency Check**: Run `composer validate` and `composer outdated` to ensure clean resolution.
- **Unit & Integration Tests**: Run fully automated test suite:
  ```bash
  cd apps/backend
  composer test
  ```
- **Static Analysis**: Run PHPStan to catch type/compatibility issues:
  ```bash
  composer lint
  ```

### Manual Verification
- **Application Start**: Verify the application boots correctly:
  ```bash
  symfony console about
  symfony console cache:clear
  ```
- **API Check**: Verify key API endpoints (e.g., health check, article list) are responsive.
