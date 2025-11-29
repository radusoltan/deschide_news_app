# Performance Tester Agent

**Type**: Specialized Testing Agent
**Purpose**: Comprehensive performance testing and optimization validation
**Scope**: Full application stack performance metrics

## Agent Description

This agent measures and validates:
- Page load performance
- API response times
- Database query performance
- CDN asset delivery
- Frontend rendering performance
- Memory usage and leaks
- Network optimization
- Caching effectiveness
- Search performance
- Concurrent user load

## Testing Scope

### 1. **Frontend Performance Metrics**

#### Core Web Vitals

**Metrics to Measure:**
- **LCP (Largest Contentful Paint)** - Target: < 2.5s
- **FID (First Input Delay)** - Target: < 100ms
- **CLS (Cumulative Layout Shift)** - Target: < 0.1
- **FCP (First Contentful Paint)** - Target: < 1.8s
- **TTFB (Time to First Byte)** - Target: < 600ms
- **TTI (Time to Interactive)** - Target: < 3.5s

**Test Cases:**
- ✅ Homepage LCP < 2.5s
- ✅ Article page LCP < 2s
- ✅ Category page LCP < 2.5s
- ✅ Admin panel LCP < 3s
- ✅ No layout shift during page load (CLS < 0.1)
- ✅ First input responsive (FID < 100ms)
- ✅ Interactive within 3.5s (TTI)

#### Page Load Performance

**Pages to Test:**
- Homepage (`/ro`)
- Article page (`/ro/article/{slug}`)
- Category page (`/ro/category/{slug}`)
- Search results (`/ro/search?q=test`)
- Admin dashboard (`/ro/admin`)

**Test Cases:**
- ✅ Full page load < 3s on fast 3G
- ✅ Full page load < 1.5s on broadband
- ✅ JavaScript bundle size < 500KB
- ✅ CSS bundle size < 100KB
- ✅ Images are lazy-loaded
- ✅ Fonts are preloaded
- ✅ Critical CSS is inlined
- ✅ Non-critical resources deferred

#### Rendering Performance

**Test Cases:**
- ✅ No jank during scrolling (60fps)
- ✅ Smooth animations (60fps)
- ✅ No long tasks (> 50ms)
- ✅ Main thread not blocked
- ✅ Component render time < 16ms
- ✅ Re-renders are optimized

### 2. **Backend API Performance**

#### Endpoint Response Times

**Endpoints to Test:**
- `GET /api/articles` - Target: < 200ms
- `GET /api/articles/{id}` - Target: < 150ms
- `GET /api/categories` - Target: < 100ms
- `GET /api/important_articles_lists` - Target: < 200ms
- `POST /api/articles` - Target: < 300ms (authenticated)
- `GET /api/images` - Target: < 200ms

**Test Cases:**
- ✅ Article list responds in < 200ms
- ✅ Single article responds in < 150ms
- ✅ Categories respond in < 100ms
- ✅ Important articles respond in < 200ms
- ✅ Search responds in < 500ms
- ✅ Image upload completes in < 2s

#### Database Query Performance

**Test Cases:**
- ✅ Article query with eager loading < 50ms
- ✅ Category query < 20ms
- ✅ No N+1 queries detected
- ✅ Proper indexes are used
- ✅ Query count per request < 10
- ✅ Complex queries use joins (not loops)
- ✅ Pagination queries are optimized

#### Caching Effectiveness

**Test Cases:**
- ✅ Redis cache hit rate > 80%
- ✅ Cached responses < 10ms
- ✅ Cache invalidation works correctly
- ✅ HTTP cache headers present
- ✅ Static assets cached for 1 year
- ✅ API responses cached appropriately
- ✅ ETags are used correctly

### 3. **CDN and Asset Performance**

#### Image Delivery

**Test Cases:**
- ✅ Original images load in < 1s
- ✅ Thumbnails load in < 500ms
- ✅ Images are served with correct format (WebP)
- ✅ Images are compressed (< 200KB)
- ✅ Responsive images use srcset
- ✅ Images have proper cache headers
- ✅ CDN serves images (not origin)

#### Static Asset Delivery

**Test Cases:**
- ✅ JavaScript files are minified
- ✅ JavaScript files are gzipped/brotli
- ✅ CSS files are minified
- ✅ CSS files are gzipped/brotli
- ✅ Fonts are woff2 format
- ✅ Assets have cache-busting hashes
- ✅ Assets served with long cache headers

### 4. **Search Performance (Elasticsearch)**

**Test Cases:**
- ✅ Simple search query < 200ms
- ✅ Complex search query < 500ms
- ✅ Faceted search < 800ms
- ✅ Search with pagination < 300ms
- ✅ Search indexes are optimized
- ✅ Search results are relevant
- ✅ Search handles typos (fuzzy matching)

### 5. **Database Performance**

#### Query Analysis

**Test Cases:**
- ✅ EXPLAIN ANALYZE shows optimal query plans
- ✅ Indexes are used (not sequential scans)
- ✅ Query execution time < 50ms
- ✅ Connection pool is sized correctly
- ✅ No connection leaks
- ✅ Transactions are short-lived
- ✅ Deadlocks are not occurring

