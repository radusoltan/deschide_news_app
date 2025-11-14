# Production Category Import Guide

**Document Version**: 1.0
**Last Updated**: 9 Noiembrie 2025
**Target Environment**: Production

---

## 📋 Overview

Acest document descrie pașii necesari pentru importul categoriilor în mediul de producție al aplicației Deschide News. Importul include:

- **28 categorii** totale (24 active + 4 archived)
- **Traduceri complete** în 3 limbi: Română (RO), Engleză (EN), Rusă (RU)
- **Mappings** către sursele originale (Webflow și arhive vechi)

---

## 📦 Prerequisites

### 1. Verificare Cod Sursă

Asigurați-vă că următoarele fișiere sunt prezente și corecte:

```bash
# Fișier de date pentru import
deschide_backend/data/import/categories_complete.json

# Comandă de import
deschide_backend/src/Command/Import/ImportCompleteCategoriesCommand.php

# Entitate Category cu configurare corectă
deschide_backend/src/Entity/Category.php
```

### 2. Verificare Configurație Gedmo

**Fișier**: `config/packages/stof_doctrine_extensions.yaml`

```yaml
stof_doctrine_extensions:
    default_locale: ro
    orm:
        default:
            translatable: true
            sluggable: true
            timestampable: true
```

**Fișier**: `config/packages/framework.yaml`

```yaml
framework:
    default_locale: ro  # IMPORTANT: Must be 'ro'
```

**Fișier**: `config/services.yaml`

```yaml
# Gedmo Translatable Listener (alias for autowiring)
Gedmo\Translatable\TranslatableListener:
    alias: stof_doctrine_extensions.listener.translatable
    public: false
```

### 3. Verificare Entitate Category

**Fișier**: `src/Entity/Category.php`

Verificați că câmpul `slug` **NU ARE** adnotarea `#[Gedmo\Translatable]`:

```php
// ✅ CORRECT - Slug should NOT be translatable
#[Gedmo\Slug(fields: ['title'], unique: true, updatable: true)]
#[ORM\Column(type: Types::STRING, length: 255, unique: true)]
private ?string $slug = null;

// ❌ INCORRECT - Do NOT add this annotation
// #[Gedmo\Translatable]  // This would cause NULL slugs for EN/RU
```

Verificați că câmpul `title` **ARE** adnotarea `#[Gedmo\Translatable]`:

```php
// ✅ CORRECT - Title should be translatable
#[Gedmo\Translatable]
#[ORM\Column(type: Types::STRING, length: 255)]
private ?string $title = null;
```

### 4. Verificare Database

```bash
# Connect to production database
psql -h <DB_HOST> -U <DB_USER> -d <DB_NAME>

# Verify tables exist
\dt

# Should see:
# - category
# - ext_translations
# - ext_log_entries (optional)
```

---

## 🚀 Import Steps

### Step 1: Backup Database

**CRITICAL**: Întotdeauna faceți backup înainte de import!

```bash
# Full database backup
pg_dump -h <DB_HOST> -U <DB_USER> -d <DB_NAME> > backup_before_category_import_$(date +%Y%m%d_%H%M%S).sql

# Backup only category tables
pg_dump -h <DB_HOST> -U <DB_USER> -d <DB_NAME> \
  -t category \
  -t ext_translations \
  > backup_categories_$(date +%Y%m%d_%H%M%S).sql
```

### Step 2: Verificare Fișier de Import

```bash
# Navigate to backend directory
cd /var/www/deschide_news_app/deschide_backend

# Verify import file exists
ls -lh data/import/categories_complete.json

# Check file contents (should show 28 categories)
cat data/import/categories_complete.json | jq '. | length'
# Expected output: 28

# Verify structure
cat data/import/categories_complete.json | jq '.[0]'
# Should show first category with translations object
```

### Step 3: Clear Cache

```bash
# Clear Symfony cache
php bin/console cache:clear --env=prod

# Warm up cache
php bin/console cache:warmup --env=prod
```

### Step 4: Run Import Command

```bash
# DRY RUN - Preview what will be imported (recommended first)
php bin/console app:import:complete-categories --env=prod

# Check the output carefully
# Verify:
# - Number of categories to import: 28
# - Number of translations: 56 (28 EN + 28 RU)
# - Mapping data looks correct

# ACTUAL IMPORT - Use --force flag
php bin/console app:import:complete-categories --force --env=prod
```

**Expected Output**:
```
Starting complete category import...
Processing 28 categories from categories_complete.json

Category 1/28: Alegeri (elections)
  ✓ Created/Updated category (ID: XX)
  ✓ Saved EN translation: Elections
  ✓ Saved RU translation: Выборы

...

Category 28/28: Video (video)
  ✓ Created/Updated category (ID: XX)
  ✓ Saved EN translation: Video
  ✓ Saved RU translation: Видео

Import Summary:
✓ 28 categories processed
✓ 56 translations imported (28 EN + 28 RU)
✓ Status: 24 active, 4 archived
```

### Step 5: Verification

