# Archive Content Processing Command

## Overview

The `app:archive:process-content` command processes legacy articles from Newscoop and Beta databases, transforming proprietary shortcodes into modern HTML.

**Created**: 2025-12-15
**Command Path**: `src/Command/Archive/ProcessArchiveContentCommand.php`
**Service**: `App\Service\Archive\ContentProcessor`

## Features

### Shortcode Transformations

The command transforms the following legacy shortcodes:

| Legacy Shortcode | Modern HTML | Example |
|-----------------|-------------|---------|
| `<!** Image X>` | `<figure><img></figure>` | Full image with optional caption, alignment |
| `<!** Link Internal ...>` | `<a href="/arhiva/article/123">` | Internal article links |
| `<!** Title>` | `<h3 class="article-subheading">` | Subheadings within articles |
| `<!-- Snippet X -->` | Removed | Deprecated snippet references |

### Key Capabilities

✅ **Batch Processing** - Process articles in batches with `--limit` and `--offset`
✅ **Dry Run Mode** - Preview transformations without saving (`--dry-run`)
✅ **JSON Export** - Save processed content to JSON files for review (`--save-processed`)
✅ **Sample Display** - Show before/after examples (`--show-samples`)
✅ **Filtered Processing** - Process only articles with shortcodes (`--only-with-shortcodes`)
✅ **Statistics** - Detailed metrics on transformations
✅ **Multi-Source** - Support for both Newscoop and Beta databases
✅ **Progress Bar** - Real-time processing progress
✅ **Error Handling** - Graceful error handling with logging

## Usage

### Basic Commands

```bash
# Process 100 articles from Newscoop (dry run)
symfony console app:archive:process-content --source=newscoop --limit=100 --dry-run

# Process articles with shortcodes only and show samples
symfony console app:archive:process-content \
  --source=newscoop \
  --limit=50 \
  --only-with-shortcodes \
  --show-samples \
  --dry-run

# Export processed content to JSON
symfony console app:archive:process-content \
  --source=newscoop \
  --limit=1000 \
  --only-with-shortcodes \
  --save-processed=/tmp/archive_export

# Process all sources (newscoop + beta)
symfony console app:archive:process-content \
  --source=all \
  --only-with-shortcodes \
  --dry-run
```

### Options Reference

| Option | Shortcut | Default | Description |
|--------|----------|---------|-------------|
| `--source` | `-s` | `all` | Source database: `newscoop`, `beta`, or `all` |
| `--limit` | `-l` | (none) | Limit number of articles to process |
| `--offset` | `-o` | `0` | Offset for resuming batch processing |
| `--dry-run` | `-d` | (flag) | Preview mode - no saving |
| `--save-processed` | (none) | (none) | Directory path for JSON export |
| `--language` | (none) | `2` | Language ID (2=Romanian, 1=English, etc.) |
| `--show-samples` | (none) | (flag) | Display before/after transformation samples |
| `--only-with-shortcodes` | (none) | (flag) | Filter to articles containing shortcodes |
| `--no-interaction` | `-n` | (flag) | Skip confirmation prompts |

## Database Schema

### Newscoop Database

**Articles Table:**
- `Number` - Article ID (primary key)
- `Name` - Article title
- `IdLanguage` - Language identifier (2=Romanian)
- `Published` - Publication status ('Y'/'N')
- `PublishDate` - Publication timestamp

**Xstiri Table (Article Content):**
- `NrArticle` - Foreign key to Articles.Number
- `IdLanguage` - Language identifier
- `FTitlu` - Full title
- `Fsubtitlu` - Subtitle
- `Flead` - Lead/excerpt
- `FContinut` - Article content (contains shortcodes)

**ArticleImages Table:**
- `NrArticle` - Foreign key to Articles.Number
- `Number` - Position/attachment number
- `IdImage` - Foreign key to Images.Id

**Images Table:**
- `Id` - Image ID
- `ImageFileName` - Physical filename
- `Description` - Alt text
- `width`, `height` - Dimensions
- `ContentType` - MIME type

### Beta Database

The Beta database is expected to have a similar structure to Newscoop. If the `Xstiri` table doesn't exist, processing will fail gracefully with an error message.

## Statistics

The command tracks and displays detailed statistics:

### Per-Source Statistics
- Images Processed
- Images Not Found
- Internal Links Processed
- Broken Links
- Titles Converted
- Snippets Removed

### Global Statistics
- Total Articles Fetched
- Articles With Shortcodes
- Articles Processed
- Success Rates (%)
- Errors

## Output Examples

### Console Output

```
🔄 Archive Content Processor
============================

⚙️  Configuration
----------------
 Source        NEWSCOOP
 Language ID   2 (Romanian)
 Limit         100
 Mode          💾 LIVE (will process)
 Filter        ✅ Only articles with shortcodes

📂 Processing: NEWSCOOP
-----------------------
Found 100 articles
 100/100 [============================] 100%

📊 Statistics: NEWSCOOP
-----------------------
+------------------+-------+
| Metric           | Count |
+------------------+-------+
| Images Processed | 245   |
| Images Not Found | 3     |
| Links Processed  | 12    |
+------------------+-------+

✅ Image success rate: 98.79%
✅ Link success rate: 100.00%
```

