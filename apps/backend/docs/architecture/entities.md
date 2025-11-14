# Entity Relationship Documentation

## Overview

Deschide News backend uses **Doctrine ORM** with **Gedmo extensions** for:
- **Translatable**: Multilingual content (ro, en, ru)
- **Sluggable**: Auto-generated URL slugs
- **Timestampable**: Created/updated timestamps
- **SoftDeletable**: Soft delete support (planned)

## Entity Relationship Diagram

```
┌─────────────┐          ┌──────────────┐
│   Category  │1       * │   Article    │
│─────────────│◄─────────│──────────────│
│ id          │          │ id           │
│ name †      │          │ title †      │
│ slug †      │          │ content †    │
│ parent_id   │          │ lead †       │
│ created_at  │          │ slug         │
│ updated_at  │          │ status       │
└─────────────┘          │ badge        │
                         │ category_id  │
                         │ author_id    │
                         │ published_at │
                         │ created_at   │
                         │ updated_at   │
                         └──────────────┘
                                ▲
                                │ *
                                │
                         ┌──────┴───────┐
                         │              │
                    ┌────┴────┐    ┌────┴────────────┐
                    │ Author  │1  *│ ArticleImage    │
                    │─────────│◄───│─────────────────│
                    │ id      │    │ id              │
                    │ name    │    │ article_id      │
                    │ slug    │    │ image_id        │
                    │ bio     │    │ position        │
                    │ email   │    │ is_featured     │
                    └─────────┘    └─────────────────┘
                                            │
                                            │ *
                                            ▼
                                   ┌────────────────┐
                                   │     Image      │1
                                   │────────────────│◄─┐
                                   │ id             │  │
                                   │ filename       │  │
                                   │ path           │  │
                                   │ width          │  │
                                   │ height         │  │
                                   │ mime_type      │  │
                                   │ size           │  │
                                   │ alt            │  │
                                   │ created_at     │  │
                                   └────────────────┘  │
                                            │          │
                                            │1         │*
                                            ▼          │
                                   ┌────────────────┐  │
                                   │   Thumbnail    │──┘
                                   │────────────────│
                                   │ id             │
                                   │ image_id       │
                                   │ profile_id     │
                                   │ filename       │
                                   │ path           │
                                   │ width          │
                                   │ height         │
                                   │ created_at     │
                                   └────────────────┘
                                            │
                                            │ *
                                            ▼
                                   ┌──────────────────┐
                                   │ ThumbnailProfile │
                                   │──────────────────│
                                   │ id               │
                                   │ name             │
                                   │ width            │
                                   │ height           │
                                   │ format           │
                                   └──────────────────┘

┌─────────────────┐
│ ArticleLock     │
│─────────────────│
│ id              │
│ article_id      │
│ user_id         │
│ locked_at       │
│ expires_at      │
└─────────────────┘

┌──────────────────────┐
│ ImportantArticlesList│
│──────────────────────│
│ id                   │
│ article_id           │
│ position             │
│ created_at           │
└──────────────────────┘

┌────────────────┐
│ RefreshToken   │
│────────────────│
│ id             │
│ refresh_token  │
│ username       │
│ valid          │
└────────────────┘

┌────────────────┐
│ User           │
│────────────────│
│ id             │
│ email          │
│ password       │
│ roles          │
│ first_name     │
│ last_name      │
│ is_active      │
│ created_at     │
│ updated_at     │
└────────────────┘

Legend:
† = Translatable field (Gedmo)
1 = One relationship
* = Many relationship
```

## Core Entities

### 1. Article

**Purpose**: News articles with multilingual support

**Table**: `articles`

**Relationships**:
- `ManyToOne` → Category
- `ManyToOne` → Author
- `OneToMany` → ArticleImage
- `OneToMany` → ArticleLock

**Translatable Fields**:
- `title` - Article headline
- `lead` - Article summary/introduction
- `content` - Full article content (HTML/Markdown)
- `meta_title` - SEO meta title
- `meta_description` - SEO meta description
- `slug` - URL-friendly identifier (auto-generated from title)

