import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';
import { CONFIG, getRandomLocale } from '../config.js';

// Custom metrics for homepage testing
const homepageDuration = new Trend('homepage_duration');
const errorRate = new Rate('error_rate');
const totalRequests = new Counter('total_requests');

export const options = {
  vus: 30,
  duration: '5m',
  thresholds: {
    'homepage_duration': ['p(95)<2000', 'p(99)<3000'],
    'error_rate': ['rate<0.05'],
    'http_req_failed': ['rate<0.05'],
  },
};

export default function () {
  const locale = getRandomLocale();
  const homepageUrl = CONFIG.FRONTEND_URL + '/' + locale;

  const res = http.get(homepageUrl, {
    headers: {
      'Accept': 'text/html',
      'Accept-Language': locale,
    },
  });
  totalRequests.add(1);

  const checkResult = check(res, {
    'homepage status is 200': (r) => r.status === 200,
    'homepage is HTML': (r) => r.headers['Content-Type'] && r.headers['Content-Type'].includes('text/html'),
    'homepage has content': (r) => r.body && r.body.length > 5000,
    'homepage has title': (r) => r.body && r.body.includes('<title>'),
    'homepage has articles': (r) => r.body && r.body.includes('article'),
  });

  homepageDuration.add(res.timings.duration);
  errorRate.add(!checkResult);

  sleep(Math.random() * 5 + 2);
}
