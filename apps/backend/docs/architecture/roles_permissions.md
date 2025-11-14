# Roles & Permissions Matrix

## Overview

Deschide News uses **Symfony Security** with **role-based access control (RBAC)** to manage user permissions across the API and admin interfaces.

---

## 🎭 Role Hierarchy

```
ROLE_SUPER_ADMIN
    └── ROLE_ADMIN
            └── ROLE_EDITOR
                    └── ROLE_USER
                            └── PUBLIC_ACCESS (anonymous)
```

**Inheritance**: Each role inherits all permissions from roles below it.

Example: `ROLE_ADMIN` automatically has all `ROLE_EDITOR` and `ROLE_USER` permissions.

---

## 👥 Role Definitions

### PUBLIC_ACCESS (Anonymous Users)

**Description**: Non-authenticated visitors

**Access Level**: Read-only public content

**Use Cases**:
- Website visitors
- Search engines
- External applications consuming public API

---

### ROLE_USER

**Description**: Basic authenticated user

**Access Level**: Read access to protected resources

**Use Cases**:
- Registered readers
- API consumers with authentication
- Reserved for future features (e.g., comments, favorites)

**Current Usage**: Minimal (mostly public access is sufficient)

---

### ROLE_EDITOR

**Description**: Content creator and editor

**Access Level**: Create, edit, and publish content

**Use Cases**:
- Journalists
- Content writers
- Translators
- Guest contributors

**Key Responsibilities**:
- Write and edit articles
- Upload and manage images
- Create LiveText coverage
- Translate content
- Publish own articles

---

### ROLE_ADMIN

**Description**: System administrator

**Access Level**: Full CRUD on all resources except users

**Use Cases**:
- Chief editors
- Content managers
- Site administrators

**Key Responsibilities**:
- Manage all articles (any author)
- Manage categories
- Manage authors
- Manage important articles (homepage featured)
- View analytics and statistics
- Configure content workflows

---

### ROLE_SUPER_ADMIN

**Description**: Super administrator with full system access

**Access Level**: Unrestricted access to all features

**Use Cases**:
- Technical administrators
- System owners
- DevOps personnel

**Key Responsibilities**:
- User management (create, edit, delete users)
- Role assignment
- System configuration
- Access to all admin features
- Emergency interventions

---

## 📋 Permissions Matrix

### Articles (`/api/articles`)

| Action | Endpoint | PUBLIC | ROLE_USER | ROLE_EDITOR | ROLE_ADMIN | ROLE_SUPER_ADMIN |
|--------|----------|:------:|:---------:|:-----------:|:----------:|:----------------:|
| **List published articles** | `GET /api/articles?status=published` | ✅ | ✅ | ✅ | ✅ | ✅ |
| **View article detail** | `GET /api/articles/{id}` | ✅ | ✅ | ✅ | ✅ | ✅ |
| **List all articles (incl. drafts)** | `GET /api/articles` | ❌ | ❌ | ✅ (own) | ✅ (all) | ✅ (all) |
| **Create article** | `POST /api/articles` | ❌ | ❌ | ✅ | ✅ | ✅ |
| **Update own article** | `PATCH /api/articles/{id}` | ❌ | ❌ | ✅ | ✅ | ✅ |
| **Update any article** | `PATCH /api/articles/{id}` | ❌ | ❌ | ❌ | ✅ | ✅ |
| **Delete own article** | `DELETE /api/articles/{id}` | ❌ | ❌ | ✅ | ✅ | ✅ |
| **Delete any article** | `DELETE /api/articles/{id}` | ❌ | ❌ | ❌ | ✅ | ✅ |
| **Publish article** | `PATCH /api/articles/{id}` (status=published) | ❌ | ❌ | ✅ | ✅ | ✅ |
| **Schedule article** | `PATCH /api/articles/{id}` (status=scheduled) | ❌ | ❌ | ✅ | ✅ | ✅ |

---

### Categories (`/api/categories`)

