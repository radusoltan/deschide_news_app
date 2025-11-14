# Live Text Feature - Implementation Roadmap

## Executive Summary

Acest document prezintă roadmap-ul pentru implementarea unei funcționalități de **Live Text** în aplicația Deschide News App, similară cu [24livetext.com](https://www.24livetext.com/livetext).

Live Text-ul va permite redactorilor să publice știri în timp real despre evenimente în desfășurare (breaking news, evenimente sportive, conferințe live, etc.) cu actualizări automate pentru cititori fără refresh de pagină.

## Funcționalități Principale (Core Features)

### 1. **Live Text Posts (Postări în Timp Real)**
- Crearea de "live texts" pentru evenimente specifice
- Adăugarea de "posts/updates" secvențiale în cadrul unui live text
- Timestamp automat și ordonare cronologică inversă (cele mai noi sus)
- Editor rich-text pentru formatare conținut
- Suport multimedia: imagini, video embeds, tweet embeds, etc.

### 2. **Real-Time Updates (Actualizări în Timp Real)**
- Actualizări instant pentru cititori fără refresh (folosind Mercure)
- Notificări vizuale pentru posturi noi (highlight color, badge "NEW")
- Notificări sonore opționale pentru breaking updates
- Auto-scroll sau notificare pentru posturi noi

### 3. **Key Points / Highlights**
- Marcarea anumitor posturi ca "key points" / "highlights"
- Vizualizare separată a momentelor importante
- Timeline vizual cu evenimente cheie

### 4. **Collaboration (Colaborare în Echipă)**
- Multiple persoane pot posta în același live text simultan
- Afișare autor pentru fiecare post
- Sistem de permisiuni (cine poate posta în ce live text)

### 5. **Live Text Status Management**
- Status: Draft / Live / Paused / Ended
- Programare start/end pentru live text
- Indicatori vizuali de status (LIVE badge, ENDED badge)

### 6. **Interactive Features**
- Reactions/Likes pentru posturi individuale
- Comments (opțional, per live text sau per post)
- Share functionality pentru posturi individuale

### 7. **Multimedia Support**
- Upload imagini (cu preview)
- Embed video (YouTube, Vimeo, etc.)
- Embed social media (Twitter/X, Facebook, Instagram)
- Embed custom HTML/iframe

### 8. **Templates & Customization**
- Template-uri predefinite pentru tipuri de evenimente:
  - Breaking News
  - Sport Events (cu score tracking)
  - Conferences / Speeches
  - Elections
- Customizare culori și stiluri per live text

### 9. **Analytics & Metrics**
- Viewers counter în timp real
- Engagement metrics (views, reactions, shares)
- Time spent tracking

### 10. **Multi-language Support**
- Live texts în ro/en/ru (consistent cu aplicația)
- Traduceri pentru UI elements

### 11. **Post Versioning System** ⭐ NEW (din Liveblog analysis)
- Istoric complet pentru fiecare modificare post
- Rollback capabilities pentru editori
- Audit trail pentru compliance
- View diff între versiuni

### 12. **Post Flags System** ⭐ NEW (din Liveblog analysis)
- Flags: `sticky` (rămâne în top), `breaking` (breaking news badge), `pinned` (highlight în timeline), `featured` (homepage)
- Visual indicators pentru fiecare flag type
- Prioritizare automată în afișare

### 13. **Comments System cu Moderare** ⭐ NEW (din Liveblog analysis)
- Comentarii per post cu threading (opțional)
- Auto-approve pentru utilizatori autentificați
- Manual moderation pentru anonimi
- Spam detection și rate limiting
- Real-time comments via Mercure

### 14. **Polls/Voting System** ⭐ NEW (din Liveblog analysis)
- Polls interactive în posturi
- Real-time vote counting
- Anonymous voting (rate limited per IP)
- Results visualization cu charts
- Multiple choice și single choice support

### 15. **Enhanced Theme System** ⭐ NEW (din Liveblog analysis)
- Asset pipeline pentru theme customization
- System themes vs. user themes
- Dynamic CSS loading
- Theme preview în admin
- White-label capabilities pentru parteneri

### 16. **Syndication System** ⭐ NEW (din Liveblog analysis)
- RSS feed pentru fiecare live text
- Webhook notifications pentru posturi noi
- JSON API pentru partner websites
- Auto-posting pe social media
- Embed widgets pentru external sites

### 17. **Bandwidth & Cost Monitoring** ⭐ NEW (din Liveblog analysis)
- Real-time bandwidth usage tracking
- CDN cost estimation
- Traffic throttling pentru high-load events
- Alert system pentru unusual traffic spikes

### 18. **Advertisements Module** ⭐ NEW (din Liveblog analysis)
- Ad injection la interval configurable (ex: fiecare 10 posturi)
- Sponsored posts cu badge
- Click tracking și impression tracking
- A/B testing pentru ad creative

## Arhitectură Tehnică

### Backend (Symfony)

**Noi Entități:**
1. **LiveText** - Container pentru un eveniment live
2. **LiveTextPost** - Postări individuale în cadrul live text-ului
3. **LiveTextPostVersion** - ⭐ NEW: Versiuni posturi pentru audit trail
4. **LiveTextTemplate** - Template-uri predefinite
5. **LiveTextReaction** - Reactions/likes pentru posturi
6. **LiveTextComment** - ⭐ NEW: Comentarii per post cu moderare
7. **LiveTextPoll** - ⭐ NEW: Polls interactive
8. **LiveTextPollVote** - ⭐ NEW: Votes pentru polls
9. **LiveTextView** - Tracking vizualizări
10. **LiveTextBandwidthUsage** - ⭐ NEW: Tracking bandwidth
11. **LiveTextSyndication** - ⭐ NEW: Config syndication
12. **LiveTextAdvertisement** - ⭐ NEW: Ads în live text
13. **LiveTextCollaborator** - Relație Many-to-Many între LiveText și User

**Servicii:**
- `LiveTextService` - Business logic pentru live texts
- `LiveTextPostService` - Business logic pentru posturi
- `LiveTextPostVersionService` - ⭐ NEW: Versioning management
- `LiveTextCommentService` - ⭐ NEW: Comments și moderare
- `LiveTextPollService` - ⭐ NEW: Polls și voting
- `LiveTextNotificationService` - Publicare evenimente Mercure
- `LiveTextAnalyticsService` - Colectare metrici
- `LiveTextBandwidthService` - ⭐ NEW: Bandwidth tracking
- `LiveTextSyndicationService` - ⭐ NEW: Content distribution
- `LiveTextThemeService` - ⭐ NEW: Theme management

**API Endpoints:**
- `/api/live_texts` - CRUD live texts
- `/api/live_text_posts` - CRUD posturi
- `/api/live_texts/{id}/posts` - Subcollection posturi
- `/api/live_text_posts/{id}/versions` - ⭐ NEW: Versiuni post
- `/api/live_text_posts/{id}/revert/{versionId}` - ⭐ NEW: Revert la versiune
- `/api/live_text_posts/{id}/flags` - ⭐ NEW: Post flags management
- `/api/live_text_posts/{id}/reactions` - Reactions
- `/api/live_text_posts/{id}/comments` - ⭐ NEW: Comments per post
- `/api/comments/{id}/approve` - ⭐ NEW: Aprobare comentariu (moderator)
- `/api/live_texts/{id}/polls` - ⭐ NEW: Polls în live text
- `/api/polls/{id}/vote` - ⭐ NEW: Vote în poll
- `/api/live_texts/{id}/viewers` - Viewer count
- `/api/live_texts/{id}/analytics` - Analytics detailat
- `/api/live_texts/{id}/bandwidth` - ⭐ NEW: Bandwidth usage
- `/api/live_texts/{id}/rss` - ⭐ NEW: RSS feed
- `/api/live_texts/{id}/syndicate` - ⭐ NEW: Trigger syndication
- `/api/live_text_templates` - ⭐ NEW: Theme templates
- `/api/live_texts/{id}/advertisements` - ⭐ NEW: Ads management

**Real-Time Integration:**
- Mercure topics: `deschide_news/live_text/{id}` pentru fiecare live text
- Publish events:
  - `post.created`, `post.updated`, `post.deleted`, `status.changed`
  - `comment.created` - ⭐ NEW: Comentariu nou
  - `poll.created` - ⭐ NEW: Poll nou
  - `poll.vote` - ⭐ NEW: Vote nou în poll (update counts)
  - `viewers.count` - Update viewer count în timp real

### Frontend (Next.js)

**Pagini:**
- `/[locale]/live` - Lista live texts active
- `/[locale]/live/[slug]` - Vizualizare live text individual
- `/[locale]/admin/live-texts` - Admin: Management live texts
- `/[locale]/admin/live-texts/[id]/posts` - Admin: Editor posturi

**Componente:**
- `LiveTextList` - Lista live texts
- `LiveTextCard` - Card preview live text
- `LiveTextViewer` - Viewer pentru live text (public)
- `LiveTextPostItem` - Post individual
- `LiveTextEditor` - Editor pentru crearea/editarea live texts
- `LiveTextPostEditor` - Editor pentru posturi
- `LiveTextTimeline` - Timeline cu key points
- `LiveTextNotification` - Notificare pentru posturi noi
- `LiveTextReactions` - Componenta de reactions
- `MercureSubscription` - Hook pentru real-time updates

**State Management:**
- Context API sau Zustand pentru live text state
- Real-time state sync cu Mercure

## Implementation Roadmap

### **Phase 1: Foundation & Core Entities** (Sprint 1-2)

#### Sprint 1: Database Schema & Base Entities
**Durata: 1-2 săptămâni**

**Backend Tasks:**
- [ ] Creare entitate `LiveText`
  - Proprietăți: title, slug, description, status (enum: draft/live/paused/ended), startTime, endTime, locale
  - Relații: author (User), category, collaborators (Many-to-Many cu User)
  - Gedmo: Translatable, Sluggable, Timestampable
- [ ] Creare entitate `LiveTextPost`
  - Proprietăți: content (text), contentHtml (rich text), isKeyPoint, position, publishedAt
  - Relații: liveText (ManyToOne), author (User)
  - Gedmo: Timestampable
- [ ] Creare entitate `LiveTextCollaborator`
  - Relație Many-to-Many între LiveText și User cu metadata (role, permissions)
- [ ] Creare enum `LiveTextStatus` (Draft, Live, Paused, Ended)
- [ ] Doctrine migrations pentru noi tabele
- [ ] Fixtures pentru date de test

**Deliverables:**
- ✅ Schema bazei de date completă
- ✅ Entități cu validare și relații
- ✅ Migrations funcționale
- ✅ Date de test în database

---

#### Sprint 2: Basic API Endpoints
**Durata: 1-2 săptămâni**

**Backend Tasks:**
- [ ] API Platform configuration pentru `LiveText` și `LiveTextPost`
- [ ] State Providers cu eager loading (prevent N+1)
- [ ] State Processors cu validare business logic
- [ ] Serialization groups: `livetext:read`, `livetext:write`, `livetext:detail`
- [ ] Filtre API: status, category, locale, isActive
- [ ] Ordering: publishedAt, startTime
- [ ] Pagination configuration
- [ ] Permissions & Security (doar colaboratori pot posta)

**API Endpoints Create:**
```
GET    /api/live_texts                    # List all live texts
POST   /api/live_texts                    # Create live text
GET    /api/live_texts/{id}              # Get single live text
PUT    /api/live_texts/{id}              # Update live text
DELETE /api/live_texts/{id}              # Delete live text
GET    /api/live_texts/{id}/posts        # Get posts for live text
POST   /api/live_text_posts              # Create new post
PUT    /api/live_text_posts/{id}         # Update post
DELETE /api/live_text_posts/{id}         # Delete post
```

**Testing:**
- [ ] Test cu curl toate endpoints
- [ ] Test eager loading (no N+1 queries)
- [ ] Test permissions (doar colaboratori pot posta)

**Deliverables:**
- ✅ API funcțional pentru CRUD live texts
- ✅ API funcțional pentru CRUD posturi
- ✅ Validare și permissions

---

### **Phase 2: Real-Time Updates** (Sprint 3-4)

#### Sprint 3: Mercure Integration
**Durata: 1 săptămână**

**Backend Tasks:**
- [ ] Creare `LiveTextNotificationService`
- [ ] Integrare Mercure Publisher în Processors
- [ ] Publicare evenimente pentru:
  - Post creat: `{topic: "/live_text/{id}", type: "post.created", data: postDto}`
  - Post actualizat: `{type: "post.updated", data: postDto}`
  - Post șters: `{type: "post.deleted", id: postId}`
  - Status schimbat: `{type: "status.changed", status: "live"}`
- [ ] Topic Mercure: `deschide_news/live_text/{liveTextId}`
- [ ] DTO pentru mesaje Mercure

**Frontend Tasks:**
- [ ] Creare hook `useMercureSubscription(liveTextId)`
- [ ] Integrare EventSource pentru Mercure
- [ ] Handle evenimente: post.created, post.updated, post.deleted, status.changed
- [ ] Update state la primire evenimente
- [ ] Error handling și reconnection logic

**Testing:**
- [ ] Test publish evenimente din backend
- [ ] Test subscribe din frontend
- [ ] Test reconnection la pierdere conexiune
- [ ] Test multiple subscribers simultan

**Deliverables:**
- ✅ Real-time updates funcționale
- ✅ Frontend primește posturi noi instant
- ✅ Robust error handling

---

#### Sprint 4: Live Text Viewer (Frontend Public)
**Durata: 1-2 săptămâni**

**Frontend Tasks:**
- [ ] Pagină `/[locale]/live` - Lista live texts active
  - Filter: status=live
  - Card preview cu LIVE badge
  - Viewer count
  - Ultima actualizare timestamp
- [ ] Pagină `/[locale]/live/[slug]` - Vizualizare live text
  - Header: title, description, status badge, viewer count
  - Timeline posturi (reverse chronological)
  - Real-time updates cu Mercure
  - Notificare vizuală pentru posturi noi (highlight animation)
  - Scroll behavior: auto-scroll sau notificare "New posts available"
- [ ] Componente:
  - `LiveTextCard` - Preview card
  - `LiveTextViewer` - Container principal
  - `LiveTextPost` - Post individual cu timestamp, autor, content
  - `LiveTextHeader` - Header cu info și status
  - `NewPostNotification` - Banner "X new posts available"

**Design:**
- [ ] Responsive design (mobile-first)
- [ ] Animații pentru posturi noi (fade-in, highlight)
- [ ] LIVE badge animat (pulsing red dot)
- [ ] Timestamp formatting (relative: "2 minutes ago")

**Deliverables:**
- ✅ Pagină publică funcțională pentru live texts
- ✅ Real-time updates vizibile
- ✅ UX plăcut cu animații

---

### **Phase 3: Admin Interface** (Sprint 5-6)

#### Sprint 5: Live Text Management (Admin)
**Durata: 1-2 săptămâni**

**Frontend Tasks:**
- [ ] Pagină `/[locale]/admin/live-texts` - Lista toate live texts
  - Tabel cu: title, status, start/end time, posturi count, viewers
  - Filtre: status, date range
  - Actions: Create New, Edit, Delete, View
- [ ] Pagină `/[locale]/admin/live-texts/create` - Creare live text
  - Form: title, description, category, template, startTime, endTime
  - Selectare colaboratori
  - Language selection
  - Preview
- [ ] Pagină `/[locale]/admin/live-texts/[id]/edit` - Editare live text
  - Același form ca create
  - Status management (Draft → Live → Ended)
  - Manage collaborators

**Components:**
- [ ] `LiveTextAdminTable` - Tabel management
- [ ] `LiveTextForm` - Form create/edit
- [ ] `CollaboratorSelector` - Multi-select pentru colaboratori
- [ ] `StatusToggle` - Toggle status (Draft/Live/Paused/Ended)

**Deliverables:**
- ✅ Interface admin pentru management live texts
- ✅ CRUD complet funcțional

---

#### Sprint 6: Post Editor (Admin)
**Durata: 1-2 săptămâni**

**Frontend Tasks:**
- [ ] Pagină `/[locale]/admin/live-texts/[id]/posts` - Editor posturi
  - Split view: Editor (left) + Preview (right)
  - Lista posturi existente (editabile)
  - Form rapid pentru post nou
  - Rich text editor (TinyMCE, Quill, sau Tiptap)
  - Upload imagini
  - Embed media (video, tweets)
  - Toggle "Key Point"
  - Publish instant sau schedule
- [ ] Real-time collaboration indicators
  - "User X is typing..."
  - Lock post când altcineva editează

**Components:**
- [ ] `LiveTextPostEditor` - Editor principal
- [ ] `RichTextEditor` - Rich text editing
- [ ] `MediaUploader` - Upload și embed media
- [ ] `PostPreview` - Preview în timp real
- [ ] `PostList` - Lista posturi cu quick edit

**Rich Text Features:**
- [ ] Bold, italic, underline, strikethrough
- [ ] Lists (ordered, unordered)
- [ ] Links
- [ ] Headings (H2, H3)
- [ ] Blockquotes
- [ ] Code blocks

**Deliverables:**
- ✅ Editor posturi complet funcțional
- ✅ Rich text editing
- ✅ Upload și embed media
- ✅ Preview în timp real

---

### **Phase 4: Advanced Features** (Sprint 7-9)

#### Sprint 7: Key Points & Timeline
**Durata: 1 săptămână**

**Backend Tasks:**
- [ ] Endpoint `/api/live_texts/{id}/key_points` - Filtrare posturi cu isKeyPoint=true
- [ ] Order by publishedAt

**Frontend Tasks:**
- [ ] Componenta `LiveTextTimeline` - Timeline grafic cu key points
  - Vertical timeline cu dots
  - Display doar posturi cu isKeyPoint=true
  - Clickable pentru jump to post
- [ ] Toggle view: "All Posts" vs "Key Points Only"
- [ ] Sticky sidebar cu timeline (pe desktop)

**Deliverables:**
- ✅ Timeline funcțional cu key points
- ✅ Quick navigation între momente importante

---

#### Sprint 8: Reactions & Engagement
**Durata: 1 săptămână**

**Backend Tasks:**
- [ ] Creare entitate `LiveTextReaction`
  - Proprietăți: reactionType (enum: like, love, wow, sad, angry), ipAddress, userAgent
  - Relații: liveTextPost (ManyToOne), user (ManyToOne, nullable pentru anonimi)
- [ ] Endpoint `/api/live_text_posts/{id}/reactions` - CRUD reactions
- [ ] Endpoint `/api/live_text_posts/{id}/reactions/count` - Count per type
- [ ] Rate limiting pentru anonimi (1 reaction per IP per post)

**Frontend Tasks:**
- [ ] Componenta `ReactionButtons` - Buttons pentru fiecare tip
- [ ] Display count pentru fiecare reaction type
- [ ] Optimistic updates (show instant, sync async)
- [ ] Cookie/localStorage pentru tracking reactions (anonimi)

**Deliverables:**
- ✅ Sistem de reactions funcțional
- ✅ Real-time update counts

---

#### Sprint 9: Templates & Customization
**Durata: 1 săptămână**

**Backend Tasks:**
- [ ] Creare entitate `LiveTextTemplate`
  - Proprietăți: name, description, type (enum: breaking_news, sport, conference, election), config (JSON)
  - Config JSON: colors, layout, enabledFeatures
- [ ] Seeding template-uri predefinite:
  - Breaking News (red theme, urgent notifications)
  - Sport Event (score tracking, timeline)
  - Conference (speaker tracking, Q&A)
  - Elections (results tracking, charts)
- [ ] Relație LiveText → LiveTextTemplate (ManyToOne, nullable)

**Frontend Tasks:**
- [ ] Template selector în LiveText create/edit form
- [ ] CSS customization per template
- [ ] Preview template la selecție

**Deliverables:**
- ✅ 4 template-uri predefinite
- ✅ Customizare culori și layout

---

### **Phase 5: Analytics & Polish** (Sprint 10-11)

#### Sprint 10: Analytics & Metrics
**Durata: 1 săptămână**

**Backend Tasks:**
- [ ] Creare entitate `LiveTextView`
  - Proprietăți: viewedAt, sessionId, timeSpent, ipAddress
  - Relații: liveText (ManyToOne), user (ManyToOne, nullable)
- [ ] Endpoint `/api/live_texts/{id}/analytics` - Metrici aggregate
  - Total views
  - Unique viewers
  - Average time spent
  - Peak concurrent viewers
  - Post engagement (views, reactions per post)
- [ ] `LiveTextAnalyticsService` pentru calcule
- [ ] Real-time viewer count cu Redis
  - Key: `live_text:{id}:viewers` (Set cu session IDs)
  - Expire: 5 minute inactivitate

**Frontend Tasks:**
- [ ] Real-time viewer counter în header
  - "👁 1,234 watching live"
- [ ] Admin analytics dashboard
  - Charts: viewers over time, engagement per post
  - Metrics cards: total views, avg time, peak viewers
- [ ] Tracking user presence
  - Heartbeat la 30 secunde
  - Remove din viewer count la disconnect

**Deliverables:**
- ✅ Real-time viewer count
- ✅ Admin analytics dashboard
- ✅ Engagement metrics

---

#### Sprint 11: Notifications & Polish
**Durata: 1 săptămână**

**Backend Tasks:**
- [ ] Push notifications infrastructure (opțional, poate fi Phase 6)
  - Web Push API pentru browser notifications
  - Notificări pentru: live text started, important post
- [ ] Email notifications pentru followers (opțional)

**Frontend Tasks:**
- [ ] Sound notifications pentru posturi noi
  - Toggle ON/OFF în UI
  - Sunet discret pentru posturi normale
  - Sunet urgent pentru key points
- [ ] Visual notifications
  - Browser tab title update: "(1 new) Live Text Title"
  - Favicon badge
- [ ] Accessibility improvements
  - ARIA labels pentru screen readers
  - Keyboard navigation
  - Focus management
- [ ] Performance optimization
  - Lazy loading posturi (virtualization pentru 100+ posturi)
  - Image lazy loading
  - Code splitting

**Deliverables:**
- ✅ Notificări sonore opționale
- ✅ Visual cues pentru posturi noi
- ✅ Accessibility compliant
- ✅ Performance optimizat

---

### **Phase 6: Advanced Features (Optional)** (Sprint 12+)

Aceste features pot fi implementate după lansarea MVP-ului, based on user feedback.

#### Comments System
- [ ] Comentarii per live text sau per post
- [ ] Moderare comentarii
- [ ] Real-time update comentarii noi

#### Sport-Specific Features
- [ ] Score tracking component
- [ ] Match timeline cu events (goals, cards, substitutions)
- [ ] Live stats integration

#### Social Media Integration
- [ ] Auto-posting pe social media la posturi noi
- [ ] Social sharing optimizat (Open Graph, Twitter Cards)

#### Embed Capability
- [ ] Embed live text în alte site-uri (iframe sau JavaScript widget)
- [ ] Embed API pentru parteneri

#### Advanced Analytics
- [ ] Heatmaps pentru engagement
- [ ] A/B testing pentru templates
- [ ] Funnel analysis (viewers → reactions → shares)

#### Mobile Apps
- [ ] Native mobile apps (iOS, Android) pentru admin
- [ ] Push notifications mobile

---

## Database Schema (ERD)

```
┌─────────────────┐
│    LiveText     │
├─────────────────┤
│ id              │
│ title           │ (translatable)
│ slug            │
│ description     │ (translatable)
│ status          │ (enum: draft/live/paused/ended)
│ startTime       │
│ endTime         │
│ locale          │
│ template_id     │ (FK → LiveTextTemplate)
│ category_id     │ (FK → Category)
│ author_id       │ (FK → User)
│ createdAt       │
│ updatedAt       │
└─────────────────┘
         │
         │ 1
         │
         │ N
┌─────────────────┐
│ LiveTextPost    │
├─────────────────┤
│ id              │
│ liveText_id     │ (FK → LiveText)
│ author_id       │ (FK → User)
│ content         │ (text)
│ contentHtml     │ (rich text HTML)
│ isKeyPoint      │ (boolean)
│ position        │ (int, for manual ordering)
│ publishedAt     │
│ createdAt       │
│ updatedAt       │
└─────────────────┘
         │
         │ 1
         │
         │ N
┌──────────────────────┐
│  LiveTextReaction    │
├──────────────────────┤
│ id                   │
│ liveTextPost_id      │ (FK → LiveTextPost)
│ user_id              │ (FK → User, nullable)
│ reactionType         │ (enum: like/love/wow/sad/angry)
│ ipAddress            │
│ userAgent            │
│ createdAt            │
└──────────────────────┘

┌──────────────────────────┐
│  LiveTextCollaborator    │
├──────────────────────────┤
│ id                       │
│ liveText_id              │ (FK → LiveText)
│ user_id                  │ (FK → User)
│ role                     │ (enum: editor/contributor)
│ createdAt                │
└──────────────────────────┘

┌─────────────────────┐
│   LiveTextView      │
├─────────────────────┤
│ id                  │
│ liveText_id         │ (FK → LiveText)
│ user_id             │ (FK → User, nullable)
│ sessionId           │ (string)
│ ipAddress           │
│ timeSpent           │ (seconds)
│ viewedAt            │
└─────────────────────┘

┌─────────────────────┐
│ LiveTextTemplate    │
├─────────────────────┤
│ id                  │
│ name                │
│ description         │
│ type                │ (enum: breaking_news/sport/conference/election)
│ config              │ (JSON: colors, layout, features)
│ createdAt           │
│ updatedAt           │
└─────────────────────┘
```

---

## API Endpoints Summary

### Live Texts
| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| GET | `/api/live_texts` | List all live texts | Public |
| GET | `/api/live_texts?status=live` | List active live texts | Public |
| GET | `/api/live_texts/{id}` | Get single live text | Public |
| POST | `/api/live_texts` | Create live text | Admin/Editor |
| PUT | `/api/live_texts/{id}` | Update live text | Admin/Editor/Collaborator |
| PATCH | `/api/live_texts/{id}` | Partial update | Admin/Editor/Collaborator |
| DELETE | `/api/live_texts/{id}` | Delete live text | Admin/Editor |
| GET | `/api/live_texts/{id}/posts` | Get posts for live text | Public |
| GET | `/api/live_texts/{id}/key_points` | Get key points only | Public |
| GET | `/api/live_texts/{id}/analytics` | Get analytics | Admin/Editor/Collaborator |

### Live Text Posts
| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| GET | `/api/live_text_posts` | List all posts | Public |
| GET | `/api/live_text_posts/{id}` | Get single post | Public |
| POST | `/api/live_text_posts` | Create post | Admin/Editor/Collaborator |
| PUT | `/api/live_text_posts/{id}` | Update post | Admin/Editor/Author |
| DELETE | `/api/live_text_posts/{id}` | Delete post | Admin/Editor/Author |
| GET | `/api/live_text_posts/{id}/reactions` | Get reactions for post | Public |
| POST | `/api/live_text_posts/{id}/reactions` | Add reaction | Public (rate limited) |
| GET | `/api/live_text_posts/{id}/reactions/count` | Get reaction counts | Public |

### Templates
| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| GET | `/api/live_text_templates` | List all templates | Public |
| GET | `/api/live_text_templates/{id}` | Get single template | Public |
| POST | `/api/live_text_templates` | Create template | Admin |
| PUT | `/api/live_text_templates/{id}` | Update template | Admin |
| DELETE | `/api/live_text_templates/{id}` | Delete template | Admin |

---

## Mercure Topics & Events

### Topic Structure
```
deschide_news/live_text/{liveTextId}
```

### Event Types

**1. post.created**
```json
{
  "type": "post.created",
  "liveTextId": 123,
  "post": {
    "id": 456,
    "content": "Breaking: ...",
    "contentHtml": "<p>Breaking: ...</p>",
    "author": {"id": 1, "name": "John Doe"},
    "isKeyPoint": false,
    "publishedAt": "2025-11-03T14:30:00Z"
  }
}
```

**2. post.updated**
```json
{
  "type": "post.updated",
  "liveTextId": 123,
  "post": { /* full post object */ }
}
```

**3. post.deleted**
```json
{
  "type": "post.deleted",
  "liveTextId": 123,
  "postId": 456
}
```

**4. status.changed**
```json
{
  "type": "status.changed",
  "liveTextId": 123,
  "status": "live",
  "changedAt": "2025-11-03T14:00:00Z"
}
```

**5. viewers.count**
```json
{
  "type": "viewers.count",
  "liveTextId": 123,
  "count": 1234
}
```

---

## Frontend Routes

### Public Pages
| Route | Component | Description |
|-------|-----------|-------------|
| `/[locale]/live` | `LiveTextsListPage` | Lista live texts active |
| `/[locale]/live/[slug]` | `LiveTextViewerPage` | Vizualizare live text |
| `/[locale]/live/archive` | `LiveTextsArchivePage` | Live blogs încheiate |

### Admin Pages
| Route | Component | Description |
|-------|-----------|-------------|
| `/[locale]/admin/live-texts` | `LiveTextsManagementPage` | Management live texts |
| `/[locale]/admin/live-texts/create` | `LiveTextCreatePage` | Creare live text nou |
| `/[locale]/admin/live-texts/[id]/edit` | `LiveTextEditPage` | Editare live text |
| `/[locale]/admin/live-texts/[id]/posts` | `LiveTextPostsEditorPage` | Editor posturi |
| `/[locale]/admin/live-texts/[id]/analytics` | `LiveTextAnalyticsPage` | Analytics dashboard |

---

## Technology Stack Summary

### Backend
- **Framework:** Symfony 7.3 (PHP 8.4)
- **API:** API Platform 4.0
- **Database:** PostgreSQL 17
- **ORM:** Doctrine 3.5
- **Real-Time:** Mercure Hub
- **Cache:** Redis (DB 1)
- **Queue:** RabbitMQ (pentru notificări async, email, etc.)
- **Auth:** JWT (Lexik JWT Bundle)

### Frontend
- **Framework:** Next.js 16 (React 19.2)
- **Language:** TypeScript
- **Styling:** Tailwind CSS 4
- **Rich Text Editor:** TipTap sau Quill
- **Real-Time:** Mercure (EventSource)
- **State Management:** Context API sau Zustand
- **Forms:** React Hook Form + Zod validation

### Infrastructure (Already Available)
- **Message Queue:** RabbitMQ
- **Cache:** Redis
- **Real-Time Push:** Mercure
- **Monitoring:** Prometheus + Grafana
- **Search:** Elasticsearch (for searching old live texts)

---

## Success Metrics (KPIs)

### Technical Metrics
- [ ] Real-time latency < 500ms (time from post creation to viewer update)
- [ ] Concurrent viewers support: 10,000+ per live text
- [ ] Page load time < 2 seconds
- [ ] Time to first post visible < 1 second
- [ ] Uptime: 99.9%

### Business Metrics
- [ ] Average time spent on live text > 5 minutes
- [ ] Engagement rate (reactions/views) > 10%
- [ ] Return visitors rate > 30%
- [ ] Social shares per live text > 50

---

## Risk Assessment & Mitigation

### Technical Risks

**1. Real-Time Performance at Scale**
- **Risk:** Mercure poate avea probleme cu 10,000+ concurrent viewers
- **Mitigation:**
  - Load testing cu 10k+ connections
  - Horizontal scaling Mercure (multiple hubs)
  - CDN caching pentru static assets
  - Redis pentru viewer count (evitare DB hits)

**2. Database Load**
- **Risk:** Multe INSERT-uri pentru posturi noi + reactions
- **Mitigation:**
  - Async processing cu RabbitMQ pentru reactions
  - Database indexing optim (liveText_id, publishedAt)
  - Connection pooling
  - Read replicas pentru analytics

**3. Rich Text Editor Security**
- **Risk:** XSS vulnerabilities prin contentHtml
- **Mitigation:**
  - HTML sanitization (HTMLPurifier sau DOMPurify)
  - Content Security Policy (CSP)
  - Validator strict pentru allowed HTML tags

**4. Real-Time Connection Reliability**
- **Risk:** EventSource disconnects în condiții de rețea slabă
- **Mitigation:**
  - Auto-reconnect logic cu exponential backoff
  - Fetch missed posts la reconnect (based on lastPostId)
  - Offline indicator în UI

### Product Risks

**1. User Adoption**
- **Risk:** Editorii nu adoptă feature-ul
- **Mitigation:**
  - Training sessions pentru redactori
  - Simple, intuitive UI
  - Quick start templates

**2. Content Quality**
- **Risk:** Posturi prea scurte, spam
- **Mitigation:**
  - Guidelines pentru redactori
  - Minimum content length validation
  - Preview before publish

---

## Timeline Summary

### Original Timeline (pre-Liveblog analysis)
| Phase | Sprints | Duration | Key Deliverables |
|-------|---------|----------|------------------|
| **Phase 1: Foundation** | 1-2 | 2-4 weeks | Database schema, base entities, basic API |
| **Phase 2: Real-Time** | 3-4 | 2-4 weeks | Mercure integration, public viewer page |
| **Phase 3: Admin** | 5-6 | 2-4 weeks | Admin management, post editor |
| **Phase 4: Advanced** | 7-9 | 3 weeks | Key points, reactions, templates |
| **Phase 5: Polish** | 10-11 | 2 weeks | Analytics, notifications, optimization |
| **Phase 6: Optional** | 12+ | TBD | Comments, sport features, mobile apps |

**Original MVP Timeline:** 11-17 weeks (2.5 - 4 months)

---

### Updated Timeline (cu features din Liveblog analysis) ⭐ NEW

| Phase | Sprints | Duration | Key Deliverables | New Features |
|-------|---------|----------|------------------|--------------|
| **Phase 1: Foundation** | 1-2 | 2-4 weeks | Database schema, base entities, basic API | + Post Flags, + Versioning entities |
| **Phase 2: Real-Time** | 3-4 | 2-4 weeks | Mercure integration, public viewer page | + Comments events, + Poll events |
| **Phase 3: Admin** | 5-6 | 2-4 weeks | Admin management, post editor | + Version history UI, + Flag toggles |
| **Phase 4: Advanced** | 7-9 | 4 weeks | Key points, reactions, templates | + Comments cu moderare, + Polls system |
| **Phase 5: Polish** | 10-11 | 2 weeks | Analytics, notifications, optimization | + Bandwidth monitoring |
| **Phase 6: Enhanced** | 12-14 | 3 weeks | ⭐ NEW Phase | + Syndication, + Ads, + Theme marketplace |

**Updated MVP Timeline:** 14-20 weeks (3.5 - 5 months) - includes all essential features

**MVP Includes:** Phases 1-5 (Sprints 1-11)
**Extended Features:** Phase 6 (Sprints 12-14) - pentru monetization și distribution

---

## Next Steps

### Immediate Actions (Next 2 Weeks)

1. **Review & Approve Roadmap**
   - [ ] Review acest document cu stakeholders
   - [ ] Prioritize features (MoSCoW: Must/Should/Could/Won't)
   - [ ] Confirm timeline și resources

2. **Design Phase**
   - [ ] Wireframes pentru public viewer page
   - [ ] Wireframes pentru admin interface
   - [ ] UI/UX mockups în Figma
   - [ ] Design system components pentru live text

3. **Technical Preparation**
   - [ ] Mercure load testing (capacity planning)
   - [ ] Database indexing strategy
   - [ ] Security review (XSS, CSRF, rate limiting)

4. **Sprint 1 Kickoff**
   - [ ] Creare entities și migrations
   - [ ] Setup development environment pentru live text
   - [ ] Fixtures pentru testing

---

## Liveblog/Liveblog Open-Source Analysis ⭐ NEW

Am analizat repository-ul open-source [liveblog/liveblog](https://github.com/liveblog/liveblog) (7,970 commits, 97 releases) pentru a identifica best practices și features utile.

### Key Insights Adopted

**1. Post Versioning System** - Must-have pentru audit trail și rollback capabilities
- Implementare: Entitate `LiveTextPostVersion` cu diff viewer
- Use case: Editor poate revert la versiune anterioară

**2. Post Flags System** - Pentru prioritizare și categorisire posturi
- Flags: `sticky`, `breaking`, `pinned`, `featured`
- Use case: Posturi breaking rămân vizibile în top

**3. Comments cu Moderare** - Pentru user engagement
- Auto-approve pentru authenticated users
- Manual moderation pentru anonimi
- Real-time comments via Mercure

**4. Polls/Voting System** - Pentru interactivitate
- Real-time vote counting
- Anonymous voting cu rate limiting
- Results visualization

**5. Enhanced Theme System** - Pentru customization
- System themes vs. user themes
- Dynamic CSS loading
- White-label capabilities

**6. Syndication System** - Pentru content distribution
- RSS feeds
- Webhook notifications
- Partner API

**7. Bandwidth Monitoring** - Pentru cost control
- Real-time bandwidth tracking
- CDN cost estimation
- Traffic throttling

**8. Granular Permissions** - Pentru role-based access
- 7+ distinct privileges
- Fine-grained control per action
- Symfony Security Voters implementation

### Architecture Patterns Learned

**Modular Design:** Separate modules pentru posts, comments, polls, themes, syndication
**Testing Strategy:** Behavior-Driven Development (BDD) pentru complex features
**Performance:** Elasticsearch pentru search, Redis pentru caching, MongoDB/PostgreSQL pentru persistence
**Real-Time:** WebSocket (Liveblog) vs. Mercure (Deschide News) - Mercure este suficient pentru use-case-ul nostru

### Documentation

Pentru analiza completă, vezi: `/docs/live-text-liveblog-analysis.md`

---

## Appendix

### A. Similar Platforms Analysis

**24livetext.com:**
- ✅ Real-time updates
- ✅ Key points
- ✅ Team collaboration
- ✅ Templates
- ✅ Multimedia embeds

**Liveblog/Liveblog (Open-Source):** ⭐ NEW
- ✅ Post versioning system
- ✅ Post flags (sticky, breaking, pinned)
- ✅ Comments cu moderare
- ✅ Polls/Voting interactive
- ✅ Theme system cu marketplace
- ✅ Syndication (RSS, webhooks)
- ✅ Bandwidth monitoring
- ✅ Granular permissions
- ✅ Modular architecture (Python/MongoDB/Elasticsearch)

**The Guardian Live Texts:**
- ✅ Rich media embeds
- ✅ Timeline navigation
- ✅ Key events sidebar
- ✅ Social sharing per post

**BBC Live Pages:**
- ✅ Breaking news notifications
- ✅ Video live streams integration
- ✅ Related content sidebar
- ✅ Accessibility focus

### B. Open Source Alternatives

Am analizat următoarele soluții:
- **Liveblog/Liveblog** ⭐ ANALYZED (GitHub) - Production-ready, Python/MongoDB, 7,970 commits
  - ✅ Pros: Mature, feature-rich, proven at scale
  - ❌ Cons: Python stack (vs. PHP), MongoDB (vs. PostgreSQL), complex setup
  - 📝 Verdict: Nu folosim direct, dar am adoptat best practices și features
- **Live Text** (WordPress plugin) - Basic features, limited real-time
- **ScribbleLive** (acquired by Rock Content) - Enterprise, expensive
- **Custom build** ✅ RECOMMENDED - Full control, integration cu existing stack Symfony/Next.js

### C. Resources & Documentation

**Symfony:**
- [API Platform Documentation](https://api-platform.com/docs/)
- [Doctrine Relations](https://www.doctrine-project.org/projects/doctrine-orm/en/latest/reference/association-mapping.html)
- [Mercure Protocol](https://mercure.rocks/)

**Next.js:**
- [Server Components](https://nextjs.org/docs/app/building-your-application/rendering/server-components)
- [EventSource API](https://developer.mozilla.org/en-US/docs/Web/API/EventSource)

**Real-Time:**
- [Mercure.rocks Documentation](https://mercure.rocks/docs)
- [Server-Sent Events](https://developer.mozilla.org/en-US/docs/Web/API/Server-sent_events)

---

## Conclusion

Implementarea funcționalității de Live Text este un proiect ambițios dar fezabil, având în vedere infrastructura existentă (Mercure, RabbitMQ, Redis) și arhitectura solidă a aplicației Deschide News App.

### Impact analiza Liveblog/Liveblog ⭐ NEW

Analiză repository-ului open-source Liveblog a adus **8 features majore noi** și valuable insights despre arhitectură:
- ✅ Post Versioning - Audit trail complet
- ✅ Post Flags - Prioritizare automată
- ✅ Comments cu Moderare - User engagement crescut
- ✅ Polls/Voting - Interactivitate
- ✅ Enhanced Theme System - Customization fără limite
- ✅ Syndication - Content distribution automată
- ✅ Bandwidth Monitoring - Cost control
- ✅ Granular Permissions - Security îmbunătățit

**Recomandări:**
1. **Start cu MVP** (Phases 1-5) pentru validare concept
2. **Prioritize features din Liveblog analysis** - proven patterns la scale
3. **Iterații rapide** cu feedback de la redactori
4. **Focus pe performance** (real-time la 10k+ viewers)
5. **Prioritize UX** (editor simplu, viewer rapid)
6. **Consider Phase 6** pentru monetization (ads) și distribution (syndication)

**Estimated Total Effort:**
- **MVP Original:** 11-17 săptămâni
- **MVP Updated (cu Liveblog features):** 14-20 săptămâni (3.5-5 luni)
- **Full Implementation (Phase 1-6):** 17-23 săptămâni (4-5.5 luni)

Cu o echipă de 2-3 dezvoltatori (1 backend, 1 frontend, 1 full-stack), timeline-ul este realist. Features-urile din Liveblog analysis adaugă +3 săptămâni dar oferă value semnificativ pentru engagement, monetization și distribution.

**Next Milestones:**
- ✅ Roadmap complet cu features din Liveblog analysis
- ⏳ Stakeholder review și prioritization
- ⏳ Design phase (wireframes, mockups)
- ⏳ Sprint 1 kickoff (entities & migrations)

---

**Document Version:** 2.0 ⭐ UPDATED cu Liveblog analysis
**Last Updated:** 2025-11-03
**Author:** Claude Code
**Status:** Enhanced - Ready for Final Review
**Changelog:** Added 8 major features from Liveblog analysis, updated timeline, enhanced architecture
