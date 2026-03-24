/* eslint-disable @next/next/no-img-element */
/**
 * Jest Setup File
 *
 * Runs after Jest is initialized but before tests run
 * Used for global test configuration and setup
 */

// Add custom jest matchers from @testing-library/jest-dom
import '@testing-library/jest-dom';

// Mock Next.js router
jest.mock('next/navigation', () => ({
  useRouter() {
    return {
      push: jest.fn(),
      replace: jest.fn(),
      prefetch: jest.fn(),
      back: jest.fn(),
      pathname: '/',
      query: {},
      asPath: '/',
    };
  },
  usePathname() {
    return '/';
  },
  useSearchParams() {
    return new URLSearchParams();
  },
  useParams() {
    return {};
  },
}));

// Mock next/image
jest.mock('next/image', () => ({
  __esModule: true,
  default: (props) => {
    // eslint-disable-next-line jsx-a11y/alt-text
    return <img {...props} />;
  },
}));

// Mock environment variables
process.env.NEXT_PUBLIC_API_URL = 'http://localhost:8081';
process.env.NEXT_PUBLIC_CDN_URL = 'http://localhost:8082';
process.env.NEXT_PUBLIC_SITE_URL = 'http://localhost:3005';
process.env.NEXT_PUBLIC_APP_NAME = 'Deschide News';

// Global test timeout
jest.setTimeout(10000);

// Suppress console errors in tests (optional)
// global.console = {
//   ...console,
//   error: jest.fn(),
//   warn: jest.fn(),
// };
