/* eslint-disable @next/next/no-img-element */
/**
 * Jest Setup File
 *
 * Runs after Jest is initialized but before tests run
 * Used for global test configuration and setup
 */

// Add custom jest matchers from @testing-library/jest-dom
import '@testing-library/jest-dom';
import { TextEncoder, TextDecoder } from 'util';
import { ReadableStream, TransformStream, WritableStream } from 'stream/web';

class MockHeaders {
  constructor(init = {}) {
    this.map = new Map(
      Object.entries(init).map(([key, value]) => [key.toLowerCase(), value])
    );
  }

  get(name) {
    return this.map.get(name.toLowerCase()) ?? null;
  }

  set(name, value) {
    this.map.set(name.toLowerCase(), value);
  }

  append(name, value) {
    this.set(name, value);
  }

  has(name) {
    return this.map.has(name.toLowerCase());
  }

  delete(name) {
    this.map.delete(name.toLowerCase());
  }
}

class MockRequest {
  constructor(input = '', init = {}) {
    this.url = typeof input === 'string' ? input : input?.url || '';
    this.method = init.method || 'GET';
    this.headers = new MockHeaders(init.headers);
  }

  clone() {
    return new MockRequest(this.url, {
      method: this.method,
      headers: Object.fromEntries(this.headers.map.entries()),
    });
  }
}

class MockResponse {
  constructor(body = null, init = {}) {
    this.body = body;
    this.status = init.status || 200;
    this.ok = this.status >= 200 && this.status < 300;
    this.headers = new MockHeaders(init.headers);
  }

  async json() {
    return typeof this.body === 'string' ? JSON.parse(this.body) : this.body;
  }

  async text() {
    if (typeof this.body === 'string') {
      return this.body;
    }

    return JSON.stringify(this.body);
  }
}

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
process.env.NEXT_PUBLIC_MERCURE_URL = 'http://localhost:3000/.well-known/mercure';
process.env.SESSION_SECRET = 'test-session-secret';

if (typeof global.TextEncoder === 'undefined') {
  global.TextEncoder = TextEncoder;
}

if (typeof global.TextDecoder === 'undefined') {
  global.TextDecoder = TextDecoder;
}

if (typeof global.ReadableStream === 'undefined') {
  global.ReadableStream = ReadableStream;
}

if (typeof global.WritableStream === 'undefined') {
  global.WritableStream = WritableStream;
}

if (typeof global.TransformStream === 'undefined') {
  global.TransformStream = TransformStream;
}

if (typeof global.Headers === 'undefined') {
  global.Headers = MockHeaders;
}

if (typeof global.Request === 'undefined') {
  global.Request = MockRequest;
}

if (typeof global.Response === 'undefined') {
  global.Response = MockResponse;
}

// Global test timeout
jest.setTimeout(10000);

// Suppress console errors in tests (optional)
// global.console = {
//   ...console,
//   error: jest.fn(),
//   warn: jest.fn(),
// };
