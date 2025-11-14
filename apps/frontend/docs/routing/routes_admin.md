# Admin Routes Documentation

## Overview

This document describes all admin/CMS routes in the Deschide News frontend, accessible only to authenticated users with appropriate roles.

---

## 🔐 Access Control

### Protected Routes

All admin routes require authentication:

```
/[locale]/admin/*
```

### Role Requirements

| Route | ROLE_EDITOR | ROLE_ADMIN | ROLE_SUPER_ADMIN |
|-------|:-----------:|:----------:|:----------------:|
| `/admin` | ✅ | ✅ | ✅ |
| `/admin/articles` | ✅ | ✅ | ✅ |
| `/admin/articles/new` | ✅ | ✅ | ✅ |
| `/admin/articles/[id]/edit` | ✅ (own) | ✅ (all) | ✅ (all) |
| `/admin/categories` | ❌ | ✅ | ✅ |
| `/admin/images` | ✅ | ✅ | ✅ |
| `/admin/live-texts` | ✅ | ✅ | ✅ |
| `/admin/statistics` | ❌ | ✅ | ✅ |
| `/admin/users` | ❌ | ❌ | ✅ |

---

## 📁 Admin Route Structure

```
app/[locale]/admin/
├── page.tsx                      # Dashboard
├── layout.tsx                    # Admin layout (auth check)
├── articles/
│   ├── page.tsx                  # Article list
│   ├── new/page.tsx              # Create article
│   └── [id]/
│       └── edit/page.tsx         # Edit article
├── categories/
│   ├── page.tsx                  # Category management
│   ├── new/page.tsx              # Create category
│   └── [id]/edit/page.tsx        # Edit category
├── images/
│   ├── page.tsx                  # Media library
│   └── upload/page.tsx           # Upload images
├── live-texts/
│   ├── page.tsx                  # LiveText list
│   ├── new/page.tsx              # Create LiveText
│   └── [id]/
│       ├── edit/page.tsx         # Edit LiveText
│       └── posts/page.tsx        # Manage posts
└── statistics/
    └── page.tsx                  # Analytics dashboard
```

---

## 📊 Dashboard

### Route

**Path**: `/[locale]/admin`

**File**: `app/[locale]/admin/page.tsx`

**Access**: ROLE_EDITOR, ROLE_ADMIN, ROLE_SUPER_ADMIN

### Features

1. **Statistics Overview**
   - Total articles count
   - Published today
   - Draft articles
   - Total views (last 30 days)
   - Active LiveText events

2. **Quick Actions**
   - Create new article
   - Create new LiveText
   - Upload image
   - View pending approvals (ROLE_ADMIN)

3. **Recent Activity**
   - Latest articles (last 10)
   - Recent uploads
   - User activity log (ROLE_ADMIN)

4. **Charts**
   - Articles published per day (last 30 days)
   - Views trend
   - Category distribution

### Implementation

```typescript
import { getStatistics } from '@/lib/api/statistics';
import { getRecentArticles } from '@/lib/api/articles';

export default async function AdminDashboard({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  
  // Fetch dashboard data
  const stats = await getStatistics();
  const recentArticles = await getRecentArticles({ locale, limit: 10 });
  
  return (
    <div className="dashboard">
      <DashboardHeader />
      
      <div className="stats-grid">
        <StatCard title="Total Articles" value={stats.totalArticles} />
        <StatCard title="Published Today" value={stats.publishedToday} />
        <StatCard title="Drafts" value={stats.drafts} />
        <StatCard title="Total Views" value={stats.totalViews} />
      </div>
      
      <QuickActions />
      
      <ChartsSection stats={stats} />
      
      <RecentActivity articles={recentArticles} />
    </div>
  );
}
```

---

## 📝 Article Management

### Article List

**Path**: `/[locale]/admin/articles`

**File**: `app/[locale]/admin/articles/page.tsx`

**Features**:
- Filterable table (status, category, author)
- Search by title
- Sortable columns (date, views, status)
- Bulk actions (publish, archive, delete)
- Pagination

**Implementation**:

```typescript
'use client';

import { useState, useEffect } from 'react';
import { getArticles } from '@/lib/api/articles';

export default function ArticlesListPage({ params }) {
  const { locale } = params;
  const [articles, setArticles] = useState([]);
  const [filters, setFilters] = useState({
    status: 'all',
    category: null,
    search: '',
  });
  
  useEffect(() => {
    async function fetchArticles() {
      const data = await getArticles({
        locale,
        ...filters,
        page: 1,
        itemsPerPage: 50,
      });
      setArticles(data.items);
    }
    
    fetchArticles();
  }, [filters, locale]);
  
  return (
    <div>
      <ArticlesListHeader />
      <ArticlesFilters filters={filters} onChange={setFilters} />
      <ArticlesTable articles={articles} />
      <Pagination />
    </div>
  );
}
```

---

### Create Article

**Path**: `/[locale]/admin/articles/new`

**File**: `app/[locale]/admin/articles/new/page.tsx`

**Features**:

1. **Basic Information**
   - Title (required)
   - Slug (auto-generated, editable)
   - Lead paragraph
   - Category (select)
   - Author (auto-fill current user)

2. **Content Editor**
   - Rich text editor (TinyMCE, Lexical, or similar)
   - Markdown support
   - Image insertion
   - Video embedding
   - Code blocks

3. **Images**
   - Upload new image
   - Select from media library
   - Set featured image
   - Reorder images (drag & drop)

4. **SEO**
   - Meta title
   - Meta description
   - Open Graph image

5. **Publishing Options**
   - Status: Draft, Scheduled, Published
   - Publish date/time
   - Badge: Breaking, Exclusive, etc.

6. **Translations**
   - Switch locale (ro/en/ru)
   - Translate fields
   - Copy from default locale

### Implementation

```typescript
'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { createArticle } from '@/lib/api/articles';
import { ArticleForm } from '@/components/admin/articles/ArticleForm';

export default function CreateArticlePage({ params }) {
  const router = useRouter();
  const { locale } = params;
  const [loading, setLoading] = useState(false);
  
  const handleSubmit = async (data) => {
    setLoading(true);
    
    try {
      const article = await createArticle(data, locale);
      
      // Redirect to edit page
      router.push(`/${locale}/admin/articles/${article.id}/edit`);
    } catch (error) {
      alert('Failed to create article: ' + error.message);
    } finally {
      setLoading(false);
    }
  };
  
  return (
    <div>
      <h1>Create New Article</h1>
      <ArticleForm onSubmit={handleSubmit} loading={loading} />
    </div>
  );
}
```

**ArticleForm Component**:

```typescript
// components/admin/articles/ArticleForm.tsx

export function ArticleForm({ onSubmit, initialData, loading }) {
  const [formData, setFormData] = useState(initialData || {
    title: '',
    slug: '',
    lead: '',
    content: '',
    category: null,
    status: 'draft',
    publishedAt: null,
    badge: null,
  });
  
  const handleSubmit = (e) => {
    e.preventDefault();
    onSubmit(formData);
  };
  
  return (
    <form onSubmit={handleSubmit}>
      {/* Title */}
      <div>
        <label>Title</label>
        <input
          type="text"
          value={formData.title}
          onChange={(e) => setFormData({ ...formData, title: e.target.value })}
          required
        />
      </div>
      
      {/* Slug */}
      <div>
        <label>Slug</label>
        <input
          type="text"
          value={formData.slug}
          onChange={(e) => setFormData({ ...formData, slug: e.target.value })}
        />
      </div>
      
      {/* Rich Text Editor */}
      <div>
        <label>Content</label>
        <RichTextEditor
          value={formData.content}
          onChange={(content) => setFormData({ ...formData, content })}
        />
      </div>
      
      {/* Category Select */}
      <CategorySelect
        value={formData.category}
        onChange={(category) => setFormData({ ...formData, category })}
      />
      
      {/* Image Management */}
      <AttachedImagesSection articleId={formData.id} />
      
      {/* SEO Fields */}
      <SeoFields data={formData} onChange={setFormData} />
      
      {/* Publishing Options */}
      <PublishingOptions data={formData} onChange={setFormData} />
      
      <button type="submit" disabled={loading}>
        {loading ? 'Saving...' : 'Save Article'}
      </button>
    </form>
  );
}
```

---

### Edit Article

**Path**: `/[locale]/admin/articles/[id]/edit`

**File**: `app/[locale]/admin/articles/[id]/edit/page.tsx`

**Features**: Same as Create Article, plus:

- Version history
- Lock indicator (if another user editing)
- Preview button
- Delete button (ROLE_ADMIN)

**Implementation**:

```typescript
export default async function EditArticlePage({ params }) {
  const { locale, id } = await params;
  
  // Fetch article
  const article = await getArticle(id, locale);
  
  // Check edit permission
  if (!canEdit(article)) {
    redirect(`/${locale}/admin/articles`);
  }
  
  // Check if locked by another user
  const lock = await checkArticleLock(id);
  
  return (
    <div>
      {lock && <LockWarning lock={lock} />}
      
      <EditArticleForm article={article} locale={locale} />
    </div>
  );
}
```

---

## 🗂️ Category Management

### Category List

**Path**: `/[locale]/admin/categories`

**File**: `app/[locale]/admin/categories/page.tsx`

**Access**: ROLE_ADMIN, ROLE_SUPER_ADMIN

**Features**:
- Hierarchical tree view
- Drag & drop reordering
- Create/Edit/Delete
- Article count per category

---

### Create/Edit Category

**Fields**:
- Name (translatable)
- Description (translatable)
- Slug (auto-generated, translatable)
- Parent category
- Position

---

## 🖼️ Media Library

### Image Gallery

**Path**: `/[locale]/admin/images`

**File**: `app/[locale]/admin/images/page.tsx`

**Features**:

1. **Grid View**
   - Thumbnail previews
   - Image metadata (size, dimensions, date)
   - Search by filename
   - Filter by date

2. **Upload**
   - Drag & drop
   - Multiple file upload
   - Progress indicator
   - Alt text input

3. **Image Details**
   - View full size
   - Edit alt text
   - View usage (which articles use this image)
   - Delete (if not in use)

**Implementation**:

```typescript
'use client';

import { useState } from 'react';
import { uploadImage } from '@/lib/api/images';

export function ImageUpload() {
  const [uploading, setUploading] = useState(false);
  const [progress, setProgress] = useState(0);
  
  const handleUpload = async (files: FileList) => {
    setUploading(true);
    
    for (const file of Array.from(files)) {
      const formData = new FormData();
      formData.append('file', file);
      formData.append('alt', file.name);
      
      await uploadImage(formData, (progressEvent) => {
        setProgress(Math.round((progressEvent.loaded * 100) / progressEvent.total));
      });
    }
    
    setUploading(false);
  };
  
  return (
    <div
      onDrop={(e) => {
        e.preventDefault();
        handleUpload(e.dataTransfer.files);
      }}
      onDragOver={(e) => e.preventDefault()}
    >
      {uploading ? (
        <ProgressBar value={progress} />
      ) : (
        <p>Drag & drop images here or click to browse</p>
      )}
    </div>
  );
}
```

---

## 🔴 LiveText Management

### LiveText List

**Path**: `/[locale]/admin/live-texts`

**File**: `app/[locale]/admin/live-texts/page.tsx`

**Features**:
- List all LiveText events
- Filter by status (draft, live, ended)
- Create new LiveText
- Start/Stop live coverage

---

### Create LiveText

**Path**: `/[locale]/admin/live-texts/new`

**Fields**:
- Title (translatable)
- Description (translatable)
- Start time
- End time (optional)
- Type: General, Sport Match, Breaking News
- Sport match details (if applicable)

---

### LiveText Post Editor

**Path**: `/[locale]/admin/live-texts/[id]/posts`

**File**: `app/[locale]/admin/live-texts/[id]/posts/page.tsx`

**Features**:

1. **Post Creation**
   - Rich text content
   - Pin to top
   - Mark as breaking
   - Attach images/videos

2. **Real-time Preview**
   - See how post appears to readers
   - Live updates via Mercure

3. **Post Management**
   - Edit existing posts
   - Delete posts
   - Reorder posts

4. **Sport Match Updates** (if applicable)
   - Update score
   - Add match events (goal, card, substitution)
   - Timeline view

**Implementation**:

```typescript
'use client';

import { useState } from 'react';
import { createLiveTextPost } from '@/lib/api/livetext';

export function PostEditor({ liveTextId }) {
  const [content, setContent] = useState('');
  const [isPinned, setIsPinned] = useState(false);
  const [isBreaking, setIsBreaking] = useState(false);
  
  const handleSubmit = async () => {
    await createLiveTextPost({
      liveText: liveTextId,
      content,
      isPinned,
      isBreaking,
    });
    
    setContent('');
    setIsPinned(false);
    setIsBreaking(false);
  };
  
  return (
    <div className="post-editor">
      <textarea
        value={content}
        onChange={(e) => setContent(e.target.value)}
        placeholder="Write post content..."
      />
      
      <div className="options">
        <label>
          <input
            type="checkbox"
            checked={isPinned}
            onChange={(e) => setIsPinned(e.target.checked)}
          />
          Pin to top
        </label>
        
        <label>
          <input
            type="checkbox"
            checked={isBreaking}
            onChange={(e) => setIsBreaking(e.target.checked)}
          />
          Mark as breaking
        </label>
      </div>
      
      <button onClick={handleSubmit}>Publish Post</button>
    </div>
  );
}
```