#### Database Size and Growth

**Test Cases:**
- ✅ Table sizes are reasonable
- ✅ Indexes are properly maintained
- ✅ No bloat in tables
- ✅ Vacuum is running regularly
- ✅ Statistics are up to date

### 6. **Concurrent Load Testing**

#### User Simulation

**Test Scenarios:**
- 10 concurrent users
- 50 concurrent users
- 100 concurrent users
- 500 concurrent users (stress test)

**Test Cases:**
- ✅ 10 users: All requests < 500ms
- ✅ 50 users: All requests < 1s
- ✅ 100 users: 95th percentile < 2s
- ✅ 500 users: System remains stable
- ✅ No errors under load
- ✅ Database connections managed properly
- ✅ Memory usage remains stable
- ✅ CPU usage < 80%

#### Spike Testing

**Test Cases:**
- ✅ Sudden traffic spike handled gracefully
- ✅ System recovers after spike
- ✅ No cascading failures
- ✅ Rate limiting works correctly
- ✅ Error messages are user-friendly

### 7. **Memory and Resource Usage**

#### Frontend Memory

**Test Cases:**
- ✅ Page memory usage < 50MB
- ✅ No memory leaks detected
- ✅ Memory usage stable over time
- ✅ Components cleanup properly
- ✅ Event listeners are removed

#### Backend Memory

**Test Cases:**
- ✅ PHP memory usage < 128MB per request
- ✅ No memory leaks in long-running processes
- ✅ Garbage collection is effective
- ✅ Object lifecycle managed properly

### 8. **Network Performance**

**Test Cases:**
- ✅ Total page size < 2MB
- ✅ Number of requests < 50
- ✅ HTTP/2 or HTTP/3 is used
- ✅ Connection keep-alive is enabled
- ✅ Domain sharding is not used (anti-pattern with HTTP/2)
- ✅ DNS prefetch for external domains
- ✅ Preconnect for critical origins

### 9. **Mobile Performance**

**Test Cases:**
- ✅ Mobile page load < 3s on 4G
- ✅ Mobile page load < 5s on 3G
- ✅ Mobile JavaScript bundle < 300KB
- ✅ Mobile images are optimized
- ✅ Touch responsiveness < 100ms
- ✅ No horizontal scrolling
- ✅ Viewport is optimized

### 10. **Admin Panel Performance**

**Test Cases:**
- ✅ Admin dashboard loads in < 2s
- ✅ Article list with 1000 articles loads in < 1s
- ✅ Article editor loads in < 1.5s
- ✅ Image upload progress is smooth
- ✅ Form submissions < 500ms
- ✅ No lag when typing in editor
- ✅ Drag and drop is smooth (60fps)

## Performance Testing Tools

### Lighthouse Metrics
- Use Lighthouse to measure Core Web Vitals
- Run on all key pages
- Target score: > 90 for Performance

### Browser Performance API
- `performance.timing` - Navigation timing
- `performance.getEntriesByType('paint')` - Paint metrics
- `performance.getEntriesByType('resource')` - Resource loading

### Backend Profiling
- Symfony Profiler toolbar
- Blackfire.io (if available)
- Database query logging

## Testing Workflow

### Step 1: Setup
1. Start all services in production mode
2. Enable performance monitoring
3. Clear all caches
4. Populate database with realistic data (10,000 articles)

### Step 2: Execute Performance Tests

#### Frontend Performance
```
1. Navigate to http://localhost:3005/ro
2. Measure page load time
3. Measure Core Web Vitals
4. Check network waterfall
5. Analyze JavaScript execution
6. Verify image lazy-loading
7. Screenshot performance metrics
```

#### Backend Performance
```
1. GET http://127.0.0.1:8081/api/articles?page=1&itemsPerPage=30
2. Measure response time
3. Check database query count
4. Verify eager loading
5. Check cache usage
6. Repeat with cache cold and warm
```

#### Load Testing
```
1. Simulate 10 concurrent users
2. Each user loads homepage
3. Each user navigates to 5 articles
4. Each user performs 2 searches
5. Measure response times
6. Check error rate
7. Monitor resource usage
```

### Step 3: Report Results
- Generate performance report
- Compare against benchmarks
- Identify bottlenecks
- Provide optimization recommendations

## Playwright MCP Tools to Use

### Navigation and Timing
- `mcp__playwright__playwright_navigate` - Navigate and measure
- `mcp__playwright__playwright_evaluate` - Run performance.timing

### Screenshots
- `mcp__playwright__playwright_screenshot` - Capture performance metrics

### Network Analysis
- Monitor network requests during page load

## Example Test Scenarios

### Scenario 1: Homepage Load Performance
```
1. Clear browser cache
2. Navigate: http://localhost:3005/ro
3. Wait for full page load
4. Execute JavaScript:
   return {
     domContentLoaded: performance.timing.domContentLoadedEventEnd - performance.timing.navigationStart,
     loadComplete: performance.timing.loadEventEnd - performance.timing.navigationStart,
     ttfb: performance.timing.responseStart - performance.timing.requestStart
   }
5. Assert: domContentLoaded < 1500ms
6. Assert: loadComplete < 3000ms
7. Assert: ttfb < 600ms
8. Screenshot: "homepage-performance.png"
```

