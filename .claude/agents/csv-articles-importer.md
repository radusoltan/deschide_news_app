# CSV Articles Importer Agent

> **Agent Type**: Data Import Specialist
> **Purpose**: Import articles from CSV files (Playwright-scraped data) into Deschide News App

---

## Design Philosophy

Following Anthropic's core principles:
1. **Simplicity** - Single responsibility: CSV → Symfony migration
2. **Transparency** - Clear field mapping and validation rules
3. **Well-documented ACI** - Explicit CSV schema documentation

---

## Overview

This agent handles importing articles from CSV files that were scraped using Playwright from the Buchis website (previous CMS version).

### Source Files

**Location**: `/var/www/deschide_news_app/migration_strategy/`

| File | Description | Estimated Records |
|------|-------------|-------------------|
| `buchis.csv` | Primary export | ~5000 articles |
| `buchis_2.csv` | Secondary export | ~3000 articles |

---

## CSV Schema

### Required Fields

| Column | Symfony Field | Type | Validation |
|--------|---------------|------|------------|
| `Item ID` | External mapping | String | Required, unique |
| `Title` | `title` | String | Required, max 255 |
| `Slug` | `slug` | String | Required, auto-increment if duplicate |

### Optional Fields

| Column | Symfony Field | Type | Default |
|--------|---------------|------|---------|
| `Lead text` | `lead` | Text | null |
| `Content Text` | `content` | HTML Text | null |
| `Category` | `category` | Relation | Create if not exists |
| `Author` | `authors` | Relation | Parse "First Last" |
| `Main Image` | `articleImages[0]` | URL | Download & store |
| `Short Description` | `articleImages[0].image.alt` | String | null |

### Date Fields

| Column | Symfony Field | Format | Priority |
|--------|---------------|--------|----------|
| `Manual Data` | `publishedAt` | RFC3339/ISO | Primary (preferred) |
| `Published On` | `publishedAt` | RFC3339/ISO | Secondary (fallback) |

### Boolean Flags

| Column | Symfony Field | True Value |
|--------|---------------|------------|
| `Is Breaking News?` | `badge: breaking` | `"true"` |
| `Is News alert?` | `badge: alert` | `"true"` |
| `Is Flash News?` | `badge: flash` | `"true"` |
| `Is featured?` | `isFeatured` | `"true"` |
| `Draft` | Skip import | `"true"` |
| `Archived` | Skip import | `"true"` |

### Metadata Fields (Stored in ExternalArticleMapping)

| Column | Mapping Field | Description |
|--------|---------------|-------------|
| `Collection ID` | `metadata.collection_id` | Original collection |
| `Locale ID` | `metadata.locale_id` | Original locale |

---

## Import Command

**Command**: `symfony console app:import:csv-articles`

### Options

| Option | Short | Default | Description |
|--------|-------|---------|-------------|
| `--file` | `-f` | All files | Specific CSV file |
| `--limit` | - | All | Max articles to import |
| `--offset` | - | 0 | Skip first N records |
| `--dry-run` | - | false | Preview without saving |
| `--locale` | `-l` | `ro` | Target locale |

### Examples

```bash
# Import all from specific file
symfony console app:import:csv-articles --file=buchis.csv

# Import first 100 articles (dry run)
symfony console app:import:csv-articles --limit=100 --dry-run

# Continue from offset
symfony console app:import:csv-articles --offset=500 --limit=500

# Import to English locale
symfony console app:import:csv-articles --locale=en
```

---

## Import Workflow

### Process Flow

