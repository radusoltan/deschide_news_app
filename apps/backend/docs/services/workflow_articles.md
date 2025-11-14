# Article Workflow Documentation

## Overview

This document describes the complete lifecycle of news articles in the Deschide News platform, from creation to archival.

---

## 📊 Article States

### Status Enum

**Definition**: `src/Enum/ArticleStatus.php`

```php
enum ArticleStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case SCHEDULED = 'scheduled';
    case ARCHIVED = 'archived';
}
```

### State Descriptions

| Status | Description | Visibility | Who Can Change |
|--------|-------------|------------|----------------|
| **draft** | Work in progress, not ready | Editors only | ROLE_EDITOR (owner), ROLE_ADMIN |
| **published** | Live on website | Public | ROLE_EDITOR, ROLE_ADMIN |
| **scheduled** | Queued for future publication | Editors only | ROLE_EDITOR, ROLE_ADMIN |
| **archived** | No longer active, hidden | Editors only | ROLE_ADMIN |

---

## 🔄 Workflow Diagram

```
┌─────────┐
│  START  │
└────┬────┘
     │
     v
┌─────────────────┐
│  Create Article │ ──────> Status: DRAFT
│  (ROLE_EDITOR)  │         publishedAt: null
└────┬────────────┘
     │
     v
┌──────────────────────┐
│  Edit Content        │
│  - Add title/content │ ──────> Status: DRAFT
│  - Upload images     │         (can edit indefinitely)
│  - Set category      │
│  - Translate         │
└────┬─────────────────┘
     │
     v
┌──────────────────────┐
│  Ready to Publish?   │
└────┬────────┬────────┘
     │        │
     │ Yes    │ No ────────> Keep as DRAFT
     │        │              (save for later)
     v        v
┌─────────┐  ┌──────────────┐
│ Publish │  │   Schedule   │
│  Now?   │  │   Future?    │
└────┬────┘  └──────┬───────┘
     │              │
     │ Yes          │ Yes
     v              v
┌──────────────┐  ┌───────────────────┐
│  PUBLISHED   │  │    SCHEDULED      │
│ publishedAt: │  │  publishedAt:     │
│    NOW       │  │  FUTURE_DATE      │
└──────┬───────┘  └─────┬─────────────┘
       │                │
       │                │ (Cron job runs)
       │                v
       │          ┌──────────────┐
       │          │  Auto-publish│
       │          │  when time   │
       │          │  is reached  │
       │          └──────┬───────┘
       │                 │
       v                 v
┌────────────────────────────┐
│      PUBLISHED             │
│   (Live on website)        │
└──────┬─────────────────────┘
       │
       │ (After time passes)
       │ (Manual action)
       v
┌────────────────────────────┐
│       ARCHIVED             │
│  (Hidden from public)      │
└────────────────────────────┘
```

---

## 📝 Step-by-Step Workflow

### 1. Article Creation

**Actor**: ROLE_EDITOR or higher

**Action**: `POST /api/articles`

**Request**:
```json
{
  "title": "Breaking News Title",
  "lead": "Article summary...",
  "content": "<p>Full article content...</p>",
  "category": "/api/categories/5",
  "author": "/api/authors/2",
  "status": "draft"
}
```

**Backend Processing**:
```php
// src/State/ArticleProcessor.php

public function process($data, Operation $operation, ...) {
    // 1. Validate required fields
    $this->validator->validate($data);
    
    // 2. Set defaults
    if (!$data->getStatus()) {
        $data->setStatus(ArticleStatus::DRAFT);
    }
    
    // 3. Generate slug from title
    // Handled by Gedmo Sluggable
    
    // 4. Set current user as created_by (if implemented)
    $data->setCreatedBy($this->security->getUser());
    
    // 5. Persist to database
    $this->entityManager->persist($data);
    $this->entityManager->flush();
    
    return $data;
}
```

**Result**:
- Article ID: 101
- Status: `draft`
- publishedAt: `null`
- Visible only in admin panel

