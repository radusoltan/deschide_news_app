# Liveblog/Liveblog Repository Analysis
## Insights pentru implementarea Live Text în Deschide News App

**Data analiză:** 2025-11-03
**Repository analizat:** https://github.com/liveblog/liveblog
**Versiune:** 3.91.0 (97 releases, 7,970 commits)

---

## Executive Summary

Liveblog este o aplicație open-source dezvoltată pentru jurnaliști, oferind coverage în timp real pentru evenimente de breaking news. După analiză, am identificat **12 features și patterns cheie** care ar fi extrem de utile pentru implementarea funcționalității Live Text în Deschide News App.

---

## 1. Arhitectură Tehnică

### Stack Tehnic (Liveblog)
| Component | Tehnologie | Note |
|-----------|------------|------|
| **Backend** | Python 3.6 + Superdesk | REST API (port 5000), WebSocket (port 5100) |
| **Frontend** | AngularJS + TypeScript | Grunt build, Webpack bundling |
| **Database** | MongoDB | Document store pentru posturi |
| **Search** | Elasticsearch | Full-text search și indexing |
| **Cache** | Redis | Sessions și caching |
| **Storage** | Amazon S3 | Asset storage pentru media |

### Stack Tehnic (Deschide News App) - Comparație
| Component | Tehnologie | Note |
|-----------|------------|------|
| **Backend** | Symfony 7.3 (PHP 8.4) | REST API cu API Platform |
| **Frontend** | Next.js 16 (React 19.2) | TypeScript, Tailwind CSS |
| **Database** | PostgreSQL 17 | Relational database |
| **Search** | Elasticsearch | ✅ Deja disponibil |
| **Cache** | Redis | ✅ Deja disponibil |
| **Real-Time** | Mercure Hub | SSE-based (vs WebSocket) |
| **Storage** | CDN local | Port 8082 pentru static assets |

**Concluzie:** Stack-ul Deschide News este mai modern și mai bine potrivit pentru scale. Avem deja majoritatea infrastructurii necesare.

---

## 2. Features Utile pentru Deschide News App

### 🎯 **Must-Have Features** (din Liveblog)

#### 2.1 **Post Versioning System**
**Ce face în Liveblog:**
- Endpoint `/posts_versions` pentru tracking versiuni
- Istoric complet pentru fiecare modificare
- Rollback capabilities

**De ce este util pentru noi:**
- Jurnaliștii pot vedea ce s-a schimbat într-un post
- Audit trail pentru compliance
- Undo functionality pentru editori

**Implementare în Deschide News:**
```php
// Entitate nouă: LiveTextPostVersion
class LiveTextPostVersion
{
    private ?int $id;
    private LiveTextPost $post;
    private string $content;
    private string $contentHtml;
    private User $author;
    private DateTime $createdAt;
    private int $versionNumber;
    private ?string $changeNote; // Ce s-a modificat
}
```

**API Endpoint:**
- `GET /api/live_text_posts/{id}/versions` - Lista versiuni
- `POST /api/live_text_posts/{id}/revert/{versionId}` - Revert la versiune

**Prioritate:** ⭐⭐⭐⭐⭐ (Must-have pentru Sprint 6-7)

---

#### 2.2 **Post Flags System**
**Ce face în Liveblog:**
- Endpoint `/post_flags` pentru marcarea posturilor
- Flags: `sticky`, `breaking`, `highlight`, `pinned`

**De ce este util pentru noi:**
- Posturi "sticky" la top (important announcements)
- Breaking news badge automat
- Pin posturi pentru vizibilitate

**Implementare în Deschide News:**
```php
enum LiveTextPostFlag: string
{
    case STICKY = 'sticky';      // Rămâne în top
    case BREAKING = 'breaking';  // Breaking news badge
    case PINNED = 'pinned';      // Pinned în timeline
    case FEATURED = 'featured';  // Featured în homepage
}

class LiveTextPost
{
    // ...
    #[ORM\Column(type: 'json')]
    private array $flags = [];

    public function hasFlag(LiveTextPostFlag $flag): bool
    {
        return in_array($flag->value, $this->flags);
    }
}
```

