# Deschide News Platform - Business Overview

**Document Type**: Business & Technical Overview  
**Target Audience**: External Auditor  
**Version**: 1.0  
**Date**: November 2025

---

## 📋 Project Summary

**Deschide News** is a modern multilingual news platform designed to deliver real-time news content across Romania, Moldova, and international audiences. The platform consists of:

- **Public News Portal**: User-facing website with articles, categories, search, and real-time coverage
- **Admin CMS**: Content management system for editors and administrators
- **RESTful API**: Backend providing data and services to frontend and external consumers

---

## 🎯 Business Goals

### Primary Objectives

1. **Multilingual Content Delivery**: Support Romanian, English, and Russian languages
2. **Real-time News Coverage**: LiveText feature for breaking news and sports events
3. **SEO Optimization**: High search engine visibility and social media integration
4. **Scalability**: Handle high traffic during breaking news events
5. **Editorial Workflow**: Efficient content creation and publishing process

### Key Differentiators

- **LiveText Feature**: Real-time coverage with automatic updates (similar to live blogs)
- **Advanced SEO**: Automatic sitemap, Open Graph, structured data
- **Image Optimization**: 10 thumbnail profiles with WebP format for performance
- **Flexible Categorization**: Hierarchical categories with translations

---

## 🏗️ System Architecture

### Technology Stack

**Backend (API)**:
- Symfony 7.3 + API Platform 3.x
- PHP 8.4
- PostgreSQL 17
- Elasticsearch 8.x
- Redis (caching)
- RabbitMQ (async processing)

**Frontend**:
- Next.js 16 (App Router)
- React 19.2
- TypeScript 5.x
- Tailwind CSS 4
- Turbopack bundler

**Infrastructure**:
- Development: Local Symfony server + Next.js dev server
- Production: Nginx + PM2 (planned)
- CDN: Static file server for images (development), Cloudflare (production planned)

### System Diagram

```
┌─────────────┐         ┌──────────────┐         ┌─────────────┐
│   Readers   │────────>│   Next.js    │────────>│  Symfony    │
│  (Public)   │  HTTP   │   Frontend   │   API   │   Backend   │
└─────────────┘         │  Port 3005   │         │  Port 8081  │
                        └──────────────┘         └─────────────┘
                                │                        │
                                │                        ├──> PostgreSQL
                                │                        ├──> Elasticsearch
                                │                        ├──> Redis Cache
                                │                        └──> RabbitMQ
                                │
                                v
                        ┌──────────────┐
                        │   CDN/Static │
                        │  Port 8082   │
                        └──────────────┘
```

---

## 👥 User Roles & Access Levels

### Public Users (Not authenticated)
- Read published articles
- Search articles
- View LiveText coverage
- Browse categories
- Access in 3 languages (ro/en/ru)

### ROLE_USER (Authenticated)
- Same as public users
- Access to protected resources (if any)

### ROLE_EDITOR
- Create and edit articles
- Upload images
- Manage article-image associations
- Create LiveText posts
- Publish content (own articles)

### ROLE_ADMIN
- All ROLE_EDITOR permissions
- Manage categories
- Manage authors
- Manage all articles (any author)
- View analytics/statistics
- Manage important articles list (homepage featured)

### ROLE_SUPER_ADMIN
- All ROLE_ADMIN permissions
- User management
- System configuration
- Access to all admin features

---

## 📰 Core Entities & Workflows

### 1. Article

**Purpose**: News content with multilingual support

**Key Fields**:
- Title, Lead, Content (translatable)
- Slug (auto-generated, translatable)
- Status: draft → published → archived
- Badge: breaking, exclusive, analysis, opinion, video, photo_gallery
- Published date (scheduled publishing support)
- Category (single)
- Author (single)
- Images (multiple, ordered)

