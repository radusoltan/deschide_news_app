# Archive Content Processing - Quick Start Guide

## TL;DR

Transform legacy Newscoop shortcodes to modern HTML.

```bash
# Quick preview (recommended first step)
symfony console app:archive:process-content \
  --source=newscoop \
  --limit=10 \
  --only-with-shortcodes \
  --show-samples \
  --dry-run
```

## Common Use Cases

### 1. Preview Transformations (Dry Run)
```bash
symfony console app:archive:process-content \
  --source=newscoop \
  --limit=100 \
  --only-with-shortcodes \
  --show-samples \
  --dry-run
```

**Output**: Statistics + 3 before/after samples, no database changes.

### 2. Export to JSON for Review
```bash
mkdir -p /tmp/archive_export

symfony console app:archive:process-content \
  --source=newscoop \
  --limit=1000 \
  --only-with-shortcodes \
  --save-processed=/tmp/archive_export \
  --no-interaction
```

**Output**: JSON files in `/tmp/archive_export/processed_newscoop_*.json`

### 3. Process All Articles with Shortcodes
```bash
# Process in batches to avoid memory issues
symfony console app:archive:process-content \
  --source=newscoop \
  --only-with-shortcodes \
  --save-processed=/var/www/archive_processed \
  --no-interaction
```

**Expected**: ~16,182 articles, ~30-40 minutes

### 4. Resume from Offset
```bash
# If processing was interrupted, resume from article 5000
symfony console app:archive:process-content \
  --source=newscoop \
  --offset=5000 \
  --limit=5000 \
  --only-with-shortcodes \
  --save-processed=/var/www/archive_processed \
  --no-interaction
```

### 5. Batch Processing Script
```bash
#!/bin/bash
# Process all articles in batches of 5000

OUTPUT_DIR="/var/www/archive_processed"
mkdir -p "$OUTPUT_DIR"

for i in {0..3}; do
  offset=$((i * 5000))
  echo "Processing batch $((i+1))/4 (offset: $offset)..."

  symfony console app:archive:process-content \
    --source=newscoop \
    --limit=5000 \
    --offset=$offset \
    --only-with-shortcodes \
    --save-processed="$OUTPUT_DIR" \
    --no-interaction

  echo "Batch $((i+1)) complete. Waiting 5 seconds..."
  sleep 5
done

echo "All batches processed! Check $OUTPUT_DIR for results."
```

Save as `scripts/process_archive_batch.sh` and run:
```bash
chmod +x scripts/process_archive_batch.sh
./scripts/process_archive_batch.sh
```

## What Gets Transformed?

### Image Shortcodes
**Before:**
```html
<!** Image 3 align="middle" width="700" >
```

**After:**
```html
<figure class="figure mx-auto">
  <img src="/images/alpha/cms-image-000000160.jpg"
       alt=""
       loading="lazy"
       width="700"
       height="1080">
</figure>
```

### Internal Links
**Before:**
```html
<!** Link Internal IdPublication=1&IdLanguage=2&NrArticle=123>
  Read more
<!** EndLink>
```

**After:**
```html
<a href="/arhiva/article/123">Read more</a>
```

### Subheadings
**Before:**
```html
<!** Title>Important Section<!** EndTitle>
```

**After:**
```html
<h3 class="article-subheading">Important Section</h3>
```

### Snippets
**Before:**
```html
<!-- Snippet 42 -->
```

**After:**
```html
(removed)
```

## Options Cheat Sheet

| What You Want | Command Options |
|---------------|----------------|
| Just preview, no changes | `--dry-run` |
| Show before/after examples | `--show-samples` |
| Save to JSON files | `--save-processed=/path/to/dir` |
| Only articles with shortcodes | `--only-with-shortcodes` |
| Process 100 articles | `--limit=100` |
| Start from article 500 | `--offset=500` |
| Process Newscoop only | `--source=newscoop` |
| Process Beta only | `--source=beta` |
| Process both databases | `--source=all` |
| Skip confirmation prompt | `--no-interaction` |

## Expected Results

For **Newscoop database** (Romanian articles with shortcodes):

```
📊 Final Statistics
-------------------
Total Articles Fetched:   16,182
Articles With Shortcodes: 16,182 (100%)
Articles Processed:       16,182
Images Processed:         ~35,000-40,000
Internal Links:           ~500-1,000
Titles Converted:         ~200-500
✅ Image success rate:    ~98-99%
✅ Link success rate:     ~100%
```

## Troubleshooting

### Command not found
```bash
# Clear cache
symfony console cache:clear

# List available commands
symfony console list app:archive
```

### Out of memory
```bash
# Reduce batch size
symfony console app:archive:process-content --limit=1000
```

### Beta database error
```bash
# Process only Newscoop
symfony console app:archive:process-content --source=newscoop
```

### Review JSON output
```bash
# Check exported files
ls -lh /tmp/archive_export/

# View JSON (pretty-printed)
cat /tmp/archive_export/processed_newscoop_*.json | jq '.metadata'

# Count articles with changes
cat /tmp/archive_export/processed_newscoop_*.json | \
  jq '.articles[] | select(.has_changes == true)' | \
  jq -s 'length'
```

## Next Steps After Processing

1. **Review JSON Output**
   ```bash
   # Check statistics in metadata
   jq '.metadata' /path/to/processed_newscoop_*.json

   # Count shortcode types
   jq '.articles[].shortcode_counts' /path/to/*.json | jq -s 'add'
   ```

2. **Validate Sample Articles**
   - Open JSON files in editor
   - Compare original vs processed content
   - Check image URLs are correct
   - Verify links point to valid paths

3. **Create Import Command** (next phase)
   - Read processed JSON files
   - Create new Article entities
   - Map to new database schema
   - Handle image migration
   - Update article relationships

4. **Import to Production**
   - Run migration in staging first
   - Validate data integrity
   - Test frontend rendering
   - Deploy to production

## Performance Tips

- Use `--only-with-shortcodes` to skip articles without transformations
- Process in batches of 5000 for optimal performance
- Use `--no-interaction` in scripts to avoid prompts
- Monitor memory usage with `top` or `htop` during processing
- Run during off-peak hours for large batches

## Help

```bash
# Full command documentation
symfony console app:archive:process-content --help

# Check command version and options
symfony console list app:archive
```

---

**Quick Reference**: For full documentation, see [ARCHIVE_CONTENT_PROCESSING.md](./ARCHIVE_CONTENT_PROCESSING.md)