**Frontend behavior:**
- Posturi `sticky` se afișează mereu la top
- Posturi `breaking` au badge roșu "BREAKING"
- Posturi `pinned` sunt highlight în timeline

**Prioritate:** ⭐⭐⭐⭐ (Should-have pentru Sprint 7)

---

#### 2.3 **Comments System (cu moderare)**
**Ce face în Liveblog:**
- Endpoint `/post_comments` dedicat pentru comentarii
- Permissions granulare: `create`, `read`, `update`, `delete`
- Moderation capabilities

**De ce este util pentru noi:**
- User engagement ridicat
- Community discussion pe evenimente live
- Moderare profesională

**Implementare în Deschide News:**
```php
class LiveTextPostComment
{
    private ?int $id;
    private LiveTextPost $post;
    private ?User $user;        // nullable pentru anonimi
    private string $content;
    private CommentStatus $status; // pending/approved/rejected
    private ?User $moderator;
    private ?string $ipAddress;
    private DateTime $createdAt;
    private ?DateTime $moderatedAt;
}

enum CommentStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}
```

**Features:**
- Auto-approve pentru utilizatori autentificați (configurable)
- Manual moderation pentru anonimi
- Rate limiting (max 5 comentarii / 15 minute per IP)
- Spam detection (keyword filtering)
- Real-time update comentarii noi via Mercure

**API Endpoints:**
- `GET /api/live_text_posts/{id}/comments` - Lista comentarii
- `POST /api/live_text_posts/{id}/comments` - Creare comentariu
- `PATCH /api/comments/{id}/approve` - Aprobare (moderator only)
- `DELETE /api/comments/{id}` - Ștergere

**Prioritate:** ⭐⭐⭐ (Could-have pentru Phase 6)

---

#### 2.4 **Theme System**
**Ce face în Liveblog:**
- Themes modular cu `themes/` directory
- Asset pipeline (Gulp) pentru compilare
- Theme marketplace pentru distribuție
- System themes vs. user themes

**De ce este util pentru noi:**
- Template-uri diferite pentru tipuri evenimente
- Branding customizat per live text
- White-label capabilities pentru parteneri

**Implementare în Deschide News:**
```php
class LiveTextTheme
{
    private ?int $id;
    private string $name;
    private string $description;
    private ThemeType $type;
    private array $config; // JSON: colors, fonts, layout
    private array $assets; // CSS, JS files
    private bool $isSystemTheme;
    private ?string $previewImage;
    private DateTime $createdAt;
}

enum ThemeType: string
{
    case BREAKING_NEWS = 'breaking_news';
    case SPORT_EVENT = 'sport_event';
    case CONFERENCE = 'conference';
    case ELECTION = 'election';
    case CUSTOM = 'custom';
}
```

**Frontend Implementation:**
- Dynamic CSS loading pe baza theme-ului selectat
- Next.js CSS modules pentru theme isolation
- Tailwind CSS cu custom color schemes

**Example Config:**
```json
{
  "colors": {
    "primary": "#ef4444",
    "secondary": "#1e40af",
    "background": "#ffffff",
    "text": "#1f2937"
  },
  "fonts": {
    "heading": "Inter",
    "body": "Open Sans"
  },
  "layout": {
    "postsPerPage": 20,
    "showTimeline": true,
    "showReactions": true
  },
  "features": {
    "enableComments": true,
    "enablePolls": false,
    "enableScoreTracking": false
  }
}
```

**Prioritate:** ⭐⭐⭐⭐ (Should-have pentru Sprint 9)

---

#### 2.5 **Syndication System**
**Ce face în Liveblog:**
- Content distribution către multiple destinations
- Behavior-driven tests (BDD cu Behave)
- API pentru external consumers

**De ce este util pentru noi:**
- Distribuție live text pe multiple platformuri
- Partner integrations
- RSS feeds pentru live updates