### JSON Export Format

```json
{
  "metadata": {
    "source": "newscoop",
    "processed_at": "2025-12-15 12:16:35",
    "total_articles": 100,
    "articles_with_changes": 87
  },
  "articles": [
    {
      "article_number": 14,
      "title": "Article Title",
      "language_id": 2,
      "published_date": "2016-08-15 13:04:28",
      "has_shortcodes": true,
      "shortcode_counts": {
        "images": 2,
        "internal_links": 0,
        "titles": 0,
        "snippets": 0
      },
      "has_changes": true,
      "original_length": 1926,
      "processed_length": 2082,
      "source": "newscoop"
    }
  ]
}
```

## Workflow Recommendations

### 1. Initial Analysis (Dry Run)
```bash
# Preview processing without changes
symfony console app:archive:process-content \
  --source=newscoop \
  --limit=100 \
  --only-with-shortcodes \
  --show-samples \
  --dry-run
```

**Purpose**: Understand the scope and verify transformations are correct.

### 2. Small Batch Export
```bash
# Export a small batch for manual review
mkdir -p /var/www/archive_export
symfony console app:archive:process-content \
  --source=newscoop \
  --limit=1000 \
  --only-with-shortcodes \
  --save-processed=/var/www/archive_export
```

**Purpose**: Review JSON output and validate transformations.

### 3. Large-Scale Processing
```bash
# Process in batches of 5000
for i in {0..3}; do
  offset=$((i * 5000))
  symfony console app:archive:process-content \
    --source=newscoop \
    --limit=5000 \
    --offset=$offset \
    --only-with-shortcodes \
    --save-processed=/var/www/archive_export \
    --no-interaction
  sleep 5
done
```

**Purpose**: Process all ~16,182 articles with shortcodes in manageable batches.

### 4. Import to New Database
After processing and exporting to JSON:
1. Review JSON files in export directory
2. Create import command to read JSON and create new Article entities
3. Run import with proper validation and error handling

## Expected Processing Volume

Based on database analysis:

| Source | Articles with Shortcodes | Est. Processing Time* |
|--------|-------------------------|---------------------|
| Newscoop | ~16,182 | ~32 minutes |
| Beta | ~4,046 | ~8 minutes |
| **Total** | **~20,228** | **~40 minutes** |

*Estimated at 500 articles/minute (depends on hardware and DB connection speed)

## Performance Considerations

- **Batch Size**: Use `--limit=5000` for optimal performance
- **Database Load**: Queries are optimized with proper JOINs and indexes
- **Memory Usage**: ~6MB per batch (monitored by progress bar)
- **Image Cache**: ContentProcessor caches image lookups to reduce DB queries
- **Progress Tracking**: Use `--offset` to resume interrupted processing

## Error Handling

The command handles errors gracefully:

- **Database Connection Errors**: Logged with context, processing continues for other sources
- **Missing Images**: Logged, HTML comment inserted: `<!-- Image X not found -->`
- **Broken Links**: Text-only fallback, logged for review
- **Malformed Shortcodes**: Skipped with warning

All errors are logged to `var/log/dev.log` with full context.

## Troubleshooting

### "Table 'Xstiri' doesn't exist"
**Solution**: The Beta database may have a different schema. Process only Newscoop:
```bash
symfony console app:archive:process-content --source=newscoop
```

### "Cannot autowire service"
**Solution**: Clear Symfony cache:
```bash
symfony console cache:clear
```

### "Failed to generate JWT token"
**Solution**: This command does NOT require JWT authentication. The error suggests a different command or service issue.

### "Out of memory"
**Solution**: Reduce batch size:
```bash
symfony console app:archive:process-content --limit=1000
```

## Related Services

- **ContentProcessor**: `src/Service/Archive/ContentProcessor.php`
  - Core transformation logic
  - Regex pattern matching
  - Image/link lookups
  - HTML generation

- **Database Connections**:
  - `doctrine.dbal.newscoop_connection` (MySQL)
  - `doctrine.dbal.beta_deschide_connection` (MySQL)

## Future Enhancements

Potential improvements for future iterations:

- [ ] Direct database import (bypass JSON export)
- [ ] Parallel processing with Symfony Messenger
- [ ] Image migration (download from legacy servers)
- [ ] Link resolution (map old article IDs to new slugs)
- [ ] Automatic testing with sample articles
- [ ] Progress persistence (resume from last processed article)
- [ ] Webhook notifications for long-running jobs

## Testing

```bash
# Unit test for ContentProcessor
vendor/bin/phpunit tests/Service/Archive/ContentProcessorTest.php

# Integration test for command
symfony console app:archive:process-content --limit=1 --dry-run
```

## See Also

- [ContentProcessor Documentation](./SERVICE_CONTENT_PROCESSOR.md)
- [Archive Import Strategy](./ARCHIVE_IMPORT_STRATEGY.md)
- [Database Migration Plan](./DATABASE_MIGRATION_PLAN.md)

---

**Last Updated**: 2025-12-15
**Maintainer**: Backend Team
**Status**: ✅ Production Ready
