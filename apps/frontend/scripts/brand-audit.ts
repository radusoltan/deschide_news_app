#!/usr/bin/env tsx
/**
 * Brand Compliance Audit Script
 *
 * Validates that the codebase follows Deschide brand guidelines:
 * - No red text (#E92628)
 * - Mindaro used sparingly (max 10%)
 * - Only League Spartan + Poppins fonts
 * - Logo minimum size enforced
 *
 * Usage: pnpm brand-audit
 */

import fs from 'fs';
import path from 'path';

interface Violation {
  file: string;
  rule: string;
  message: string;
  line?: number;
  matches?: string[];
  count?: number;
}

// Patterns that indicate brand violations
const FORBIDDEN_PATTERNS = {
  // Red text is FORBIDDEN per brandbook section 1.2, 1.3
  redText: /(?:text-\[#E92628\]|text-red-cmyk|color:\s*#E92628|rgb\(\s*233,\s*38,\s*40\s*\))/gi,

  // Old red classes that should be replaced with deschide-tomato
  oldRedClasses: /(?:text-red-600|bg-red-600|border-red-600|hover:text-red-600|hover:bg-red-600)/gi,

  // Wrong fonts (anything that's not League Spartan, Poppins, or CSS variables)
  wrongFonts: /font-family:\s*(?!.*(?:League\s*Spartan|Poppins|var\(--font|inherit|sans-serif|system-ui))[^;}"']+/gi,

  // Mindaro overuse (more than 5 occurrences in a single file is suspicious)
  mindaroUsage: /(?:text-deschide-mindaro|text-brand-mindaro|bg-deschide-mindaro|#D4FB8C)/gi,

  // Logo size violations (looking for size props smaller than 'sm')
  logoSizeViolation: /Logo[^>]*size\s*=\s*["'](?:xs|tiny|micro)["']/gi,
};

// Allowed patterns (for false positive reduction)
const ALLOWED_PATTERNS = {
  // Comments explaining brand rules
  brandComments: /\/\/.*(?:brand|FORBIDDEN|NEVER|sparingly)/i,
  // CSS custom properties definitions
  cssVarDefinitions: /--color-(?:red-cmyk|mindaro)/,
};

async function getAllFiles(dir: string): Promise<string[]> {
  const files: string[] = [];

  try {
    const items = fs.readdirSync(dir);

    for (const item of items) {
      const fullPath = path.join(dir, item);
      const stat = fs.statSync(fullPath);

      // Skip directories we don't want to check
      if (stat.isDirectory()) {
        if (
          item.startsWith('.') ||
          item === 'node_modules' ||
          item === '.next' ||
          item === 'dist' ||
          item === 'coverage'
        ) {
          continue;
        }
        files.push(...await getAllFiles(fullPath));
      } else if (stat.isFile()) {
        // Only check relevant file types
        if (
          item.endsWith('.tsx') ||
          item.endsWith('.ts') ||
          item.endsWith('.css') ||
          item.endsWith('.scss')
        ) {
          files.push(fullPath);
        }
      }
    }
  } catch (error) {
    console.error(`Error reading directory ${dir}:`, error);
  }

  return files;
}

function isAllowedContext(content: string, match: string, index: number): boolean {
  // Get surrounding context (50 chars before and after)
  const start = Math.max(0, index - 50);
  const end = Math.min(content.length, index + match.length + 50);
  const context = content.substring(start, end);

  // Check if it's in a comment or definition
  for (const pattern of Object.values(ALLOWED_PATTERNS)) {
    if (pattern.test(context)) {
      return true;
    }
  }

  return false;
}

async function auditFiles(dir: string): Promise<Violation[]> {
  const files = await getAllFiles(dir);
  const violations: Violation[] = [];

  console.log(`\nScanning ${files.length} files...\n`);

  for (const file of files) {
    const content = fs.readFileSync(file, 'utf-8');
    const relativePath = path.relative(dir, file);

    // Check for red text (FORBIDDEN)
    let match;
    const redTextPattern = new RegExp(FORBIDDEN_PATTERNS.redText.source, 'gi');
    while ((match = redTextPattern.exec(content)) !== null) {
      if (!isAllowedContext(content, match[0], match.index)) {
        violations.push({
          file: relativePath,
          rule: 'RED_TEXT_FORBIDDEN',
          message: 'Text rosu (#E92628) este INTERZIS conform brandbook section 1.2',
          matches: [match[0]],
        });
      }
    }

    // Check for old red classes (should be replaced)
    const oldRedPattern = new RegExp(FORBIDDEN_PATTERNS.oldRedClasses.source, 'gi');
    const oldRedMatches: string[] = [];
    while ((match = oldRedPattern.exec(content)) !== null) {
      if (!isAllowedContext(content, match[0], match.index)) {
        oldRedMatches.push(match[0]);
      }
    }
    if (oldRedMatches.length > 0) {
      violations.push({
        file: relativePath,
        rule: 'OLD_RED_CLASSES',
        message: 'Clasele red-600 ar trebui inlocuite cu deschide-tomato',
        matches: [...new Set(oldRedMatches)],
        count: oldRedMatches.length,
      });
    }

    // Check for wrong fonts (only in CSS files)
    if (file.endsWith('.css') || file.endsWith('.scss')) {
      const fontPattern = new RegExp(FORBIDDEN_PATTERNS.wrongFonts.source, 'gi');
      while ((match = fontPattern.exec(content)) !== null) {
        violations.push({
          file: relativePath,
          rule: 'WRONG_FONTS',
          message: 'Doar League Spartan si Poppins sunt permise',
          matches: [match[0]],
        });
      }
    }

    // Check for Mindaro overuse
    const mindaroPattern = new RegExp(FORBIDDEN_PATTERNS.mindaroUsage.source, 'gi');
    const mindaroMatches: string[] = [];
    while ((match = mindaroPattern.exec(content)) !== null) {
      mindaroMatches.push(match[0]);
    }
    if (mindaroMatches.length > 5) {
      violations.push({
        file: relativePath,
        rule: 'MINDARO_OVERUSE',
        message: 'Mindaro folosit prea des (max 10% in design, ~5 instante/fisier recomandat)',
        count: mindaroMatches.length,
      });
    }

    // Check for logo size violations
    const logoPattern = new RegExp(FORBIDDEN_PATTERNS.logoSizeViolation.source, 'gi');
    while ((match = logoPattern.exec(content)) !== null) {
      violations.push({
        file: relativePath,
        rule: 'LOGO_SIZE_VIOLATION',
        message: 'Logo minimum size este 20px (sm). Dimensiunile xs/tiny/micro nu sunt permise.',
        matches: [match[0]],
      });
    }
  }

  return violations;
}

function printReport(violations: Violation[]): void {
  if (violations.length === 0) {
    console.log('=====================================');
    console.log('  BRAND COMPLIANCE: PASSED');
    console.log('=====================================');
    console.log('\nNo violations found. The codebase follows brand guidelines.');
    return;
  }

  console.log('=====================================');
  console.log('  BRAND COMPLIANCE: FAILED');
  console.log(`  Found ${violations.length} violation(s)`);
  console.log('=====================================\n');

  // Group by rule
  const byRule: Record<string, Violation[]> = {};
  for (const v of violations) {
    if (!byRule[v.rule]) byRule[v.rule] = [];
    byRule[v.rule].push(v);
  }

  for (const [rule, ruleViolations] of Object.entries(byRule)) {
    console.log(`\n${rule} (${ruleViolations.length} violation(s))`);
    console.log('-'.repeat(50));

    for (const v of ruleViolations) {
      console.log(`  File: ${v.file}`);
      console.log(`  ${v.message}`);
      if (v.matches && v.matches.length > 0) {
        console.log(`  Found: ${v.matches.slice(0, 3).join(', ')}${v.matches.length > 3 ? '...' : ''}`);
      }
      if (v.count) {
        console.log(`  Count: ${v.count} occurrences`);
      }
      console.log('');
    }
  }

  console.log('\n=====================================');
  console.log('  ACTION REQUIRED');
  console.log('=====================================');
  console.log('\nFix the violations above to ensure brand compliance.');
  console.log('\nQuick fixes:');
  console.log('  - Replace red-600 with deschide-tomato');
  console.log('  - Remove any #E92628 text colors');
  console.log('  - Reduce Mindaro usage to max 10%');
  console.log('  - Use Logo size="sm" or larger');
}

// Main execution
async function main(): Promise<void> {
  console.log('Deschide Brand Compliance Audit');
  console.log('================================\n');

  const frontendDir = path.resolve(__dirname, '..');
  console.log(`Scanning: ${frontendDir}`);

  const violations = await auditFiles(frontendDir);
  printReport(violations);

  // Exit with error code if violations found
  if (violations.length > 0) {
    process.exit(1);
  }
}

main().catch(console.error);
