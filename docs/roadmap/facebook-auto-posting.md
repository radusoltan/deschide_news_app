# Plan de Implementare: Auto-Posting Facebook pentru Articole

**Data**: 10 Noiembrie 2025
**Proiect**: Deschide News App
**Autor**: Plan generat cu Claude Code

---

## 📋 Cerințe și Obiective

### Funcționalitate Dorită
- **Auto-post articole pe Facebook** când sunt publicate
- **Suport multilingv** (ro, en, ru) cu page-uri separate sau aceeași pagină
- **Queue-based processing** folosind Symfony Messenger (async)
- **Token management** cu long-lived page access tokens
- **Retry mechanism** pentru failed posts
- **Logging și monitoring** pentru debugging
- **Admin control** - enable/disable auto-posting per article

### Referință Tehnică
Implementarea se bazează pe articolul: [Automatically Posting to a Facebook Page Using the Facebook SDK v5 for PHP](https://www.adamboother.com/blog/automatically-posting-to-a-facebook-page-using-the-facebook-sdk-v5-for-php-facebook-api/)

---

## 🏗️ Arhitectură Soluție

### 1. Facebook SDK Integration
**Package**: `facebook/graph-sdk` v5.x (recomandat în articol)

```bash
composer require facebook/graph-sdk
```

### 2. Entități Noi

#### a) `SocialMediaPost` Entity
Stochează postări făcute pe social media:
- Track status (pending, posted, failed)
- Retry logic
- Link către Article

#### b) Extension `Article` Entity
- Flag `autoPostToFacebook` (boolean)
- Relationship cu `SocialMediaPost`

### 3. Service Layer

#### Extindere `SocialMediaService`
Metode noi:
- `postArticleToFacebook(Article $article, string $locale)`
- `scheduleArticlePost(Article $article)`
- `retryFailedPost(SocialMediaPost $post)`
- `getFacebookPageToken(string $locale)` - get long-lived token by locale

#### Nou: `FacebookTokenService`
- Manage Facebook App credentials
- Exchange tokens (user → long-lived page token)
- Token storage și refresh
- Multi-page support (ro, en, ru)

### 4. Message/Handler Pattern

#### Message: `PostArticleToSocialMediaMessage`
```php
class PostArticleToSocialMediaMessage
{
    public function __construct(
        public readonly int $articleId,
        public readonly string $platform, // 'facebook', 'twitter', etc.
        public readonly string $locale
    ) {}
}
```

#### Handler: `PostArticleToSocialMediaHandler`
- Dispatch când articol devine `published`
- Async processing via RabbitMQ
- Retry failed posts (3 attempts cu exponential backoff)

### 5. Event-Driven Trigger

#### ArticleProcessor sau EventSubscriber
- Detect când `status` → `published`
- Verify `autoPostToFacebook === true`
- Dispatch `PostArticleToSocialMediaMessage` pentru fiecare locale

---

## 📝 Plan Implementare Pas cu Pas

### Ziua 1-2: Setup Facebook SDK + Token Management

#### Task 1.1: Instalare Facebook SDK
```bash
cd /var/www/deschide_news_app/deschide_backend
composer require facebook/graph-sdk
```

#### Task 1.2: Creare `FacebookTokenService`
**Fișier**: `src/Service/FacebookTokenService.php`

**Funcționalități**:
- Inițializare Facebook SDK
- Exchange user token → long-lived token
- Store tokens în database/Redis
- Multi-page support (3 page IDs pentru ro/en/ru)

**Metodele principale**:
```php
public function __construct(
    private string $appId,
    private string $appSecret,
    private string $appVersion
) {}

public function exchangeToken(string $shortLivedToken): string
public function getPageAccessToken(string $userToken, string $pageId): string
public function storePageToken(string $locale, string $token, ?DateTimeInterface $expiresAt): void
public function getStoredToken(string $locale): ?string
public function isTokenExpiringSoon(string $locale, int $daysThreshold = 7): bool
```

#### Task 1.3: Setup Facebook App
1. Creare Facebook App în https://developers.facebook.com
2. Configurare **Facebook Login** product
3. Obținere **App ID** și **App Secret**
4. Configurare **Valid OAuth Redirect URIs**
5. Add **permissions**: `pages_manage_posts`, `pages_read_engagement`

**Required Permissions**:
- `pages_manage_posts` - Post content to Pages
- `pages_read_engagement` - Read Page engagement data

#### Task 1.4: Generare Long-Lived Page Tokens
**Command**: `app:facebook:generate-token`
- Interactive command pentru OAuth flow
- Exchange user token → page token
- Store în database

**Environment variables** (`.env.local`):
```bash
# Facebook App Credentials
FACEBOOK_APP_ID=your_app_id
FACEBOOK_APP_SECRET=your_app_secret
FACEBOOK_APP_VERSION=v21.0

# Facebook Page IDs (per locale)
FACEBOOK_PAGE_ID_RO=123456789
FACEBOOK_PAGE_ID_EN=987654321
FACEBOOK_PAGE_ID_RU=456789123

# Optional: Store tokens in .env.local for testing
# Production: Use database storage
FACEBOOK_ACCESS_TOKEN_RO=stored_in_db
FACEBOOK_ACCESS_TOKEN_EN=stored_in_db
FACEBOOK_ACCESS_TOKEN_RU=stored_in_db
```

**Adăugare în `.env.example`**:
```bash
# Facebook Integration
FACEBOOK_APP_ID=your_facebook_app_id_here
FACEBOOK_APP_SECRET=your_facebook_app_secret_here
FACEBOOK_APP_VERSION=v21.0
FACEBOOK_PAGE_ID_RO=
FACEBOOK_PAGE_ID_EN=
FACEBOOK_PAGE_ID_RU=
```

---

### Ziua 3: Entități și Database

#### Task 3.1: Creare `SocialMediaPost` Entity
```bash
symfony console make:entity SocialMediaPost
```

**Proprietăți**:
```php
#[ORM\Entity(repositoryClass: SocialMediaPostRepository::class)]
#[ORM\Table(name: 'social_media_posts')]
#[ORM\Index(name: 'idx_social_status', columns: ['status'])]
#[ORM\Index(name: 'idx_social_platform_locale', columns: ['platform', 'locale'])]
class SocialMediaPost
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Article::class, inversedBy: 'socialMediaPosts')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Article $article = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $platform; // 'facebook', 'twitter', 'telegram'

    #[ORM\Column(type: Types::STRING, length: 10)]
    private string $locale; // 'ro', 'en', 'ru'

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $status = 'pending'; // pending, posting, posted, failed

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $postId = null; // Facebook post ID

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $postUrl = null; // Link către post

    #[ORM\Column(type: Types::TEXT)]
    private string $message; // Content posted

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $attemptCount = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $lastAttemptAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $postedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    // Getters and setters...
}
```

**Migration**:
```bash
symfony console make:migration
symfony console doctrine:migrations:migrate
```

#### Task 3.2: Creare `FacebookToken` Entity (optional - pentru database storage)
```bash
symfony console make:entity FacebookToken
```

**Proprietăți**:
```php
#[ORM\Entity(repositoryClass: FacebookTokenRepository::class)]
#[ORM\Table(name: 'facebook_tokens')]
#[ORM\UniqueConstraint(name: 'unique_locale', columns: ['locale'])]
class FacebookToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 10, unique: true)]
    private string $locale; // 'ro', 'en', 'ru'

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $pageId;

    #[ORM\Column(type: Types::TEXT)]
    private string $accessToken; // Consider encryption

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    // Getters and setters...
}
```

#### Task 3.3: Extindere `Article` Entity
Adăugare proprietăți:
```php
#[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
#[Groups(['article:read', 'article:write'])]
private bool $autoPostToFacebook = false;

#[ORM\OneToMany(mappedBy: 'article', targetEntity: SocialMediaPost::class, cascade: ['persist', 'remove'])]
private Collection $socialMediaPosts;

public function __construct()
{
    // ... existing code ...
    $this->socialMediaPosts = new ArrayCollection();
}

public function isAutoPostToFacebook(): bool
{
    return $this->autoPostToFacebook;
}

public function setAutoPostToFacebook(bool $autoPostToFacebook): self
{
    $this->autoPostToFacebook = $autoPostToFacebook;
    return $this;
}

public function getSocialMediaPosts(): Collection
{
    return $this->socialMediaPosts;
}

public function addSocialMediaPost(SocialMediaPost $post): self
{
    if (!$this->socialMediaPosts->contains($post)) {
        $this->socialMediaPosts->add($post);
        $post->setArticle($this);
    }
    return $this;
}
```

**Migration**:
```bash
symfony console make:migration
symfony console doctrine:migrations:migrate
```

---

### Ziua 4-5: Service Layer + Message/Handler

#### Task 4.1: Extindere `SocialMediaService`
**Fișier**: `src/Service/SocialMediaService.php`

**Adăugare dependency injection**:
```php
public function __construct(
    private readonly HttpClientInterface $httpClient,
    private readonly LoggerInterface $logger,
    private readonly FacebookTokenService $facebookTokenService,
    private readonly EntityManagerInterface $entityManager,
    private readonly string $frontendUrl,
    // ... existing dependencies ...
)
```

**Noi metode**:
```php
/**
 * Post article to Facebook page.
 */
public function postArticleToFacebook(Article $article, string $locale): SocialMediaPost
{
    // 1. Create SocialMediaPost entity with status 'posting'
    $socialPost = new SocialMediaPost();
    $socialPost->setArticle($article);
    $socialPost->setPlatform('facebook');
    $socialPost->setLocale($locale);
    $socialPost->setStatus('posting');

    try {
        // 2. Get Facebook page access token
        $pageAccessToken = $this->facebookTokenService->getStoredToken($locale);
        if (!$pageAccessToken) {
            throw new \RuntimeException("No Facebook token found for locale: {$locale}");
        }

        // 3. Build post content
        $postData = $this->buildArticlePostContent($article, $locale);
        $socialPost->setMessage($postData['message']);

        // 4. Get Facebook SDK instance
        $fb = $this->facebookTokenService->getFacebookInstance();

        // 5. Get page ID for locale
        $pageId = $this->getPageIdForLocale($locale);

        // 6. Post to Facebook Graph API
        $response = $fb->post(
            "/{$pageId}/feed",
            $postData,
            $pageAccessToken
        );

        $graphNode = $response->getGraphNode();
        $postId = $graphNode['id'];

        // 7. Update SocialMediaPost entity
        $socialPost->setStatus('posted');
        $socialPost->setPostId($postId);
        $socialPost->setPostUrl("https://www.facebook.com/{$postId}");
        $socialPost->setPostedAt(new \DateTimeImmutable());

        $this->logger->info('Facebook post created successfully', [
            'article_id' => $article->getId(),
            'locale' => $locale,
            'post_id' => $postId,
        ]);

    } catch (\Exception $e) {
        // Handle error
        $socialPost->setStatus('failed');
        $socialPost->setErrorMessage($e->getMessage());
        $socialPost->setAttemptCount($socialPost->getAttemptCount() + 1);

        $this->logger->error('Failed to post article to Facebook', [
            'article_id' => $article->getId(),
            'locale' => $locale,
            'error' => $e->getMessage(),
            'attempt' => $socialPost->getAttemptCount(),
        ]);

        throw $e;
    } finally {
        $socialPost->setLastAttemptAt(new \DateTimeImmutable());
        $this->entityManager->persist($socialPost);
        $this->entityManager->flush();
    }

    return $socialPost;
}

/**
 * Build article post content for Facebook.
 */
private function buildArticlePostContent(Article $article, string $locale): array
{
    $url = $this->getArticleUrl($article, $locale);

    // Get translated title and lead
    $title = $article->getTitle();
    $lead = $article->getLead();

    // Build message (Facebook will auto-generate preview from URL)
    $message = $title;
    if ($lead) {
        $message .= "\n\n" . $lead;
    }

    // Truncate if needed (max 500 chars for page posts)
    if (strlen($message) > 500) {
        $message = substr($message, 0, 497) . '...';
    }

    $postData = [
        'message' => $message,
        'link' => $url,
    ];

    // Add featured image if available
    $featuredImage = $this->getArticleFeaturedImage($article);
    if ($featuredImage) {
        $postData['picture'] = $featuredImage;
    }

    return $postData;
}

/**
 * Get article URL for frontend.
 */
private function getArticleUrl(Article $article, string $locale): string
{
    return sprintf(
        '%s/%s/articles/%s',
        rtrim($this->frontendUrl, '/'),
        $locale,
        $article->getSlug()
    );
}

/**
 * Get article featured image URL.
 */
private function getArticleFeaturedImage(Article $article): ?string
{
    $articleImages = $article->getArticleImages();

    foreach ($articleImages as $articleImage) {
        if ($articleImage->isFeatured()) {
            $image = $articleImage->getImage();
            if ($image) {
                // Return CDN URL
                return sprintf(
                    '%s/uploads/%s',
                    getenv('NEXT_PUBLIC_CDN_URL') ?: 'http://127.0.0.1:8082',
                    $image->getPath()
                );
            }
        }
    }

    return null;
}

/**
 * Get Facebook page ID for locale.
 */
private function getPageIdForLocale(string $locale): string
{
    return match ($locale) {
        'ro' => getenv('FACEBOOK_PAGE_ID_RO'),
        'en' => getenv('FACEBOOK_PAGE_ID_EN'),
        'ru' => getenv('FACEBOOK_PAGE_ID_RU'),
        default => throw new \InvalidArgumentException("Invalid locale: {$locale}"),
    };
}

/**
 * Retry failed social media post.
 */
public function retryFailedPost(SocialMediaPost $post): void
{
    if ($post->getAttemptCount() >= 3) {
        $this->logger->warning('Max retry attempts reached for social media post', [
            'post_id' => $post->getId(),
            'article_id' => $post->getArticle()->getId(),
        ]);
        return;
    }

    $this->postArticleToFacebook($post->getArticle(), $post->getLocale());
}
```

#### Task 4.2: Implementare `FacebookTokenService`
**Fișier**: `src/Service/FacebookTokenService.php`

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\FacebookToken;
use App\Repository\FacebookTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Facebook\Facebook;
use Facebook\Exceptions\FacebookSDKException;
use Psr\Log\LoggerInterface;

class FacebookTokenService
{
    private Facebook $facebook;

    public function __construct(
        private readonly string $appId,
        private readonly string $appSecret,
        private readonly string $appVersion,
        private readonly EntityManagerInterface $entityManager,
        private readonly FacebookTokenRepository $tokenRepository,
        private readonly LoggerInterface $logger
    ) {
        $this->facebook = new Facebook([
            'app_id' => $this->appId,
            'app_secret' => $this->appSecret,
            'default_graph_version' => $this->appVersion,
        ]);
    }

    /**
     * Get Facebook SDK instance.
     */
    public function getFacebookInstance(): Facebook
    {
        return $this->facebook;
    }

    /**
     * Exchange short-lived token for long-lived user token.
     */
    public function exchangeToken(string $shortLivedToken): string
    {
        try {
            $oAuth2Client = $this->facebook->getOAuth2Client();
            $longLivedToken = $oAuth2Client->getLongLivedAccessToken($shortLivedToken);

            $this->logger->info('Exchanged short-lived token for long-lived token');

            return $longLivedToken->getValue();
        } catch (FacebookSDKException $e) {
            $this->logger->error('Failed to exchange Facebook token', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get page access token from user token.
     */
    public function getPageAccessToken(string $userToken, string $pageId): array
    {
        try {
            $response = $this->facebook->get('/me/accounts', $userToken);
            $pages = $response->getDecodedBody()['data'] ?? [];

            foreach ($pages as $page) {
                if ($page['id'] === $pageId) {
                    return [
                        'access_token' => $page['access_token'],
                        'page_id' => $page['id'],
                        'page_name' => $page['name'],
                    ];
                }
            }

            throw new \RuntimeException("Page with ID {$pageId} not found in user's pages");
        } catch (FacebookSDKException $e) {
            $this->logger->error('Failed to get page access token', [
                'error' => $e->getMessage(),
                'page_id' => $pageId,
            ]);
            throw $e;
        }
    }

    /**
     * Store page token in database.
     */
    public function storePageToken(
        string $locale,
        string $pageId,
        string $accessToken,
        ?DateTimeInterface $expiresAt = null
    ): void {
        $token = $this->tokenRepository->findOneBy(['locale' => $locale]);

        if (!$token) {
            $token = new FacebookToken();
            $token->setLocale($locale);
            $token->setCreatedAt(new \DateTimeImmutable());
        }

        $token->setPageId($pageId);
        $token->setAccessToken($accessToken);
        $token->setExpiresAt($expiresAt ? \DateTimeImmutable::createFromInterface($expiresAt) : null);
        $token->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($token);
        $this->entityManager->flush();

        $this->logger->info('Stored Facebook page token', [
            'locale' => $locale,
            'page_id' => $pageId,
            'expires_at' => $expiresAt?->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get stored token for locale.
     */
    public function getStoredToken(string $locale): ?string
    {
        $token = $this->tokenRepository->findOneBy(['locale' => $locale]);

        if (!$token) {
            return null;
        }

        // Check if token is expired
        if ($token->getExpiresAt() && $token->getExpiresAt() < new \DateTimeImmutable()) {
            $this->logger->warning('Facebook token expired', [
                'locale' => $locale,
                'expired_at' => $token->getExpiresAt()->format('Y-m-d H:i:s'),
            ]);
            return null;
        }

        return $token->getAccessToken();
    }

    /**
     * Check if token is expiring soon.
     */
    public function isTokenExpiringSoon(string $locale, int $daysThreshold = 7): bool
    {
        $token = $this->tokenRepository->findOneBy(['locale' => $locale]);

        if (!$token || !$token->getExpiresAt()) {
            return false;
        }

        $threshold = new \DateTimeImmutable("+{$daysThreshold} days");
        return $token->getExpiresAt() < $threshold;
    }

    /**
     * Get all tokens expiring soon.
     */
    public function getTokensExpiringSoon(int $daysThreshold = 7): array
    {
        $locales = ['ro', 'en', 'ru'];
        $expiringSoon = [];

        foreach ($locales as $locale) {
            if ($this->isTokenExpiringSoon($locale, $daysThreshold)) {
                $token = $this->tokenRepository->findOneBy(['locale' => $locale]);
                $expiringSoon[] = [
                    'locale' => $locale,
                    'expires_at' => $token->getExpiresAt(),
                    'days_remaining' => $token->getExpiresAt()->diff(new \DateTimeImmutable())->days,
                ];
            }
        }

        return $expiringSoon;
    }
}
```

#### Task 4.3: Configurare Service în `services.yaml`
**Fișier**: `config/services.yaml`

```yaml
services:
    # ... existing services ...

    App\Service\FacebookTokenService:
        arguments:
            $appId: '%env(FACEBOOK_APP_ID)%'
            $appSecret: '%env(FACEBOOK_APP_SECRET)%'
            $appVersion: '%env(FACEBOOK_APP_VERSION)%'

    App\Service\SocialMediaService:
        arguments:
            $frontendUrl: '%env(NEXT_PUBLIC_API_URL)%'
            $facebookPageId: '%env(FACEBOOK_PAGE_ID_RO)%'
            $facebookAccessToken: '%env(FACEBOOK_ACCESS_TOKEN_RO)%'
            # ... other arguments ...
```

#### Task 4.4: Creare `PostArticleToSocialMediaMessage`
**Fișier**: `src/Message/PostArticleToSocialMediaMessage.php`

```php
<?php

declare(strict_types=1);

namespace App\Message;

class PostArticleToSocialMediaMessage
{
    public function __construct(
        public readonly int $articleId,
        public readonly string $platform,
        public readonly string $locale,
        public readonly int $retryCount = 0
    ) {
    }
}
```

#### Task 4.5: Creare `PostArticleToSocialMediaHandler`
**Fișier**: `src/MessageHandler/PostArticleToSocialMediaHandler.php`

```php
<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\PostArticleToSocialMediaMessage;
use App\Repository\ArticleRepository;
use App\Service\SocialMediaService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class PostArticleToSocialMediaHandler
{
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY_MS = [1000, 2000, 5000]; // Exponential backoff

    public function __construct(
        private readonly SocialMediaService $socialMediaService,
        private readonly ArticleRepository $articleRepository,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(PostArticleToSocialMediaMessage $message): void
    {
        $article = $this->articleRepository->find($message->articleId);

        if (!$article) {
            $this->logger->error('Article not found for social media posting', [
                'article_id' => $message->articleId,
            ]);
            return;
        }

        if (!$article->isAutoPostToFacebook()) {
            $this->logger->info('Article auto-posting disabled', [
                'article_id' => $message->articleId,
            ]);
            return;
        }

        try {
            if ($message->platform === 'facebook') {
                $this->socialMediaService->postArticleToFacebook(
                    $article,
                    $message->locale
                );

                $this->logger->info('Successfully posted article to Facebook', [
                    'article_id' => $message->articleId,
                    'locale' => $message->locale,
                    'attempt' => $message->retryCount + 1,
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to post article to social media', [
                'article_id' => $message->articleId,
                'platform' => $message->platform,
                'locale' => $message->locale,
                'error' => $e->getMessage(),
                'attempt' => $message->retryCount + 1,
            ]);

            // Retry logic
            if ($message->retryCount < self::MAX_RETRIES - 1) {
                $newRetryCount = $message->retryCount + 1;

                $this->logger->info('Scheduling retry for social media post', [
                    'article_id' => $message->articleId,
                    'retry_count' => $newRetryCount,
                    'delay_ms' => self::RETRY_DELAY_MS[$message->retryCount],
                ]);

                // Re-dispatch with incremented retry count
                $this->messageBus->dispatch(
                    new PostArticleToSocialMediaMessage(
                        articleId: $message->articleId,
                        platform: $message->platform,
                        locale: $message->locale,
                        retryCount: $newRetryCount
                    )
                );
            } else {
                $this->logger->error('Max retries reached for social media post', [
                    'article_id' => $message->articleId,
                    'platform' => $message->platform,
                    'locale' => $message->locale,
                ]);
            }

            // Don't throw - we've handled the error
        }
    }
}
```

---

### Ziua 6: Event Integration + Auto-Trigger

#### Task 6.1: Modificare `ArticleProcessor`
**Fișier**: `src/State/ArticleProcessor.php`

Adăugare logică în metoda `process()`:
```php
use App\Message\PostArticleToSocialMediaMessage;
use Symfony\Component\Messenger\MessageBusInterface;

public function __construct(
    // ... existing dependencies ...
    private readonly MessageBusInterface $messageBus,
) {}

public function process($data, Operation $operation, array $uriVariables = [], array $context = []): mixed
{
    // ... existing validation and processing ...

    // Check if article is being published
    $isNewlyPublished = false;
    if ($data instanceof Article) {
        $originalData = $context['previous_data'] ?? null;

        // Check if status changed to 'published'
        if ($data->getStatus() === ArticleStatus::Published) {
            if (!$originalData || $originalData->getStatus() !== ArticleStatus::Published) {
                $isNewlyPublished = true;
            }
        }
    }

    // Persist the article
    $this->entityManager->persist($data);
    $this->entityManager->flush();

    // Dispatch social media posting messages
    if ($isNewlyPublished && $data->isAutoPostToFacebook()) {
        $locales = ['ro', 'en', 'ru'];

        foreach ($locales as $locale) {
            $this->messageBus->dispatch(
                new PostArticleToSocialMediaMessage(
                    articleId: $data->getId(),
                    platform: 'facebook',
                    locale: $locale
                )
            );
        }

        $this->logger->info('Dispatched social media posting messages', [
            'article_id' => $data->getId(),
            'locales' => $locales,
        ]);
    }

    return $data;
}
```

**Alternativ**: Creare `ArticlePublishedEventSubscriber`

**Fișier**: `src/EventSubscriber/ArticlePublishedEventSubscriber.php`

```php
<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Article;
use App\Enum\ArticleStatus;
use App\Message\PostArticleToSocialMediaMessage;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsDoctrineListener(event: Events::postUpdate)]
class ArticlePublishedEventSubscriber
{
    public function __construct(
        private readonly MessageBusInterface $messageBus
    ) {
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Article) {
            return;
        }

        $changeSet = $args->getObjectManager()
            ->getUnitOfWork()
            ->getEntityChangeSet($entity);

        // Check if status changed to 'published'
        if (isset($changeSet['status'])) {
            [$oldStatus, $newStatus] = $changeSet['status'];

            if ($newStatus === ArticleStatus::Published &&
                $oldStatus !== ArticleStatus::Published &&
                $entity->isAutoPostToFacebook()) {

                // Dispatch messages for all locales
                $locales = ['ro', 'en', 'ru'];

                foreach ($locales as $locale) {
                    $this->messageBus->dispatch(
                        new PostArticleToSocialMediaMessage(
                            articleId: $entity->getId(),
                            platform: 'facebook',
                            locale: $locale
                        )
                    );
                }
            }
        }
    }
}
```

---

### Ziua 7: Commands pentru Manual Posting + Retry

#### Task 7.1: Creare Command `app:facebook:post-article`
**Fișier**: `src/Command/Facebook/PostArticleCommand.php`

```php
<?php

declare(strict_types=1);

namespace App\Command\Facebook;

use App\Repository\ArticleRepository;
use App\Service\SocialMediaService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:facebook:post-article',
    description: 'Manually post an article to Facebook'
)]
class PostArticleCommand extends Command
{
    public function __construct(
        private readonly SocialMediaService $socialMediaService,
        private readonly ArticleRepository $articleRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('article-id', InputArgument::REQUIRED, 'Article ID to post')
            ->addOption('locale', 'l', InputOption::VALUE_OPTIONAL, 'Locale (ro, en, ru)', 'ro')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force posting even if autoPostToFacebook is disabled');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $articleId = (int) $input->getArgument('article-id');
        $locale = $input->getOption('locale');
        $force = $input->getOption('force');

        $article = $this->articleRepository->find($articleId);

        if (!$article) {
            $io->error("Article with ID {$articleId} not found.");
            return Command::FAILURE;
        }

        if (!$article->isAutoPostToFacebook() && !$force) {
            $io->warning('Auto-posting is disabled for this article. Use --force to override.');
            return Command::FAILURE;
        }

        $io->info("Posting article '{$article->getTitle()}' to Facebook ({$locale})...");

        try {
            $socialPost = $this->socialMediaService->postArticleToFacebook($article, $locale);

            $io->success("Article posted successfully!");
            $io->table(
                ['Property', 'Value'],
                [
                    ['Post ID', $socialPost->getPostId()],
                    ['Post URL', $socialPost->getPostUrl()],
                    ['Status', $socialPost->getStatus()],
                    ['Posted At', $socialPost->getPostedAt()?->format('Y-m-d H:i:s')],
                ]
            );

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("Failed to post article: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
```

#### Task 7.2: Creare Command `app:facebook:retry-failed`
**Fișier**: `src/Command/Facebook/RetryFailedPostsCommand.php`

```php
<?php

declare(strict_types=1);

namespace App\Command\Facebook;

use App\Repository\SocialMediaPostRepository;
use App\Service\SocialMediaService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:facebook:retry-failed',
    description: 'Retry failed Facebook posts'
)]
class RetryFailedPostsCommand extends Command
{
    public function __construct(
        private readonly SocialMediaService $socialMediaService,
        private readonly SocialMediaPostRepository $postRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('max-retries', null, InputOption::VALUE_OPTIONAL, 'Maximum retry attempts', 3)
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Limit number of posts to retry', 10);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $maxRetries = (int) $input->getOption('max-retries');
        $limit = (int) $input->getOption('limit');

        $failedPosts = $this->postRepository->findFailedPosts($maxRetries, $limit);

        if (empty($failedPosts)) {
            $io->success('No failed posts to retry.');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Found %d failed posts to retry.', count($failedPosts)));

        $successCount = 0;
        $failureCount = 0;

        foreach ($failedPosts as $post) {
            $io->text(sprintf(
                'Retrying post #%d (Article: %d, Locale: %s, Attempts: %d)',
                $post->getId(),
                $post->getArticle()->getId(),
                $post->getLocale(),
                $post->getAttemptCount()
            ));

            try {
                $this->socialMediaService->retryFailedPost($post);
                $successCount++;
                $io->success('✓ Success');
            } catch (\Exception $e) {
                $failureCount++;
                $io->error("✗ Failed: {$e->getMessage()}");
            }
        }

        $io->table(
            ['Status', 'Count'],
            [
                ['Success', $successCount],
                ['Failed', $failureCount],
                ['Total', count($failedPosts)],
            ]
        );

        return Command::SUCCESS;
    }
}
```

**Repository method** (`SocialMediaPostRepository::findFailedPosts()`):
```php
public function findFailedPosts(int $maxAttempts = 3, int $limit = 10): array
{
    return $this->createQueryBuilder('smp')
        ->where('smp.status = :status')
        ->andWhere('smp.attemptCount < :maxAttempts')
        ->setParameter('status', 'failed')
        ->setParameter('maxAttempts', $maxAttempts)
        ->setMaxResults($limit)
        ->orderBy('smp.lastAttemptAt', 'ASC')
        ->getQuery()
        ->getResult();
}
```

#### Task 7.3: Creare Command `app:facebook:token-status`
**Fișier**: `src/Command/Facebook/TokenStatusCommand.php`

```php
<?php

declare(strict_types=1);

namespace App\Command\Facebook;

use App\Service\FacebookTokenService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:facebook:token-status',
    description: 'Check Facebook token status and expiration'
)]
class TokenStatusCommand extends Command
{
    public function __construct(
        private readonly FacebookTokenService $tokenService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $locales = ['ro', 'en', 'ru'];

        $io->title('Facebook Token Status');

        $rows = [];
        $hasExpiring = false;

        foreach ($locales as $locale) {
            $token = $this->tokenService->getStoredToken($locale);
            $isExpiring = $this->tokenService->isTokenExpiringSoon($locale, 7);

            if ($isExpiring) {
                $hasExpiring = true;
            }

            $rows[] = [
                $locale,
                $token ? '✓' : '✗',
                $isExpiring ? '⚠ Expiring soon' : '✓ Valid',
            ];
        }

        $io->table(['Locale', 'Token Present', 'Status'], $rows);

        $expiringTokens = $this->tokenService->getTokensExpiringSoon(7);
        if (!empty($expiringTokens)) {
            $io->warning('Some tokens are expiring soon:');
            foreach ($expiringTokens as $token) {
                $io->text(sprintf(
                    '  - %s: expires in %d days (%s)',
                    $token['locale'],
                    $token['days_remaining'],
                    $token['expires_at']->format('Y-m-d H:i:s')
                ));
            }

            $io->note('Run "php bin/console app:facebook:generate-token" to refresh tokens.');
        }

        return $hasExpiring ? Command::FAILURE : Command::SUCCESS;
    }
}
```

#### Task 7.4: Creare Command `app:facebook:generate-token` (Interactive)
**Fișier**: `src/Command/Facebook/GenerateTokenCommand.php`

```php
<?php

declare(strict_types=1);

namespace App\Command\Facebook;

use App\Service\FacebookTokenService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:facebook:generate-token',
    description: 'Generate and store Facebook page access token'
)]
class GenerateTokenCommand extends Command
{
    public function __construct(
        private readonly FacebookTokenService $tokenService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Facebook Page Access Token Generator');

        $io->section('Step 1: Get Short-Lived User Token');
        $io->text([
            '1. Go to: https://developers.facebook.com/tools/explorer/',
            '2. Select your app from the dropdown',
            '3. Click "Generate Access Token"',
            '4. Grant permissions: pages_manage_posts, pages_read_engagement',
            '5. Copy the generated token',
        ]);

        $helper = $this->getHelper('question');
        $question = new Question('Paste the short-lived user token: ');
        $shortLivedToken = $helper->ask($input, $output, $question);

        if (!$shortLivedToken) {
            $io->error('Token is required.');
            return Command::FAILURE;
        }

        try {
            $io->section('Step 2: Exchange for Long-Lived Token');
            $longLivedToken = $this->tokenService->exchangeToken($shortLivedToken);
            $io->success('✓ Long-lived user token obtained');

            $io->section('Step 3: Get Page Access Token');

            $localeQuestion = new ChoiceQuestion(
                'Select locale for this page:',
                ['ro', 'en', 'ru'],
                0
            );
            $locale = $helper->ask($input, $output, $localeQuestion);

            $pageIdQuestion = new Question("Enter Facebook Page ID for {$locale}: ");
            $pageId = $helper->ask($input, $output, $pageIdQuestion);

            $pageData = $this->tokenService->getPageAccessToken($longLivedToken, $pageId);

            $io->success("✓ Page access token obtained for: {$pageData['page_name']}");

            $io->section('Step 4: Store Token');
            $this->tokenService->storePageToken(
                $locale,
                $pageData['page_id'],
                $pageData['access_token'],
                null // Page tokens typically don't expire if app is in production
            );

            $io->success("✓ Token stored successfully for locale: {$locale}");

            $io->note([
                'Token has been saved to the database.',
                'You can verify with: php bin/console app:facebook:token-status',
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("Failed to generate token: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
```

---

### Ziua 8: Testing + Documentation

#### Task 8.1: Unit Tests

**Fișier**: `tests/Unit/Service/FacebookTokenServiceTest.php`

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\FacebookTokenService;
use PHPUnit\Framework\TestCase;

class FacebookTokenServiceTest extends TestCase
{
    public function testExchangeToken(): void
    {
        // Mock Facebook SDK responses
        // Test token exchange
        $this->markTestIncomplete('TODO: Implement test');
    }

    public function testGetPageAccessToken(): void
    {
        // Test page token retrieval
        $this->markTestIncomplete('TODO: Implement test');
    }

    public function testIsTokenExpiringSoon(): void
    {
        // Test expiration detection
        $this->markTestIncomplete('TODO: Implement test');
    }
}
```

**Fișier**: `tests/Unit/Service/SocialMediaServiceTest.php`

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\SocialMediaService;
use PHPUnit\Framework\TestCase;

class SocialMediaServiceTest extends TestCase
{
    public function testBuildArticlePostContent(): void
    {
        // Test post content building
        $this->markTestIncomplete('TODO: Implement test');
    }

    public function testPostArticleToFacebook(): void
    {
        // Mock Facebook Graph API
        // Test post creation
        $this->markTestIncomplete('TODO: Implement test');
    }
}
```

#### Task 8.2: Integration Tests

**Fișier**: `tests/Functional/FacebookPostingTest.php`

```php
<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Article;
use App\Enum\ArticleStatus;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class FacebookPostingTest extends KernelTestCase
{
    public function testArticlePublishTriggersMessage(): void
    {
        // Create article
        // Set autoPostToFacebook = true
        // Publish article
        // Assert message dispatched
        $this->markTestIncomplete('TODO: Implement test');
    }

    public function testFailedPostRetry(): void
    {
        // Create failed social post
        // Trigger retry
        // Assert retry attempts
        $this->markTestIncomplete('TODO: Implement test');
    }
}
```

#### Task 8.3: Documentație
**Fișier**: `docs/facebook-auto-posting.md`

```markdown
# Facebook Auto-Posting Documentation

## Overview
Automatic posting of published articles to Facebook pages with multi-language support.

## Setup Guide

### 1. Create Facebook App
1. Go to https://developers.facebook.com
2. Create new app (type: Business)
3. Add Facebook Login product
4. Configure OAuth redirect URIs
5. Get App ID and App Secret

### 2. Configure Environment
Add to `.env.local`:
```bash
FACEBOOK_APP_ID=your_app_id
FACEBOOK_APP_SECRET=your_app_secret
FACEBOOK_APP_VERSION=v21.0
FACEBOOK_PAGE_ID_RO=123456789
FACEBOOK_PAGE_ID_EN=987654321
FACEBOOK_PAGE_ID_RU=456789123
```

### 3. Generate Page Tokens
```bash
php bin/console app:facebook:generate-token
```

Follow interactive prompts for each locale.

### 4. Enable Auto-Posting
Set `autoPostToFacebook = true` on Article entity via API or admin panel.

## Commands

### Post Article Manually
```bash
php bin/console app:facebook:post-article 123 --locale=ro
```

### Check Token Status
```bash
php bin/console app:facebook:token-status
```

### Retry Failed Posts
```bash
php bin/console app:facebook:retry-failed --limit=10
```

## Troubleshooting

### Token Expired
Run `php bin/console app:facebook:generate-token` to refresh.

### Post Failed
Check logs: `var/log/dev.log`
Retry manually: `php bin/console app:facebook:post-article <id>`

### Rate Limits
Facebook limits: 200 calls/hour per user, 4800/day per app.
Implement delays if hitting limits.

## Architecture

- **Async Processing**: Messages dispatched via Symfony Messenger
- **Retry Logic**: 3 attempts with exponential backoff
- **Multi-Locale**: Separate page IDs per locale
- **Token Storage**: Database with encryption recommended

## Security

- Never commit tokens to git
- Store tokens encrypted in database
- Rotate tokens every 60 days
- Monitor token expiration with cron job
```

#### Task 8.4: Update main CLAUDE.md
Add section about Facebook integration to `/var/www/deschide_news_app/CLAUDE.md`

---

## 🔧 Configurare Messenger Transport

**Fișier**: `config/packages/messenger.yaml`

```yaml
framework:
    messenger:
        failure_transport: failed

        transports:
            async:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                retry_strategy:
                    max_retries: 3
                    delay: 1000
                    multiplier: 2
                    max_delay: 10000

            failed: 'doctrine://default?queue_name=failed'

        routing:
            App\Message\PostArticleToSocialMediaMessage: async
            # Other messages...
```

**Start worker**:
```bash
# Development
symfony console messenger:consume async -vv

# Production (with PM2)
pm2 start "symfony console messenger:consume async --time-limit=3600" --name deschide-messenger
```

---

## 🔐 Facebook Token Lifecycle

### Tipuri de Tokens

1. **User Access Token** (short-lived, 1-2 ore)
   - Obținut prin OAuth flow
   - Folosit pentru a obține page token

2. **Long-Lived User Token** (60 zile)
   - Exchange via Graph API:
   ```
   GET /oauth/access_token?
       grant_type=fb_exchange_token&
       client_id={app-id}&
       client_secret={app-secret}&
       fb_exchange_token={short-lived-token}
   ```

3. **Page Access Token** (60 zile sau never-expiring)
   - Get via: `GET /{user-id}/accounts`
   - **Acesta e token-ul pe care îl stocăm și îl folosim**
   - For published apps, page tokens may not expire

### Token Refresh Strategy

**Option 1: Manual Refresh (Recommended for start)**
- Set up cron job to check expiration weekly
- Alert admin via email when token expires in < 7 days
- Manual refresh via command

**Option 2: Automated Refresh (Future enhancement)**
- Implement OAuth refresh flow
- Auto-refresh tokens when expiring
- Requires user interaction for initial grant

### Cron Job for Token Monitoring
```bash
# Run weekly on Monday at 9 AM
0 9 * * 1 cd /var/www/deschide_news_app/deschide_backend && php bin/console app:facebook:token-status
```

---

## 📊 Monitoring și Logging

### Metrics (Prometheus Integration)

Add to `src/Service/MetricsService.php`:

```php
// Counter for posts
$this->prometheusClient->getOrRegisterCounter(
    'deschide',
    'facebook_posts_total',
    'Total Facebook posts',
    ['status', 'locale']
)->inc(['status' => 'success', 'locale' => 'ro']);

// Histogram for post duration
$this->prometheusClient->getOrRegisterHistogram(
    'deschide',
    'facebook_post_duration_seconds',
    'Facebook post duration',
    ['locale']
)->observe($duration, ['locale' => 'ro']);

// Gauge for token expiry
$this->prometheusClient->getOrRegisterGauge(
    'deschide',
    'facebook_token_expiry_days',
    'Days until Facebook token expires',
    ['locale']
)->set($daysUntilExpiry, ['locale' => 'ro']);
```

### Logs

**Info logs**:
```php
$this->logger->info('Facebook post created', [
    'article_id' => $article->getId(),
    'article_title' => $article->getTitle(),
    'locale' => $locale,
    'post_id' => $postId,
    'post_url' => $postUrl,
]);
```

**Error logs**:
```php
$this->logger->error('Facebook post failed', [
    'article_id' => $article->getId(),
    'locale' => $locale,
    'error' => $e->getMessage(),
    'error_code' => $e->getCode(),
    'attempt' => $attemptCount,
    'max_attempts' => 3,
]);
```

**Warning logs**:
```php
$this->logger->warning('Facebook token expiring soon', [
    'locale' => $locale,
    'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
    'days_remaining' => $daysRemaining,
]);
```

---

## ⚠️ Considerații Importante

### 1. Facebook Rate Limits
- **200 calls/hour** per user token
- **4800 calls/day** per app
- Implement rate limiting în service:
  ```php
  // Use Redis to track request count
  $key = "facebook:rate_limit:{$locale}:" . date('YmdH');
  $count = $redis->incr($key);
  if ($count > 190) {
      throw new RateLimitException("Facebook rate limit approaching");
  }
  $redis->expire($key, 3600);
  ```

### 2. Token Security
- **NEVER commit** tokens to git (use `.gitignore`)
- **Encrypt tokens** in database using `paragonie/halite` or similar
- **Rotate tokens** every 60 days
- **Monitor access** logs for suspicious activity
- Consider using AWS Secrets Manager or similar for production

### 3. Content Policy Compliance
- Respect [Facebook Community Standards](https://transparency.fb.com/policies/community-standards/)
- Avoid spam (max 1 post per article)
- Check for duplicate posts before creating
- Don't post misleading or clickbait content
- Include proper attribution and sources

### 4. Error Handling
- Catch `FacebookResponseException` for API errors
- Log errors with full context (article ID, locale, message)
- Implement exponential backoff for retries (1s, 2s, 5s)
- Alert admin on repeated failures
- Don't block article publishing if social post fails

### 5. Multilanguage Strategy

**Option A: Separate Pages per Locale**
- 3 different Facebook pages (recommended)
- Each locale posts to its own page
- Better audience targeting
- Requires 3 page access tokens

**Option B: Single Page, Multiple Languages**
- 1 Facebook page with posts in all languages
- May confuse followers
- Easier token management
- Less recommended for user experience

**Recommendation**: Use separate pages (Option A)

### 6. Image Handling
- Facebook supports images up to 8MB
- Recommended: 1200x630px (OG image size)
- Use CDN URLs for images
- Ensure images are publicly accessible
- Fallback to default image if none available

### 7. Testing Strategy
- Use [Facebook Graph API Explorer](https://developers.facebook.com/tools/explorer/) for testing
- Create test page for development
- Use `.env.test` with test credentials
- Mock Facebook SDK in unit tests
- Integration tests with real API (separate test page)

---

## 🎯 Rezultat Final

După implementarea completă, aplicația va avea:

✅ **Auto-posting** când articol devine `published`
✅ **Async processing** via RabbitMQ Messenger
✅ **Retry mechanism** pentru failed posts (3 attempts)
✅ **Multi-locale support** (ro, en, ru) cu pagini separate
✅ **Token management** cu long-lived page tokens
✅ **Monitoring** via logs + Prometheus metrics
✅ **Admin commands** pentru manual control și debugging
✅ **Admin control** per article (flag `autoPostToFacebook`)
✅ **Database tracking** (`SocialMediaPost` entity)
✅ **Error handling** cu logging detaliat
✅ **Security** cu token encryption și expiry monitoring

---

## 📅 Timeline Estimat

| Ziua | Tasks | Ore Estimate |
|------|-------|--------------|
| 1-2 | Facebook SDK + Token Service | 8-12h |
| 3 | Entități și database | 4-6h |
| 4-5 | Service layer + Message/Handler | 8-10h |
| 6 | Event integration + auto-trigger | 4-6h |
| 7 | Commands | 4-6h |
| 8 | Testing + Documentation | 6-8h |
| **Total** | | **34-48h** |

---

## 🔄 Post-Implementation Tasks

### Maintenance
- [ ] Setup cron job pentru token monitoring
- [ ] Configure alerts pentru failed posts
- [ ] Monitor Facebook API usage și rate limits
- [ ] Review logs weekly pentru errors

### Future Enhancements
- [ ] Auto-refresh tokens (OAuth flow)
- [ ] Schedule posts for specific times
- [ ] A/B testing pentru post formats
- [ ] Analytics integration (track clicks, engagement)
- [ ] Support pentru Instagram (Facebook Graph API)
- [ ] Support pentru video posts
- [ ] Custom post templates per category

---

## 📚 Resurse Utile

- [Facebook Graph API Documentation](https://developers.facebook.com/docs/graph-api)
- [Facebook SDK for PHP v5](https://github.com/facebookarchive/php-graph-sdk)
- [Facebook Pages API](https://developers.facebook.com/docs/pages)
- [Access Token Debugger](https://developers.facebook.com/tools/debug/accesstoken/)
- [Symfony Messenger Documentation](https://symfony.com/doc/current/messenger.html)

---

**Document creat**: 10 Noiembrie 2025
**Versiune**: 1.0
**Status**: ✅ Plan complet, gata pentru implementare
