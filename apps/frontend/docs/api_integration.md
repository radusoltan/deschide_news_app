# Frontend API Integration Documentation

## Overview

The frontend communicates with the Symfony backend API using:
- **Custom API Client** with automatic token refresh
- **Fetch API** for HTTP requests
- **TypeScript** for type safety
- **React Query** for data caching and synchronization (planned)

## Architecture

### API Client Location

```
lib/
├── api-client.ts          # Core API client with auth
├── api/
│   ├── articles.ts        # Article endpoints
│   ├── categories.ts      # Category endpoints
│   ├── authors.ts         # Author endpoints
│   ├── images.ts          # Image endpoints
│   ├── livetext.ts        # LiveText endpoints
│   └── statistics.ts      # Statistics endpoints
└── types/
    ├── article.ts         # Article types
    ├── category.ts        # Category types
    └── api.ts             # Common API types
```

## API Client Implementation

### Core Client (`lib/api-client.ts`)

```typescript
const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8081';

interface ApiRequestOptions extends RequestInit {
  locale?: string;
  requiresAuth?: boolean;
}

export async function apiRequest<T>(
  endpoint: string,
  options: ApiRequestOptions = {}
): Promise<T> {
  const { locale = 'ro', requiresAuth = false, ...fetchOptions } = options;
  
  const headers = new Headers(fetchOptions.headers);
  headers.set('Accept', 'application/ld+json');
  headers.set('Content-Type', 'application/json');
  headers.set('Accept-Language', locale);
  
  // Add JWT token if authentication required
  if (requiresAuth) {
    const token = getAccessToken();
    if (token) {
      headers.set('Authorization', `Bearer ${token}`);
    }
  }
  
  const url = `${API_URL}${endpoint}`;
  
  let response = await fetch(url, {
    ...fetchOptions,
    headers
  });
  
  // Handle token expiration
  if (response.status === 401 && requiresAuth) {
    const newToken = await refreshAccessToken();
    if (newToken) {
      headers.set('Authorization', `Bearer ${newToken}`);
      response = await fetch(url, {
        ...fetchOptions,
        headers
      });
    }
  }
  
  if (!response.ok) {
    throw new ApiError(response.status, await response.text());
  }
  
  return response.json();
}
```

### Token Management

```typescript
// Store tokens in memory (not localStorage for security)
let accessToken: string | null = null;
let refreshToken: string | null = null;

export function setTokens(access: string, refresh: string): void {
  accessToken = access;
  refreshToken = refresh;
}

export function getAccessToken(): string | null {
  return accessToken;
}

export async function refreshAccessToken(): Promise<string | null> {
  if (!refreshToken) return null;
  
  try {
    const response = await fetch(`${API_URL}/api/token/refresh`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ refresh_token: refreshToken })
    });
    
    if (!response.ok) return null;
    
    const data = await response.json();
    setTokens(data.token, data.refresh_token);
    return data.token;
  } catch (error) {
    return null;
  }
}
```

## API Functions

### Articles (`lib/api/articles.ts`)

```typescript
import { apiRequest } from '../api-client';
import type { Article, ArticleCollection } from '../types/article';

export async function getArticles(params: {
  page?: number;
  itemsPerPage?: number;
  category?: number;
  status?: string;
  locale?: string;
}): Promise<ArticleCollection> {
  const queryString = new URLSearchParams(
    Object.entries(params).filter(([_, v]) => v != null)
  ).toString();
  
  return apiRequest<ArticleCollection>(
    `/api/articles?${queryString}`,
    { locale: params.locale }
  );
}

export async function getArticle(
  id: number,
  locale: string = 'ro'
): Promise<Article> {
  return apiRequest<Article>(`/api/articles/${id}`, { locale });
}

export async function createArticle(
  data: Partial<Article>,
  locale: string = 'ro'
): Promise<Article> {
  return apiRequest<Article>('/api/articles', {
    method: 'POST',
    body: JSON.stringify(data),
    locale,
    requiresAuth: true
  });
}

export async function updateArticle(
  id: number,
  data: Partial<Article>,
  locale: string = 'ro'
): Promise<Article> {
  return apiRequest<Article>(`/api/articles/${id}`, {
    method: 'PATCH',
    body: JSON.stringify(data),
    locale,
    requiresAuth: true
  });
}

export async function deleteArticle(id: number): Promise<void> {
  return apiRequest<void>(`/api/articles/${id}`, {
    method: 'DELETE',
    requiresAuth: true
  });
}
```

### Categories (`lib/api/categories.ts`)

```typescript
export async function getCategories(
  locale: string = 'ro'
): Promise<Category[]> {
  const response = await apiRequest<CategoryCollection>(
    '/api/categories',
    { locale }
  );
  return response['hydra:member'];
}
```

## TypeScript Types

### Article Types (`lib/types/article.ts`)

```typescript
export interface Article {
  '@id': string;
  '@type': string;
  id: number;
  title: string;
  slug: string;
  lead: string;
  content: string;
  status: 'draft' | 'published' | 'scheduled' | 'archived';
  badge: 'breaking' | 'exclusive' | 'analysis' | 'opinion' | 'video' | 'photo_gallery' | null;
  publishedAt: string | null;
  createdAt: string;
  updatedAt: string;
  category: Category;
  author: Author;
  articleImages: ArticleImage[];
  metaTitle?: string;
  metaDescription?: string;
  readingTime?: number;
}

export interface ArticleCollection {
  '@context': string;
  '@id': string;
  '@type': string;
  'hydra:member': Article[];
  'hydra:totalItems': number;
  'hydra:view': {
    '@id': string;
    '@type': string;
    'hydra:first': string;
    'hydra:last': string;
    'hydra:previous'?: string;
    'hydra:next'?: string;
  };
}
```

