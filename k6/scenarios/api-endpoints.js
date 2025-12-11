import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';
import { CONFIG, ENDPOINTS, getRandomLocale } from '../config.js';

// Custom metrics for API testing
const apiDuration = new Trend('api_duration');
const errorRate = new Rate('error_rate');
const totalRequests = new Counter('total_requests');

export const options = {
  vus: 50,
  duration: '5m',
  thresholds: {
    'api_duration': ['p(95)<300', 'p(99)<500'],
    'error_rate': ['rate<0.01'],
    'http_req_failed': ['rate<0.01'],
  },
};

export default function () {
  const locale = getRandomLocale();
  const headers = {
    'Accept-Language': locale,
    'Accept': 'application/ld+json',
  };

  const endpoints = [
    { name: 'articles', url: ENDPOINTS.articles },
    { name: 'categories', url: ENDPOINTS.categories },
    { name: 'authors', url: ENDPOINTS.authors },
    { name: 'images', url: ENDPOINTS.images },
    { name: 'important', url: ENDPOINTS.important },
  ];

  endpoints.forEach((endpoint) => {
    const url = CONFIG.BACKEND_URL + endpoint.url;
    const res = http.get(url, { headers });
    totalRequests.add(1);

    const checkResult = check(res, {
      [endpoint.name + ' status is 200']: (r) => r.status === 200,
      [endpoint.name + ' is JSON-LD']: (r) => r.headers['Content-Type'] && r.headers['Content-Type'].includes('application/ld+json'),
      [endpoint.name + ' has hydra:member']: (r) => {
        try {
          const body = JSON.parse(r.body);
          return 'hydra:member' in body;
        } catch (e) {
          return false;
        }
      },
    });

    apiDuration.add(res.timings.duration);
    errorRate.add(!checkResult);

    sleep(0.5);
  });

  sleep(Math.random() * 2 + 1);
}
