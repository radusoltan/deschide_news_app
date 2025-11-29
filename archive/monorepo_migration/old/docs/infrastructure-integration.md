# Infrastructure Integration - Deschide News App

**Data creare:** 2025-10-27
**Status:** Planning & Implementation Guide

---

## 📋 Overview

Aplicația Deschide News va folosi infrastructura shared disponibilă pe server, integrând servicii care sunt deja configurate și utilizate de `pm_ai` și `ecom`.

### Infrastructură Disponibilă

| Service | Port | Status | Usage |
|---------|------|--------|-------|
| **PostgreSQL** | 5432 | ✅ Active | Primary database |
| **Redis** | 6379 | ✅ Active | Cache, session storage |
| **RabbitMQ** | 5672, 15672 | ✅ Active | Message queue |
| **Elasticsearch** | 9200, 9300 | ✅ Active | Search engine |
| **Mercure** | 3000 | ✅ Active | Real-time updates |
| **Prometheus** | 9090 | ✅ Active | Metrics collection |
| **Grafana** | 3002 | ✅ Active | Monitoring dashboards |

---

## 🗄️ PostgreSQL - Primary Database

### Purpose în Deschide News

PostgreSQL va fi database-ul principal pentru toate entitățile aplicației.

### Configuration

**Database Name:** `deschide_news`

**Connection String (.env):**
```env
DATABASE_URL="postgresql://deschide_user:secure_password@127.0.0.1:5432/deschide_news?serverVersion=17&charset=utf8"
```

### Setup

```bash
# Create database
createdb deschide_news

# Create user
psql -c "CREATE USER deschide_user WITH PASSWORD 'secure_password';"
psql -c "GRANT ALL PRIVILEGES ON DATABASE deschide_news TO deschide_user;"

# Update .env in deschide_backend
cd /var/www/deschide_news_app/deschide_backend
echo "DATABASE_URL=\"postgresql://deschide_user:secure_password@127.0.0.1:5432/deschide_news?serverVersion=17&charset=utf8\"" >> .env

# Run migrations
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

### Tables Created

După implementarea Sprint 1-3:
- `user` - utilizatori și autentificare
- `author` - autori de articole
- `category` - categorii articole
- `article` - articole
- `image` - imagini
- `thumbnail` - thumbnails generate
- `thumbnail_profile` - profile pentru thumbnails
- `article_image` - pivot table
- `article_author` - pivot table
- `ext_translations` - Gedmo translations
- `refresh_tokens` - JWT refresh tokens

### Monitoring

```sql
-- Check database size
SELECT pg_size_pretty(pg_database_size('deschide_news'));

-- Active connections
SELECT count(*) FROM pg_stat_activity WHERE datname = 'deschide_news';

-- Table sizes
SELECT
    schemaname,
    tablename,
    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) AS size
FROM pg_tables
WHERE schemaname = 'public'
ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC;
```

---

## 🚀 Redis - Cache & Sessions

### Purpose în Deschide News

1. **HTTP Cache** - Cache pentru API responses
2. **Session Storage** - Session data pentru admin
3. **Rate Limiting** - Token bucket pentru rate limiter
4. **Lock Management** - Distributed locks pentru operații critice

### Configuration

**config/packages/cache.yaml:**
```yaml
framework:
    cache:
        app: cache.adapter.redis
        system: cache.adapter.redis
        default_redis_provider: redis://localhost:6379/1  # DB 1 pentru deschide_news

        pools:
            cache.articles:
                adapter: cache.adapter.redis
                provider: redis://localhost:6379/1
                default_lifetime: 3600  # 1 hour

            cache.categories:
                adapter: cache.adapter.redis
                provider: redis://localhost:6379/1
                default_lifetime: 7200  # 2 hours

            cache.translations:
                adapter: cache.adapter.redis
                provider: redis://localhost:6379/1
                default_lifetime: 86400  # 24 hours
```

### Usage Examples

**Cache API Responses:**
```php
// src/Service/ArticleCacheService.php
class ArticleCacheService
{
    public function __construct(
        private CacheInterface $articlesCache,
        private ArticleRepository $articleRepository
    ) {}

