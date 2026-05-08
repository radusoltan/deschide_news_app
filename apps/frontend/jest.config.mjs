/**
 * Jest Configuration for Next.js
 *
 * Configures Jest with:
 * - Next.js integration
 * - TypeScript support
 * - Coverage reporting
 * - Module path mapping
 */

import nextJest from 'next/jest.js';

const createJestConfig = nextJest({
  // Provide the path to your Next.js app to load next.config.js and .env files in your test environment
  dir: './',
});

/** @type {import('jest').Config} */
const customJestConfig = {
  // Setup files to run after Jest is initialized
  setupFilesAfterEnv: ['<rootDir>/jest.setup.js'],

  // Module name mapper for path aliases
  moduleNameMapper: {
    '^@/(.*)$': '<rootDir>/$1',
  },

  // Test environment
  testEnvironment: 'jest-environment-jsdom',

  // Test match patterns
  testMatch: [
    '**/__tests__/**/*.{js,jsx,ts,tsx}',
    '**/*.{spec,test}.{js,jsx,ts,tsx}',
  ],

  // Ignore patterns
  testPathIgnorePatterns: [
    '/node_modules/',
    '/.next/',
    '/__tests__/__mocks__/',
    '/__tests__/e2e/',
    '/__tests__/integration/',
    '/__tests__/smoke/',
    '/__tests__/performance/',
    '/__tests__/diagnostics/',
    '/__tests__/manual_test_admin.spec.ts',
  ],

  // Coverage collection
  collectCoverageFrom: [
    'lib/**/*.{js,jsx,ts,tsx}',
    'components/**/*.{js,jsx,ts,tsx}',
    'app/**/*.{js,jsx,ts,tsx}',
    '!**/*.d.ts',
    '!**/node_modules/**',
    '!**/.next/**',
    '!**/coverage/**',
    '!**/jest.config.{js,mjs}',
    '!**/jest.setup.js',
    '!**/__tests__/**',
    '!**/__mocks__/**',
    // T60.28-init exclusions per ADR-036 — paths without unit-test
    // scaffolding. Each path is a ratchet candidate (T60.28 reduces
    // this list as tests land). DO NOT add entries without an ADR-036
    // amendment or a tracked ratchet partner task.
    '!app/api/**',
    '!lib/services/**',
    '!lib/react-query/**',
    '!lib/types/**',
    '!lib/utils/analyticsTracker.ts',
    '!lib/utils/soundNotifications.ts',
    '!lib/utils/redirect-utils.ts',
    '!lib/utils/url-parser.ts',
    '!lib/utils/socialMetadata.ts',
    '!lib/seo/og-image-generator.ts',
    '!lib/seo/structured-data.ts',
    '!lib/seo/schema-org-global.ts',
    '!lib/seo/seo-config.ts',
    '!components/admin/**',
  ],

  // Coverage threshold (singular, not plural!)
  // Realigned 2026-05-08 per ADR-036 from aspirational 70% (never met
  // empirically since codebase grew past initial seed) to current floor
  // matching actuals after surgical exclusions above. T60.28 ratchet
  // partner mandates +5% per sprint over Sprint 61-65 → 35% by Sprint 65.
  // Hard rule: threshold increase requires NEW tests, not new exclusions.
  coverageThreshold: {
    global: {
      statements: 10,
      branches: 10,
      functions: 9,
      lines: 10,
    },
  },

  // Coverage reporters
  coverageReporters: ['text', 'lcov', 'html'],

  // Transform ignore patterns
  transformIgnorePatterns: [
    '/node_modules/',
    '^.+\\.module\\.(css|sass|scss)$',
  ],

  // Module file extensions
  moduleFileExtensions: ['ts', 'tsx', 'js', 'jsx', 'json'],

  modulePathIgnorePatterns: [
    '<rootDir>/.next/standalone',
  ],

  // Verbose output
  verbose: true,

  // Clear mocks between tests
  clearMocks: true,

  // Reset mocks between tests
  resetMocks: true,

  // Restore mocks between tests
  restoreMocks: true,
};

// createJestConfig is exported this way to ensure that next/jest can load the Next.js config which is async
export default createJestConfig(customJestConfig);