**Implementare în Deschide News:**
```php
class LiveTextSyndication
{
    private ?int $id;
    private LiveText $liveText;
    private string $destination; // URL sau identifier
    private SyndicationType $type;
    private array $config;
    private bool $isActive;
    private DateTime $lastSyncAt;
}

enum SyndicationType: string
{
    case RSS_FEED = 'rss_feed';
    case JSON_API = 'json_api';
    case WEBHOOK = 'webhook';
    case SOCIAL_MEDIA = 'social_media';
}
```

**Features:**
- RSS feed pentru fiecare live text
- Webhook notifications pentru posturi noi
- JSON API pentru partner websites
- Auto-posting pe social media (Twitter, Facebook)

**API Endpoints:**
- `GET /api/live_texts/{id}/rss` - RSS feed
- `GET /api/live_texts/{id}/embed.json` - JSON pentru embed
- `POST /api/live_texts/{id}/syndicate` - Trigger syndication

**Prioritate:** ⭐⭐ (Could-have pentru Phase 6)

---

#### 2.6 **Polls/Voting System**
**Ce face în Liveblog:**
- Module `/polls` pentru poll functionality
- User voting cu rezultate în timp real

**De ce este util pentru noi:**
- Engagement ridicat (polls interactive)
- Feedback instant de la cititori
- Predicții pentru evenimente sportive

**Implementare în Deschide News:**
```php
class LiveTextPoll
{
    private ?int $id;
    private LiveText $liveText;
    private string $question;
    private array $options; // JSON array de opțiuni
    private DateTime $startTime;
    private ?DateTime $endTime;
    private bool $allowMultipleVotes;
    private int $totalVotes;
}

class LiveTextPollVote
{
    private ?int $id;
    private LiveTextPoll $poll;
    private ?User $user;
    private string $optionId;
    private ?string $ipAddress;
    private DateTime $votedAt;
}
```

**Features:**
- Real-time vote counting
- Anonymous voting (rate limited per IP)
- Results visualization (charts)
- Embed polls în posturi

**Frontend Component:**
```typescript
<LiveTextPoll
  question="Cine va câștiga meciul?"
  options={['Echipa A', 'Echipa B', 'Egalitate']}
  totalVotes={1234}
  results={[45, 35, 20]} // percentages
  userVoted={true}
/>
```

**Prioritate:** ⭐⭐⭐ (Should-have pentru Phase 6)

---

#### 2.7 **Analytics & Bandwidth Monitoring**
**Ce face în Liveblog:**
- Module `/analytics` pentru metrics
- Module `/bandwidth` pentru usage tracking
- Throttling pentru high-traffic events

**De ce este util pentru noi:**
- Traffic monitoring în timp real
- Cost control pentru CDN bandwidth
- Performance optimization insights

**Implementare în Deschide News:**
```php
class LiveTextAnalytics
{
    private ?int $id;
    private LiveText $liveText;
    private int $totalViews;
    private int $uniqueViewers;
    private int $peakConcurrentViewers;
    private float $averageTimeSpent;
    private int $totalReactions;
    private int $totalComments;
    private int $totalShares;
    private array $viewsByHour; // JSON histogram
    private array $topReferrers;
    private DateTime $calculatedAt;
}

class LiveTextBandwidthUsage
{
    private ?int $id;
    private LiveText $liveText;
    private float $bandwidthMB;
    private int $requestCount;
    private DateTime $date;
}
```

**Monitoring Dashboard:**
- Real-time charts cu viewers over time
- Geographic distribution (via IP lookup)
- Engagement metrics per post
- Bandwidth costs estimation

**Prioritate:** ⭐⭐⭐⭐ (Must-have pentru Sprint 10)

---

#### 2.8 **User Role & Permission System**
**Ce face în Liveblog:**
- 7 privileges granulare pentru posturi
- Role-based access control
- Fine-grained permissions per action

**Permissions identificate:**
```
- posts (create/read posts)
- publish_post (publish to live)
- submit_post (submit for review)
- post_comments_create
- post_comments_read
- post_comments_update
- post_comments_delete
```