```
[CSV IMPORT WORKFLOW]
     │
     ├── 1. Load CSV file
     │   └── League\Csv\Reader::createFromPath()
     │
     ├── 2. For each record:
     │   │
     │   ├── 2.1 Validate required fields
     │   │   └── Item ID, Title, Slug must exist
     │   │
     │   ├── 2.2 Check for existing import
     │   │   └── ExternalArticleMapping.findArticleByExternalId()
     │   │
     │   ├── 2.3 Skip drafts/archived
     │   │   └── If Draft=true OR Archived=true → skip
     │   │
     │   ├── 2.4 Create Article entity
     │   │   ├── Set title, slug (handle duplicates)
     │   │   ├── Set lead, content (clean HTML)
     │   │   ├── Set status = PUBLISHED
     │   │   ├── Set badges from flags
     │   │   └── Set publishedAt from dates
     │   │
     │   ├── 2.5 Handle Category
     │   │   └── findOrCreateCategory()
     │   │
     │   ├── 2.6 Handle Author
     │   │   └── findOrCreateAuthor()
     │   │
     │   ├── 2.7 Handle Main Image
     │   │   ├── Download from URL
     │   │   ├── Save to uploads/images/
     │   │   └── Create ArticleImage relation
     │   │
     │   └── 2.8 Create External Mapping
     │       └── ExternalArticleMapping(source='csv_buchis')
     │
     ├── 3. Batch flush (every 50 records)
     │   └── Prevent memory exhaustion
     │
     └── 4. Final statistics
```

---

## Invocation Examples

```
@csv-articles-importer analyze CSV structure
@csv-articles-importer preview import --file=buchis.csv --limit=10
@csv-articles-importer run import --file=buchis.csv
@csv-articles-importer continue import --offset=500
@csv-articles-importer check problematic articles
@csv-articles-importer generate import report
```

---

## Data Transformations

### Slug Handling

```php
// Handle duplicate slugs
$baseSlug = $record['Slug'];
$slug = $baseSlug;
$counter = 1;

while ($this->articleRepository->findOneBy(['slug' => $slug])) {
    $slug = $baseSlug . '-' . $counter;
    $counter++;
    
    if ($counter > 100) {
        $slug = $baseSlug . '-' . uniqid();
        break;
    }
}
```

### Author Parsing

```php
// Parse "First Last" format
$nameParts = explode(' ', $authorName, 2);
$firstName = $nameParts[0] ?? '';
$lastName = $nameParts[1] ?? '';

// Generate placeholder email
$emailSlug = $this->slugger->slug($authorName)->lower()->toString();
$author->setEmail($emailSlug . '@imported.deschide.md');
```

### Date Parsing

```php
// Parse various date formats
private function parseDate(string $dateString): ?DateTimeImmutable
{
    try {
        // Remove timezone info in parentheses
        $cleaned = preg_replace('/\s*\([^)]*\)$/', '', $dateString);
        return new DateTimeImmutable($cleaned);
    } catch (Exception $e) {
        return null;
    }
}
```

### HTML Cleaning

```php
private function cleanHtml(string $content): string
{
    // Remove excessive whitespace
    $content = preg_replace('/\s+/', ' ', $content);
    
    // Fix common HTML issues
    $content = str_replace(['<p> </p>', '<p></p>'], '', $content);
    
    return trim($content);
}
```

---

## Image Handling

### Download Process

```php
private function downloadAndCreateImage(string $imageUrl, string $description): ?Image
{
    // Download image
    $response = $this->httpClient->request('GET', $imageUrl, [
        'timeout' => 30,
    ]);
    
    if ($response->getStatusCode() !== 200) {
        return null;
    }
    
    // Determine extension from content type
    $contentType = $response->getHeaders()['content-type'][0];
    $extension = match ($contentType) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        default => 'jpg',
    };
    
    // Generate unique filename
    $filename = sprintf('csv_import_%s.%s', uniqid('', true), $extension);
    
    // Save to uploads directory
    file_put_contents(self::UPLOAD_DIR . $filename, $response->getContent());
    
    // Create Image entity
    $image = new Image();
    $image->setFilename($filename);
    $image->setPath('images/' . $filename);
    // ... set other properties
    
    return $image;
}
```

### Image Storage

| Property | Value |
|----------|-------|
| Upload Directory | `/var/www/deschide_news_app/apps/backend/public/uploads/images/` |
| Filename Pattern | `csv_import_{uniqid}.{ext}` |
| Max Filename Length | 255 characters |
| Supported Types | JPEG, PNG, GIF, WebP |

---

## External Mapping

### ExternalArticleMapping Entity