    public function getArticleBySlug(string $slug, string $locale): ?Article
    {
        $cacheKey = "article.{$slug}.{$locale}";

        return $this->articlesCache->get($cacheKey, function (ItemInterface $item) use ($slug, $locale) {
            $item->expiresAfter(3600); // 1 hour

            return $this->articleRepository->findOneBySlugAndLocale($slug, $locale);
        });
    }

    public function invalidateArticle(Article $article): void
    {
        // Invalidate all locale versions
        foreach (['ro', 'en', 'ru'] as $locale) {
            $cacheKey = "article.{$article->getSlug()}.{$locale}";
            $this->articlesCache->delete($cacheKey);
        }
    }
}
```

**Rate Limiting:**
```yaml
# config/packages/rate_limiter.yaml
framework:
    rate_limiter:
        api_public:
            policy: 'sliding_window'
            limit: 100
            interval: '1 minute'
            storage_service: 'cache.adapter.redis'
```

### Redis Key Namespacing

Pentru a evita conflictele cu alte aplicații:
```
deschide_news:articles:{slug}:{locale}
deschide_news:categories:list:{locale}
deschide_news:user:session:{id}
deschide_news:rate_limit:api:{ip}
```

### Monitoring

```bash
# Connect to Redis
redis-cli

# Select deschide_news database
SELECT 1

# View all keys
KEYS deschide_news:*

# Monitor real-time commands
MONITOR

# Memory usage
INFO memory

# Check cache hit rate
INFO stats
```

---

## 🐰 RabbitMQ - Message Queue

### Purpose în Deschide News

1. **Async Thumbnail Generation** - Generate thumbnails in background
2. **Email Notifications** - Send emails asynchronously
3. **Article Indexing** - Index articles în Elasticsearch
4. **Statistics Aggregation** - Process view counts, analytics

### Configuration

**config/packages/messenger.yaml:**
```yaml
framework:
    messenger:
        transports:
            async:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    exchange:
                        name: deschide_news
                        type: topic
                    queues:
                        thumbnails:
                            binding_keys: ['thumbnail.generate']
                        notifications:
                            binding_keys: ['notification.*']
                        indexing:
                            binding_keys: ['search.index']

        routing:
            'App\Message\GenerateThumbnailsMessage': async
            'App\Message\IndexArticleMessage': async
            'App\Message\SendNotificationMessage': async
```

**.env:**
```env
MESSENGER_TRANSPORT_DSN=amqp://guest:guest@localhost:5672/%2f/deschide_news_messages
```

### Message Examples

**1. Thumbnail Generation:**
```php
// src/Message/GenerateThumbnailsMessage.php
class GenerateThumbnailsMessage
{
    public function __construct(
        private int $imageId
    ) {}

    public function getImageId(): int
    {
        return $this->imageId;
    }
}

// src/MessageHandler/GenerateThumbnailsHandler.php
#[AsMessageHandler]
class GenerateThumbnailsHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private ThumbnailGeneratorService $thumbnailGenerator,
        private ThumbnailProfileRepository $profileRepository
    ) {}

    public function __invoke(GenerateThumbnailsMessage $message): void
    {
        $image = $this->em->find(Image::class, $message->getImageId());

        if (!$image) {
            return;
        }

        $profiles = $this->profileRepository->findAll();

        foreach ($profiles as $profile) {
            $thumbnail = $this->thumbnailGenerator->generateThumbnail($image, $profile);
            $this->em->persist($thumbnail);
        }

        $this->em->flush();
    }
}
```

**2. Article Indexing:**
```php
// src/Message/IndexArticleMessage.php
class IndexArticleMessage
{
    public function __construct(
        private int $articleId,
        private string $action  // 'index' or 'delete'
    ) {}
}

// src/MessageHandler/IndexArticleHandler.php
#[AsMessageHandler]
class IndexArticleHandler
{
    public function __construct(
        private ElasticsearchService $elasticsearchService,
        private ArticleRepository $articleRepository
    ) {}