### Scenario 2: API Response Time
```
1. Clear Redis cache
2. Start timer
3. GET http://127.0.0.1:8081/api/articles?page=1&itemsPerPage=30
4. End timer
5. Measure: response_time_cold_cache
6. Assert: response_time_cold_cache < 500ms

7. Start timer
8. GET http://127.0.0.1:8081/api/articles?page=1&itemsPerPage=30
9. End timer
10. Measure: response_time_warm_cache
11. Assert: response_time_warm_cache < 100ms
```

### Scenario 3: Image Load Performance
```
1. Navigate: http://localhost:3005/ro/article/test-article
2. Wait for images to load
3. Execute JavaScript:
   const images = performance.getEntriesByType('resource')
     .filter(r => r.initiatorType === 'img');
   return images.map(img => ({
     name: img.name,
     duration: img.duration,
     size: img.transferSize
   }));
4. For each image:
   Assert: duration < 1000ms
   Assert: size < 200000 bytes (200KB)
5. Verify: Images loaded from CDN (http://127.0.0.1:8082)
```

### Scenario 4: Database Query Performance
```
1. Enable Symfony profiler
2. GET http://127.0.0.1:8081/api/articles/140
3. Check Symfony profiler:
   - Query count: < 5 queries
   - Total query time: < 50ms
   - No N+1 queries
   - Proper eager loading
4. Check EXPLAIN ANALYZE for main query
5. Assert: Query uses indexes
```

### Scenario 5: Concurrent Load Test
```
1. Spawn 50 concurrent Playwright browsers
2. Each browser:
   - Navigate to homepage
   - Wait for load
   - Click random article
   - Wait for load
   - Navigate back
   - Wait for load
3. Measure for each browser:
   - Total test duration
   - Page load times
   - Any errors
4. Calculate:
   - Average response time
   - 95th percentile response time
   - Error rate
5. Assert:
   - Average < 1000ms
   - 95th percentile < 2000ms
   - Error rate < 1%
```

## Performance Benchmarks

### Target Metrics

| Metric | Target | Current | Status |
|--------|--------|---------|--------|
| Homepage load | < 2s | TBD | ⏳ |
| Article page load | < 1.5s | TBD | ⏳ |
| API response | < 200ms | TBD | ⏳ |
| Database query | < 50ms | TBD | ⏳ |
| Search query | < 500ms | TBD | ⏳ |
| Image load | < 1s | TBD | ⏳ |
| LCP | < 2.5s | TBD | ⏳ |
| FID | < 100ms | TBD | ⏳ |
| CLS | < 0.1 | TBD | ⏳ |

## Environment Configuration

```bash
# Performance testing mode
NODE_ENV=production
APP_ENV=prod
APP_DEBUG=0

# Database
DATABASE_POOL_SIZE=20

# Redis
REDIS_MAX_CONNECTIONS=100

# Test configuration
CONCURRENT_USERS=50
TEST_DURATION=300
RAMP_UP_TIME=30
```

## Test Execution Commands

```bash
# Run all performance tests
pnpm test:performance

# Run frontend performance only
pnpm test:performance:frontend

# Run backend performance only
pnpm test:performance:backend

# Run load test
pnpm test:load

# Generate performance report
pnpm test:performance:report
```

## Expected Outcomes

After running this agent:
- ✅ Performance benchmarks established
- ✅ Bottlenecks identified
- ✅ Core Web Vitals measured
- ✅ API response times validated
- ✅ Database queries optimized
- ✅ Load capacity determined
- ✅ Memory usage profiled
- ✅ Performance report generated
- ✅ Optimization recommendations provided

## Performance Optimization Recommendations

Based on test results, this agent will provide recommendations such as:
- Enable HTTP/2 or HTTP/3
- Implement service worker for caching
- Use CDN for static assets
- Optimize database indexes
- Implement Redis caching
- Lazy-load images
- Code splitting for JavaScript
- Tree shaking to reduce bundle size
- Preload critical resources
- Defer non-critical resources

## Integration with CI/CD

This agent can be integrated into CI/CD pipeline:
1. Run performance tests on staging before production
2. Fail deployment if performance regresses > 10%
3. Generate performance budgets
4. Track performance over time
5. Alert on performance degradation

## Troubleshooting

### Common Issues
1. **Slow page load**: Check network waterfall, optimize critical path
2. **High TTFB**: Optimize server-side rendering, add caching
3. **Large bundle size**: Code splitting, tree shaking
4. **Slow database**: Add indexes, optimize queries, use eager loading
5. **Memory leaks**: Profile with DevTools, check component cleanup

## Agent Invocation

To use this agent, call:
```
Use the Task tool with:
- subagent_type: "general-purpose"
- prompt: "Run performance tests following the performance-tester agent specification"
```

Or invoke directly:
```
@performance-tester test full application performance
@performance-tester test frontend performance
@performance-tester test backend API performance
@performance-tester test database performance
@performance-tester test load capacity
@performance-tester test Core Web Vitals
```
