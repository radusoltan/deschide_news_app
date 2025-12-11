import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';
import { CONFIG, ENDPOINTS, getRandomLocale, getThinkTime } from './config.js';

// Custom metrics
const articlesDuration = new Trend('articles_duration');
const categoriesDuration = new Trend('categories_duration');
const errorRate = new Rate('error_rate');
const totalRequests = new Counter('total_requests');

// Test configuration - API ONLY (no frontend)
export const options = {
  vus: __ENV.VUS || 500,
  duration: __ENV.DURATION || '60s',
  thresholds: {
    'articles_duration': ['p(95)<500'],         // 95% of article requests under 500ms
    'categories_duration': ['p(95)<300'],       // 95% of category requests under 300ms
    'error_rate': ['rate<0.01'],                // Error rate under 1%
    'http_req_duration': ['p(95)<500', 'p(99)<1000'],
    'http_req_failed': ['rate<0.01'],
  },
};

export default function () {
  const locale = getRandomLocale();
  const headers = {
    'Accept-Language': locale,
    'Accept': 'application/ld+json',
  };

  // Test 1: Get articles list
  const articlesUrl = `${CONFIG.BACKEND_URL}${ENDPOINTS.articles}?page=1&itemsPerPage=30`;
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

  sleep(getThinkTime());

  // Test 2: Get categories
  const categoriesUrl = `${CONFIG.BACKEND_URL}${ENDPOINTS.categories}`;
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

  sleep(getThinkTime());

  // Test 3: Get specific article (simulate clicking on an article)
  if (articlesCheck && articlesRes.body) {
    try {
      const articlesData = JSON.parse(articlesRes.body);
      const articles = articlesData['hydra:member'];

      if (articles && articles.length > 0) {
        const randomArticle = articles[Math.floor(Math.random() * articles.length)];
        const articleId = randomArticle.id;

        const articleUrl = `${CONFIG.BACKEND_URL}/api/articles/${articleId}`;
        const articleRes = http.get(articleUrl, { headers });
        totalRequests.add(1);

        const articleCheck = check(articleRes, {
          'article detail status is 200': (r) => r.status === 200,
          'article has title': (r) => {
            try {
              const body = JSON.parse(r.body);
              return body.title && body.title.length > 0;
            } catch (e) {
              return false;
            }
          },
        });

        articlesDuration.add(articleRes.timings.duration);
        errorRate.add(!articleCheck);
      }
    } catch (e) {
      errorRate.add(1);
    }
  }

  sleep(getThinkTime());
}

export function handleSummary(data) {
  let summary = '\n\n';
  summary += ' ═══════════════════════════════════════════════════════\n';
  summary += '     API-ONLY LOAD TEST - DESCHIDE NEWS BACKEND         \n';
  summary += ' ═══════════════════════════════════════════════════════\n\n';

  const testDuration = data.state.testRunDurationMs / 1000;
  summary += ` Test Duration: ${testDuration.toFixed(2)}s\n\n`;

  summary += ` VUs (concurrent users): ${data.metrics.vus_max?.values.max || 'N/A'}\n`;
  summary += ` Iterations: ${data.metrics.iterations?.values.count || 'N/A'}\n\n`;

  const reqDuration = data.metrics.http_req_duration;
  if (reqDuration) {
    summary += ' HTTP Request Duration:\n';
    summary += `   avg: ${reqDuration.values.avg.toFixed(2)}ms\n`;
    summary += `   min: ${reqDuration.values.min.toFixed(2)}ms\n`;
    summary += `   med: ${reqDuration.values.med.toFixed(2)}ms\n`;
    summary += `   max: ${reqDuration.values.max.toFixed(2)}ms\n`;
    summary += `   p(90): ${reqDuration.values['p(90)'].toFixed(2)}ms\n`;
    summary += `   p(95): ${reqDuration.values['p(95)'].toFixed(2)}ms ⭐ TARGET < 500ms\n`;
    summary += `   p(99): ${reqDuration.values['p(99)'].toFixed(2)}ms\n\n`;
  }

  if (data.metrics.articles_duration) {
    summary += ` Articles p95: ${data.metrics.articles_duration.values['p(95)'].toFixed(2)}ms\n`;
  }
  if (data.metrics.categories_duration) {
    summary += ` Categories p95: ${data.metrics.categories_duration.values['p(95)'].toFixed(2)}ms\n`;
  }

  if (data.metrics.error_rate) {
    const errorPct = (data.metrics.error_rate.values.rate * 100).toFixed(2);
    summary += `\n Error Rate: ${errorPct}% (target: < 1%)\n`;
  }

  if (data.metrics.total_requests) {
    summary += ` Total Requests: ${data.metrics.total_requests.values.count}\n`;
    summary += ` Throughput: ${(data.metrics.total_requests.values.count / testDuration).toFixed(2)} req/s\n`;
  }

  summary += '\n ═══════════════════════════════════════════════════════\n\n';

  return { 'stdout': summary };
}