**Implementare în Deschide News:**
```php
enum LiveTextPermission: string
{
    case VIEW = 'live_text.view';
    case CREATE = 'live_text.create';
    case EDIT = 'live_text.edit';
    case DELETE = 'live_text.delete';
    case PUBLISH = 'live_text.publish';
    case POST_CREATE = 'live_text.post.create';
    case POST_EDIT_OWN = 'live_text.post.edit_own';
    case POST_EDIT_ANY = 'live_text.post.edit_any';
    case POST_DELETE_OWN = 'live_text.post.delete_own';
    case POST_DELETE_ANY = 'live_text.post.delete_any';
    case COMMENT_MODERATE = 'live_text.comment.moderate';
    case ANALYTICS_VIEW = 'live_text.analytics.view';
}

// Roluri predefinite
class LiveTextRole
{
    const VIEWER = 'viewer';           // Doar citire
    const CONTRIBUTOR = 'contributor'; // Creare posturi (submit for review)
    const EDITOR = 'editor';          // Edit, publish posturi
    const MODERATOR = 'moderator';    // Moderare comentarii
    const ADMIN = 'admin';            // Full control
}
```

**Security Voters în Symfony:**
```php
class LiveTextPostVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof LiveTextPost &&
               in_array($attribute, ['EDIT', 'DELETE', 'PUBLISH']);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        $post = $subject;

        return match($attribute) {
            'EDIT' => $this->canEdit($post, $user),
            'DELETE' => $this->canDelete($post, $user),
            'PUBLISH' => $this->canPublish($post, $user),
            default => false
        };
    }
}
```

**Prioritate:** ⭐⭐⭐⭐⭐ (Must-have pentru Sprint 2)

---

#### 2.9 **Advertisements Module**
**Ce face în Liveblog:**
- Module `/advertisements` pentru ad management
- Integrare în live text stream

**De ce este util pentru noi:**
- Monetizare pentru live texts
- Sponsored posts
- Ad placements în posturi

**Implementare în Deschide News:**
```php
class LiveTextAdvertisement
{
    private ?int $id;
    private LiveText $liveText;
    private string $title;
    private string $content;
    private ?string $imageUrl;
    private ?string $clickUrl;
    private int $position; // La fiecare N posturi
    private DateTime $startTime;
    private DateTime $endTime;
    private int $impressions;
    private int $clicks;
}
```

**Features:**
- Ad injection la fiecare 10 posturi (configurable)
- A/B testing pentru ad creative
- Click tracking
- Impression tracking
- Sponsor badges

**Prioritate:** ⭐⭐ (Could-have pentru Phase 6 - Monetization)

---

#### 2.10 **Video Upload & Embed**
**Ce face în Liveblog:**
- Module `/video_upload` pentru video handling
- Direct upload support

**De ce este util pentru noi:**
- Video embeds din posturi (YouTube, Vimeo, etc.)
- Direct video upload (S3 sau CDN)
- Live stream embed

**Implementare în Deschide News:**
- Reutilizare sistem existing de imagini (Image entity)
- Extindere pentru video files
- Thumbnail generation pentru videos
- Adaptive bitrate streaming (HLS/DASH)

**Prioritate:** ⭐⭐⭐ (Should-have pentru Sprint 6)

---

#### 2.11 **Multi-Language Support**
**Ce face în Liveblog:**
- Module `/languages` pentru i18n
- Content în multiple limbi

**De ce este util pentru noi:**
- **DEJA IMPLEMENTAT** în Deschide News (ro/en/ru)
- Gedmo Translatable pentru entități
- API Platform locale detection

**Observație:** Deschide News are deja un sistem superior de multi-language cu Gedmo Translatable. Nu este nevoie de modificări.

---

#### 2.12 **Instance Settings / Configuration Management**
**Ce faire în Liveblog:**
- Module `/instance_settings` pentru config
- Runtime configuration changes

**Implementare în Deschide News:**
```php
class LiveTextSettings
{
    private ?int $id;
    private LiveText $liveText;
    private array $settings; // JSON

    // Settings examples:
    // - autoPublish: bool
    // - postsPerPage: int
    // - enableComments: bool
    // - enableReactions: bool
    // - enablePolls: bool
    // - moderationMode: 'auto'|'manual'
    // - notificationSound: bool
}
```

