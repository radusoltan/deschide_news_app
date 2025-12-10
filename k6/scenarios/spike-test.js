import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate } from 'k6/metrics';

const errorRate = new Rate('errors');

export const options = {
  stages: [
    { duration: '1m', target: 10 },    // Baseline
    { duration: '10s', target: 500 },  // Spike!
    { duration: '1m', target: 500 },   // Stay at spike
    { duration: '10s', target: 10 },   // Scale down
    { duration: '2m', target: 10 },    // Recovery period
    { duration: '10s', target: 0 },    // Ramp down
  ],

  thresholds: {
    http_req_duration: ['p(95)<3000'],
    http_req_failed: ['rate<0.30'],
  },
};

const BACKEND_URL = __ENV.BACKEND_URL || 'http://127.0.0.1:8081';

export default function () {
  const res = http.get(`${BACKEND_URL}/api/articles?itemsPerPage=5`);

  check(res, {
    'status is 200 or 429 or 503': (r) => [200, 429, 503].includes(r.status),
  }) || errorRate.add(1);

  sleep(0.1);
}

export function handleSummary(data) {
  console.log('\n=== SPIKE TEST SUMMARY ===');
  console.log(`Total Requests: ${data.metrics.http_reqs?.values?.count || 0}`);
  console.log(`Error Rate: ${((data.metrics.http_req_failed?.values?.rate || 0) * 100).toFixed(2)}%`);
  console.log(`P95 Response Time: ${(data.metrics.http_req_duration?.values?.['p(95)'] || 0).toFixed(2)}ms`);
  console.log(`P99 Response Time: ${(data.metrics.http_req_duration?.values?.['p(99)'] || 0).toFixed(2)}ms`);

  return {
    'stdout': JSON.stringify({
      total_requests: data.metrics.http_reqs?.values?.count || 0,
      error_rate: ((data.metrics.http_req_failed?.values?.rate || 0) * 100).toFixed(2) + '%',
      p95: (data.metrics.http_req_duration?.values?.['p(95)'] || 0).toFixed(2) + 'ms',
      p99: (data.metrics.http_req_duration?.values?.['p(99)'] || 0).toFixed(2) + 'ms',
    }, null, 2),
    'results/spike-test-results.json': JSON.stringify(data, null, 2),
  };
}
