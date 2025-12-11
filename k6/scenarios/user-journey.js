import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';
import { CONFIG, ENDPOINTS, getRandomLocale, getThinkTime } from '../config.js';

// Custom metrics for user journey
const journeyDuration = new Trend('journey_duration');
const errorRate = new Rate('error_rate');
const totalRequests = new Counter('total_requests');
const successfulJourneys = new Counter('successful_journeys');

export const options = {
  vus: 20,
  duration: '10m',
  thresholds: {
    'journey_duration': ['p(95)<5000'],
    'error_rate': ['rate<0.05'],
    'http_req_failed': ['rate<0.05'],
  },
};

export default function () {
  const startTime = Date.now();
  let journeySuccess = true;
  let locale = getRandomLocale();

  const headers = {
    'Accept-Language': locale,
    'Accept': 'application/ld+json',
  };

  // Step 1: Visit homepage
  const homepageUrl = CONFIG.FRONTEND_URL + '/' + locale;
  const homepageRes = http.get(homepageUrl, {
    headers: {
      'Accept': 'text/html',
      'Accept-Language': locale,
    },
  });
  totalRequests.add(1);

  if (!check(homepageRes, { 'homepage loaded': (r) => r.status === 200 })) {
    journeySuccess = false;
    errorRate.add(1);
  }

  sleep(getThinkTime());

  // Step 2: Browse categories
  const categoriesUrl = CONFIG.BACKEND_URL + ENDPOINTS.categories;
  const categoriesRes = http.get(categoriesUrl, { headers });
  totalRequests.add(1);

  if (!check(categoriesRes, { 'categories loaded': (r) => r.status === 200 })) {
    journeySuccess = false;
    errorRate.add(1);
  }

  sleep(getThinkTime());

  // Step 3: Get articles from a category
  const articlesUrl = CONFIG.BACKEND_URL + ENDPOINTS.articles + '?page=1&itemsPerPage=30';
  const articlesRes = http.get(articlesUrl, { headers });
  totalRequests.add(1);

  let articles = [];
  if (check(articlesRes, { 'articles loaded': (r) => r.status === 200 })) {
    try {
      const articlesData = JSON.parse(articlesRes.body);
      articles = articlesData['hydra:member'] || [];
    } catch (e) {
      journeySuccess = false;
      errorRate.add(1);
    }
  } else {
    journeySuccess = false;
    errorRate.add(1);
  }

  sleep(getThinkTime());

  // Step 4: Read 2-3 articles
  const articlesToRead = Math.min(3, articles.length);
  for (let i = 0; i < articlesToRead; i++) {
    if (articles[i]) {
      const articleUrl = CONFIG.BACKEND_URL + '/api/articles/' + articles[i].id;
      const articleRes = http.get(articleUrl, { headers });
      totalRequests.add(1);

      if (!check(articleRes, { 'article loaded': (r) => r.status === 200 })) {
        journeySuccess = false;
        errorRate.add(1);
      }

      sleep(getThinkTime() * 2);
    }
  }

  // Step 5: Switch language
  const newLocale = CONFIG.LOCALES[Math.floor(Math.random() * CONFIG.LOCALES.length)];
  const newHomepageUrl = CONFIG.FRONTEND_URL + '/' + newLocale;
  const newHomepageRes = http.get(newHomepageUrl, {
    headers: {
      'Accept': 'text/html',
      'Accept-Language': newLocale,
    },
  });
  totalRequests.add(1);

  if (!check(newHomepageRes, { 'new locale homepage loaded': (r) => r.status === 200 })) {
    journeySuccess = false;
    errorRate.add(1);
  }

  const endTime = Date.now();
  journeyDuration.add(endTime - startTime);

  if (journeySuccess) {
    successfulJourneys.add(1);
  }

  sleep(getThinkTime());
}