**Prioritate:** ⭐⭐⭐ (Should-have pentru Sprint 5)

---

## 3. Architectural Patterns (Lessons Learned)

### 3.1 **Modular Architecture**
**Liveblog Pattern:**
```
server/liveblog/
├── posts/          # Post management
├── comments/       # Comments
├── polls/          # Polls
├── themes/         # Themes
├── syndication/    # Content distribution
├── analytics/      # Analytics
└── advertisements/ # Ads
```

**Aplicare în Deschide News:**
```
deschide_backend/src/
├── Entity/LiveText/
│   ├── LiveText.php
│   ├── LiveTextPost.php
│   ├── LiveTextPostVersion.php
│   ├── LiveTextComment.php
│   ├── LiveTextPoll.php
│   ├── LiveTextTheme.php
│   └── LiveTextSyndication.php
├── Service/LiveText/
│   ├── LiveTextService.php
│   ├── LiveTextPostService.php
│   ├── LiveTextCommentService.php
│   ├── LiveTextAnalyticsService.php
│   └── LiveTextNotificationService.php
├── State/LiveText/
│   ├── LiveTextProvider.php
│   ├── LiveTextPostProvider.php
│   └── LiveTextCommentProvider.php
└── Command/LiveText/
    ├── CleanupExpiredCommand.php
    └── PublishScheduledCommand.php
```

---

### 3.2 **WebSocket vs. Mercure pentru Real-Time**

**Liveblog folosește:**
- WebSocket server pe port 5100
- Bidirectional communication
- Custom protocol

**Deschide News folosește:**
- Mercure (Server-Sent Events)
- Unidirectional (server → client)
- Standard HTTP/2

**Concluzie:** Mercure este suficient pentru use-case-ul nostru (server push). WebSocket ar fi overkill și mai complex de scalat.

---

### 3.3 **Elasticsearch Indexing**

**Liveblog Pattern:**
- Explicit index creation via commands
- Rebuild capability pentru troubleshooting
- Asynchronous indexing

**Aplicare în Deschide News:**
```bash
# Commands pentru Live Text
symfony console app:elasticsearch:create-live-text-index
symfony console app:elasticsearch:index-live-texts
symfony console app:elasticsearch:index-live-text-posts
```

**Index Structure:**
```json
{
  "live_text_posts": {
    "mappings": {
      "properties": {
        "liveTextId": { "type": "keyword" },
        "content": { "type": "text", "analyzer": "romanian" },
        "contentHtml": { "type": "text" },
        "author": { "type": "text" },
        "publishedAt": { "type": "date" },
        "isKeyPoint": { "type": "boolean" },
        "flags": { "type": "keyword" }
      }
    }
  }
}
```

---

### 3.4 **Asset Storage Strategy**

**Liveblog:**
- Amazon S3 pentru published assets
- Separate storage pentru production

**Deschide News:**
- CDN local pe port 8082 (development)
- Posibilitate migrare S3/CloudFront (production)

**Recomandare:**
- Development: CDN local
- Production: CloudFront + S3 pentru scale
- Async upload cu RabbitMQ

---

### 3.5 **Testing Strategy**

**Liveblog folosește:**
- Behavior-Driven Development (Behave/Gherkin)
- Feature files pentru scenarios
- Multiple testing layers (unit, integration, E2E)

**Example (Syndication Feature):**
```gherkin
Feature: Syndication
  Scenario: Syndicare live text pe platform externă
    Given un live text activ "Breaking News"
    And o configurație de syndicare pentru "RSS Feed"
    When un post nou este publicat
    Then RSS feed-ul este actualizat în < 5 secunde
    And webhook-ul este notificat
```

**Aplicare în Deschide News:**
```bash
# Vom folosi:
# - PHPUnit pentru unit tests
# - Behat pentru BDD (opțional, pentru Phase 6)
# - Symfony Functional Tests pentru API
```

---

## 4. Database Schema Improvements

### Comparație: Liveblog (MongoDB) vs. Deschide News (PostgreSQL)