| Action | Endpoint | PUBLIC | ROLE_USER | ROLE_EDITOR | ROLE_ADMIN | ROLE_SUPER_ADMIN |
|--------|----------|:------:|:---------:|:-----------:|:----------:|:----------------:|
| **List categories** | `GET /api/categories` | ✅ | ✅ | ✅ | ✅ | ✅ |
| **View category detail** | `GET /api/categories/{id}` | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Create category** | `POST /api/categories` | ❌ | ❌ | ❌ | ✅ | ✅ |
| **Update category** | `PATCH /api/categories/{id}` | ❌ | ❌ | ❌ | ✅ | ✅ |
| **Delete category** | `DELETE /api/categories/{id}` | ❌ | ❌ | ❌ | ✅ | ✅ |

---

### Authors (`/api/authors`)

| Action | Endpoint | PUBLIC | ROLE_USER | ROLE_EDITOR | ROLE_ADMIN | ROLE_SUPER_ADMIN |
|--------|----------|:------:|:---------:|:-----------:|:----------:|:----------------:|
| **List authors** | `GET /api/authors` | ✅ | ✅ | ✅ | ✅ | ✅ |
| **View author detail** | `GET /api/authors/{id}` | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Create author** | `POST /api/authors` | ❌ | ❌ | ❌ | ✅ | ✅ |
| **Update author** | `PATCH /api/authors/{id}` | ❌ | ❌ | ❌ | ✅ | ✅ |
| **Delete author** | `DELETE /api/authors/{id}` | ❌ | ❌ | ❌ | ✅ | ✅ |

---

### Images (`/api/images`)

| Action | Endpoint | PUBLIC | ROLE_USER | ROLE_EDITOR | ROLE_ADMIN | ROLE_SUPER_ADMIN |
|--------|----------|:------:|:---------:|:-----------:|:----------:|:----------------:|
| **List images** | `GET /api/images` | ❌ | ❌ | ✅ | ✅ | ✅ |
| **View image detail** | `GET /api/images/{id}` | ✅ * | ✅ | ✅ | ✅ | ✅ |
| **Upload image** | `POST /api/images` | ❌ | ❌ | ✅ | ✅ | ✅ |
| **Update image metadata** | `PATCH /api/images/{id}` | ❌ | ❌ | ✅ | ✅ | ✅ |
| **Delete image** | `DELETE /api/images/{id}` | ❌ | ❌ | ✅ | ✅ | ✅ |

*Public can access image files via CDN but not API metadata

---

### Thumbnails (`/api/thumbnails`)

| Action | Endpoint | PUBLIC | ROLE_USER | ROLE_EDITOR | ROLE_ADMIN | ROLE_SUPER_ADMIN |
|--------|----------|:------:|:---------:|:-----------:|:----------:|:----------------:|
| **List thumbnails** | `GET /api/thumbnails` | ❌ | ❌ | ✅ | ✅ | ✅ |
| **View thumbnail detail** | `GET /api/thumbnails/{id}` | ✅ * | ✅ | ✅ | ✅ | ✅ |
| **Regenerate thumbnails** | `POST /api/thumbnails/regenerate` | ❌ | ❌ | ❌ | ✅ | ✅ |

*Public can access thumbnail files via CDN

---

### Article Images (`/api/article_images`)

| Action | Endpoint | PUBLIC | ROLE_USER | ROLE_EDITOR | ROLE_ADMIN | ROLE_SUPER_ADMIN |
|--------|----------|:------:|:---------:|:-----------:|:----------:|:----------------:|
| **List article-image associations** | `GET /api/article_images` | ❌ | ❌ | ✅ | ✅ | ✅ |
| **Attach image to article** | `POST /api/article_images` | ❌ | ❌ | ✅ | ✅ | ✅ |
| **Update position/featured** | `PATCH /api/article_images/{id}` | ❌ | ❌ | ✅ | ✅ | ✅ |
| **Remove image from article** | `DELETE /api/article_images/{id}` | ❌ | ❌ | ✅ | ✅ | ✅ |

---

### Important Articles (`/api/important_articles_lists`)

| Action | Endpoint | PUBLIC | ROLE_USER | ROLE_EDITOR | ROLE_ADMIN | ROLE_SUPER_ADMIN |
|--------|----------|:------:|:---------:|:-----------:|:----------:|:----------------:|
| **List important articles** | `GET /api/important_articles_lists` | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Add to important list** | `POST /api/important_articles_lists` | ❌ | ❌ | ❌ | ✅ | ✅ |
| **Update position** | `PATCH /api/important_articles_lists/{id}` | ❌ | ❌ | ❌ | ✅ | ✅ |
| **Remove from list** | `DELETE /api/important_articles_lists/{id}` | ❌ | ❌ | ❌ | ✅ | ✅ |