    public function __invoke(IndexArticleMessage $message): void
    {
        $article = $this->articleRepository->find($message->getArticleId());

        if ($message->getAction() === 'delete') {
            $this->elasticsearchService->deleteArticle($article->getId());
            return;
        }

        if ($article && $article->getStatus() === ArticleStatus::PUBLISHED) {
            $this->elasticsearchService->indexArticle($article);
        }
    }
}
```

### Worker Management

**Start workers:**
```bash
# Single worker
php bin/console messenger:consume async -vv

# Multiple workers (recommended)
php bin/console messenger:consume async --limit=100 &
php bin/console messenger:consume async --limit=100 &
php bin/console messenger:consume async --limit=100 &
```

**Systemd Service:**
```ini
# /etc/systemd/system/deschide-news-worker@.service
[Unit]
Description=Deschide News Messenger Worker %i
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/deschide_news_app/deschide_backend
ExecStart=/usr/bin/php /var/www/deschide_news_app/deschide_backend/bin/console messenger:consume async --time-limit=3600 --memory-limit=128M
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

**Start 3 workers:**
```bash
sudo systemctl enable deschide-news-worker@1
sudo systemctl enable deschide-news-worker@2
sudo systemctl enable deschide-news-worker@3

sudo systemctl start deschide-news-worker@{1,2,3}
```

### Monitoring RabbitMQ

**Web UI:**
- URL: http://localhost:15672
- User: guest
- Password: guest

**Check queues:**
```bash
# List queues
rabbitmqctl list_queues

# Check messages in queue
rabbitmqctl list_queues name messages messages_ready messages_unacknowledged

# Check failed messages
php bin/console messenger:failed:show
```

---

## 🔍 Elasticsearch - Search Engine

### Purpose în Deschide News

1. **Full-text Search** - Search în articole (title, content, lead)
2. **Faceted Search** - Filter by category, author, date, badge
3. **Multilanguage Search** - Search per locale
4. **Auto-complete** - Suggestions pentru search bar

### Configuration

**config/packages/elasticsearch.yaml:**
```yaml
parameters:
    elasticsearch.host: 'https://localhost:9200'
    elasticsearch.username: 'elastic'
    elasticsearch.password: '%env(ELASTICSEARCH_PASSWORD)%'

services:
    App\Service\ElasticsearchService:
        arguments:
            $hosts: ['%elasticsearch.host%']
            $username: '%elasticsearch.username%'
            $password: '%elasticsearch.password%'
```

**.env:**
```env
ELASTICSEARCH_PASSWORD=WsAEcDWAbQjb5XGUnpvk
```

### ElasticsearchService Implementation

