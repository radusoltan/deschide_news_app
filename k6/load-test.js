import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';
import { CONFIG, ENDPOINTS, getRandomLocale, getThinkTime } from './config.js';

// Custom metrics
const articlesDuration = new Trend('articles_duration');
const categoriesDuration = new Trend('categories_duration');
const homepageDuration = new Trend('homepage_duration');
const errorRate = new Rate('error_rate');
const totalRequests = new Counter('total_requests');

// Test configuration
export const options = {
  stages: [
    { duration: '30s', target: 10 },   // Warm up: 30s → 10 users
    { duration: '1m', target: 25 },    // Ramp up: 1m → 25 users
    { duration: '2m', target: 50 },    // Ramp up: 2m → 50 users
    { duration: '3m', target: 50 },    // Steady: 3m @ 50 users
    { duration: '1m', target: 100 },   // Peak: 1m → 100 users
    { duration: '2m', target: 100 },   // Steady peak: 2m @ 100 users
    { duration: '1m', target: 0 },     // Ramp down: 1m → 0 users
  ],
  thresholds: {
    'articles_duration': ['p(95)<300'],         // 95% of article requests under 300ms
    'categories_duration': ['p(95)<150'],       // 95% of category requests under 150ms
    'homepage_duration': ['p(95)<2000'],        // 95% of homepage loads under 2s
    'error_rate': ['rate<0.05'],                // Error rate under 5%
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
        // API Platform uses @hydra:member or member depending on format
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
        // API Platform uses @hydra:member or member depending on format
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
        // Get random article ID
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

  // Test 4: Frontend homepage
  const homepageUrl = `${CONFIG.FRONTEND_URL}/${locale}`;
  const homepageRes = http.get(homepageUrl, {
    headers: {
      'Accept': 'text/html',
      'Accept-Language': locale,
    },
  });
  totalRequests.add(1);

  const homepageCheck = check(homepageRes, {
    'homepage status is 200': (r) => r.status === 200,
    'homepage has content': (r) => r.body && r.body.length > 1000,
  });

  homepageDuration.add(homepageRes.timings.duration);
  errorRate.add(!homepageCheck);

  sleep(getThinkTime());
}

export function handleSummary(data) {
  return {
    'stdout': textSummary(data, { indent: ' ', enableColors: true }),
  };
}

function textSummary(data, options = {}) {
  const indent = options.indent || '';
  const enableColors = options.enableColors || false;

  let summary = '\n\n';
  summary += indent + '═══════════════════════════════════════════════════════\n';
  summary += indent + '         LOAD TEST SUMMARY - DESCHIDE NEWS APP         \n';
  summary += indent + '═══════════════════════════════════════════════════════\n\n';

  // Test duration
  const testDuration = data.state.testRunDurationMs / 1000;
  summary += indent + `Test Duration: ${testDuration.toFixed(2)}s\n\n`;

  // VUs
  summary += indent + `VUs (max): ${data.metrics.vus_max?.values.max || 'N/A'}\n`;
  summary += indent + `Iterations: ${data.metrics.iterations?.values.count || 'N/A'}\n\n`;

  // Request metrics
  const reqDuration = data.metrics.http_req_duration;
  if (reqDuration) {
    summary += indent + 'HTTP Request Duration:\n';
    summary += indent + `  avg: ${reqDuration.values.avg.toFixed(2)}ms\n`;
    summary += indent + `  min: ${reqDuration.values.min.toFixed(2)}ms\n`;
    summary += indent + `  med: ${reqDuration.values.med.toFixed(2)}ms\n`;
    summary += indent + `  max: ${reqDuration.values.max.toFixed(2)}ms\n`;
    if (reqDuration.values['p(90)']) {
      summary += indent + `  p(90): ${reqDuration.values['p(90)'].toFixed(2)}ms\n`;
    }
    if (reqDuration.values['p(95)']) {
      summary += indent + `  p(95): ${reqDuration.values['p(95)'].toFixed(2)}ms\n`;
    }
    if (reqDuration.values['p(99)']) {
      summary += indent + `  p(99): ${reqDuration.values['p(99)'].toFixed(2)}ms\n`;
    }
    summary += '\n';
  }

  // Custom metrics
  if (data.metrics.articles_duration) {
    summary += indent + `Articles Duration (p95): ${data.metrics.articles_duration.values['p(95)'].toFixed(2)}ms\n`;
  }
  if (data.metrics.categories_duration) {
    summary += indent + `Categories Duration (p95): ${data.metrics.categories_duration.values['p(95)'].toFixed(2)}ms\n`;
  }
  if (data.metrics.homepage_duration) {
    summary += indent + `Homepage Duration (p95): ${data.metrics.homepage_duration.values['p(95)'].toFixed(2)}ms\n`;
  }

  // Error rate
  if (data.metrics.error_rate) {
    const errorPct = (data.metrics.error_rate.values.rate * 100).toFixed(2);
    summary += indent + `\nError Rate: ${errorPct}%\n`;
  }

  // Total requests
  if (data.metrics.total_requests) {
    summary += indent + `Total Requests: ${data.metrics.total_requests.values.count}\n`;
  }

  summary += indent + '\n═══════════════════════════════════════════════════════\n\n';

  return summary;
}