**Non-translatable Fields**:
- `status` - Enum: draft, published, scheduled, archived
- `badge` - Enum: breaking, exclusive, analysis, opinion, video, photo_gallery
- `published_at` - Publication timestamp
- `is_featured` - Boolean flag
- `views_count` - View counter
- `reading_time` - Estimated reading minutes

**File**: `src/Entity/Article.php`

---

### 2. Category

**Purpose**: Hierarchical article categorization

**Table**: `categories`

**Relationships**:
- `ManyToOne` → Category (parent)
- `OneToMany` → Category (children)
- `OneToMany` → Article

**Translatable Fields**:
- `name` - Category name
- `description` - Category description
- `slug` - URL slug

**Features**:
- Hierarchical (self-referencing parent-child)
- Auto-generated slugs with transliteration
- Position-based ordering

**File**: `src/Entity/Category.php`

---

### 3. Author

**Purpose**: Article authors/journalists

**Table**: `authors`

**Relationships**:
- `OneToMany` → Article

**Fields**:
- `name` - Full name
- `slug` - URL slug (auto-generated)
- `bio` - Biography
- `email` - Contact email
- `avatar` - Avatar image path
- `twitter` - Twitter handle
- `facebook` - Facebook profile

**File**: `src/Entity/Author.php`

---

### 4. Image

**Purpose**: Original uploaded images

**Table**: `images`

**Relationships**:
- `OneToMany` → Thumbnail
- `OneToMany` → ArticleImage

**Fields**:
- `filename` - Generated unique filename
- `path` - Relative path (e.g., `images/filename.jpg`)
- `original_filename` - Original upload name
- `width` - Image width in pixels
- `height` - Image height in pixels
- `mime_type` - MIME type (image/jpeg, image/png, etc.)
- `size` - File size in bytes
- `alt` - Alt text for accessibility

**File**: `src/Entity/Image.php`

**Storage**: `/public/uploads/images/`

---

### 5. Thumbnail

**Purpose**: Generated thumbnail variants

**Table**: `thumbnails`

**Relationships**:
- `ManyToOne` → Image
- `ManyToOne` → ThumbnailProfile

**Fields**:
- `filename` - Thumbnail filename
- `path` - Relative path (e.g., `thumbnails/hero_big/filename.webp`)
- `width` - Thumbnail width
- `height` - Thumbnail height
- `file_size` - File size in bytes

**File**: `src/Entity/Thumbnail.php`

**Storage**: `/public/uploads/thumbnails/{profile}/`

---

### 6. ThumbnailProfile

**Purpose**: Thumbnail generation configurations

**Table**: `thumbnail_profiles`

**Relationships**:
- `OneToMany` → Thumbnail

**Fields**:
- `name` - Profile identifier (e.g., `hero_big`)
- `width` - Target width
- `height` - Target height
- `format` - Output format (webp, jpg, png)
- `quality` - Image quality (1-100)
- `crop_mode` - Resize strategy (fit, fill, crop)

**File**: `src/Entity/ThumbnailProfile.php`

**Predefined Profiles** (10 total):
1. `hero_big` - 1920x1080 WebP
2. `hero_small` - 800x600 WebP
3. `article_main` - 1600x900 WebP
4. `article_inline` - 1200x675 WebP
5. `card_large` - 800x600 WebP
6. `card_medium` - 600x400 WebP
7. `card_small` - 400x300 WebP
8. `list_item` - 300x200 WebP
9. `mobile_hero` - 800x600 WebP
10. `gallery` - 1920x600 WebP

---

### 7. ArticleImage

**Purpose**: Many-to-Many association between Article and Image

**Table**: `article_image`

**Relationships**:
- `ManyToOne` → Article
- `ManyToOne` → Image

**Fields**:
- `position` - Display order (0, 1, 2, ...)
- `is_featured` - Featured/main image flag

**File**: `src/Entity/ArticleImage.php`

---

### 8. User

**Purpose**: Authentication and authorization

**Table**: `users`

**Fields**:
- `email` - Unique identifier
- `password` - Hashed password (Sodium)
- `roles` - JSON array of roles
- `first_name` - User first name
- `last_name` - User last name
- `is_active` - Account status

**Roles**:
- `ROLE_USER`
- `ROLE_EDITOR`
- `ROLE_ADMIN`
- `ROLE_SUPER_ADMIN`