```php
// src/Service/ElasticsearchService.php
use Elastic\Elasticsearch\ClientBuilder;

class ElasticsearchService
{
    private Client $client;
    private const INDEX_NAME = 'deschide_articles';

    public function __construct(
        array $hosts,
        string $username,
        string $password
    ) {
        $this->client = ClientBuilder::create()
            ->setHosts($hosts)
            ->setBasicAuthentication($username, $password)
            ->setSSLVerification(false)  // Only for dev with self-signed cert
            ->build();

        $this->createIndexIfNotExists();
    }

    private function createIndexIfNotExists(): void
    {
        if ($this->client->indices()->exists(['index' => self::INDEX_NAME])->asBool()) {
            return;
        }

        $this->client->indices()->create([
            'index' => self::INDEX_NAME,
            'body' => [
                'settings' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 0,
                    'analysis' => [
                        'analyzer' => [
                            'romanian' => [
                                'type' => 'standard',
                                'stopwords' => '_romanian_'
                            ],
                            'russian' => [
                                'type' => 'standard',
                                'stopwords' => '_russian_'
                            ]
                        ]
                    ]
                ],
                'mappings' => [
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'title' => [
                            'type' => 'text',
                            'fields' => [
                                'keyword' => ['type' => 'keyword']
                            ]
                        ],
                        'slug' => ['type' => 'keyword'],
                        'lead' => ['type' => 'text'],
                        'content' => ['type' => 'text'],
                        'locale' => ['type' => 'keyword'],
                        'category' => [
                            'properties' => [
                                'id' => ['type' => 'integer'],
                                'name' => ['type' => 'keyword']
                            ]
                        ],
                        'authors' => [
                            'type' => 'nested',
                            'properties' => [
                                'id' => ['type' => 'integer'],
                                'name' => ['type' => 'text']
                            ]
                        ],
                        'badge' => ['type' => 'keyword'],
                        'publishedAt' => ['type' => 'date'],
                        'viewCount' => ['type' => 'integer']
                    ]
                ]
            ]
        ]);
    }

    public function indexArticle(Article $article): void
    {
        // Index pentru fiecare locale
        foreach (['ro', 'en', 'ru'] as $locale) {
            // Set locale pentru Gedmo
            // ... (load article in locale)

            $document = [
                'id' => $article->getId(),
                'title' => $article->getTitle(),
                'slug' => $article->getSlug(),
                'lead' => $article->getLead(),
                'content' => strip_tags($article->getContent()),
                'locale' => $locale,
                'category' => [
                    'id' => $article->getCategory()->getId(),
                    'name' => $article->getCategory()->getTitle()
                ],
                'authors' => array_map(fn($author) => [
                    'id' => $author->getId(),
                    'name' => $author->getFullName()
                ], $article->getAuthors()->toArray()),
                'badge' => $article->getBadge()?->value,
                'publishedAt' => $article->getPublishedAt()?->format('c'),
                'viewCount' => $article->getViewCount()
            ];

            $this->client->index([
                'index' => self::INDEX_NAME,
                'id' => "{$article->getId()}_{$locale}",
                'body' => $document
            ]);
        }
    }

    public function search(
        string $query,
        string $locale,
        ?int $categoryId = null,
        ?string $badge = null,
        int $from = 0,
        int $size = 20
    ): array {
        $must = [
            ['match' => ['locale' => $locale]],
            [
                'multi_match' => [
                    'query' => $query,
                    'fields' => ['title^3', 'lead^2', 'content'],
                    'type' => 'best_fields',
                    'fuzziness' => 'AUTO'
                ]
            ]
        ];

        if ($categoryId) {
            $must[] = ['term' => ['category.id' => $categoryId]];
        }

        if ($badge) {
            $must[] = ['term' => ['badge' => $badge]];
        }

        $params = [
            'index' => self::INDEX_NAME,
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => $must
                    ]
                ],
                'sort' => [
                    ['_score' => ['order' => 'desc']],
                    ['publishedAt' => ['order' => 'desc']]
                ],
                'from' => $from,
                'size' => $size,
                'highlight' => [
                    'fields' => [
                        'title' => new \stdClass(),
                        'lead' => new \stdClass(),
                        'content' => ['fragment_size' => 150, 'number_of_fragments' => 3]
                    ]
                ]
            ]
        ];

        $response = $this->client->search($params);

        return [
            'total' => $response['hits']['total']['value'],
            'hits' => array_map(fn($hit) => [
                'id' => $hit['_source']['id'],
                'title' => $hit['_source']['title'],
                'slug' => $hit['_source']['slug'],
                'lead' => $hit['_source']['lead'],
                'highlight' => $hit['highlight'] ?? null,
                'score' => $hit['_score']
            ], $response['hits']['hits'])
        ];
    }

    public function deleteArticle(int $articleId): void
    {
        foreach (['ro', 'en', 'ru'] as $locale) {
            try {
                $this->client->delete([
                    'index' => self::INDEX_NAME,
                    'id' => "{$articleId}_{$locale}"
                ]);
            } catch (\Exception $e) {
                // Document might not exist for this locale
            }
        }
    }
}
```

### Search Controller

```php
// src/Controller/Api/Public/SearchController.php
#[Route('/api/public/search')]
class SearchController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function search(
        Request $request,
        ElasticsearchService $elasticsearch
    ): JsonResponse {
        $query = $request->query->get('q', '');
        $locale = $request->getLocale();
        $categoryId = $request->query->getInt('category');
        $badge = $request->query->get('badge');
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 20);

        if (empty($query)) {
            return $this->json(['error' => 'Query parameter required'], 400);
        }

        $results = $elasticsearch->search(
            $query,
            $locale,
            $categoryId ?: null,
            $badge ?: null,
            ($page - 1) * $limit,
            $limit
        );

        return $this->json([
            'query' => $query,
            'total' => $results['total'],
            'results' => $results['hits'],
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'pages' => (int) ceil($results['total'] / $limit)
            ]
        ]);
    }
}
```

