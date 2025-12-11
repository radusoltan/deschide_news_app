# Strategie Serviciu Publicare Rețele Sociale - Deschide News


https://graph.facebook.com/oauth/access_token?grant_type=fb_exchange_token&client_id=367676820233510&client_secret=63d933d6ec13eb1548f04e6a7d9dcf55&fb_exchange_token=EAAFOZAm5DHSYBPz8YF5fL7v77z9AGVIz6WjxHyYzDc8BEVbiY7FeVFZC4Sezuc8lPSqXkEkCZAnKVpTAIMsyivp1CJi0Qp30auRaMUSKbG9MIWzqZBBPMIUrSl5RC1PspWcnh8etpeEMTMWJ4cUkIXUtJZBOcSlN4puyNKZA69CD1z7pcY6eW1ONjZBz1PrEzZBgiteCiaZCUnzkbGaqKInsnCqs8L2ZBt13VokTp2dRcElwbPjbjYJQoZD



**Data**: 9 Noiembrie 2025
**Status**: 📋 Analiză & Planificare
**Complexitate**: 🟡 Medie-Mare (3-5 săptămâni implementare completă)

---

## 📋 Cuprins

1. [Analiza Cerințelor](#analiza-cerințelor)
2. [Opțiuni de Integrare](#opțiuni-de-integrare)
3. [Arhitectură Recomandată](#arhitectură-recomandată)
4. [Plan de Implementare](#plan-de-implementare)
5. [Complexitate & Timeline](#complexitate--timeline)
6. [Costuri & Dependențe](#costuri--dependențe)
7. [Provocări & Soluții](#provocări--soluții)
8. [Întrebări de Decizie](#întrebări-de-decizie)

---

## Analiza Cerințelor

### 1. Platforme Sociale Target

| Platformă | Prioritate | API Disponibil | Complexitate | Status OAuth |
|-----------|------------|----------------|--------------|--------------|
| **Facebook** | 🔴 Mare | ✅ Graph API | 🟡 Medie | OAuth 2.0 |
| **Instagram** | 🔴 Mare | ✅ Graph API | 🟡 Medie | OAuth 2.0 (via Facebook) |
| **Twitter/X** | 🟠 Medie | ✅ API v2 | 🟡 Medie | OAuth 2.0 |
| **LinkedIn** | 🟠 Medie | ✅ Share API | 🟢 Ușoară | OAuth 2.0 |
| **Telegram** | 🟡 Opțional | ✅ Bot API | 🟢 Ușoară | Bot Token |
| **WhatsApp** | 🟡 Opțional | ✅ Business API | 🔴 Mare | Cont Business |

**Recomandare**: Start cu Facebook + Instagram (aceeași integrare), apoi Twitter/X și LinkedIn.

### 2. Funcționalități Necesare

#### ✅ Nivel 1 - Publicare Simplă (MVP)
- Publicare manuală articol pe o platformă selectată
- Preview înainte de publicare
- Format: Titlu + Lead + Link + Imagine
- Tracking post ID (pentru statistici viitoare)

#### ✅ Nivel 2 - Publicare Automată
- Publicare automată la publicarea articolului
- Configurare per categorie (care categorii se auto-publică)
- Scheduling (publicare programată)
- Template-uri personalizabile pentru fiecare platformă

#### ✅ Nivel 3 - Management Avansat
- Publicare pe multiple platforme simultan
- Crossposting cu conținut adaptat per platformă
- Draft-uri de social media post
- Republicare articole vechi

#### ✅ Nivel 4 - Analytics & Engagement
- Statistici engagement (likes, shares, comments)
- Răspunsuri la comentarii din admin panel
- A/B testing pentru titluri
- Optimizare orar publicare

### 3. Structură Date Necesară

```php
// Entitate: SocialMediaAccount
- id
- platform (enum: FACEBOOK, INSTAGRAM, TWITTER, LINKEDIN, TELEGRAM)
- accountName
- accountId (platform-specific ID)
- accessToken (encrypted)
- refreshToken (encrypted)
- tokenExpiresAt
- isActive
- createdAt
- updatedAt

// Entitate: SocialMediaPost
- id
- article (ManyToOne → Article)
- account (ManyToOne → SocialMediaAccount)
- platform
- postContent (text customizat)
- postUrl (link către postul publicat)
- platformPostId (ID-ul postului pe platformă)
- status (enum: DRAFT, SCHEDULED, PUBLISHED, FAILED)
- scheduledAt
- publishedAt
- errorMessage
- engagement (JSON: likes, shares, comments)
- createdAt
- updatedAt

// Entitate: SocialMediaTemplate
- id
- platform
- category (optional - template per categorie)
- titleTemplate ("{title}")
- bodyTemplate ("{lead}\n\nCitește mai mult: {url}")
- hashtags
- isDefault
- createdAt
- updatedAt
```

---

## Opțiuni de Integrare

### 📦 Opțiunea 1: Implementare Custom cu SDK-uri Oficiale

**Pachete PHP Necesare:**
```json
{
  "require": {
    "facebook/graph-sdk": "^6.0",           // Facebook + Instagram
    "abraham/twitteroauth": "^5.0",         // Twitter/X OAuth
    "happyr/linkedin-api-client": "^2.0",   // LinkedIn
    "longman/telegram-bot": "^0.80"         // Telegram (optional)
  }
}
```

**Avantaje:**
- ✅ Control complet asupra integrării
- ✅ Costuri zero pentru pachete (open source)
- ✅ Flexibilitate maximă pentru customizare
- ✅ Fără dependență de servicii terțe

**Dezavantaje:**
- ❌ Timp de implementare: 3-5 săptămâni
- ❌ Necesită mentenanță pentru fiecare API
- ❌ Rate limits & error handling manual
- ❌ Actualizări API trebuie monitorizate

**Complexitate:** 🟡 Medie-Mare
**Timeline:** 3-5 săptămâni (60-100 ore)
**Cost:** €0 (doar dev time)

---

### 📦 Opțiunea 2: Serviciu SaaS (Buffer, Hootsuite, Later)

**Servicii Disponibile:**

| Serviciu | Cost/lună | Platforme | API Disponibil | Limite |
|----------|-----------|-----------|----------------|--------|
| **Buffer** | $5-$25 | FB, IG, TW, LI | ✅ API | 10-100 posts/lună |
| **Hootsuite** | $49-$249 | Toate | ✅ API | Unlimited posts |
| **Later** | $12.50-$53 | FB, IG, TW | ✅ API | 30-unlimited |
| **Agorapulse** | €49-€149 | Toate | ✅ API | Unlimited |

**Avantaje:**
- ✅ Implementare rapidă (1-2 săptămâni)
- ✅ Analytics incluse
- ✅ UI pentru management
- ✅ Mentenanță asigurată de provider

**Dezavantaje:**
- ❌ Costuri recurente (€50-250/lună)
- ❌ Dependență de serviciu terț
- ❌ Limite API (vary per plan)
- ❌ Customizare limitată

**Complexitate:** 🟢 Ușoară
**Timeline:** 1-2 săptămâni (20-30 ore)
**Cost:** €50-250/lună (€600-3,000/an)

---

### 📦 Opțiunea 3: Hybridă (OAuth Custom + Publicare via API-uri Native)

**Implementare:**
- OAuth 2.0 flow custom pentru autentificare
- Symfony HTTP Client pentru API calls
- Symfony Messenger pentru procesare async
- Redis pentru rate limiting & caching
- Cron jobs pentru retry failed posts

**Pachete Necesare:**
```bash
composer require symfony/http-client
composer require symfony/messenger
# Eventual KNP OAuth2 Client pentru flow-ul OAuth
composer require knpuniversity/oauth2-client-bundle
```

**Avantaje:**
- ✅ No recurring costs (doar API-uri gratuite)
- ✅ Control complet
- ✅ Integrare nativă în aplicație
- ✅ Async processing cu Messenger

**Dezavantaje:**
- ❌ Complexitate OAuth flow pentru fiecare platformă
- ❌ Gestionare rate limits manual
- ❌ Testing extensiv necesar

**Complexitate:** 🟡 Medie
**Timeline:** 2-4 săptămâni (50-80 ore)
**Cost:** €0 (doar dev time)

---

## Arhitectură Recomandată

### 🏆 Recomandare: Opțiunea 3 (Hybridă - OAuth + API Native)

#### De ce?
1. **Zero costuri recurente** - Important pentru scalabilitate
2. **Integrare nativă** - Se integrează perfect cu Symfony Messenger
3. **Control total** - Customizare completă pentru nevoile business
4. **Experiență utilizator** - Totul în admin panel, fără tool-uri externe

### Arhitectura Tehnică

```
┌─────────────────────────────────────────────────────────────┐
│                    ADMIN PANEL (Frontend)                    │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │ Connect      │  │ Publish      │  │ Templates    │      │
│  │ Accounts     │  │ Article      │  │ Management   │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
└─────────────────────────────────────────────────────────────┘
                            ↓ API calls
┌─────────────────────────────────────────────────────────────┐
│                   BACKEND (Symfony 7.3)                      │
│  ┌──────────────────────────────────────────────────────┐   │
│  │            SocialMediaService                        │   │
│  │  - OAuth flow (connect/disconnect accounts)         │   │
│  │  - Token refresh automation                          │   │
│  │  - Platform factory (strategie pentru fiecare API)  │   │
│  └──────────────────────────────────────────────────────┘   │
│                            ↓                                 │
│  ┌──────────────────────────────────────────────────────┐   │
│  │         Symfony Messenger (Async)                    │   │
│  │  - PublishToSocialMediaMessage                       │   │
│  │  - RefreshSocialTokenMessage                         │   │
│  │  - FetchEngagementStatsMessage                       │   │
│  └──────────────────────────────────────────────────────┘   │
│                            ↓                                 │
│  ┌──────────────────────────────────────────────────────┐   │
│  │         Platform Adapters (Strategy Pattern)         │   │
│  │  - FacebookAdapter  (Graph API v19)                  │   │
│  │  - InstagramAdapter (Graph API v19)                  │   │
│  │  - TwitterAdapter   (API v2)                         │   │
│  │  - LinkedInAdapter  (Share API v2)                   │   │
│  └──────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
                            ↓ Store data
┌─────────────────────────────────────────────────────────────┐
│                   PostgreSQL Database                        │
│  - social_media_accounts (OAuth tokens, encrypted)          │
│  - social_media_posts (tracking publicări)                  │
│  - social_media_templates (template-uri per platformă)      │
└─────────────────────────────────────────────────────────────┘
                            ↓ Cache & Queue
┌─────────────────────────────────────────────────────────────┐
│                          Redis                               │
│  - Rate limiting counters                                    │
│  - Cached access tokens                                      │
│  - Job queue (Messenger transport)                           │
└─────────────────────────────────────────────────────────────┘
```

### Componente Principale

#### 1. **SocialMediaService** (Core Business Logic)

```php
// src/Service/SocialMediaService.php
namespace App\Service;

class SocialMediaService
{
    public function __construct(
        private SocialMediaAccountRepository $accountRepo,
        private SocialMediaPostRepository $postRepo,
        private PlatformAdapterFactory $adapterFactory,
        private MessageBusInterface $messageBus,
        private EncryptionService $encryption
    ) {}

    /**
     * Connect new social media account (OAuth flow)
     */
    public function connectAccount(
        SocialMediaPlatform $platform,
        string $code
    ): SocialMediaAccount {
        $adapter = $this->adapterFactory->create($platform);

        // Exchange code for access token
        $tokenData = $adapter->getAccessToken($code);

        // Create account record
        $account = new SocialMediaAccount();
        $account->setPlatform($platform);
        $account->setAccessToken($this->encryption->encrypt($tokenData['access_token']));
        $account->setRefreshToken($this->encryption->encrypt($tokenData['refresh_token']));
        $account->setTokenExpiresAt(new \DateTimeImmutable('+' . $tokenData['expires_in'] . ' seconds'));

        $this->accountRepo->save($account);

        return $account;
    }

    /**
     * Publish article to social media
     */
    public function publishArticle(
        Article $article,
        SocialMediaAccount $account,
        ?string $customContent = null,
        ?DateTimeImmutable $scheduledAt = null
    ): SocialMediaPost {
        $post = new SocialMediaPost();
        $post->setArticle($article);
        $post->setAccount($account);
        $post->setPlatform($account->getPlatform());
        $post->setStatus(SocialMediaPostStatus::DRAFT);

        // Generate content from template if not custom
        if (!$customContent) {
            $template = $this->templateRepo->findDefaultForPlatform($account->getPlatform());
            $customContent = $this->generateContentFromTemplate($article, $template);
        }
        $post->setPostContent($customContent);

        if ($scheduledAt) {
            $post->setScheduledAt($scheduledAt);
            $post->setStatus(SocialMediaPostStatus::SCHEDULED);
        }

        $this->postRepo->save($post);

        // Dispatch async job
        $this->messageBus->dispatch(new PublishToSocialMediaMessage($post->getId()));

        return $post;
    }

    /**
     * Publish to multiple platforms
     */
    public function publishToMultiplePlatforms(
        Article $article,
        array $accountIds,
        ?array $customContents = null
    ): array {
        $posts = [];

        foreach ($accountIds as $index => $accountId) {
            $account = $this->accountRepo->find($accountId);
            $content = $customContents[$index] ?? null;

            $posts[] = $this->publishArticle($article, $account, $content);
        }

        return $posts;
    }
}
```

#### 2. **Platform Adapters** (Strategy Pattern)

```php
// src/Service/SocialMedia/PlatformAdapterInterface.php
namespace App\Service\SocialMedia;

interface PlatformAdapterInterface
{
    public function getOAuthUrl(): string;
    public function getAccessToken(string $code): array;
    public function refreshAccessToken(string $refreshToken): array;
    public function publish(SocialMediaPost $post, string $accessToken): array;
    public function deletePost(string $postId, string $accessToken): bool;
    public function getEngagementStats(string $postId, string $accessToken): array;
}

// src/Service/SocialMedia/Adapter/FacebookAdapter.php
namespace App\Service\SocialMedia\Adapter;

use Facebook\Facebook;

class FacebookAdapter implements PlatformAdapterInterface
{
    private Facebook $fb;

    public function __construct(
        string $appId,
        string $appSecret,
        string $callbackUrl
    ) {
        $this->fb = new Facebook([
            'app_id' => $appId,
            'app_secret' => $appSecret,
            'default_graph_version' => 'v19.0',
        ]);
    }

    public function getOAuthUrl(): string
    {
        $helper = $this->fb->getRedirectLoginHelper();
        return $helper->getLoginUrl(
            $this->callbackUrl,
            ['pages_manage_posts', 'pages_read_engagement']
        );
    }

    public function publish(SocialMediaPost $post, string $accessToken): array
    {
        $article = $post->getArticle();
        $featuredImage = $article->getArticleImages()->first();

        $data = [
            'message' => $post->getPostContent(),
            'link' => $this->generateArticleUrl($article),
        ];

        if ($featuredImage) {
            $data['picture'] = $this->generateImageUrl($featuredImage->getImage());
        }

        $response = $this->fb->post('/me/feed', $data, $accessToken);
        $graphNode = $response->getGraphNode();

        return [
            'post_id' => $graphNode['id'],
            'post_url' => "https://facebook.com/{$graphNode['id']}",
        ];
    }

    public function getEngagementStats(string $postId, string $accessToken): array
    {
        $response = $this->fb->get(
            "/{$postId}?fields=likes.summary(true),comments.summary(true),shares",
            $accessToken
        );

        $data = $response->getGraphNode();

        return [
            'likes' => $data['likes']['summary']['total_count'] ?? 0,
            'comments' => $data['comments']['summary']['total_count'] ?? 0,
            'shares' => $data['shares']['count'] ?? 0,
        ];
    }
}
```

#### 3. **Messenger Handler** (Async Processing)

```php
// src/Message/PublishToSocialMediaMessage.php
namespace App\Message;

class PublishToSocialMediaMessage
{
    public function __construct(
        private int $postId
    ) {}

    public function getPostId(): int
    {
        return $this->postId;
    }
}

// src/MessageHandler/PublishToSocialMediaHandler.php
namespace App\MessageHandler;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class PublishToSocialMediaHandler
{
    public function __construct(
        private SocialMediaPostRepository $postRepo,
        private PlatformAdapterFactory $adapterFactory,
        private EncryptionService $encryption,
        private LoggerInterface $logger
    ) {}

    public function __invoke(PublishToSocialMediaMessage $message): void
    {
        $post = $this->postRepo->find($message->getPostId());

        if (!$post) {
            throw new \RuntimeException("Post {$message->getPostId()} not found");
        }

        // Check if scheduled
        if ($post->getStatus() === SocialMediaPostStatus::SCHEDULED) {
            if ($post->getScheduledAt() > new \DateTimeImmutable()) {
                // Not yet time to publish
                return;
            }
        }

        try {
            $account = $post->getAccount();
            $adapter = $this->adapterFactory->create($account->getPlatform());

            // Decrypt access token
            $accessToken = $this->encryption->decrypt($account->getAccessToken());

            // Check token expiration
            if ($account->getTokenExpiresAt() < new \DateTimeImmutable()) {
                // Refresh token
                $refreshToken = $this->encryption->decrypt($account->getRefreshToken());
                $newTokenData = $adapter->refreshAccessToken($refreshToken);

                $account->setAccessToken($this->encryption->encrypt($newTokenData['access_token']));
                $account->setTokenExpiresAt(new \DateTimeImmutable('+' . $newTokenData['expires_in'] . ' seconds'));
                $this->accountRepo->save($account);

                $accessToken = $newTokenData['access_token'];
            }

            // Publish to platform
            $result = $adapter->publish($post, $accessToken);

            // Update post status
            $post->setStatus(SocialMediaPostStatus::PUBLISHED);
            $post->setPublishedAt(new \DateTimeImmutable());
            $post->setPlatformPostId($result['post_id']);
            $post->setPostUrl($result['post_url']);

            $this->logger->info("Published post {$post->getId()} to {$account->getPlatform()->value}");

        } catch (\Exception $e) {
            $post->setStatus(SocialMediaPostStatus::FAILED);
            $post->setErrorMessage($e->getMessage());

            $this->logger->error("Failed to publish post {$post->getId()}: {$e->getMessage()}");
        }

        $this->postRepo->save($post);
    }
}
```

#### 4. **API Endpoints** (Admin Panel Integration)

```php
// src/Controller/Admin/SocialMediaController.php
namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/admin/social-media', name: 'admin_social_media_')]
class SocialMediaController extends AbstractController
{
    /**
     * Get OAuth URL to connect account
     */
    #[Route('/connect/{platform}', name: 'connect', methods: ['GET'])]
    public function getOAuthUrl(
        SocialMediaPlatform $platform,
        PlatformAdapterFactory $adapterFactory
    ): JsonResponse {
        $adapter = $adapterFactory->create($platform);
        $url = $adapter->getOAuthUrl();

        return $this->json(['oauth_url' => $url]);
    }

    /**
     * OAuth callback - save account
     */
    #[Route('/callback/{platform}', name: 'callback', methods: ['GET'])]
    public function handleCallback(
        SocialMediaPlatform $platform,
        Request $request,
        SocialMediaService $socialMediaService
    ): JsonResponse {
        $code = $request->query->get('code');

        if (!$code) {
            return $this->json(['error' => 'No code provided'], 400);
        }

        $account = $socialMediaService->connectAccount($platform, $code);

        return $this->json([
            'message' => 'Account connected successfully',
            'account' => [
                'id' => $account->getId(),
                'platform' => $account->getPlatform()->value,
                'accountName' => $account->getAccountName(),
            ]
        ]);
    }

    /**
     * List all connected accounts
     */
    #[Route('/accounts', name: 'accounts_list', methods: ['GET'])]
    public function listAccounts(
        SocialMediaAccountRepository $accountRepo
    ): JsonResponse {
        $accounts = $accountRepo->findBy(['isActive' => true]);

        return $this->json($accounts, 200, [], [
            'groups' => ['social_account:read']
        ]);
    }

    /**
     * Publish article to social media
     */
    #[Route('/publish', name: 'publish', methods: ['POST'])]
    public function publishArticle(
        Request $request,
        SocialMediaService $socialMediaService,
        ArticleRepository $articleRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $article = $articleRepo->find($data['article_id']);
        $accountIds = $data['account_ids'];
        $customContents = $data['custom_contents'] ?? null;
        $scheduledAt = isset($data['scheduled_at'])
            ? new \DateTimeImmutable($data['scheduled_at'])
            : null;

        $posts = $socialMediaService->publishToMultiplePlatforms(
            $article,
            $accountIds,
            $customContents
        );

        return $this->json([
            'message' => 'Publishing initiated',
            'posts' => array_map(fn($p) => ['id' => $p->getId(), 'status' => $p->getStatus()->value], $posts)
        ]);
    }

    /**
     * Get publishing history for article
     */
    #[Route('/posts/article/{id}', name: 'posts_by_article', methods: ['GET'])]
    public function getPostsByArticle(
        Article $article,
        SocialMediaPostRepository $postRepo
    ): JsonResponse {
        $posts = $postRepo->findBy(['article' => $article], ['publishedAt' => 'DESC']);

        return $this->json($posts, 200, [], [
            'groups' => ['social_post:read']
        ]);
    }
}
```

---

## Plan de Implementare

### 📅 FAZA 1: Setup & Infrastructură (1 săptămână, 15-20 ore)

#### 1.1 Database Schema & Entities
**Timeline**: 2-3 ore

```bash
# Create entities
symfony console make:entity SocialMediaAccount
symfony console make:entity SocialMediaPost
symfony console make:entity SocialMediaTemplate

# Create enums
# src/Enum/SocialMediaPlatform.php
# src/Enum/SocialMediaPostStatus.php

# Create migration
symfony console make:migration
symfony console doctrine:migrations:migrate
```

**Entități create:**
- `SocialMediaAccount` (conturi conectate)
- `SocialMediaPost` (tracking posts)
- `SocialMediaTemplate` (template-uri)

#### 1.2 Composer Dependencies
**Timeline**: 1 oră

```bash
# Install required packages
composer require facebook/graph-sdk:^6.0
composer require abraham/twitteroauth:^5.0
composer require happyr/linkedin-api-client:^2.0
composer require knpuniversity/oauth2-client-bundle

# Encryption service (pentru token storage)
composer require paragonie/halite
```

#### 1.3 Environment Variables
**Timeline**: 1 oră

```bash
# .env.local
# Facebook App
FACEBOOK_APP_ID=your_app_id
FACEBOOK_APP_SECRET=your_app_secret
FACEBOOK_CALLBACK_URL=http://127.0.0.1:8081/api/admin/social-media/callback/facebook

# Twitter/X App
TWITTER_API_KEY=your_api_key
TWITTER_API_SECRET=your_api_secret
TWITTER_CALLBACK_URL=http://127.0.0.1:8081/api/admin/social-media/callback/twitter

# LinkedIn App
LINKEDIN_CLIENT_ID=your_client_id
LINKEDIN_CLIENT_SECRET=your_client_secret
LINKEDIN_CALLBACK_URL=http://127.0.0.1:8081/api/admin/social-media/callback/linkedin

# Encryption key (generate with: symfony console app:generate-encryption-key)
SOCIAL_MEDIA_ENCRYPTION_KEY=generated_key_here
```

#### 1.4 Messenger Configuration
**Timeline**: 2 ore

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            social_media: '%env(MESSENGER_TRANSPORT_DSN)%'

        routing:
            'App\Message\PublishToSocialMediaMessage': social_media
            'App\Message\RefreshSocialTokenMessage': social_media
            'App\Message\FetchEngagementStatsMessage': social_media
```

**Deliverable FAZA 1:**
- ✅ 3 entități create + migrations
- ✅ Dependencies instalate
- ✅ Environment variables configurate
- ✅ Messenger routing configurat

---

### 📅 FAZA 2: Core Services & Adapters (1.5 săptămâni, 25-30 ore)

#### 2.1 Encryption Service
**Timeline**: 3 ore

```php
// src/Service/EncryptionService.php
namespace App\Service;

use ParagonIE\Halite\Symmetric\Crypto;
use ParagonIE\Halite\Symmetric\EncryptionKey;
use ParagonIE\HiddenString\HiddenString;

class EncryptionService
{
    private EncryptionKey $key;

    public function __construct(string $encryptionKey)
    {
        $this->key = new EncryptionKey(new HiddenString($encryptionKey));
    }

    public function encrypt(string $plaintext): string
    {
        return Crypto::encrypt(new HiddenString($plaintext), $this->key);
    }

    public function decrypt(string $ciphertext): string
    {
        return Crypto::decrypt($ciphertext, $this->key)->getString();
    }
}
```

#### 2.2 Platform Adapters
**Timeline**: 15-20 ore (5-7 ore per platformă)

**Priority Order:**
1. **FacebookAdapter** (+ Instagram) - 7 ore
2. **TwitterAdapter** - 6 ore
3. **LinkedInAdapter** - 5 ore

Fiecare adapter implementează:
- OAuth 2.0 flow
- Token refresh logic
- Publish method
- Delete method (optional)
- Get engagement stats method

#### 2.3 Social Media Service
**Timeline**: 5 ore

Implementare `SocialMediaService` cu metode:
- `connectAccount()`
- `disconnectAccount()`
- `publishArticle()`
- `publishToMultiplePlatforms()`
- `schedulePost()`
- `getAccountStatus()`

#### 2.4 Messenger Handlers
**Timeline**: 4 ore

```php
// Handlers:
- PublishToSocialMediaHandler
- RefreshSocialTokenHandler (cron job pentru refresh preventiv)
- FetchEngagementStatsHandler (periodic stats update)
```

**Deliverable FAZA 2:**
- ✅ EncryptionService funcțional
- ✅ 3 Platform Adapters complete (Facebook, Twitter, LinkedIn)
- ✅ SocialMediaService implementat
- ✅ 3 Messenger Handlers funcționali
- ✅ Unit tests pentru core logic

---

### 📅 FAZA 3: API Endpoints & Admin Integration (1 săptămână, 15-20 ore)

#### 3.1 API Controllers
**Timeline**: 8 ore

```php
// Controllers:
- SocialMediaController (OAuth, connect/disconnect, list accounts)
- SocialMediaPostController (publish, schedule, history, stats)
- SocialMediaTemplateController (CRUD templates)
```

**Endpoints:**
```
GET    /api/admin/social-media/connect/{platform}          # Get OAuth URL
GET    /api/admin/social-media/callback/{platform}         # OAuth callback
GET    /api/admin/social-media/accounts                    # List accounts
DELETE /api/admin/social-media/accounts/{id}               # Disconnect
POST   /api/admin/social-media/publish                     # Publish article
GET    /api/admin/social-media/posts/article/{id}          # Post history
GET    /api/admin/social-media/posts/{id}/stats            # Engagement stats
GET    /api/admin/social-media/templates                   # List templates
POST   /api/admin/social-media/templates                   # Create template
```

#### 3.2 Serialization Groups
**Timeline**: 2 ore

```php
// Add to entities:
#[Groups(['social_account:read', 'social_account:write'])]
#[Groups(['social_post:read', 'social_post:write'])]
#[Groups(['social_template:read', 'social_template:write'])]
```

#### 3.3 Validation & Security
**Timeline**: 3 ore

- Rate limiting pentru OAuth callbacks
- Validation pentru post content (max length per platform)
- Security checks (ensure user owns account)

#### 3.4 API Documentation
**Timeline**: 2 ore

- OpenAPI/Swagger annotations
- Postman collection pentru testing

**Deliverable FAZA 3:**
- ✅ 10+ API endpoints funcționale
- ✅ Serialization groups configurate
- ✅ Validation & security checks
- ✅ API documentation completă
- ✅ Postman collection pentru testing

---

### 📅 FAZA 4: Frontend Admin Panel (1.5 săptămâni, 25-30 ore)

#### 4.1 Social Media Accounts Management
**Timeline**: 8 ore

**Page:** `/admin/social-media/accounts`

**Componente:**
- `ConnectedAccountsList.tsx` - List all connected accounts
- `ConnectAccountButton.tsx` - OAuth flow trigger
- `AccountStatusBadge.tsx` - Status indicators (active, expired, error)
- `DisconnectAccountModal.tsx` - Confirmation modal

**Features:**
- Connect new account (OAuth popup)
- View all connected accounts
- See token expiration dates
- Disconnect account

#### 4.2 Article Publishing Interface
**Timeline**: 10 ore

**Integration:** În article edit page (`/admin/articles/{id}/edit`)

**Componente:**
- `SocialMediaPublishPanel.tsx` - Main publishing panel
- `PlatformSelector.tsx` - Select platforms to publish to
- `PostContentEditor.tsx` - Customize content per platform
- `PostPreview.tsx` - Preview how post will look
- `SchedulePublishModal.tsx` - Schedule for later
- `PublishingHistory.tsx` - Past publications for this article

**Features:**
- Select platforms (multi-select)
- Auto-generate content from template
- Edit content per platform
- Character count per platform (Twitter: 280, etc.)
- Image preview
- Hashtag suggestions
- Schedule publishing
- Instant publish button
- View publishing history

#### 4.3 Templates Management
**Timeline**: 5 ore

**Page:** `/admin/social-media/templates`

**Componente:**
- `TemplatesList.tsx`
- `TemplateEditor.tsx`
- `TemplateVariables.tsx` - Show available variables ({title}, {lead}, etc.)

**Features:**
- CRUD templates
- Default template per platform
- Template preview
- Category-specific templates (optional)

#### 4.4 Analytics Dashboard
**Timeline**: 5 ore

**Page:** `/admin/social-media/analytics`

**Componente:**
- `EngagementChart.tsx` - Chart showing engagement over time
- `TopPerformingPosts.tsx` - Best performing articles
- `PlatformComparison.tsx` - Compare platforms

**Metrics:**
- Total posts published
- Engagement per platform (likes, shares, comments)
- Click-through rates
- Best posting times

**Deliverable FAZA 4:**
- ✅ Accounts management page
- ✅ Publishing interface în article editor
- ✅ Templates management
- ✅ Analytics dashboard
- ✅ Responsive design (mobile-friendly)

---

### 📅 FAZA 5: Automation & Advanced Features (1 săptămână, 15-20 ore)

#### 5.1 Auto-Publish on Article Publish
**Timeline**: 4 ore

```php
// EventSubscriber: ArticlePublishedSubscriber.php
#[AsEventListener(event: 'article.published')]
class ArticlePublishedSubscriber
{
    public function onArticlePublished(ArticlePublishedEvent $event): void
    {
        $article = $event->getArticle();

        // Check if category has auto-publish enabled
        $category = $article->getCategory();
        if (!$category || !$category->hasAutoPublish()) {
            return;
        }

        // Get enabled accounts for this category
        $accounts = $this->getAutoPublishAccountsForCategory($category);

        // Publish to all accounts
        foreach ($accounts as $account) {
            $this->socialMediaService->publishArticle($article, $account);
        }
    }
}
```

**Features:**
- Enable/disable auto-publish per category
- Select which accounts to auto-publish to
- Default template per category

#### 5.2 Scheduled Posts Processing
**Timeline**: 3 ore

```php
// Command: ProcessScheduledPostsCommand.php
#[AsCommand(name: 'app:social-media:process-scheduled')]
class ProcessScheduledPostsCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = new \DateTimeImmutable();

        // Find all scheduled posts ready to publish
        $posts = $this->postRepo->findScheduledBeforeDate($now);

        foreach ($posts as $post) {
            $this->messageBus->dispatch(new PublishToSocialMediaMessage($post->getId()));
        }

        return Command::SUCCESS;
    }
}
```

**Cron Job:**
```bash
# Run every 5 minutes
*/5 * * * * cd /var/www/deschide_news_app/deschide_backend && symfony console app:social-media:process-scheduled
```

#### 5.3 Token Refresh Automation
**Timeline**: 3 ore

```php
// Command: RefreshExpiringTokensCommand.php
#[AsCommand(name: 'app:social-media:refresh-tokens')]
class RefreshExpiringTokensCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Find tokens expiring in next 24 hours
        $tomorrow = (new \DateTimeImmutable())->modify('+24 hours');
        $accounts = $this->accountRepo->findExpiringBefore($tomorrow);

        foreach ($accounts as $account) {
            $this->messageBus->dispatch(new RefreshSocialTokenMessage($account->getId()));
        }

        return Command::SUCCESS;
    }
}
```

**Cron Job:**
```bash
# Run daily
0 2 * * * cd /var/www/deschide_news_app/deschide_backend && symfony console app:social-media:refresh-tokens
```

#### 5.4 Engagement Stats Collection
**Timeline**: 4 ore

```php
// Command: FetchEngagementStatsCommand.php
#[AsCommand(name: 'app:social-media:fetch-stats')]
class FetchEngagementStatsCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Fetch stats for posts from last 7 days
        $since = (new \DateTimeImmutable())->modify('-7 days');
        $posts = $this->postRepo->findPublishedSince($since);

        foreach ($posts as $post) {
            $this->messageBus->dispatch(new FetchEngagementStatsMessage($post->getId()));
        }

        return Command::SUCCESS;
    }
}
```

**Cron Job:**
```bash
# Run twice daily
0 8,20 * * * cd /var/www/deschide_news_app/deschide_backend && symfony console app:social-media:fetch-stats
```

#### 5.5 Rate Limiting
**Timeline**: 2 ore

```php
// Service: RateLimiter using Redis
class SocialMediaRateLimiter
{
    // Facebook: 200 calls/hour
    // Twitter: 300 posts/3 hours
    // LinkedIn: 100 posts/day

    public function canPublish(SocialMediaPlatform $platform): bool
    {
        $key = "rate_limit:{$platform->value}:" . date('YmdH');
        $count = $this->redis->get($key) ?? 0;

        return $count < $this->getLimitForPlatform($platform);
    }

    public function incrementCounter(SocialMediaPlatform $platform): void
    {
        $key = "rate_limit:{$platform->value}:" . date('YmdH');
        $this->redis->incr($key);
        $this->redis->expire($key, 3600);
    }
}
```

**Deliverable FAZA 5:**
- ✅ Auto-publish on article publish
- ✅ Scheduled posts processor (cron job)
- ✅ Token refresh automation (cron job)
- ✅ Engagement stats collection (cron job)
- ✅ Rate limiting implementation

---

### 📅 FAZA 6: Testing & Optimization (1 săptămână, 15-20 ore)

#### 6.1 Unit Tests
**Timeline**: 8 ore

**Tests:**
- `SocialMediaServiceTest` (12 test cases)
- `FacebookAdapterTest` (8 test cases)
- `TwitterAdapterTest` (8 test cases)
- `LinkedInAdapterTest` (8 test cases)
- `EncryptionServiceTest` (4 test cases)

**Coverage target:** 80%+

#### 6.2 Integration Tests
**Timeline**: 5 ore

**Tests:**
- OAuth flow end-to-end (with mocked API responses)
- Publishing workflow (article → multiple platforms)
- Token refresh flow
- Engagement stats fetching

#### 6.3 Performance Optimization
**Timeline**: 3 ore

- Add database indexes (platform_post_id, publishedAt)
- Cache engagement stats (Redis, 1 hour TTL)
- Optimize queries (eager loading for relationships)
- Add API response caching where appropriate

#### 6.4 Error Handling & Logging
**Timeline**: 2 ore

- Centralized error handling pentru API failures
- Structured logging (Monolog)
- Retry logic pentru failed posts (max 3 retries)
- Admin notifications pentru critical failures

**Deliverable FAZA 6:**
- ✅ 40+ unit tests (80% coverage)
- ✅ Integration tests complete
- ✅ Performance optimizations applied
- ✅ Error handling & logging configured
- ✅ Retry mechanism implemented

---

## Complexitate & Timeline

### 📊 Estimare Completă

| Faza | Descriere | Ore | Săptămâni | Complexitate |
|------|-----------|-----|-----------|--------------|
| **FAZA 1** | Setup & Infrastructură | 15-20 | 1 | 🟢 Ușoară |
| **FAZA 2** | Core Services & Adapters | 25-30 | 1.5 | 🟡 Medie |
| **FAZA 3** | API Endpoints | 15-20 | 1 | 🟢 Ușoară |
| **FAZA 4** | Frontend Admin Panel | 25-30 | 1.5 | 🟡 Medie |
| **FAZA 5** | Automation & Advanced | 15-20 | 1 | 🟡 Medie |
| **FAZA 6** | Testing & Optimization | 15-20 | 1 | 🟢 Ușoară |
| **TOTAL** | **MVP Complet** | **110-140 ore** | **7-8 săptămâni** | 🟡 **Medie-Mare** |

### 📅 Timeline Realist (Part-time developer, 20h/săptămână)

```
Săptămâna 1: FAZA 1 completă + Start FAZA 2
Săptămâna 2-3: FAZA 2 completă (adapters + services)
Săptămâna 4: FAZA 3 completă (API endpoints)
Săptămâna 5-6: FAZA 4 completă (frontend admin)
Săptămâna 7: FAZA 5 completă (automation)
Săptămâna 8: FAZA 6 completă (testing) + deployment

TOTAL: 8 săptămâni (2 luni)
```

### 📅 Timeline Aggressive (Full-time, 40h/săptămână)

```
Săptămâna 1: FAZA 1 + FAZA 2 complete
Săptămâna 2: FAZA 3 + Start FAZA 4
Săptămâna 3: FAZA 4 completă
Săptămâna 4: FAZA 5 + FAZA 6 + deployment

TOTAL: 4 săptămâni (1 lună)
```

---

## Costuri & Dependențe

### 💰 Costuri Recurente

| Item | Cost | Frecvență | Total/An |
|------|------|-----------|----------|
| **Facebook Developer Account** | €0 | - | €0 |
| **Twitter/X API** | €0-100/lună | Lunar | €0-1,200 |
| **LinkedIn Developer** | €0 | - | €0 |
| **Server Resources** (CPU/RAM for workers) | +10% | Lunar | Minimal |
| **TOTAL** | **€0-100/lună** | - | **€0-1,200/an** |

**Note:**
- Twitter/X: Basic tier gratuit (până la 1,500 posts/lună). Pro tier ($100/lună) pentru unlimited.
- Facebook & LinkedIn: API-uri gratuite (cu rate limits rezonabile)
- No external SaaS needed (Buffer, Hootsuite, etc.)

### 📦 Dependențe Externe

#### Required:
- `facebook/graph-sdk` (open source, MIT)
- `abraham/twitteroauth` (open source, MIT)
- `happyr/linkedin-api-client` (open source, MIT)
- `knpuniversity/oauth2-client-bundle` (open source, MIT)
- `paragonie/halite` (encryption, open source)

#### Optional:
- `longman/telegram-bot` (pentru Telegram, dacă se dorește)

**Total cost dependențe:** €0 (toate open source)

### 🔑 API Keys & Credentials Necesare

Pentru fiecare platformă trebuie create developer apps:

#### Facebook + Instagram
1. Create app la https://developers.facebook.com
2. Add "Facebook Login" product
3. Configure OAuth redirect URIs
4. Get App ID + App Secret
5. Submit for review (pentru permissions: `pages_manage_posts`, `pages_read_engagement`)

**Timp setup:** 2-3 ore (+ 1-2 săptămâni review from Facebook)

#### Twitter/X
1. Create app la https://developer.twitter.com
2. Enable OAuth 2.0
3. Configure callback URLs
4. Get API Key + API Secret
5. Optionally upgrade to Pro tier ($100/lună)

**Timp setup:** 1-2 ore

#### LinkedIn
1. Create app la https://www.linkedin.com/developers
2. Add "Share on LinkedIn" product
3. Configure redirect URIs
4. Get Client ID + Client Secret
5. Submit for verification

**Timp setup:** 1-2 ore (+ câteva zile verification)

**Total timp setup API credentials:** 4-7 ore + 1-3 săptămâni review/approval

---

## Provocări & Soluții

### ⚠️ Provocare 1: OAuth Flow Complexity

**Problema:**
Fiecare platformă are propriul flow OAuth 2.0 cu nuanțe diferite (scopes, permissions, token lifetimes).

**Soluție:**
```php
// Use strategy pattern + abstract factory
interface PlatformAdapterInterface {
    public function getOAuthUrl(): string;
    public function handleCallback(string $code): array;
}

// Centralized OAuth handler
class OAuthService {
    public function initiateOAuth(SocialMediaPlatform $platform): string {
        $adapter = $this->factory->create($platform);
        return $adapter->getOAuthUrl();
    }
}
```

**Risc:** 🟡 Mediu
**Mitigare:** Testing extensiv pentru fiecare flow + fallback error handling

---

### ⚠️ Provocare 2: Token Expiration & Refresh

**Problema:**
Access tokens expiră (Facebook: 60 zile, Twitter: 2 ore). Trebuie refresh automat fără intervenție user.

**Soluție:**
```php
// Cron job zilnic pentru refresh preventiv
// Refresh tokens 24h înainte de expirare
#[AsCommand(name: 'app:social-media:refresh-tokens')]
class RefreshExpiringTokensCommand extends Command {
    // Find accounts expiring in 24h
    // Dispatch RefreshSocialTokenMessage
}

// In handler, check token before every publish
if ($account->getTokenExpiresAt() < new \DateTimeImmutable()) {
    $newToken = $adapter->refreshAccessToken($refreshToken);
    $account->setAccessToken($newToken);
}
```

**Risc:** 🟡 Mediu
**Mitigare:** Proactive refresh + user notifications pentru re-autentificare dacă refresh failure

---

### ⚠️ Provocare 3: Rate Limiting

**Problema:**
Fiecare platformă are limite diferite:
- Facebook: 200 calls/hour
- Twitter: 300 posts/3 hours (free tier)
- LinkedIn: 100 posts/day

**Soluție:**
```php
// Redis-based rate limiter
class SocialMediaRateLimiter {
    private array $limits = [
        SocialMediaPlatform::FACEBOOK => ['limit' => 200, 'window' => 3600],
        SocialMediaPlatform::TWITTER => ['limit' => 300, 'window' => 10800],
        SocialMediaPlatform::LINKEDIN => ['limit' => 100, 'window' => 86400],
    ];

    public function canPublish(SocialMediaPlatform $platform, SocialMediaAccount $account): bool {
        $key = "rate_limit:{$platform->value}:{$account->getId()}:" . $this->getWindow($platform);
        $count = $this->redis->get($key) ?? 0;

        return $count < $this->limits[$platform->value]['limit'];
    }
}

// In handler
if (!$this->rateLimiter->canPublish($platform, $account)) {
    // Requeue message for later
    throw new RecoverableMessageHandlingException('Rate limit exceeded, retrying later');
}
```

**Risc:** 🟢 Scăzut
**Mitigare:** Queue messages when rate limited + retry with exponential backoff

---

### ⚠️ Provocare 4: Content Format Differences

**Problema:**
Fiecare platformă are cerințe diferite:
- Twitter: max 280 caractere
- Facebook: 63,206 caractere (dar optimal <250)
- LinkedIn: 3,000 caractere
- Imagini: dimensiuni/formate diferite

**Soluție:**
```php
// Platform-specific content validators
class TwitterContentValidator implements ContentValidatorInterface {
    public function validate(string $content): ValidationResult {
        if (mb_strlen($content) > 280) {
            return ValidationResult::error('Content exceeds 280 characters');
        }
        return ValidationResult::success();
    }

    public function truncate(string $content): string {
        return mb_substr($content, 0, 277) . '...';
    }
}

// In frontend
<PostContentEditor
    platform={platform}
    maxLength={getMaxLengthForPlatform(platform)}
    onExceedLimit={(content) => autoTruncate(content, platform)}
/>
```

**Risc:** 🟢 Scăzut
**Mitigare:** Real-time validation în UI + auto-truncate cu warning

---

### ⚠️ Provocare 5: API Changes & Deprecations

**Problema:**
Social media APIs se schimbă frecvent (Facebook Graph API versioning, Twitter API v1 → v2).

**Soluție:**
```php
// Version-specific adapters
class FacebookGraphV19Adapter implements PlatformAdapterInterface {
    private const API_VERSION = 'v19.0';

    // All calls use versioned endpoints
    public function publish(...) {
        return $this->fb->post('/me/feed', $data, $accessToken);
    }
}

// Easy to add new version
class FacebookGraphV20Adapter extends FacebookGraphV19Adapter {
    private const API_VERSION = 'v20.0';
    // Override only changed methods
}
```

**Risc:** 🟡 Mediu
**Mitigare:** Monitor API changelog + version pinning + tests for compatibility

---

### ⚠️ Provocare 6: Failed Posts & Retry Logic

**Problema:**
Network errors, API downtime, invalid tokens → posts nu se publică.

**Soluție:**
```php
// Messenger retry strategy
// config/packages/messenger.yaml
framework:
    messenger:
        failure_transport: failed

        transports:
            social_media:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                retry_strategy:
                    max_retries: 3
                    delay: 1000
                    multiplier: 2
                    max_delay: 0

            failed: 'doctrine://default?queue_name=failed'

// In handler
try {
    $adapter->publish($post, $accessToken);
} catch (RecoverableException $e) {
    // Will be retried (network error, rate limit, etc.)
    throw new RecoverableMessageHandlingException($e->getMessage());
} catch (UnrecoverableException $e) {
    // Won't retry (invalid token, content violation, etc.)
    $post->setStatus(SocialMediaPostStatus::FAILED);
    $post->setErrorMessage($e->getMessage());
    $this->logger->error("Unrecoverable error: {$e->getMessage()}");
}

// Manual retry from admin panel
POST /api/admin/social-media/posts/{id}/retry
```

**Risc:** 🟡 Mediu
**Mitigare:** Automatic retry (3x) + manual retry option + detailed error logs

---

### ⚠️ Provocare 7: Security - Token Storage

**Problema:**
Access tokens sunt sensibili. Dacă database leak → compromised social accounts.

**Soluție:**
```php
// Encrypt all tokens before storage
use ParagonIE\Halite\Symmetric\Crypto;

class EncryptionService {
    public function encrypt(string $plaintext): string {
        return Crypto::encrypt(new HiddenString($plaintext), $this->key);
    }
}

// In entity setter
public function setAccessToken(string $token): self {
    $this->accessToken = $this->encryption->encrypt($token);
    return $this;
}

// In handler
$accessToken = $this->encryption->decrypt($account->getAccessToken());
```

**Extra:**
- Encryption key în `.env.local` (NOT in git)
- Database-level encryption (PostgreSQL pgcrypto) optional
- Audit log pentru token access

**Risc:** 🔴 Mare (security critical)
**Mitigare:** Strong encryption + key rotation policy + audit logging

---

## Întrebări de Decizie

Înainte de a începe implementarea, vă rog să decideți:

### 1. Platforme Prioritare

Care platforme vreți în MVP (prima versiune)?

- 🅰️ **Facebook + Instagram** (recomandat - aceeași API, coverage mare)
- 🅱️ **Facebook + Instagram + Twitter/X** (coverage complet știri)
- 🅲️ **Toate** (Facebook, Instagram, Twitter, LinkedIn, Telegram) - timeline +2 săptămâni

**Recomandare:** 🅱️ Facebook + Instagram + Twitter/X

---

### 2. Nivel Funcționalitate MVP

Care nivel de funcționalitate pentru prima versiune?

- 🅰️ **Nivel 1** - Doar publicare manuală (MVP simplu, 4 săptămâni)
- 🅱️ **Nivel 2** - Publicare + Auto-publish + Scheduling (recomandat, 6 săptămâni)
- 🅲️ **Nivel 3** - Toate features + Analytics (complet, 8 săptămâni)

**Recomandare:** 🅱️ Nivel 2 (auto-publish esențial pentru workflow eficient)

---

### 3. Auto-Publish Strategy

Cum vreți să funcționeze auto-publish?

- 🅰️ **Global ON/OFF** - Toate articolele publicate se auto-postează
- 🅱️ **Per Categorie** - Doar anumite categorii au auto-publish (recomandat)
- 🅲️ **Per Articol** - Editor decide manual pentru fiecare articol
- 🅳️ **Hybrid** - Default per categorie + override per articol

**Recomandare:** 🅳️ Hybrid (flexibilitate maximă)

---

### 4. Twitter/X API Tier

Care tier Twitter API?

- 🅰️ **Free Tier** - 1,500 posts/lună (€0/lună)
- 🅱️ **Basic Tier** - 3,000 posts/lună (€100/lună)
- 🅲️ **Pro Tier** - 10,000 posts/lună (€5,000/lună)

**Context:** Cu ~80,000 articole active + ~150 articole noi/lună, free tier e suficient (5 posts/zi).

**Recomandare:** 🅰️ Free Tier (upgrade dacă necesită mai mult)

---

### 5. Image Handling

Cum gestionăm imaginile pentru social media?

- 🅰️ **Use Featured Image** - Întotdeauna prima imagine featured
- 🅱️ **Let Editor Choose** - Editor selectează care imagine pentru social (recomandat)
- 🅲️ **Generate Social Cards** - Auto-generat Open Graph images cu titlu overlay

**Recomandare:** 🅱️ Editor choice (pentru început) → 🅲️ Social cards (în viitor)

---

### 6. Template System

Cât de complex trebuie să fie template-ul?

- 🅰️ **Simple** - Un template global pentru toate platformele
- 🅱️ **Platform-Specific** - Template diferit per platformă (recomandat)
- 🅲️ **Advanced** - Template per platformă + per categorie

**Recomandare:** 🅱️ Platform-specific (Twitter needs shorter text vs Facebook)

---

### 7. Analytics Collection

Colectăm statistici engagement?

- 🅰️ **Nu** - Doar tracking că s-a publicat (simplu, fără API calls extra)
- 🅱️ **Da - Basic** - Likes, shares, comments (2x/zi collection)
- 🅲️ **Da - Advanced** - + Click-through rates, reach, impressions (necesită Facebook Pixel)

**Recomandare:** 🅱️ Basic analytics (valoros pentru editorial team)

---

### 8. Timeline Preferată

Care timeline preferați?

- 🅰️ **Aggressive** - 4 săptămâni (full-time developer, 40h/săptămână)
- 🅱️ **Realist** - 8 săptămâni (part-time, 20h/săptămână)
- 🅲️ **Relaxat** - 12 săptămâni (10h/săptămână, alături de alte taskuri)

**Recomandare:** 🅱️ Realist (quality > speed)

---

## Concluzie

### ✅ Răspuns la Întrebarea Inițială: "Cât de complicat va fi?"

**Complexitate Generală:** 🟡 **MEDIE-MARE**

**Breakdown:**
- 🟢 **Ușor**: Setup infrastructură, API endpoints, UI basic
- 🟡 **Mediu**: OAuth flows, Platform adapters, Token management
- 🔴 **Challenging**: Security (token encryption), Rate limiting, Error handling

**Timeline Estimat:**
- **MVP Simplu** (Publicare manuală FB+IG): 4 săptămâni (60-80 ore)
- **MVP Recomandat** (Auto-publish FB+IG+TW): 6 săptămâni (90-120 ore)
- **Full Feature** (Toate platforme + Analytics): 8 săptămâni (110-140 ore)

**Costuri:**
- **Dev Time**: 110-140 ore (€5,500-7,000 la €50/oră)
- **Recurring Costs**: €0-100/lună (Twitter API optional)
- **Dependencies**: €0 (toate open source)

**Risc:**
- 🟢 **Tehnic**: Scăzut (tehnologii mature, documentație bună)
- 🟡 **Timeline**: Mediu (API reviews pot întârzia 1-3 săptămâni)
- 🟡 **Mentenanță**: Mediu (API changes pot necesita updates)

### 🎯 Recomandare Finală

**Implementare Opțiunea 3 (Hybridă - OAuth + API Native)**

**Motivație:**
1. ✅ Zero costuri recurente (vs €600-3,000/an pentru SaaS)
2. ✅ Control total & customizare
3. ✅ Integrare nativă în aplicație
4. ✅ Scalabil (poate adăuga platforme noi)
5. ✅ Tehnologii deja în stack (Symfony, Messenger, Redis)

**Start cu MVP Nivel 2:**
- Facebook + Instagram + Twitter/X
- Publicare manuală + Auto-publish + Scheduling
- Basic templates per platformă
- Engagement stats collection (2x/zi)

**Roadmap post-MVP:**
- LinkedIn adapter (+1 săptămână)
- Advanced templates (per categorie)
- Social card auto-generation
- A/B testing titluri
- Analytics dashboard avansat

---

**Data Creare**: 9 Noiembrie 2025
**Autor**: Claude Code
**Status Document**: ✅ Completă - Gata pentru Review & Decizie
