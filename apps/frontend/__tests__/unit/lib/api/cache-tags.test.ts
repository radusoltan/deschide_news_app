/**
 * Cache Tags Regression Guard (T60.10)
 *
 * Static-analysis test asserting that every `next: { revalidate: N }` block
 * in lib/api/ also declares `tags: [ ... ]`. Without tags, fetch entries
 * are invisible to the /api/revalidate endpoint and can serve stale data
 * for the full revalidate window after a backend change.
 *
 * This test is a tripwire — if a future commit adds a new fetch without
 * tags, this test catches it before merge.
 */

import * as fs from 'node:fs';
import * as path from 'node:path';

const LIB_API_DIR = path.resolve(__dirname, '../../../../lib/api');

const FILES_REQUIRING_TAGS = [
  'categories.ts',
  'tags.ts',
  'topics.ts',
  'video-shows.ts',
  'special-articles.ts',
  'sitemap-data.ts',
  'slug-lookup.ts',
  'statistics.ts',
  'translations.ts',
  'articles.ts',
  'important-articles.ts',
];

/**
 * Find all `next: {` block starts and assert each has a matching `tags: [`
 * on a line within the same block (before the closing brace).
 */
function findUntaggedFetchBlocks(source: string): Array<{ line: number; preview: string }> {
  const lines = source.split('\n');
  const offences: Array<{ line: number; preview: string }> = [];

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    // Match `next: {` opening (multi-line) or `next: { ... }` single-line
    if (!/\bnext:\s*\{/.test(line)) continue;

    // Single-line variant: check the same line for `tags:`
    if (/\}/.test(line.replace(/^[^{]*\{/, ''))) {
      if (!/tags\s*:\s*\[/.test(line)) {
        offences.push({ line: i + 1, preview: line.trim() });
      }
      continue;
    }

    // Multi-line: scan forward up to 12 lines for the closing brace
    let foundTags = false;
    for (let j = i + 1; j < Math.min(i + 12, lines.length); j++) {
      if (/tags\s*:\s*\[/.test(lines[j])) {
        foundTags = true;
        break;
      }
      if (/^\s*\},?\s*$/.test(lines[j])) {
        break;
      }
    }
    if (!foundTags) {
      offences.push({ line: i + 1, preview: line.trim() });
    }
  }

  return offences;
}

describe('lib/api cache tags coherence (T60.10)', () => {
  it.each(FILES_REQUIRING_TAGS)(
    '%s declares tags: [...] alongside every revalidate: N',
    (file) => {
      const filePath = path.join(LIB_API_DIR, file);
      expect(fs.existsSync(filePath)).toBe(true);

      const source = fs.readFileSync(filePath, 'utf-8');
      const offences = findUntaggedFetchBlocks(source);

      if (offences.length > 0) {
        const formatted = offences
          .map((o) => `  L${o.line}: ${o.preview}`)
          .join('\n');
        throw new Error(
          `Found ${offences.length} untagged fetch block(s) in ${file}:\n${formatted}\n` +
            `\nFix: add \`tags: [CACHE_TAGS.<entity>, ...]\` to each next: { ... } block. ` +
            `Use constants from @/lib/data/cache-config — do not inline string literals.`,
        );
      }
    },
  );

  it('every checked file imports CACHE_TAGS from data/cache-config', () => {
    const offenders: string[] = [];
    for (const file of FILES_REQUIRING_TAGS) {
      const source = fs.readFileSync(path.join(LIB_API_DIR, file), 'utf-8');
      // articles.ts and important-articles.ts predate T60.10 and use string
      // literals like `tags: ['articles']`. They are still tagged correctly,
      // so allow the literal pattern in those two files only.
      if (file === 'articles.ts' || file === 'important-articles.ts') continue;

      if (!/from\s+['"][^'"]*cache-config['"]/.test(source)) {
        offenders.push(file);
      }
    }
    expect(offenders).toEqual([]);
  });
});