---

## 🔔 Mercure - Real-time Updates

### Purpose în Deschide News

1. **Live Article Updates** - Notify când articol nou e publicat
2. **Breaking News Alerts** - Push notifications pentru breaking news
3. **Admin Notifications** - Real-time pentru admin dashboard
4. **Live View Counts** - Update view counts în real-time

### Configuration

Mercure hub este shared pe port 3000, deja rulând pentru `pm_ai`.

**config/packages/mercure.yaml:**
```yaml
mercure:
    hubs:
        default:
            url: '%env(MERCURE_URL)%'
            public_url: '%env(MERCURE_PUBLIC_URL)%'
            jwt:
                secret: '%env(MERCURE_JWT_SECRET)%'
                publish: ['*']
                subscribe: ['*']
```

**.env:**
```env
MERCURE_URL=http://localhost:3000/.well-known/mercure
MERCURE_PUBLIC_URL=http://localhost:3000/.well-known/mercure
MERCURE_JWT_SECRET=!ChangeThisMercureHubJWTSecretKey!
```

### Usage Examples

**1. Publish Breaking News:**
```php
// src/Service/BreakingNewsNotifier.php
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class BreakingNewsNotifier
{
    public function __construct(
        private HubInterface $hub
    ) {}

    public function notifyBreakingNews(Article $article): void
    {
        $update = new Update(
            topics: 'deschide_news/breaking',  // Topic specific pentru app
            data: json_encode([
                'id' => $article->getId(),
                'title' => $article->getTitle(),
                'slug' => $article->getSlug(),
                'badge' => $article->getBadge()?->value,
                'publishedAt' => $article->getPublishedAt()?->format('c')
            ]),
            private: false  // Public update
        );

        $this->hub->publish($update);
    }
}
```

**2. Notify Article Published:**
```php
// În ArticleController când publish article
public function publish(int $id, BreakingNewsNotifier $notifier): JsonResponse
{
    $article = $this->articleRepository->find($id);
    $article->setStatus(ArticleStatus::PUBLISHED);
    $article->setPublishedAt(new \DateTime());

    $this->em->flush();

    // Send real-time notification
    if ($article->getBadge() === ArticleBadge::BREAKING) {
        $notifier->notifyBreakingNews($article);
    }

    return $this->json(['message' => 'Article published']);
}
```

**3. Frontend Subscription (Next.js):**
```typescript
// hooks/useBreakingNews.ts
import { useEffect, useState } from 'react';

export function useBreakingNews() {
  const [breakingNews, setBreakingNews] = useState<Article[]>([]);

  useEffect(() => {
    const eventSource = new EventSource(
      'http://localhost:3000/.well-known/mercure?topic=deschide_news/breaking'
    );

    eventSource.onmessage = (event) => {
      const article = JSON.parse(event.data);
      setBreakingNews(prev => [article, ...prev]);

      // Show notification
      if (Notification.permission === 'granted') {
        new Notification('Breaking News!', {
          body: article.title,
          icon: '/breaking-news-icon.png'
        });
      }
    };

    return () => eventSource.close();
  }, []);

  return breakingNews;
}
```

### Topics Namespacing

Pentru a evita conflictele cu alte apps:
```
deschide_news/breaking           # Breaking news
deschide_news/articles/new       # New articles published
deschide_news/admin/notifications # Admin notifications
deschide_news/stats/views        # Live view counts
```

---

## 📊 Prometheus - Metrics Collection

### Purpose în Deschide News

1. **Application Metrics** - Request rate, response time, errors
2. **Business Metrics** - Articles published, views, searches
3. **Infrastructure Metrics** - PHP-FPM, Database, Redis
4. **Custom Alerts** - Alert când site down, slow responses

### Configuration

Prometheus este deja configurat și rulează pe port 9090.

**Add job pentru deschide_news în `/etc/prometheus/prometheus.yml`:**
```yaml
scrape_configs:
  - job_name: 'deschide_news'
    scrape_interval: 15s
    static_configs:
      - targets: ['localhost:80']
        labels:
          app: 'deschide_news'
          env: 'dev'
    metrics_path: '/metrics'
```