**Workflow**:
```
1. Editor creates article (status: draft)
2. Editor adds images, sets category/author
3. Editor translates to other languages (optional)
4. Editor publishes:
   - Immediately: status → published, publishedAt = now
   - Scheduled: status → scheduled, publishedAt = future
5. Automated task publishes scheduled articles at specified time
6. Published articles appear on website
7. Editor can archive outdated articles
```

### 2. Category

**Purpose**: Hierarchical organization of articles

**Features**:
- Parent-child relationships (e.g., Politics → Local Politics)
- Translatable names and descriptions
- Auto-generated slugs (e.g., "politica" → "politics" → "политика")
- Position-based ordering

### 3. Image & Thumbnails

**Purpose**: Image management with automatic optimization

**Workflow**:
```
1. Editor uploads original image (PNG/JPG/WebP)
2. System extracts metadata (dimensions, size, MIME type)
3. System generates 10 thumbnail variants (WebP, different sizes)
4. Thumbnails stored in separate directories by profile
5. Frontend loads appropriate thumbnail based on context
```

**Thumbnail Profiles**:
- hero_big (1920×1080) - Homepage hero
- card_large (800×600) - Article cards
- list_item (300×200) - List views
- And 7 more...

### 4. LiveText (Real-time Coverage)

**Purpose**: Live blogging for breaking news and sports

**Workflow**:
```
1. Editor creates LiveText event (title, description)
2. Editor sets status: scheduled → live
3. Editor creates posts (timestamped updates)
4. Posts published in real-time via Mercure (SSE)
5. Readers see updates without page refresh
6. Editor can pin important posts, mark as breaking
7. Event ends: status → ended
```

**Features**:
- Real-time viewer counter
- Reader reactions (like, love, wow)
- Sport match integration (score tracking)
- Embeddable widget for external sites
- Analytics (views, engagement, peak viewers)

---

## 🌍 Multilanguage System

### Implementation: Gedmo Translatable

**Supported Languages**:
- **Romanian (ro)** - Default/fallback
- **English (en)**
- **Russian (ru)**

**How it works**:
1. Content created in Romanian (default locale)
2. Translations stored in separate `ext_translations` table
3. Frontend requests content with `Accept-Language: en` header
4. Backend automatically loads English translation if available
5. Fallback to Romanian if translation missing

**Translatable Entities**:
- Article (title, lead, content, meta tags, slug)
- Category (name, description, slug)
- LiveText (title, description)
- LiveTextPost (content)

---

## 🔒 Security & Authentication

### Authentication: JWT (JSON Web Tokens)

**Flow**:
1. User submits email + password to `/api/login_check`
2. Backend validates credentials
3. Backend returns:
   - Access token (expires in 1 hour)
   - Refresh token (expires in 7 days)
4. Frontend stores tokens (in memory or httpOnly cookies)
5. All authenticated requests include: `Authorization: Bearer {token}`
6. Token auto-refresh when expired (using refresh token)

### Security Measures

- **Password Hashing**: Sodium algorithm (PHP 8.4)
- **CORS Policy**: Restricted to frontend domain
- **HTTPS Only**: Production requirement
- **Rate Limiting**: Planned for login endpoint
- **Input Validation**: Symfony Validator + API Platform
- **SQL Injection Prevention**: Doctrine ORM (parameterized queries)
- **XSS Prevention**: Content sanitization

---

## 📊 Performance & Scalability

### Optimization Strategies

**Backend**:
- **Eager Loading**: Prevent N+1 queries with JOINs
- **Redis Caching**: API responses, frequently accessed data
- **Elasticsearch**: Full-text search offloading
- **Async Processing**: Thumbnail generation via RabbitMQ
- **Database Indexing**: On frequently queried fields (slug, status, published_at)

**Frontend**:
- **Image Optimization**: WebP format, 10 responsive sizes
- **Code Splitting**: Route-based automatic splitting
- **Static Generation**: Pre-rendered pages where possible
- **CDN**: Static assets served from dedicated CDN
- **Prefetching**: Automatic link prefetching