## Locale Handling

### Accept-Language Header

All requests include the current locale:

```typescript
// Automatic locale detection from URL
const locale = useParams().locale || 'ro';

// Pass to API functions
const articles = await getArticles({ locale });
```

### Backend Translation

Backend automatically returns translated content based on `Accept-Language` header:

```
Request:  Accept-Language: en
Response: Article title in English

Request:  Accept-Language: ro
Response: Article title in Romanian
```

## Error Handling

### ApiError Class

```typescript
export class ApiError extends Error {
  constructor(
    public status: number,
    public message: string
  ) {
    super(message);
    this.name = 'ApiError';
  }
}
```

### Usage in Components

```typescript
try {
  const article = await getArticle(id, locale);
  setArticle(article);
} catch (error) {
  if (error instanceof ApiError) {
    if (error.status === 404) {
      // Show not found page
    } else if (error.status === 401) {
      // Redirect to login
    } else {
      // Show generic error
    }
  }
}
```

## Authentication Flow

### Login

```typescript
// app/[locale]/login/page.tsx
async function handleLogin(email: string, password: string) {
  const response = await fetch(`${API_URL}/api/login_check`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ username: email, password })
  });
  
  if (!response.ok) {
    throw new Error('Invalid credentials');
  }
  
  const { token, refresh_token } = await response.json();
  setTokens(token, refresh_token);
  
  // Redirect to admin
  router.push(`/${locale}/admin`);
}
```

### Protected Routes

```typescript
// middleware.ts or layout component
export async function middleware(request: NextRequest) {
  const token = getAccessToken();
  
  if (!token && request.nextUrl.pathname.includes('/admin')) {
    return NextResponse.redirect(new URL('/ro/login', request.url));
  }
  
  return NextResponse.next();
}
```

## Image URLs

### CDN Integration

```typescript
const CDN_URL = process.env.NEXT_PUBLIC_CDN_URL || 'http://127.0.0.1:8082';

export function getImageUrl(imagePath: string): string {
  return `${CDN_URL}/uploads/${imagePath}`;
}

export function getThumbnailUrl(thumbnailPath: string): string {
  return `${CDN_URL}/uploads/${thumbnailPath}`;
}
```

### Usage in Components

```typescript
import Image from 'next/image';
import { getImageUrl } from '@/lib/utils/image';

export function ArticleCard({ article }: { article: Article }) {
  const featuredImage = article.articleImages.find(ai => ai.isFeatured);
  
  return (
    <div>
      {featuredImage && (
        <Image
          src={getImageUrl(featuredImage.image.path)}
          alt={featuredImage.image.alt || article.title}
          width={featuredImage.image.width}
          height={featuredImage.image.height}
        />
      )}
    </div>
  );
}
```

## Pagination

### Hydra Pagination

Backend returns pagination metadata:

```json
{
  "hydra:view": {
    "hydra:first": "/api/articles?page=1",
    "hydra:last": "/api/articles?page=10",
    "hydra:previous": "/api/articles?page=1",
    "hydra:next": "/api/articles?page=3"
  },
  "hydra:totalItems": 245
}
```

### Frontend Implementation

```typescript
function ArticleList() {
  const [page, setPage] = useState(1);
  const [articles, setArticles] = useState<Article[]>([]);
  const [totalPages, setTotalPages] = useState(0);
  
  useEffect(() => {
    async function fetchArticles() {
      const response = await getArticles({ page, itemsPerPage: 20 });
      setArticles(response['hydra:member']);
      
      // Calculate total pages
      const total = response['hydra:totalItems'];
      setTotalPages(Math.ceil(total / 20));
    }
    
    fetchArticles();
  }, [page]);
  
  return (
    <div>
      {articles.map(article => <ArticleCard key={article.id} article={article} />)}
      
      <Pagination
        currentPage={page}
        totalPages={totalPages}
        onPageChange={setPage}
      />
    </div>
  );
}
```

## Real-time Updates (Mercure)

### Subscription

```typescript
import { useMercureSubscription } from '@/lib/hooks/useMercureSubscription';

export function LiveTextViewer({ liveTextId }: { liveTextId: number }) {
  const updates = useMercureSubscription(
    `${process.env.NEXT_PUBLIC_MERCURE_URL}`,
    [`deschide_news/livetext/${liveTextId}`]
  );
  
  useEffect(() => {
    if (updates) {
      // Handle real-time update
      console.log('New post:', updates);
    }
  }, [updates]);
}
```

## Performance Optimization

### Data Caching (React Query)

```typescript
// lib/react-query/queries/article-queries.ts
import { useQuery } from '@tanstack/react-query';

export function useArticle(id: number, locale: string) {
  return useQuery({
    queryKey: ['article', id, locale],
    queryFn: () => getArticle(id, locale),
    staleTime: 5 * 60 * 1000, // 5 minutes
    cacheTime: 10 * 60 * 1000 // 10 minutes
  });
}
```

### Request Deduplication

React Query automatically deduplicates identical requests.

---

**Last Updated**: November 2025
**API Version**: 1.0
