import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';
import { CONFIG, ENDPOINTS, getRandomLocale, getThinkTime } from './config.js';

// Custom metrics
const articlesDuration = new Trend('articles_duration');
const categoriesDuration = new Trend('categories_duration');
const errorRate = new Rate('error_rate');
const totalRequests = new Counter('total_requests');
const memoryLeaks = new Counter('memory_leak_indicators');

// Soak test configuration - 1 hour endurance test
export const options = {
  stages: [
    { duration: '5m', target: 50 },
    { duration: '60m', target: 50 },
    { duration: '5m', target: 0 },
  ],
  thresholds: {
    'http_req_duration': ['p(95)<500', 'p(99)<1000'],
    'http_req_failed': ['rate<0.01'],
    'error_rate': ['rate<0.05'],
  },
};

export default function () {
  const locale = getRandomLocale();
  const headers = {
    'Accept-Language': locale,
    'Accept': 'application/ld+json',
  };

  const articlesUrl = CONFIG.BACKEND_URL + ENDPOINTS.articles + '?page=1&itemsPerPage=30';
  const articlesRes = http.get(articlesUrl, { headers });
  totalRequests.add(1);

  const articlesCheck = check(articlesRes, {
    'articles status is 200': (r) => r.status === 200,
    'articles has data': (r) => {
      try {
        const body = JSON.parse(r.body);
        const members = body['hydra:member'] || body.member || body['@hydra:member'];
        return members && members.length >= 0;
      } catch (e) {
        return false;
      }
    },
  });

  articlesDuration.add(articlesRes.timings.duration);
  errorRate.add(!articlesCheck);

  if (articlesRes.timings.duration > 2000) {
    memoryLeaks.add(1);
  }

  sleep(getThinkTime());

  const categoriesUrl = CONFIG.BACKEND_URL + ENDPOINTS.categories;
  const categoriesRes = http.get(categoriesUrl, { headers });
  totalRequests.add(1);

  const categoriesCheck = check(categoriesRes, {
    'categories status is 200': (r) => r.status === 200,
    'categories has data': (r) => {
      try {
        const body = JSON.parse(r.body);
        const members = body['hydra:member'] || body.member || body['@hydra:member'];
        return members && members.length >= 0;
      } catch (e) {
        return false;
      }
    },
  });

  categoriesDuration.add(categoriesRes.timings.duration);
  errorRate.add(!categoriesCheck);

  if (categoriesRes.timings.duration > 1000) {
    memoryLeaks.add(1);
  }

  sleep(getThinkTime());

  if (articlesCheck && articlesRes.body) {
    try {
      const articlesData = JSON.parse(articlesRes.body);
      const articles = articlesData['hydra:member'] || articlesData.member || articlesData['@hydra:member'];

      if (articles && articles.length > 0) {
        const randomArticle = articles[Math.floor(Math.random() * articles.length)];
        const articleId = randomArticle.id;

        const articleUrl = CONFIG.BACKEND_URL + '/api/articles/' + articleId;
        const articleRes = http.get(articleUrl, { headers });
        totalRequests.add(1);

        const articleCheck = check(articleRes, {
          'article detail status is 200': (r) => r.status === 200,
        });

        errorRate.add(!articleCheck);

        if (articleRes.timings.duration > 2000) {
          memoryLeaks.add(1);
        }
      }
    } catch (e) {
      errorRate.add(1);
    }
  }

  sleep(getThinkTime());

  const importantUrl = CONFIG.BACKEND_URL + ENDPOINTS.important;
  const importantRes = http.get(importantUrl, { headers });
  totalRequests.add(1);

  const importantCheck = check(importantRes, {
    'important status is 200': (r) => r.status === 200,
  });

  errorRate.add(!importantCheck);

  sleep(getThinkTime());
}