---

### 2. Content Editing

**Actor**: ROLE_EDITOR (owner) or ROLE_ADMIN

**Action**: `PATCH /api/articles/101`

**Request**:
```json
{
  "title": "Updated Breaking News Title",
  "content": "<p>Updated content with more details...</p>",
  "metaTitle": "SEO Title",
  "metaDescription": "SEO description for search engines"
}
```

**Validation**:
```php
// Check if user can edit this article
if (!$this->security->isGranted('EDIT', $article)) {
    throw new AccessDeniedException('Cannot edit this article');
}
```

**Article Lock** (prevent concurrent editing):
```php
// Check if article is locked by another user
$lock = $this->articleLockRepository->findActiveByArticle($article);

if ($lock && $lock->getUser()->getId() !== $currentUser->getId()) {
    throw new ConflictException(
        'Article is being edited by ' . $lock->getUser()->getEmail()
    );
}

// Create lock for current user (15 minutes)
$newLock = new ArticleLock();
$newLock->setArticle($article);
$newLock->setUser($currentUser);
$newLock->setExpiresAt(new \DateTime('+15 minutes'));
$this->entityManager->persist($newLock);
```

---

### 3. Adding Images

**Action**: `POST /api/article_images`

**Request**:
```json
{
  "article": "/api/articles/101",
  "image": "/api/images/45",
  "position": 0,
  "isFeatured": true
}
```

**Backend**:
```php
// ArticleImageProcessor validates:
// 1. Image exists
// 2. Article exists
// 3. User has EDIT permission on article
// 4. Position is valid (0-9)

$articleImage = new ArticleImage();
$articleImage->setArticle($article);
$articleImage->setImage($image);
$articleImage->setPosition(0);
$articleImage->setIsFeatured(true);

$this->entityManager->persist($articleImage);
$this->entityManager->flush();
```

---

### 4. Translation

**Action**: `PATCH /api/articles/101` with locale header

**Request**:
```http
PATCH /api/articles/101
Accept-Language: en
Content-Type: application/ld+json

{
  "title": "Breaking News Title (English)",
  "lead": "English summary...",
  "content": "<p>English content...</p>"
}
```

**Backend**:
```php
// Extract locale from header
$locale = $request->headers->get('Accept-Language', 'ro');

// Set translatable locale
$article->setTranslatableLocale($locale);
$article->setTitle('Breaking News Title (English)');
$article->setLead('English summary...');

// Gedmo saves to ext_translations table
$this->entityManager->flush();
```

**Result**:
- Romanian version (original) remains in `articles` table
- English translation saved in `ext_translations` table
- Slug auto-generated for English: `breaking-news-title-english`

---

### 5. Publishing

#### Option A: Publish Immediately

**Action**: `PATCH /api/articles/101`

**Request**:
```json
{
  "status": "published",
  "publishedAt": "2025-11-05T14:30:00+00:00"  // or null for NOW
}
```

**Backend**:
```php
if ($data->getStatus() === ArticleStatus::PUBLISHED) {
    // Set published timestamp if not provided
    if (!$data->getPublishedAt()) {
        $data->setPublishedAt(new \DateTime());
    }
    
    // Validate article is ready
    $this->validatePublishable($data);
    
    // Index in Elasticsearch
    $this->elasticService->indexArticle($data);
    
    // Invalidate cache
    $this->cacheInvalidator->invalidate(['article', 'homepage']);
    
    // Send Mercure update (real-time)
    $this->mercurePublisher->publish('deschide_news/breaking', [
        'type' => 'article_published',
        'article_id' => $data->getId()
    ]);
}
```

**Result**:
- Status: `published`
- publishedAt: `2025-11-05 14:30:00`
- Visible on public website immediately
- Appears in category lists, homepage
- Indexed in search

#### Option B: Schedule for Future

**Action**: `PATCH /api/articles/101`