### Metrics Endpoint Implementation

**Install prometheus bundle:**
```bash
composer require artprima/prometheus-metrics-bundle
```

**config/packages/artprima_prometheus_metrics.yaml:**
```yaml
artprima_prometheus_metrics:
    namespace: deschide_news
    ignored_routes:
        - '_wdt'
        - '_profiler'
    metrics:
        - type: counter
          name: http_requests_total
          help: 'Total HTTP requests'
          labels: ['method', 'route', 'status_code']

        - type: histogram
          name: http_request_duration_seconds
          help: 'HTTP request duration'
          labels: ['method', 'route']
          buckets: [0.005, 0.01, 0.025, 0.05, 0.075, 0.1, 0.25, 0.5, 0.75, 1.0, 2.5, 5.0, 7.5, 10.0]

        - type: counter
          name: articles_published_total
          help: 'Total articles published'
          labels: ['locale', 'category']

        - type: counter
          name: articles_viewed_total
          help: 'Total article views'
          labels: ['article_id']

        - type: gauge
          name: search_queries_processing
          help: 'Number of search queries currently processing'
```

**Custom Metrics:**
```php
// src/Service/MetricsService.php
use Artprima\PrometheusMetricsBundle\Metrics\RendererInterface;

class MetricsService
{
    public function __construct(
        private RendererInterface $renderer
    ) {}

    public function recordArticlePublished(Article $article): void
    {
        $counter = $this->renderer->getMetricsStorage()
            ->getCounter('deschide_news', 'articles_published_total');

        $counter->inc([
            'locale' => 'ro',  // or get from article
            'category' => $article->getCategory()->getSlug()
        ]);
    }

    public function recordArticleView(Article $article): void
    {
        $counter = $this->renderer->getMetricsStorage()
            ->getCounter('deschide_news', 'articles_viewed_total');

        $counter->inc(['article_id' => $article->getId()]);
    }
}
```

### Metrics Endpoint

```php
// config/routes.yaml
metrics:
    path: /metrics
    controller: Artprima\PrometheusMetricsBundle\Controller\MetricsController::prometheus
```

Test: http://api.news-app.local/metrics

---

## 📈 Grafana - Monitoring Dashboards

### Purpose în Deschide News

1. **Application Dashboard** - Overview requests, errors, performance
2. **Business Metrics** - Articles published, top articles, searches
3. **Infrastructure** - Database, Redis, RabbitMQ health
4. **Alerts** - Visual alerts pentru probleme

### Access

- URL: http://localhost:3002
- User: admin
- Password: admin

### Dashboard Creation

**1. Add Prometheus Data Source:**
- Configuration → Data Sources → Add Prometheus
- URL: http://localhost:9090
- Save & Test

**2. Create Dashboard pentru Deschide News:**

**Panel 1: HTTP Request Rate**
```promql
rate(deschide_news_http_requests_total[5m])
```

**Panel 2: Response Time (p95)**
```promql
histogram_quantile(0.95, rate(deschide_news_http_request_duration_seconds_bucket[5m]))
```

**Panel 3: Error Rate**
```promql
sum(rate(deschide_news_http_requests_total{status_code=~"5.."}[5m]))
```

**Panel 4: Articles Published (Last 24h)**
```promql
increase(deschide_news_articles_published_total[24h])
```

**Panel 5: Top Viewed Articles**
```promql
topk(10, deschide_news_articles_viewed_total)
```

**Panel 6: Database Connections**
```promql
pg_stat_activity_count{datname="deschide_news"}
```

**Panel 7: Redis Memory Usage**
```promql
redis_memory_used_bytes{db="1"}
```

**Panel 8: RabbitMQ Queue Size**
```promql
rabbitmq_queue_messages{queue="thumbnails"}
```

### Alert Rules