---

### LiveText (`/api/live_texts`)

| Action | Endpoint | PUBLIC | ROLE_USER | ROLE_EDITOR | ROLE_ADMIN | ROLE_SUPER_ADMIN |
|--------|----------|:------:|:---------:|:-----------:|:----------:|:----------------:|
| **List live LiveTexts** | `GET /api/live_texts?status=live` | ✅ | ✅ | ✅ | ✅ | ✅ |
| **View LiveText detail** | `GET /api/live_texts/{id}` | ✅ | ✅ | ✅ | ✅ | ✅ |
| **List all LiveTexts** | `GET /api/live_texts` | ❌ | ❌ | ✅ | ✅ | ✅ |
| **Create LiveText** | `POST /api/live_texts` | ❌ | ❌ | ✅ | ✅ | ✅ |
| **Update LiveText** | `PATCH /api/live_texts/{id}` | ❌ | ❌ | ✅ (own) | ✅ (all) | ✅ (all) |
| **Delete LiveText** | `DELETE /api/live_texts/{id}` | ❌ | ❌ | ❌ | ✅ | ✅ |
| **Change status to live** | `PATCH /api/live_texts/{id}` (status=live) | ❌ | ❌ | ✅ | ✅ | ✅ |

---

### LiveText Posts (`/api/live_text_posts`)

| Action | Endpoint | PUBLIC | ROLE_USER | ROLE_EDITOR | ROLE_ADMIN | ROLE_SUPER_ADMIN |
|--------|----------|:------:|:---------:|:-----------:|:----------:|:----------------:|
| **List posts for LiveText** | `GET /api/live_text_posts?liveText={id}` | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Create post** | `POST /api/live_text_posts` | ❌ | ❌ | ✅ | ✅ | ✅ |
| **Update own post** | `PATCH /api/live_text_posts/{id}` | ❌ | ❌ | ✅ | ✅ | ✅ |
| **Update any post** | `PATCH /api/live_text_posts/{id}` | ❌ | ❌ | ❌ | ✅ | ✅ |
| **Delete own post** | `DELETE /api/live_text_posts/{id}` | ❌ | ❌ | ✅ | ✅ | ✅ |
| **Delete any post** | `DELETE /api/live_text_posts/{id}` | ❌ | ❌ | ❌ | ✅ | ✅ |

---

### Users (`/api/users`) - Future Implementation

| Action | Endpoint | PUBLIC | ROLE_USER | ROLE_EDITOR | ROLE_ADMIN | ROLE_SUPER_ADMIN |
|--------|----------|:------:|:---------:|:-----------:|:----------:|:----------------:|
| **List users** | `GET /api/users` | ❌ | ❌ | ❌ | ❌ | ✅ |
| **View user profile** | `GET /api/users/{id}` | ❌ | ✅ (own) | ✅ (own) | ✅ (own) | ✅ (all) |
| **Create user** | `POST /api/users` | ❌ | ❌ | ❌ | ❌ | ✅ |
| **Update user** | `PATCH /api/users/{id}` | ❌ | ✅ (own) | ✅ (own) | ✅ (own) | ✅ (all) |
| **Delete user** | `DELETE /api/users/{id}` | ❌ | ❌ | ❌ | ❌ | ✅ |
| **Assign roles** | `PATCH /api/users/{id}/roles` | ❌ | ❌ | ❌ | ❌ | ✅ |

---

## 🔒 Security Configuration

### Symfony Security Configuration

**File**: `config/packages/security.yaml`

```yaml
security:
  role_hierarchy:
    ROLE_EDITOR: ROLE_USER
    ROLE_ADMIN: ROLE_EDITOR
    ROLE_SUPER_ADMIN: ROLE_ADMIN

  access_control:
    # Public endpoints
    - { path: ^/api/login_check, roles: PUBLIC_ACCESS }
    - { path: ^/api/token/refresh, roles: PUBLIC_ACCESS }
    - { path: ^/api/docs, roles: PUBLIC_ACCESS }
    
    # Protected endpoints
    - { path: ^/api, roles: IS_AUTHENTICATED_FULLY }
```