#### 5.1 Verify Database Records

```bash
# Connect to database
psql -h <DB_HOST> -U <DB_USER> -d <DB_NAME>

# Count categories
SELECT COUNT(*) FROM category;
-- Expected: 28

# Count translations (title field only)
SELECT COUNT(*) FROM ext_translations
WHERE object_class LIKE '%Category%'
AND field = 'title';
-- Expected: 56 (28 EN + 28 RU)

# Verify NO slug translations exist
SELECT COUNT(*) FROM ext_translations
WHERE object_class LIKE '%Category%'
AND field = 'slug';
-- Expected: 0 (slugs should NOT be translated)

# Check status distribution
SELECT status, COUNT(*)
FROM category
GROUP BY status;
-- Expected:
-- active: 24
-- archived: 4

# View sample category with translations
SELECT c.id, c.title, c.slug, c.status, c.on_front_page
FROM category c
WHERE c.slug = 'alegeri';

# View translations for a specific category
SELECT t.locale, t.field, t.content
FROM ext_translations t
WHERE t.object_class LIKE '%Category%'
AND t.foreign_key = '16'  -- Replace with actual category ID
ORDER BY t.locale, t.field;
```

#### 5.2 Test API Endpoints

```bash
# Test Romanian (default)
curl -H "Accept-Language: ro" https://api.deschide.md/api/categories/16 | jq '{title, slug}'
# Expected: {"title": "Alegeri", "slug": "alegeri"}

# Test English
curl -H "Accept-Language: en" https://api.deschide.md/api/categories/16 | jq '{title, slug}'
# Expected: {"title": "Elections", "slug": "alegeri"}

# Test Russian
curl -H "Accept-Language: ru" https://api.deschide.md/api/categories/16 | jq '{title, slug}'
# Expected: {"title": "Выборы", "slug": "alegeri"}

# Get all active categories
curl -H "Accept-Language: ro" "https://api.deschide.md/api/categories?status=active" | jq '.["hydra:member"] | length'
# Expected: 24

# Get all categories (including archived)
curl -H "Accept-Language: ro" "https://api.deschide.md/api/categories" | jq '.["hydra:member"] | length'
# Expected: 28

# Get front page categories only
curl -H "Accept-Language: ro" "https://api.deschide.md/api/categories?onFrontPage=true" | jq '.["hydra:member"] | map({id, title, slug, status})'
```

#### 5.3 Verify Translations Work for All Categories

```bash
# Test multiple categories in different languages
for cat_id in 13 16 17 18 19; do
  echo "=== Category ID: $cat_id ==="
  echo "RO:" $(curl -s -H "Accept-Language: ro" "https://api.deschide.md/api/categories/$cat_id" | jq -r '.title')
  echo "EN:" $(curl -s -H "Accept-Language: en" "https://api.deschide.md/api/categories/$cat_id" | jq -r '.title')
  echo "RU:" $(curl -s -H "Accept-Language: ru" "https://api.deschide.md/api/categories/$cat_id" | jq -r '.title')
  echo ""
done
```

---

## 📊 Expected Results

### Categories by Status

| Status | Count | Description |
|--------|-------|-------------|
| **active** | 24 | Categorii active, utilizate curent |
| **archived** | 4 | Categorii arhivate pentru istoric |
| **Total** | 28 | Total categorii importate |

### Translations Summary

| Language | Field | Count | Notes |
|----------|-------|-------|-------|
| **EN** | title | 28 | Titluri în engleză |
| **RU** | title | 28 | Titluri în rusă |
| **Total** | - | **56** | Total traduceri |

**Note**: Câmpul `slug` **NU** se traduce - folosește întotdeauna valoarea din limba română pentru consistență URL.

### Front Page Categories

Verificați că **12 categorii** au `on_front_page = true`:
- Alegeri
- Anti-Fake
- Cultură & Divertisment
- Economie
- Educație
- Moldova
- Politică
- Social
- Sport
- Tehnologie
- Lume
- Opinie

---

## 🔧 Troubleshooting

### Problem 1: Import Failed - Database Error

**Error**: `SQLSTATE[23505]: Unique violation: 7 ERROR: duplicate key value violates unique constraint`

**Solution**:
```bash
# Check for existing categories with same slug
SELECT id, title, slug FROM category WHERE slug = 'slug-value';

# If using --force flag, command should UPDATE existing records
# If not, remove conflicting records or use --force
```

### Problem 2: Translations Not Showing in API

**Error**: API returns title in Romanian regardless of `Accept-Language` header

**Diagnostic**:
```bash
# 1. Verify translations exist in database
SELECT * FROM ext_translations
WHERE object_class LIKE '%Category%'
AND field = 'title'
AND locale = 'en'
LIMIT 5;

# 2. Check LocaleSubscriber is configured
grep -r "LocaleSubscriber" src/EventSubscriber/

# 3. Verify framework.yaml has default_locale
grep "default_locale" config/packages/framework.yaml
```