**File**: `src/Entity/User.php`

---

### 9. ArticleLock

**Purpose**: Prevent concurrent editing

**Table**: `article_locks`

**Relationships**:
- `ManyToOne` → Article
- `ManyToOne` → User

**Fields**:
- `locked_at` - Lock creation timestamp
- `expires_at` - Lock expiration (default: 15 minutes)

**File**: `src/Entity/ArticleLock.php`

**Cleanup Command**: `php bin/console app:cleanup-expired-locks`

---

### 10. ImportantArticlesList

**Purpose**: Featured articles on homepage

**Table**: `important_articles_list`

**Relationships**:
- `ManyToOne` → Article

**Fields**:
- `position` - Display order (0-4, max 5 articles)

**File**: `src/Entity/ImportantArticlesList.php`

**Validation**: Maximum 5 articles allowed

---

## Translation System (Gedmo)

### ext_translations Table

**Schema**:
```sql
CREATE TABLE ext_translations (
  id SERIAL PRIMARY KEY,
  locale VARCHAR(5) NOT NULL,
  object_class VARCHAR(191) NOT NULL,
  field VARCHAR(32) NOT NULL,
  foreign_key VARCHAR(64) NOT NULL,
  content TEXT,
  UNIQUE INDEX lookup_idx (locale, object_class, foreign_key, field)
);
```

**Example Row**:
```
| locale | object_class            | field   | foreign_key | content                |
|--------|-------------------------|---------|-------------|------------------------|
| en     | App\Entity\Article      | title   | 1           | Breaking News Today    |
| ru     | App\Entity\Article      | title   | 1           | Срочные новости        |
| en     | App\Entity\Category     | name    | 5           | Politics               |
```

### Locale Handling

**Request Flow**:
1. Frontend sends `Accept-Language: en` header
2. State Provider extracts locale
3. Query applies Gedmo hint:
   ```php
   $query->setHint(\Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE, 'en');
   ```
4. Doctrine automatically loads translated fields

**Fallback**: If translation missing for requested locale, falls back to Romanian (default).

---

## Additional Entities (LiveText Feature)

### LiveText

**Purpose**: Real-time live text coverage (breaking news, sports)

**Table**: `live_texts`

**Relationships**:
- `OneToMany` → LiveTextPost
- `ManyToMany` → LiveTextCollaborator

**Translatable Fields**:
- `title`
- `description`

**Status Enum**: draft, scheduled, live, paused, ended

**File**: `src/Entity/LiveText.php`

---

### LiveTextPost

**Purpose**: Individual posts within LiveText

**Table**: `live_text_posts`

**Relationships**:
- `ManyToOne` → LiveText
- `ManyToOne` → User (author)

**Translatable Fields**:
- `content`

**Fields**:
- `is_pinned` - Pin to top
- `is_breaking` - Breaking update flag
- `position` - Display order

**File**: `src/Entity/LiveTextPost.php`

---

## Database Indexes

### Performance Optimization

**articles**:
```sql
CREATE INDEX idx_article_status ON articles(status);
CREATE INDEX idx_article_published ON articles(published_at);
CREATE INDEX idx_article_category ON articles(category_id);
CREATE INDEX idx_article_slug ON articles(slug);
```

**categories**:
```sql
CREATE INDEX idx_category_parent ON categories(parent_id);
CREATE INDEX idx_category_slug ON categories(slug);
```

**ext_translations**:
```sql
CREATE UNIQUE INDEX lookup_idx ON ext_translations(locale, object_class, foreign_key, field);
```

---

## Naming Conventions

- **Entities**: PascalCase (e.g., `ArticleImage`)
- **Tables**: snake_case (e.g., `article_image`)
- **Fields**: camelCase in PHP, snake_case in DB
- **Relationships**: Doctrine annotations define mapping

---

## Doctrine Migrations

**Location**: `/migrations/`

**Latest Schema Version**: Check `doctrine_migration_versions` table

**Run Migrations**:
```bash
php bin/console doctrine:migrations:migrate
```

**Create Migration**:
```bash
php bin/console make:migration
```

---

**Last Updated**: November 2025
**Schema Version**: See `/docs/schema.sql`