**Liveblog MongoDB Schema (inferred):**
```javascript
{
  "_id": ObjectId("..."),
  "title": "Live Event Title",
  "posts": [
    {
      "content": "Post content",
      "author": ObjectId("user_id"),
      "created_at": ISODate("..."),
      "flags": ["breaking", "sticky"],
      "versions": [...]
    }
  ]
}
```

**Deschide News PostgreSQL Schema (improved):**
```sql
-- Adăugăm câmpuri noi bazate pe insights Liveblog

ALTER TABLE live_text ADD COLUMN settings JSONB DEFAULT '{}';
ALTER TABLE live_text ADD COLUMN syndication_config JSONB DEFAULT '{}';

ALTER TABLE live_text_post ADD COLUMN flags JSONB DEFAULT '[]';
ALTER TABLE live_text_post ADD COLUMN version_number INT DEFAULT 1;
ALTER TABLE live_text_post ADD COLUMN parent_version_id INT NULL;

-- Index pentru performanță
CREATE INDEX idx_live_text_post_flags ON live_text_post USING GIN (flags);
CREATE INDEX idx_live_text_post_published ON live_text_post (published_at DESC);
CREATE INDEX idx_live_text_status ON live_text (status);
```

---

## 5. Performance & Scalability Lessons

### 5.1 **Load Testing Requirements**

**Liveblog experience:**
- Testat pentru evenimente high-traffic
- WebSocket connections management
- Database query optimization

**Pentru Deschide News:**
```bash
# Load testing scenarios
- 10,000+ concurrent viewers per live text
- 50 posts per minute (peak rate)
- 1,000 reactions per minute
- 500 comments per minute

# Tools:
- k6 pentru load testing
- Grafana pentru monitoring
- Redis pentru viewer count caching
```

---

### 5.2 **Caching Strategy**

**Cache layers:**
1. **Redis cache pentru:**
   - Viewer count (TTL: 30 seconds)
   - Recent posts (TTL: 5 minutes)
   - Analytics aggregates (TTL: 1 hour)

2. **HTTP cache headers:**
   - Public pages: Cache-Control: max-age=60
   - API responses: ETag support

3. **Database query optimization:**
   - Eager loading pentru relații
   - Materialized views pentru analytics

---

## 6. Recommended Feature Priority

### Phase 1 (Sprints 1-2) - Foundation ✅
- Live Text entities
- Live Text Post entities
- Basic API endpoints
- **NEW:** Role & Permission system

### Phase 2 (Sprints 3-4) - Real-Time ✅
- Mercure integration
- Public viewer page
- **NEW:** Post Flags system (sticky, breaking)

### Phase 3 (Sprints 5-6) - Admin Interface ✅
- Admin management UI
- Post editor with rich text
- **NEW:** Post Versioning system
- **NEW:** Instance Settings

### Phase 4 (Sprints 7-9) - Advanced Features ✅
- Key Points & Timeline
- Reactions
- **NEW:** Theme System (enhanced)
- **NEW:** Video embed support

### Phase 5 (Sprints 10-11) - Polish ✅
- Analytics dashboard
- **NEW:** Bandwidth monitoring
- Notifications
- Performance optimization

### Phase 6 (Sprints 12+) - Optional Features
- **NEW:** Comments system cu moderare ⭐⭐⭐
- **NEW:** Polls/Voting system ⭐⭐⭐
- **NEW:** Syndication (RSS, webhooks) ⭐⭐
- **NEW:** Advertisements ⭐⭐
- Social media integration
- Mobile apps

---

## 7. Updated Technology Recommendations

### Backend
```yaml
# Deja avem:
✅ Symfony 7.3
✅ API Platform 4.0
✅ PostgreSQL 17
✅ Redis
✅ Mercure
✅ Elasticsearch

# Vom adăuga:
- HTMLPurifier (pentru sanitizare contentHtml)
- Symfony Workflow (pentru post states: draft → review → published)
- Symfony Mailer (pentru notifications)
```

### Frontend
```yaml
# Deja avem:
✅ Next.js 16
✅ React 19.2
✅ TypeScript
✅ Tailwind CSS

# Vom adăuga:
- TipTap (rich text editor - modern alternative la Quill)
- Chart.js (pentru analytics dashboard)
- React Query (pentru data fetching și caching)
- Zustand (state management - mai simplu decât Redux)
```