---

## 📊 Statistics Dashboard

### Route

**Path**: `/[locale]/admin/statistics`

**File**: `app/[locale]/admin/statistics/page.tsx`

**Access**: ROLE_ADMIN, ROLE_SUPER_ADMIN

**Features**:

1. **Traffic Overview**
   - Page views (daily, weekly, monthly)
   - Unique visitors
   - Bounce rate
   - Average session duration

2. **Content Analytics**
   - Most viewed articles
   - Most shared articles
   - Category performance
   - Author performance

3. **Real-time Stats**
   - Active users now
   - Currently trending articles
   - LiveText viewer counts

4. **Date Range Selector**
   - Last 24 hours
   - Last 7 days
   - Last 30 days
   - Custom range

**Implementation**:

```typescript
import { getStatistics } from '@/lib/api/statistics';

export default async function StatisticsPage({ searchParams }) {
  const { startDate, endDate } = await searchParams;
  
  const stats = await getStatistics({
    startDate: startDate || getDefaultStartDate(),
    endDate: endDate || new Date(),
  });
  
  return (
    <div>
      <DateRangePicker />
      
      <div className="charts-grid">
        <TrafficOverviewChart data={stats.traffic} />
        <CategoryDistributionChart data={stats.categoryStats} />
        <TrendingArticlesTable articles={stats.trending} />
      </div>
    </div>
  );
}
```

---

## 👥 User Management

### Route

**Path**: `/[locale]/admin/users`

**Access**: ROLE_SUPER_ADMIN only

**Features**:
- User list
- Create user
- Edit user roles
- Activate/Deactivate accounts
- Reset password

---

## 🎨 Admin Layout

### File

`app/[locale]/admin/layout.tsx`

**Features**:

1. **Authentication Check**
   - Redirect to login if not authenticated
   - Verify role permissions

2. **Sidebar Navigation**
   - Dashboard
   - Articles
   - Categories (ROLE_ADMIN+)
   - Images
   - LiveText
   - Statistics (ROLE_ADMIN+)
   - Users (ROLE_SUPER_ADMIN)

3. **Top Bar**
   - User profile dropdown
   - Logout button
   - Language switcher
   - Notifications

**Implementation**:

```typescript
import { redirect } from 'next/navigation';
import { getServerSession } from '@/lib/auth';

export default async function AdminLayout({ children, params }) {
  const { locale } = await params;
  const session = await getServerSession();
  
  // Require authentication
  if (!session) {
    redirect(`/${locale}/login`);
  }
  
  // Require editor role or higher
  const hasAccess = session.roles.some(role => 
    ['ROLE_EDITOR', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'].includes(role)
  );
  
  if (!hasAccess) {
    redirect(`/${locale}`);
  }
  
  return (
    <div className="admin-layout">
      <AdminSidebar user={session.user} />
      <div className="admin-content">
        <AdminTopBar user={session.user} />
        <main>{children}</main>
      </div>
    </div>
  );
}
```

---

## 🚀 Performance

### Code Splitting

```typescript
// Lazy load heavy components
const RichTextEditor = dynamic(() => import('@/components/RichTextEditor'), {
  ssr: false,
  loading: () => <LoadingSpinner />,
});
```

### Data Caching

```typescript
// Use React Query for admin data
export function useArticles(filters) {
  return useQuery({
    queryKey: ['articles', filters],
    queryFn: () => getArticles(filters),
    staleTime: 30000, // 30 seconds
  });
}
```

---

## 🔔 Notifications

### Toast Notifications

```typescript
import { toast } from 'react-hot-toast';

// Success
toast.success('Article published successfully');

// Error
toast.error('Failed to save article');

// Info
toast('Article saved as draft');
```

---

**Last Updated**: November 5, 2025  
**Version**: 1.0  
**Total Admin Routes**: 15+
