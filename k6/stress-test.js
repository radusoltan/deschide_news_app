import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';
import { CONFIG, ENDPOINTS, getRandomLocale } from './config.js';

// Custom metrics
const articlesDuration = new Trend('articles_duration');
const categoriesDuration = new Trend('categories_duration');
const errorRate = new Rate('error_rate');
const totalRequests = new Counter('total_requests');
const serverErrors = new Counter('server_errors');

// Stress test configuration - Find breaking point
export const options = {
  stages: [
    { duration: '2m', target: 100 },
    { duration: '2m', target: 200 },
    { duration: '2m', target: 300 },
    { duration: '3m', target: 500 },
    { duration: '3m', target: 500 },
    { duration: '2m', target: 0 },
  ],
  thresholds: {
    'http_req_duration': ['p(95)<2000', 'p(99)<5000'],
    'http_req_failed': ['rate<0.15'],
    'error_rate': ['rate<0.15'],
  },
};

export default function () {
  const locale = getRandomLocale();
  const headers = {
    'Accept-Language': locale,
    'Accept': 'application/ld+json',
  };

  const articlesUrl = CONFIG.BACKEND_URL + ENDPOINTS.articles + '?page=1&itemsPerPage=30';
  const articlesRes = http.get(articlesUrl, { headers, timeout: '30s' });
  totalRequests.add(1);

  const articlesCheck = check(articlesRes, {
    'articles status is not 500': (r) => r.status !== 500,
    'articles responded': (r) => r.status !== 0,
  });

  if (articlesRes.status >= 500) {
    serverErrors.add(1);
  }

  articlesDuration.add(articlesRes.timings.duration);
  errorRate.add(!articlesCheck);

  sleep(Math.random() * 2 + 0.5);

  const categoriesUrl = CONFIG.BACKEND_URL + ENDPOINTS.categories;
  const categoriesRes = http.get(categoriesUrl, { headers, timeout: '30s' });
  totalRequests.add(1);

  const categoriesCheck = check(categoriesRes, {
    'categories status is not 500': (r) => r.status !== 500,
    'categories responded': (r) => r.status !== 0,
  });

  if (categoriesRes.status >= 500) {
    serverErrors.add(1);
  }

  categoriesDuration.add(categoriesRes.timings.duration);
  errorRate.add(!categoriesCheck);

  sleep(Math.random() * 2 + 0.5);

  const randomArticleId = Math.floor(Math.random() * 1000) + 1;
  const articleUrl = CONFIG.BACKEND_URL + '/api/articles/' + randomArticleId;
  const articleRes = http.get(articleUrl, { headers, timeout: '30s' });
  totalRequests.add(1);

  const articleCheck = check(articleRes, {
    'article status is not 500': (r) => r.status !== 500,
    'article responded': (r) => r.status !== 0,
  });

  if (articleRes.status >= 500) {
    serverErrors.add(1);
  }

  errorRate.add(!articleCheck);

  sleep(Math.random() * 2 + 0.5);

  const importantUrl = CONFIG.BACKEND_URL + ENDPOINTS.important;
  const importantRes = http.get(importantUrl, { headers, timeout: '30s' });
  totalRequests.add(1);

  const importantCheck = check(importantRes, {
    'important status is not 500': (r) => r.status !== 500,
    'important responded': (r) => r.status !== 0,
  });

  if (importantRes.status >= 500) {
    serverErrors.add(1);
  }

  errorRate.add(!importantCheck);

  sleep(Math.random() * 1 + 0.5);
}