**Expected Load**:
- 100,000+ daily visitors
- 500+ articles published/month
- 10+ concurrent editors
- 50,000+ images in library

---

## 🚀 Deployment & Infrastructure

### Development Environment

- **Backend**: `http://127.0.0.1:8081` (Symfony local server)
- **Frontend**: `http://localhost:3005` (Next.js dev server)
- **CDN**: `http://127.0.0.1:8082` (Static file server)
- **Database**: PostgreSQL on localhost:5432
- **Search**: Elasticsearch on localhost:9200
- **Cache**: Redis on localhost:6379

### Production Environment (Planned)

- **Backend**: Nginx + PHP-FPM (multiple workers)
- **Frontend**: PM2 (Node.js process manager)
- **CDN**: Cloudflare (images, static assets)
- **Database**: PostgreSQL with connection pooling (PgBouncer)
- **Cache**: Redis cluster
- **Monitoring**: Prometheus + Grafana
- **Logging**: ELK stack (Elasticsearch, Logstash, Kibana)

---

## 📈 Analytics & Reporting

### Metrics Tracked

**Content Metrics**:
- Article views (total, per article, per category)
- Reading time average
- Bounce rate
- Most popular articles (trending)
- Category distribution

**LiveText Metrics**:
- Active viewers (real-time)
- Total views per event
- Post engagement (reactions)
- Average session duration
- Peak viewer count

**System Metrics**:
- API response times
- Database query performance
- Cache hit/miss ratio
- Error rates
- Uptime

### Admin Dashboard

Editors and admins see:
- Real-time statistics
- Traffic overview charts
- Trending articles table
- Category distribution
- Recent user activity

---

## 🔄 Import & Migration

### Legacy System: Newscoop CMS

The platform includes migration tools to import from legacy Newscoop CMS:

**Import Commands**:
- `app:import:categories` - Import category hierarchy
- `app:import:authors` - Import journalist profiles
- `app:import:articles-with-relations` - Import articles with associations
- `app:import:images` - Import media library
- `app:import:translations` - Import English/Russian translations
- `app:import:generate-thumbnails` - Generate thumbnails for imported images

**Migration Status**: Completed for development/testing data

---

## 📞 Stakeholders & Team

**Project Owner**: Editorial Team / News Organization  
**Development Team**: Full-stack developers (Backend + Frontend)  
**Content Team**: Editors, journalists, translators  
**DevOps**: Infrastructure and deployment management  
**External Auditor**: Technical code review and security assessment

---

## 🎯 Audit Scope (Summary)

### In Scope
✅ Backend API architecture and implementation  
✅ Frontend application structure  
✅ Authentication & authorization system  
✅ Database schema and entity relationships  
✅ Multilanguage implementation  
✅ Image management and thumbnail generation  
✅ API security (JWT, CORS, validation)  
✅ Code quality and best practices  

### Out of Scope
❌ Infrastructure provisioning (servers, networks)  
❌ Content quality/editorial decisions  
❌ Design/UX review  
❌ Performance load testing (can be added if needed)  
❌ Penetration testing (can be added if needed)  

---

## 📚 Additional Resources

- **Backend Repository**: `git@github.com:radusoltan/deschide_news_app_backend.git`
- **Frontend Repository**: `git@github.com:radusoltan/deschide_news_app_frontend.git`
- **API Documentation**: `/docs/openapi.jsonld` (Hydra/JSON-LD format)
- **Database Schema**: `/docs/schema.sql`
- **Authentication Flow**: `/docs/auth_flow.md`
- **Entity Relationships**: `/docs/entities.md`
- **Audit Checklist**: `/docs/audit_checklist.md`

---

**Document Prepared By**: Development Team  
**Last Updated**: November 5, 2025  
**Status**: Production-Ready Platform  
**Version**: 1.0