**Solution**:
- Ensure `src/EventSubscriber/LocaleSubscriber.php` exists and sets `$request->setLocale($locale)`
- Verify `config/packages/framework.yaml` has `default_locale: ro`
- Clear cache: `php bin/console cache:clear --env=prod`

### Problem 3: Slugs Show NULL for EN/RU

**Error**: English/Russian API responses show `"slug": null`

**Cause**: Câmpul `slug` are adnotarea `#[Gedmo\Translatable]` în entitate

**Solution**:
```bash
# 1. Edit src/Entity/Category.php
# Remove #[Gedmo\Translatable] from slug field (keep only on title field)

# 2. Delete existing slug translations
psql -h <DB_HOST> -U <DB_USER> -d <DB_NAME> -c "
DELETE FROM ext_translations
WHERE object_class LIKE '%Category%'
AND field = 'slug';
"

# 3. Clear cache
php bin/console cache:clear --env=prod
```

### Problem 4: Only Some Categories Have Translations

**Diagnostic**:
```bash
# Find categories missing translations
SELECT c.id, c.title, c.slug,
  (SELECT COUNT(*) FROM ext_translations t
   WHERE t.object_class LIKE '%Category%'
   AND t.foreign_key::integer = c.id
   AND t.field = 'title') as translation_count
FROM category c
HAVING translation_count < 2;
```

**Solution**:
```bash
# Re-run import with --force flag
php bin/console app:import:complete-categories --force --env=prod
```

### Problem 5: Archived Categories Not Imported

**Diagnostic**:
```bash
# Check CategoryStatus enum
SELECT DISTINCT status FROM category;
-- Should show: active, archived

# Count by status
SELECT status, COUNT(*) FROM category GROUP BY status;
```

**Solution**:
- Verify `src/Enum/CategoryStatus.php` has `case ARCHIVED = 'archived';`
- Run migration if needed: `php bin/console doctrine:migrations:migrate --env=prod`
- Re-run import

---

## 🔄 Rollback Procedure

If import fails or produces incorrect results:

### Option 1: Restore from Backup

```bash
# Stop application (if using PM2/systemd)
pm2 stop deschide_backend
# or
systemctl stop deschide-backend

# Restore database from backup
psql -h <DB_HOST> -U <DB_USER> -d <DB_NAME> < backup_before_category_import_YYYYMMDD_HHMMSS.sql

# Restart application
pm2 start deschide_backend
# or
systemctl start deschide-backend
```

### Option 2: Manual Cleanup

```bash
# Delete all categories and translations
psql -h <DB_HOST> -U <DB_USER> -d <DB_NAME> <<EOF
BEGIN;
DELETE FROM ext_translations WHERE object_class LIKE '%Category%';
DELETE FROM category;
COMMIT;
EOF

# Verify cleanup
psql -h <DB_HOST> -U <DB_USER> -d <DB_NAME> -c "
SELECT 'Categories: ' || COUNT(*) FROM category
UNION ALL
SELECT 'Translations: ' || COUNT(*) FROM ext_translations WHERE object_class LIKE '%Category%';
"
```

---

## 📝 Post-Import Checklist

- [ ] **Database verification**: 28 categories, 56 translations
- [ ] **API test RO**: All categories return Romanian titles
- [ ] **API test EN**: All categories return English titles
- [ ] **API test RU**: All categories return Russian titles
- [ ] **Slug consistency**: All locales return same Romanian slug
- [ ] **Status check**: 24 active, 4 archived
- [ ] **Front page filter**: 12 categories with `onFrontPage=true`
- [ ] **Cache cleared**: Production cache cleared and warmed
- [ ] **Logs checked**: No errors in Symfony logs
- [ ] **Backup created**: Database backup stored safely

---

## 📚 Related Documentation

- **Import Strategy**: `import_strategies/categories_complete_import_strategy.md`
- **Migration Strategy**: `migration_strategy/migration_strategy.md`
- **Category Entity**: `src/Entity/Category.php`
- **Import Command**: `src/Command/Import/ImportCompleteCategoriesCommand.php`
- **Import Data**: `data/import/categories_complete.json`

---

## 🔐 Security Notes

### Environment Variables

Ensure production `.env.local` has correct database credentials:

```bash
DATABASE_URL="postgresql://prod_user:SECURE_PASSWORD@db.host:5432/deschide_prod?serverVersion=17&charset=utf8"
```

### Permissions

```bash
# Ensure import data file has correct permissions
chmod 644 data/import/categories_complete.json

# Ensure command is executable
chmod +x bin/console
```

---

## 📞 Support

If you encounter issues during production import:

1. **Check logs**: `var/log/prod.log`
2. **Database logs**: PostgreSQL logs for constraint violations
3. **Verify configuration**: All prerequisites from this guide
4. **Test in staging first**: Always test import in staging environment before production

---

**Document prepared by**: Claude Code
**Environment**: Deschide News Backend (Symfony 7.3)
**Database**: PostgreSQL 17
**PHP**: 8.4

**Last successful import**: Development - 9 Noiembrie 2025