**Request**:
```json
{
  "status": "scheduled",
  "publishedAt": "2025-11-06T09:00:00+00:00"  // Future date/time
}
```

**Backend Validation**:
```php
if ($data->getStatus() === ArticleStatus::SCHEDULED) {
    // Ensure publishedAt is in the future
    if ($data->getPublishedAt() <= new \DateTime()) {
        throw new ValidationException(
            'Scheduled articles must have future publishedAt date'
        );
    }
}
```

**Result**:
- Status: `scheduled`
- publishedAt: `2025-11-06 09:00:00`
- NOT visible on public website
- Visible in admin panel with "Scheduled" badge

---

### 6. Automated Publishing (Cron Job)

**Command**: `app:publish-scheduled-articles`

**Configuration**: Run every 5 minutes

```bash
# crontab
*/5 * * * * cd /var/www/deschide_backend && php bin/console app:publish-scheduled-articles
```

**Implementation**: `src/Command/PublishScheduledArticlesCommand.php`

```php
protected function execute(InputInterface $input, OutputInterface $output): int
{
    // Find articles with status=scheduled and publishedAt <= NOW
    $articles = $this->repository->findScheduledReady();
    
    foreach ($articles as $article) {
        // Change status to published
        $article->setStatus(ArticleStatus::PUBLISHED);
        
        // Index in Elasticsearch
        $this->elasticService->indexArticle($article);
        
        // Invalidate cache
        $this->cacheInvalidator->invalidate(['article', 'homepage']);
        
        // Log
        $this->logger->info('Published scheduled article', [
            'article_id' => $article->getId(),
            'title' => $article->getTitle()
        ]);
    }
    
    $this->entityManager->flush();
    
    $output->writeln(sprintf('Published %d scheduled articles', count($articles)));
    
    return Command::SUCCESS;
}
```

---

### 7. Archiving

**Actor**: ROLE_ADMIN

**Action**: `PATCH /api/articles/101`

**Request**:
```json
{
  "status": "archived"
}
```

**Backend**:
```php
// Only ROLE_ADMIN can archive
if (!$this->security->isGranted('ROLE_ADMIN')) {
    throw new AccessDeniedException('Only admins can archive articles');
}

$article->setStatus(ArticleStatus::ARCHIVED);

// Remove from Elasticsearch index
$this->elasticService->deleteArticle($article->getId());

// Invalidate cache
$this->cacheInvalidator->invalidate(['article', 'category', 'homepage']);
```

**Result**:
- Status: `archived`
- NOT visible on public website
- NOT in search results
- Visible in admin panel (filter: archived)
- Can be un-archived (set status back to published)

---

## 🏷️ Article Badges

### Badge Enum

**Definition**: `src/Enum/ArticleBadge.php`

```php
enum ArticleBadge: string
{
    case BREAKING = 'breaking';           // Breaking news
    case EXCLUSIVE = 'exclusive';         // Exclusive story
    case ANALYSIS = 'analysis';           // In-depth analysis
    case OPINION = 'opinion';             // Opinion piece
    case VIDEO = 'video';                 // Contains video
    case PHOTO_GALLERY = 'photo_gallery'; // Photo gallery
}
```

### Badge Usage

**Setting Badge**:
```json
{
  "badge": "breaking"
}
```

**Frontend Display**:
```tsx
{article.badge === 'breaking' && (
  <span className="badge badge-breaking">BREAKING</span>
)}
```

**Badge Behavior**:
- Independent of status (can have badge in any status)
- Optional (badge can be `null`)
- Used for visual prominence
- Filters available: `/api/articles?badge=breaking`

---

## 🔒 Access Control

### Permission Matrix (Summary)