---

## 8. Implementation Checklist (Updated)

### Immediate Additions to Roadmap

**Sprint 2 (API Endpoints):**
- [ ] Adaugă endpoints pentru versiuni: `/api/live_text_posts/{id}/versions`
- [ ] Adaugă endpoints pentru flags: `PATCH /api/live_text_posts/{id}/flags`
- [ ] Implementează role & permission system cu Symfony Security

**Sprint 6 (Post Editor):**
- [ ] Integrează TipTap rich text editor
- [ ] Adaugă video embed support
- [ ] Implementează post versioning în UI
- [ ] Adaugă flag toggles (sticky, breaking, featured)

**Sprint 7 (Advanced Features):**
- [ ] Implementează comment system cu moderare
- [ ] Adaugă poll creation UI
- [ ] Theme selector în admin

**Sprint 10 (Analytics):**
- [ ] Bandwidth monitoring dashboard
- [ ] Cost estimation pentru CDN usage
- [ ] Export analytics (CSV, PDF)

---

## 9. Security Considerations (din Liveblog)

### Vulnerabilities to Avoid

1. **XSS în contentHtml:**
   - ✅ Use HTMLPurifier pentru sanitization
   - ✅ Content Security Policy headers
   - ✅ Validate HTML tags whitelist

2. **CSRF protection:**
   - ✅ Symfony CSRF tokens (deja implementat)
   - ✅ SameSite cookie policy

3. **Rate limiting:**
   - ✅ Comment posting: 5/15min per IP
   - ✅ Reaction: 10/minute per user
   - ✅ Poll voting: 1 vote per poll per IP

4. **SQL Injection:**
   - ✅ Doctrine ORM (parametrizat queries)
   - ✅ No raw SQL în application code

5. **DoS protection:**
   - ✅ Cloudflare (sau similar) în production
   - ✅ Rate limiting la API level
   - ✅ Redis cache pentru viewer count (evitare DB hammering)

---

## 10. Conclusion & Next Steps

### Key Takeaways din Liveblog Analysis

1. **Modular architecture** este esențială pentru maintainability
2. **Post versioning** este must-have pentru audit și rollback
3. **Granular permissions** oferă flexibilitate pentru echipe
4. **Theme system** permite customization fără code changes
5. **Comments cu moderare** crește engagement semnificativ
6. **Analytics comprehensive** este esențial pentru business decisions
7. **Polls/Voting** adaugă interactivitate valoroasă

### Features Prioritizate pentru Implementare

**Must-Add (Priority 1):**
- ✅ Post Versioning System
- ✅ Post Flags (sticky, breaking, pinned)
- ✅ Role & Permission System (granular)
- ✅ Analytics & Bandwidth Monitoring

**Should-Add (Priority 2):**
- ✅ Theme System (enhanced cu marketplace concept)
- ✅ Comments cu moderare
- ✅ Polls/Voting system
- ✅ Video embed support

**Could-Add (Priority 3):**
- ✅ Syndication (RSS, webhooks)
- ✅ Advertisements
- ⬜ Marketplace pentru themes (viitor)

### Revised Timeline

**Original MVP:** 11-17 săptămâni
**Updated MVP (cu features din Liveblog):** 14-20 săptămâni (+3 weeks pentru features noi)

**Breakdown:**
- Phases 1-3: 6-10 săptămâni (unchanged)
- Phase 4: 4 săptămâni (+1 week pentru comments & polls)
- Phase 5: 2 săptămâni (unchanged, dar enhanced analytics)
- Phase 6: 2-4 săptămâni (syndication, ads)

### Immediate Actions

1. **Review acest document** cu team-ul
2. **Update roadmap** cu features noi identificate
3. **Prioritize features** based on business needs
4. **Start Sprint 1** cu confidence că avem arhitectură solidă

---

**Document Version:** 1.0
**Author:** Claude Code
**Status:** Analysis Complete - Ready for Roadmap Integration
**Last Updated:** 2025-11-03