```php
// Create mapping for deduplication
$mapping = new ExternalArticleMapping();
$mapping->setArticle($article);
$mapping->setSource('csv_buchis');           // Source identifier
$mapping->setExternalId($record['Item ID']); // Original ID
$mapping->setMetadata([
    'original_slug' => $record['Slug'],
    'collection_id' => $record['Collection ID'] ?? null,
    'locale_id' => $record['Locale ID'] ?? null,
    'import_date' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
]);
```

### Checking for Duplicates

```php
// Before importing, check if already exists
$existingArticle = $this->mappingRepository->findArticleByExternalId(
    'csv_buchis',
    $record['Item ID']
);

if ($existingArticle) {
    // Skip - already imported
    continue;
}
```

---

## Error Handling

### Problematic Articles Skip List

```php
// Known problematic articles (e.g., filename too long)
$problematicArticles = [
    '673da2a73525288561a3f39f', // Image filename exceeds 255 chars
];

if (in_array($externalId, $problematicArticles, true)) {
    $stats['skipped']++;
    continue;
}
```

### EntityManager Recovery

```php
private function resetEntityManager(): void
{
    if (!$this->entityManager->isOpen()) {
        $this->entityManager = $this->doctrine->resetManager();
        
        // Clear all caches
        $this->categoryCache = [];
        $this->authorCache = [];
        $this->imageCache = [];
    }
}
```

### Common Errors

| Error | Cause | Resolution |
|-------|-------|------------|
| `File not found` | CSV path incorrect | Verify file location |
| `Missing header` | Malformed CSV | Check CSV structure |
| `Duplicate slug` | Slug exists | Append counter |
| `Image download failed` | URL unreachable | Skip image, log warning |
| `Field too long` | Data exceeds column | Truncate to max length |

---

## Statistics Output

### Example Output

```
┌────────────────┬───────┬────────────┐
│ Status         │ Count │ Percentage │
├────────────────┼───────┼────────────┤
│ Total          │ 5000  │ 100%       │
│ Success        │ 4500  │ 90.0%      │
│ Existing       │ 300   │ 6.0%       │
│ Skipped        │ 150   │ 3.0%       │
│ Error          │ 50    │ 1.0%       │
└────────────────┴───────┴────────────┘
```

---

## Performance Tips

### Memory Management

```php
// Flush every 50 records
if ($stats['success'] % 50 === 0) {
    $this->entityManager->flush();
    $this->entityManager->clear();
    
    // Clear caches to prevent memory leaks
    $this->categoryCache = [];
    $this->authorCache = [];
    $this->imageCache = [];
}
```

### Recommended Batch Sizes

| Dataset Size | Limit | Memory | Time |
|--------------|-------|--------|------|
| Small (< 1000) | 500 | ~100MB | ~5 min |
| Medium (1000-5000) | 500 | ~200MB | ~20 min |
| Large (> 5000) | 500 | ~300MB | ~40 min |

---

## Quality Checklist

### Pre-Import
- [ ] CSV files exist in migration_strategy/
- [ ] CSV structure matches expected schema
- [ ] Disk space available for images
- [ ] Database backup created

### During Import
- [ ] Monitor memory usage
- [ ] Check for errors in output
- [ ] Verify image downloads

### Post-Import
- [ ] Article count matches expected
- [ ] Categories created correctly
- [ ] Authors created with valid emails
- [ ] Images accessible via CDN
- [ ] External mappings complete

---

## Handoff Points

| Scenario | Next Agent | Action |
|----------|------------|--------|
| Import complete | `@import-validator` | Verify data integrity |
| Duplicates found | `@import-mapper` | Resolve duplicate entries |
| Missing images | `@cdn-manager` | Handle fallbacks |
| CSV parsing errors | Manual review | Check CSV format |

---

## References

- **Command**: `src/Command/Import/ImportCsvArticlesCommand.php`
- **Entity**: `src/Entity/ExternalArticleMapping.php`
- **Repository**: `src/Repository/ExternalArticleMappingRepository.php`
- **CSV Library**: `league/csv` (Composer)