| Action | ROLE_EDITOR | ROLE_ADMIN | ROLE_SUPER_ADMIN |
|--------|:-----------:|:----------:|:----------------:|
| Create article | ✅ (own) | ✅ (any) | ✅ (any) |
| Edit own draft | ✅ | ✅ | ✅ |
| Edit any draft | ❌ | ✅ | ✅ |
| Publish own article | ✅ | ✅ | ✅ |
| Publish any article | ❌ | ✅ | ✅ |
| Schedule article | ✅ | ✅ | ✅ |
| Archive article | ❌ | ✅ | ✅ |
| Delete own article | ✅ | ✅ | ✅ |
| Delete any article | ❌ | ✅ | ✅ |

### Voter Implementation

**File**: `src/Security/Voter/ArticleVoter.php`

```php
protected function supports(string $attribute, mixed $subject): bool
{
    return in_array($attribute, ['EDIT', 'DELETE', 'PUBLISH'])
        && $subject instanceof Article;
}

protected function voteOnAttribute(
    string $attribute, 
    mixed $subject, 
    TokenInterface $token
): bool {
    $user = $token->getUser();
    $article = $subject;
    
    // ROLE_ADMIN can do anything
    if ($this->security->isGranted('ROLE_ADMIN')) {
        return true;
    }
    
    // ROLE_EDITOR can only edit own articles
    if ($this->security->isGranted('ROLE_EDITOR')) {
        return $article->getCreatedBy()?->getId() === $user->getId();
    }
    
    return false;
}
```

---

## 📊 Important Articles (Homepage Featured)

### Entity: ImportantArticlesList

**Purpose**: Manage featured articles on homepage (max 5)

**Workflow**:

1. **Add to list** (ROLE_ADMIN only):
```json
POST /api/important_articles_lists
{
  "article": "/api/articles/101",
  "position": 0
}
```

2. **Validation**:
- Max 5 articles allowed
- Article must be published
- Position 0-4

3. **Homepage Display**:
- Frontend fetches `/api/important_articles_lists?order[position]=asc`
- Displays in hero section
- Position 0 = main hero image (large)
- Positions 1-4 = secondary cards (smaller)

---

## 🔔 Notifications & Events

### Events Dispatched

**Article Published**:
```php
$this->eventDispatcher->dispatch(
    new ArticlePublishedEvent($article),
    'article.published'
);
```

**Use Cases**:
- Send Mercure push notification
- Invalidate cache
- Trigger social media auto-post (future)
- Send email to subscribers (future)

**Article Updated**:
```php
$this->eventDispatcher->dispatch(
    new ArticleUpdatedEvent($article),
    'article.updated'
);
```

---

## 📈 Analytics & Tracking

### View Counter

**Increment on read**:
```php
// In ArticleProvider (when fetching article detail)
$article->incrementViewsCount();
$this->entityManager->flush();
```

**View statistics**:
- Stored in `ArticleStatsDaily` entity
- Aggregated daily
- Accessible in admin analytics dashboard

---

## 🧪 Testing Workflow

### Manual Testing Checklist

- [ ] Create article as ROLE_EDITOR
- [ ] Verify cannot edit another editor's article
- [ ] Add images to article
- [ ] Translate article to English and Russian
- [ ] Publish article (status → published)
- [ ] Verify appears on homepage and category page
- [ ] Schedule article for future date
- [ ] Wait for scheduled time (or run cron manually)
- [ ] Verify auto-published
- [ ] Archive article as ROLE_ADMIN
- [ ] Verify no longer visible on website

### Automated Tests (Planned)

```php
// tests/Functional/ArticleWorkflowTest.php

public function testCompleteArticleWorkflow(): void
{
    // 1. Create draft
    $article = $this->createArticle(['status' => 'draft']);
    $this->assertEquals('draft', $article->getStatus());
    
    // 2. Publish
    $this->updateArticle($article, ['status' => 'published']);
    $this->assertEquals('published', $article->getStatus());
    
    // 3. Verify visible
    $response = $this->client->request('GET', '/api/articles');
    $this->assertContains($article->getId(), $response->toArray());
}
```

---

**Last Updated**: November 5, 2025  
**Version**: 1.0  
**Status**: Production workflow
