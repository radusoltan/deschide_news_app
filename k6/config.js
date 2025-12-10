// Shared configuration for k6 tests
export const CONFIG = {
  BACKEND_URL: __ENV.BACKEND_URL || 'http://127.0.0.1:8081',
  FRONTEND_URL: __ENV.FRONTEND_URL || 'http://localhost:3005',
  LOCALES: ['ro', 'en', 'ru'],
};

export const ENDPOINTS = {
  articles: '/api/articles',
  categories: '/api/categories',
  authors: '/api/authors',
  images: '/api/images',
  important: '/api/important_articles_lists',
  health: '/api/health',
};

export const THRESHOLDS = {
  // 95th percentile should be under 500ms, 99th under 1s
  http_req_duration: ['p(95)<500', 'p(99)<1000'],
  // Error rate should be less than 1%
  http_req_failed: ['rate<0.01'],
};

// Helper function to get random locale
export function getRandomLocale() {
  return CONFIG.LOCALES[Math.floor(Math.random() * CONFIG.LOCALES.length)];
}

// Helper function to sleep between requests (simulate think time)
export function getThinkTime() {
  return Math.random() * 3 + 1; // 1-4 seconds
}