### API Platform Voters

**Custom Voters** (for fine-grained control):

**ArticleVoter** (`src/Security/Voter/ArticleVoter.php`):
- `EDIT`: Can user edit this article?
  - ✅ ROLE_ADMIN (all articles)
  - ✅ ROLE_EDITOR (own articles only)
- `DELETE`: Can user delete this article?
  - ✅ ROLE_ADMIN (all articles)
  - ✅ ROLE_EDITOR (own articles only)
- `PUBLISH`: Can user publish this article?
  - ✅ ROLE_ADMIN (all articles)
  - ✅ ROLE_EDITOR (own articles only)

**LiveTextPostVoter** (`src/Security/Voter/LiveTextPostVoter.php`):
- `EDIT`: Can user edit this post?
  - ✅ ROLE_ADMIN (all posts)
  - ✅ ROLE_EDITOR (own posts only)
- `DELETE`: Can user delete this post?
  - ✅ ROLE_ADMIN (all posts)
  - ✅ ROLE_EDITOR (own posts only)

---

## 📊 Role Distribution (Recommended)

| Role | Expected Users | Percentage |
|------|----------------|------------|
| PUBLIC_ACCESS | Unlimited | N/A |
| ROLE_USER | 0-100 | 5% |
| ROLE_EDITOR | 10-50 | 80% |
| ROLE_ADMIN | 2-5 | 10% |
| ROLE_SUPER_ADMIN | 1-2 | 5% |

**Recommendations**:
- **ROLE_SUPER_ADMIN**: Only assign to technical administrators (limit to 1-2 users)
- **ROLE_ADMIN**: Chief editors, content managers (limit to 2-5 users)
- **ROLE_EDITOR**: All journalists, writers, translators (majority of users)
- **ROLE_USER**: Reserved for future features (currently minimal usage)

---

## 🛡️ Best Practices

### Role Assignment
1. **Principle of Least Privilege**: Assign the minimum role needed
2. **Regular Audits**: Review user roles quarterly
3. **Temporary Elevations**: Use time-limited role grants if needed (manual process)
4. **Role Changes**: Log all role assignments/changes

### Access Control
1. **Always check roles in Voters**: Don't rely solely on `access_control`
2. **Validate ownership**: ROLE_EDITOR should only edit own content
3. **API-level validation**: Backend validates permissions, frontend is just UX
4. **Audit logs**: Log all sensitive operations (create, update, delete)

### Testing
1. **Test each role**: Verify permissions for each role level
2. **Test inheritance**: Ensure higher roles have lower role permissions
3. **Test edge cases**: Non-owners trying to edit, deleted users, etc.

---

## 🔍 Testing Permissions

### Test User Credentials (Development)

**Created via fixtures** (`src/DataFixtures/UserFixtures.php`):

```
ROLE_SUPER_ADMIN:
  Email: superadmin@deschide.md
  Password: superadmin123

ROLE_ADMIN:
  Email: admin@deschide.md
  Password: admin123

ROLE_EDITOR:
  Email: editor@deschide.md
  Password: editor123

ROLE_USER:
  Email: user@deschide.md
  Password: user123
```

**Note**: These are development credentials only. Never use in production!

---

## 📝 Future Enhancements

### Planned Features
1. **Permission Groups**: Create custom permission bundles
2. **Resource-level Permissions**: Per-category or per-article permissions
3. **Time-based Roles**: Temporary role assignments with expiration
4. **API Keys**: Service accounts for external integrations
5. **Activity Logging**: Comprehensive audit trail for all operations
6. **Two-Factor Authentication**: For ROLE_ADMIN and ROLE_SUPER_ADMIN

### Under Consideration
- **ROLE_TRANSLATOR**: Specialized role for translation-only access
- **ROLE_MODERATOR**: For comment moderation (if comments feature added)
- **ROLE_ANALYST**: Read-only access to analytics/statistics

---

**Last Updated**: November 5, 2025  
**Version**: 1.0  
**Security Audit**: Recommended annually