**config/prometheus/alerts/deschide_news.yml:**
```yaml
groups:
  - name: deschide_news_alerts
    interval: 30s
    rules:
      - alert: HighErrorRate
        expr: rate(deschide_news_http_requests_total{status_code=~"5.."}[5m]) > 0.05
        for: 5m
        labels:
          severity: critical
        annotations:
          summary: "High error rate in Deschide News"
          description: "Error rate is {{ $value }} errors per second"

      - alert: SlowResponseTime
        expr: histogram_quantile(0.95, rate(deschide_news_http_request_duration_seconds_bucket[5m])) > 1
        for: 10m
        labels:
          severity: warning
        annotations:
          summary: "Slow response times"
          description: "95th percentile response time is {{ $value }}s"

      - alert: QueueBacklog
        expr: rabbitmq_queue_messages{queue="thumbnails"} > 100
        for: 15m
        labels:
          severity: warning
        annotations:
          summary: "Large queue backlog"
          description: "{{ $value }} messages in thumbnails queue"
```

---

## 🔧 Implementation Timeline

### Sprint 0 (Current)
- ✅ PostgreSQL database creation
- ✅ Redis configuration
- ⬜ RabbitMQ exchange setup
- ⬜ Elasticsearch index creation
- ⬜ Prometheus job configuration
- ⬜ Grafana dashboard template

### Sprint 2 (Image System)
- ✅ RabbitMQ for thumbnail generation
- ✅ Systemd workers for Messenger

### Sprint 4 (Public API)
- ✅ Redis cache implementation
- ✅ Prometheus metrics endpoint
- ✅ Grafana dashboard creation

### Sprint 5 (Admin API)
- ✅ Elasticsearch indexing on publish
- ✅ Mercure notifications for breaking news

### Sprint 7 (Advanced Features)
- ✅ Full-text search via Elasticsearch
- ✅ Complete metrics collection
- ✅ Alert rules configuration

---

## 📝 Configuration Checklist

### PostgreSQL
- [ ] Create database `deschide_news`
- [ ] Create user `deschide_user`
- [ ] Update `.env` with credentials
- [ ] Run migrations
- [ ] Verify connection

### Redis
- [ ] Update `cache.yaml` with namespace
- [ ] Test cache pools
- [ ] Configure rate limiter
- [ ] Verify connection

### RabbitMQ
- [ ] Create exchange `deschide_news`
- [ ] Update `messenger.yaml`
- [ ] Create systemd workers
- [ ] Test message dispatch
- [ ] Monitor queues

### Elasticsearch
- [ ] Install elasticsearch PHP client
- [ ] Create `ElasticsearchService`
- [ ] Create index with mappings
- [ ] Test indexing
- [ ] Test search

### Mercure
- [ ] Update `mercure.yaml`
- [ ] Test publish
- [ ] Test frontend subscription
- [ ] Configure topics

### Prometheus
- [ ] Add scrape job
- [ ] Install metrics bundle
- [ ] Create `/metrics` endpoint
- [ ] Verify scraping
- [ ] Add custom metrics

### Grafana
- [ ] Create dashboard
- [ ] Add panels
- [ ] Configure alerts
- [ ] Test notifications

---

## 🔗 Quick Access URLs

### Services
- **Grafana:** http://localhost:3002
- **Prometheus:** http://localhost:9090
- **RabbitMQ Management:** http://localhost:15672
- **Elasticsearch:** https://localhost:9200
- **Mercure:** http://localhost:3000/.well-known/mercure

### Deschide News
- **Backend API:** http://api.news-app.local
- **Metrics Endpoint:** http://api.news-app.local/metrics
- **Grafana Dashboard:** (to be created in Sprint 4)

---

## 🎯 Benefits Summary

### Performance
- **Redis caching** → 10x faster API responses
- **Async processing** → Fast upload, background thumbnails
- **Elasticsearch** → Sub-second search results

### Scalability
- **RabbitMQ** → Handle millions of messages
- **Horizontal scaling** → Add more workers easily
- **Load distribution** → Queue-based processing

### Reliability
- **Prometheus monitoring** → Detect issues early
- **Grafana alerts** → Immediate notification
- **Health checks** → Know when services fail

### Developer Experience
- **Grafana dashboards** → Visual insights
- **RabbitMQ UI** → Debug message flow
- **Prometheus queries** → Analyze performance

---

**Document Status:** ✅ Ready for Implementation
**Next Steps:** Begin with Sprint 0 configurations
