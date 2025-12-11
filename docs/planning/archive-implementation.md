# 📋 Plan de Implementare - Sistem Arhivă Articole

**Data:** 2025-11-09
**Status:** ⏳ Așteptare aprobare
**Estimare:** 3-4 săptămâni part-time (28-37 ore)
**Bază:** [archive-strategy.md](./archive-strategy.md)

---

## 📊 Rezumat Executiv

### Obiectiv
Implementarea unui sistem complet de arhivare articole pentru platforma Deschide News, cu:
- Separare clară între articole active (~80,000) și arhivate (~67,000)
- Workflow automat de arhivare pentru articole mai vechi de 4 ani
- Interface dedicată pentru browsing arhivă
- Optimizări SEO și performance

### Volume Estimate
| Categorie | Volume | Perioada | Status |
|-----------|--------|----------|--------|
| **Articole active** | ~80,000 | 2021-2024 | PUBLISHED |
| **Articole arhivate** | ~67,000 | 2016-2020 | ARCHIVED |
| **TOTAL** | **~147,000** | 2016-2024 | - |

### Beneficii
- ✅ Query-uri rapide (doar ~80k articole active vs 147k)
- ✅ Focus pe conținut recent în homepage/listări
- ✅ Arhivă separată, browsable, cu filtre
- ✅ SEO optimizat (noindex pentru arhivă veche)
- ✅ Posibilitate storage tiering în viitor

---

## 🎯 Decizii Arhitecturale

### ✅ RECOMANDĂRI (pentru aprobare)

#### 1. **Abordare tehnică: Status-Based Archive**
- Adăugare status `ARCHIVED` în enum `ArticleStatus`
- Metadata opțională: `archivedAt`, `archiveReason`
- **Justificare:** Simplu, elegant, compatibil cu codul existent

#### 2. **Vârsta threshold: 4 ani**
- Articole 2016-2020 → ARCHIVED (~67,000)
- Articole 2021-2024 → PUBLISHED (~80,000)
- **Justificare:** Balanță optimă între arhivă și conținut activ

#### 3. **Metadata suplimentară: DA**
- `archivedAt` (DateTimeImmutable) - când a fost arhivat
- `archiveReason` (string) - motiv arhivare
- **Justificare:** Audit trail complet, tracking, debugging

#### 4. **SEO: noindex, follow**
- Articole archived: `<meta name="robots" content="noindex, follow">`
- **Justificare:** Google NU indexează arhiva veche, dar păstrează link juice

#### 5. **Arhivare automată: DA**
- Cron job lunar (prima zi a lunii, 02:00)
- Comandă: `app:archive:old-articles --years=4`
- **Justificare:** Zero intervenție manuală, consistență

#### 6. **Search global: exclude archived**
- Search principal → doar articole PUBLISHED
- Arhiva → search dedicat separat
- **Justificare:** Focus pe conținut recent

---

## 📋 Plan de Implementare Detaliat

### **FAZA 1: Backend - Database & Entity** ⏱️ 2-3 ore

#### Task 1.1: Update ArticleStatus Enum
**Fișier:** `src/Enum/ArticleStatus.php`

**Modificări:**
```php
enum ArticleStatus: string
{
    case NEW = 'new';
    case SUBMITTED = 'submitted';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';  // ← NOU

    public function label(): string
    {
        return match($this) {
            self::NEW => 'Draft',
            self::SUBMITTED => 'Submitted',
            self::PUBLISHED => 'Published',
            self::ARCHIVED => 'Archived',  // ← NOU
        };
    }

    public function color(): string
    {
        return match($this) {
            self::NEW => 'gray',
            self::SUBMITTED => 'yellow',
            self::PUBLISHED => 'green',
            self::ARCHIVED => 'blue',  // ← NOU
        };
    }

    public function isArchived(): bool  // ← NOU
    {
        return $this === self::ARCHIVED;
    }

    public function isPublic(): bool
    {
        return $this === self::PUBLISHED || $this === self::ARCHIVED;
    }
}
```

#### Task 1.2: Add Archive Metadata to Article Entity
**Fișier:** `src/Entity/Article.php`

**Proprietăți noi:**
```php
#[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
#[Groups(['article:read'])]
private ?DateTimeImmutable $archivedAt = null;

#[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
#[Groups(['article:read'])]
private ?string $archiveReason = null;
```

**Metode noi:**
```php
public function archive(?string $reason = null): self
{
    $this->status = ArticleStatus::ARCHIVED;
    $this->archivedAt = new DateTimeImmutable();
    $this->archiveReason = $reason;
    return $this;
}

public function unarchive(): self
{
    $this->status = ArticleStatus::PUBLISHED;
    $this->archivedAt = null;
    $this->archiveReason = null;
    return $this;
}
```

#### Task 1.3: Create and Run Migration
**Comenzi:**
```bash
cd /var/www/deschide_news_app/deschide_backend

# Generate migration
symfony console make:migration

# Review migration file in migrations/

# Run migration
symfony console doctrine:migrations:migrate
```

**Migration generată (aproximativ):**
```sql
ALTER TABLE articles
    ADD COLUMN archived_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    ADD COLUMN archive_reason VARCHAR(255) DEFAULT NULL;

CREATE INDEX idx_article_archived_at ON articles (archived_at);

-- Doctrine va regenera constraint-ul enum automat
```

**Verificare:**
```bash
# Check database schema
symfony console doctrine:schema:validate

# Should output: [OK] The database schema is in sync with the mapping files.
```

---

### **FAZA 2: Backend - Business Logic** ⏱️ 3-4 ore

#### Task 2.1: Create ArticleArchiveService
**Fișier:** `src/Service/ArticleArchiveService.php` (nou)

**Responsabilități:**
- Arhivare/de-arhivare articol individual
- Arhivare bulk articole vechi
- Statistici arhivă

**Metode principale:**
- `archiveArticle(Article $article, ?string $reason): void`
- `unarchiveArticle(Article $article): void`
- `archiveOldArticles(int $yearsOld, int $batchSize): int`
- `getArchiveStats(): array`

**Pseudocod:**
```php
public function archiveOldArticles(int $yearsOld = 4, int $batchSize = 100): int
{
    $cutoffDate = (new DateTimeImmutable())->modify("-{$yearsOld} years");

    // Query articles older than cutoff
    $qb = $this->articleRepository->createQueryBuilder('a')
        ->where('a.status = :status')
        ->andWhere('a.publishedAt < :cutoffDate')
        ->setParameter('status', ArticleStatus::PUBLISHED)
        ->setParameter('cutoffDate', $cutoffDate)
        ->setMaxResults($batchSize);

    $articles = $qb->getQuery()->getResult();
    $count = 0;

    foreach ($articles as $article) {
        $this->archiveArticle($article, "Auto-archived: older than {$yearsOld} years");
        $count++;

        // Flush every 50 for performance
        if ($count % 50 === 0) {
            $this->entityManager->flush();
            $this->entityManager->clear();
        }
    }

    $this->entityManager->flush();
    return $count;
}
```

**Dependințe:**
- `ArticleRepository`
- `EntityManagerInterface`
- `LoggerInterface`

#### Task 2.2: Create Console Command
**Fișier:** `src/Command/ArchiveOldArticlesCommand.php` (nou)

**Comandă:**
```bash
app:archive:old-articles [options]
```

**Opțiuni:**
- `--years=N` - Archive articles older than N years (default: 4)
- `--batch-size=N` - Process N articles per batch (default: 100)
- `--dry-run` - Preview what would be archived (no actual changes)

**Output UI:**
```
Archive Old Articles
====================

Configuration
-------------
 Years old: 4
 Batch size: 100
 Mode: LIVE

 Do you want to proceed with archiving? (yes/no) [no]:
 > yes

Archiving...
============
 67120/67120 [============================] 100%

 [OK] Archived 67,120 articles older than 4 years.

Archive Statistics
==================
 --------- -------
  Status    Count
 --------- -------
  new       150
  published 79,850
  archived  67,120
 --------- -------
```

**Implementare:**
- Symfony Console Command cu SymfonyStyle
- Progress bar pentru feedback vizual
- Confirmation prompt pentru siguranță
- Dry-run support pentru preview

#### Task 2.3: Setup Automated Archiving
**Opțiune A: Cron Job (recomandat pentru simplitate)**

**Fișier:** `/etc/cron.d/deschide-archive`
```bash
# Archive old articles monthly (1st day of month, 2:00 AM)
0 2 1 * * www-data cd /var/www/deschide_news_app/deschide_backend && symfony console app:archive:old-articles --years=4 >> /var/log/deschide_archive.log 2>&1
```

**Setup:**
```bash
sudo nano /etc/cron.d/deschide-archive
# Paste content above
sudo chmod 644 /etc/cron.d/deschide-archive
```

**Opțiune B: Symfony Scheduler (pentru integrare mai strânsă)**

**Fișier:** `config/packages/scheduler.yaml`
```yaml
framework:
    scheduler:
        default_transport: doctrine

        schedules:
            archive_old_articles:
                task: 'app:archive:old-articles --years=4'
                frequency: 'first monday of month at 02:00'

# Require: composer require symfony/scheduler
# Start worker: symfony console messenger:consume scheduler_default
```

**Alegere:** Cron job (mai simplu, nu necesită worker persistent)

---

### **FAZA 3: Backend - API Endpoints** ⏱️ 2-3 ore

#### Task 3.1: Update ArticleProvider
**Fișier:** `src/State/ArticleProvider.php`

**Modificare:** Exclude archived articles din listări normale

```php
public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
{
    // ... existing code ...

    if ($operation instanceof GetCollection) {
        $queryBuilder = $this->articleRepository->createQueryBuilder('a');

        // DEFAULT: Exclude archived articles
        $includeArchived = $context['filters']['include_archived'] ?? false;
        if (!$includeArchived) {
            $queryBuilder
                ->andWhere('a.status != :archived')
                ->setParameter('archived', ArticleStatus::ARCHIVED);
        }

        // ... rest of query building ...
    }

    // ... existing code ...
}
```

**Impact:**
- GET `/api/articles` → doar PUBLISHED (fără ARCHIVED)
- GET `/api/articles?include_archived=1` → toate (dacă necesar în admin)

#### Task 3.2: Create Public Archive API
**Fișier:** `src/Controller/Api/ArchiveController.php` (nou)

**Endpoints:**

##### 1. GET `/api/archive/articles`
Lista articole arhivate (paginată)

**Parametri query:**
- `page` - număr pagină (default: 1)
- `itemsPerPage` - articole per pagină (default: 20, max: 50)
- `year` - filtrare după an publicare (ex: 2018)
- `category` - filtrare după categorie ID

**Response:**
```json
{
  "items": [
    {
      "@id": "/api/articles/123",
      "@type": "Article",
      "id": 123,
      "title": "Titlu articol vechi",
      "slug": "titlu-articol-vechi",
      "status": "archived",
      "publishedAt": "2018-05-12T10:00:00+00:00",
      "archivedAt": "2024-11-09T02:00:00+00:00",
      "archiveReason": "Auto-archived: older than 4 years"
    }
  ],
  "total": 67120,
  "page": 1,
  "itemsPerPage": 20,
  "totalPages": 3356
}
```

##### 2. GET `/api/archive/years`
Lista ani disponibili în arhivă

**Response:**
```json
[
  {"year": 2020, "count": 12500},
  {"year": 2019, "count": 15800},
  {"year": 2018, "count": 14300},
  {"year": 2017, "count": 13200},
  {"year": 2016, "count": 11320}
]
```

##### 3. GET `/api/archive/stats`
Statistici arhivă

**Response:**
```json
{
  "total": 67120,
  "by_year": [
    {"year": 2020, "count": 12500},
    {"year": 2019, "count": 15800}
  ],
  "by_category": [
    {"category": "Politică", "count": 18500},
    {"category": "Economie", "count": 12300}
  ]
}
```

**Implementare:**
- Controller standard Symfony
- Serialization groups: `['article:read', 'article:list']`
- Cache headers: `Cache-Control: public, max-age=86400`

#### Task 3.3: Create Admin Archive API
**Fișier:** `src/Controller/Admin/ArticleArchiveController.php` (nou)

**Securitate:** `#[IsGranted('ROLE_ADMIN')]`

**Endpoints:**

##### 1. POST `/api/admin/articles/{id}/archive`
Arhivează un articol specific

**Request body:**
```json
{
  "reason": "Outdated information"
}
```

**Response:** Article object cu status ARCHIVED

##### 2. POST `/api/admin/articles/{id}/unarchive`
De-arhivează un articol

**Response:** Article object cu status PUBLISHED

##### 3. POST `/api/admin/articles/archive-old`
Arhivare bulk

**Request body:**
```json
{
  "years_old": 4,
  "batch_size": 100
}
```

**Response:**
```json
{
  "message": "Bulk archive completed",
  "total_archived": 67120
}
```

**Implementare:**
- Folosește `ArticleArchiveService`
- Authentication: JWT token
- Logging pentru audit

---

### **FAZA 4: Frontend - UI/UX** ⏱️ 4-5 ore

#### Task 4.1: Create Archive Page Route
**Fișier:** `deschide_frontend/app/[locale]/arhiva/page.tsx` (nou)

**Metadata:**
```typescript
export const metadata: Metadata = {
  title: 'Arhiva - Deschide.md',
  description: 'Arhiva articolelor publicate între 2016-2020',
  robots: {
    index: false,  // NU indexa în Google
    follow: true,  // DAR urmărește link-urile
  },
};
```

**Layout:**
```tsx
export default function ArchivePage() {
  return (
    <div className="container mx-auto px-4 py-8">
      <h1 className="text-4xl font-bold mb-8">Arhiva Deschide.md</h1>

      {/* Info Banner */}
      <div className="bg-blue-50 border-l-4 border-blue-500 p-4 mb-8">
        <p className="text-blue-900">
          Această secțiune conține articole publicate în perioada 2016-2020.
          Pentru conținut recent, vizitați <a href="/" className="underline">pagina principală</a>.
        </p>
      </div>

      <ArchiveBrowser />
    </div>
  );
}
```

#### Task 4.2: Create ArchiveBrowser Component
**Fișier:** `deschide_frontend/components/archive/ArchiveBrowser.tsx` (nou)

**Responsabilități:**
- Fetch articole archived din API
- Display grid articole
- Paginare
- Integrare cu filters

**Layout:**
```
┌─────────────────────────────────────────┐
│  Sidebar (Filters)  │  Main (Articles)  │
│                     │                   │
│  Year Filter:       │  [Article Card]   │
│  ☑ Toate            │  [Article Card]   │
│  ☐ 2020 (12,500)    │  [Article Card]   │
│  ☐ 2019 (15,800)    │  [Article Card]   │
│                     │                   │
│  Category Filter:   │  [Pagination]     │
│  ☑ Toate            │                   │
│  ☐ Politică         │                   │
│  ☐ Economie         │                   │
└─────────────────────────────────────────┘
```

**State management:**
```typescript
const [articles, setArticles] = useState([]);
const [loading, setLoading] = useState(true);
const [page, setPage] = useState(1);
const [totalPages, setTotalPages] = useState(1);
const [selectedYear, setSelectedYear] = useState<number | null>(null);
const [selectedCategory, setSelectedCategory] = useState<string | null>(null);
```

**API call:**
```typescript
const fetchArchiveArticles = async () => {
  const params = new URLSearchParams({
    page: page.toString(),
    itemsPerPage: '20',
  });

  if (selectedYear) params.append('year', selectedYear.toString());
  if (selectedCategory) params.append('category', selectedCategory);

  const response = await fetch(
    `${process.env.NEXT_PUBLIC_API_URL}/api/archive/articles?${params}`
  );
  const data = await response.json();

  setArticles(data.items);
  setTotalPages(data.totalPages);
};
```

#### Task 4.3: Create Filter Components

##### YearFilter.tsx
**Fișier:** `deschide_frontend/components/archive/YearFilter.tsx` (nou)

**UI:**
```
┌─────────────────────┐
│ Filtrare după an    │
├─────────────────────┤
│ ☑ Toate perioadele  │  ← active state
│ ☐ 2020 (12,500)     │
│ ☐ 2019 (15,800)     │
│ ☐ 2018 (14,300)     │
│ ☐ 2017 (13,200)     │
│ ☐ 2016 (11,320)     │
└─────────────────────┘
```

**Features:**
- Fetch years din `/api/archive/years`
- Display count per year
- Active state highlighting
- Click handler: `onYearChange(year)`

##### CategoryFilter.tsx
**Fișier:** `deschide_frontend/components/archive/CategoryFilter.tsx` (nou)

**Similar cu YearFilter:**
- Fetch categories din `/api/categories`
- Filter by archived articles count
- Active state
- Click handler: `onCategoryChange(category)`

#### Task 4.4: Update ArticleCard Component
**Fișier:** `deschide_frontend/components/articles/ArticleCard.tsx`

**Props update:**
```typescript
interface ArticleCardProps {
  article: Article;
  isArchived?: boolean;  // ← NOU
}
```

**Archive Badge:**
```tsx
{isArchived && (
  <div className="absolute top-2 right-2">
    <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
      <ArchiveIcon className="w-4 h-4 mr-1" />
      Arhivat
    </span>
  </div>
)}
```

**Styling diferențiat:**
```tsx
<article className={`
  bg-white rounded-lg shadow-md overflow-hidden
  hover:shadow-lg transition-shadow
  ${isArchived ? 'opacity-90 border border-blue-200' : ''}
`}>
```

#### Task 4.5: Add Navigation Links
**Fișier:** `deschide_frontend/components/layout/Footer.tsx`

**Adaugă în footer:**
```tsx
<div>
  <h4 className="font-bold text-lg mb-4">Resurse</h4>
  <ul className="space-y-2">
    <li>
      <a href="/arhiva" className="hover:text-blue-400 transition-colors">
        📚 Arhiva (2016-2020)
      </a>
    </li>
    {/* ... other links ... */}
  </ul>
</div>
```

**Optional:** Link în header/main navigation (decizie UX)

---

### **FAZA 5: Admin Panel - Archive Management** ⏱️ 3-4 ore

#### Task 5.1: Create Archive Management Page
**Fișier:** `deschide_frontend/app/[locale]/admin/archive/page.tsx` (nou)

**Layout:**
```
┌──────────────────────────────────────┐
│  Gestionare Arhivă                   │
├──────────────────────────────────────┤
│  ┌────────────────────────────────┐  │
│  │  Statistici                    │  │
│  │  • Total arhivate: 67,120      │  │
│  │  • Total active: 79,850        │  │
│  │  • Ultim arhivat: 2024-11-01   │  │
│  └────────────────────────────────┘  │
│                                      │
│  ┌────────────────────────────────┐  │
│  │  Arhivare în Masă              │  │
│  │  Arhivează articole mai vechi  │  │
│  │  de: [4] ani                   │  │
│  │  [Arhivează Articole Vechi]    │  │
│  └────────────────────────────────┘  │
│                                      │
│  ┌────────────────────────────────┐  │
│  │  Articole Arhivate             │  │
│  │  [Tabel cu listă]              │  │
│  └────────────────────────────────┘  │
└──────────────────────────────────────┘
```

**Components:**
- `<ArchiveStats />` - statistici live
- `<BulkArchiveForm />` - form arhivare bulk
- `<ArchivedArticlesList />` - tabel articole

#### Task 5.2: Create BulkArchiveForm Component
**Fișier:** `deschide_frontend/components/admin/archive/BulkArchiveForm.tsx` (nou)

**Features:**
- Input număr ani (default: 4, min: 1, max: 10)
- Preview: "Vor fi arhivate articole publicate înainte de {year}"
- Confirmation dialog: "Sigur vrei să arhivezi ~N articole?"
- Progress indicator during archiving
- Success message: "Arhivare completă! {count} articole arhivate."

**API call:**
```typescript
const handleSubmit = async (e: React.FormEvent) => {
  e.preventDefault();

  if (!confirm(`Sigur vrei să arhivezi articolele mai vechi de ${yearsOld} ani?`)) {
    return;
  }

  setLoading(true);

  try {
    const response = await fetch(
      `${process.env.NEXT_PUBLIC_API_URL}/api/admin/articles/archive-old`,
      {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${getAuthToken()}`,
        },
        body: JSON.stringify({
          years_old: yearsOld,
          batch_size: 100,
        }),
      }
    );

    const data = await response.json();
    alert(`Arhivare completă! ${data.total_archived} articole arhivate.`);
    onComplete(); // Refresh stats
  } catch (error) {
    console.error('Bulk archive failed:', error);
    alert('Eroare la arhivare.');
  } finally {
    setLoading(false);
  }
};
```

#### Task 5.3: Create ArchiveStats Component
**Fișier:** `deschide_frontend/components/admin/archive/ArchiveStats.tsx` (nou)

**Display:**
```
┌─────────────────────────────────────┐
│  Statistici Arhivă                  │
├─────────────────────────────────────┤
│  Total arhivate:     67,120         │
│  Total publicate:    79,850         │
│  Procent arhivat:    45.6%          │
│                                     │
│  Top 5 Ani:                         │
│  • 2019: 15,800 articole            │
│  • 2018: 14,300 articole            │
│  • 2020: 12,500 articole            │
│                                     │
│  Top 5 Categorii:                   │
│  • Politică: 18,500 articole        │
│  • Economie: 12,300 articole        │
└─────────────────────────────────────┘
```

**Data source:** GET `/api/archive/stats`

#### Task 5.4: Create ArchivedArticlesList Component
**Fișier:** `deschide_frontend/components/admin/archive/ArchivedArticlesList.tsx` (nou)

**Table columns:**
| ID | Titlu | Categorie | Publicat | Arhivat | Motiv | Acțiuni |
|----|-------|-----------|----------|---------|-------|---------|
| 123 | Titlu... | Politică | 2018-05-12 | 2024-11-01 | Auto: >4yr | [Unarchive] [View] |

**Features:**
- Paginare (20 per pagină)
- Sort by publishedAt DESC
- Acțiune "Unarchive" → POST `/api/admin/articles/{id}/unarchive`
- Acțiune "View" → link către articol

**API call:**
```typescript
const fetchArchivedArticles = async () => {
  const response = await fetch(
    `${process.env.NEXT_PUBLIC_API_URL}/api/archive/articles?page=${page}&itemsPerPage=20`,
    {
      headers: {
        'Authorization': `Bearer ${getAuthToken()}`,
      },
    }
  );
  const data = await response.json();
  setArticles(data.items);
};
```

---

### **FAZA 6: SEO & Performance Optimization** ⏱️ 2-3 ore

#### Task 6.1: Implement Robots Meta Tags
**Fișier:** `deschide_frontend/app/[locale]/articole/[slug]/page.tsx`

**Dynamic metadata:**
```typescript
export async function generateMetadata({ params }): Promise<Metadata> {
  const article = await fetchArticle(params.slug);

  // Archived articles: noindex
  if (article.status === 'archived') {
    return {
      title: `${article.title} - Arhivat`,
      description: article.lead,
      robots: {
        index: false,   // NU indexa în Google
        follow: true,   // DAR urmărește link-urile (preservă link juice)
      },
    };
  }

  // Published articles: normal indexing
  return {
    title: article.title,
    description: article.lead,
    robots: {
      index: true,
      follow: true,
    },
    openGraph: {
      title: article.title,
      description: article.lead,
      // ... rest of OG tags
    },
  };
}
```

**Impact:**
- Articole PUBLISHED → indexate în Google
- Articole ARCHIVED → NU indexate, dar link juice păstrat
- ~67,000 URL-uri excluse din index → SEO focus pe conținut recent

#### Task 6.2: Create Archive Sitemap
**Fișier:** `deschide_frontend/app/sitemap-archive.xml/route.ts` (nou)

**Generate XML sitemap:**
```typescript
import { NextResponse } from 'next/server';

export async function GET() {
  // Fetch all archived articles (paginate if needed)
  const response = await fetch(
    `${process.env.NEXT_PUBLIC_API_URL}/api/archive/articles?itemsPerPage=10000`
  );
  const data = await response.json();

  const xml = `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  ${data.items
    .map((article) => {
      return `
    <url>
      <loc>${process.env.NEXT_PUBLIC_SITE_URL}/ro/articole/${article.slug}</loc>
      <lastmod>${article.updatedAt}</lastmod>
      <changefreq>yearly</changefreq>
      <priority>0.3</priority>
    </url>`;
    })
    .join('')}
</urlset>`;

  return new NextResponse(xml, {
    headers: {
      'Content-Type': 'application/xml',
      'Cache-Control': 'public, max-age=86400, s-maxage=604800', // 1 zi client, 1 săpt CDN
    },
  });
}
```

**robots.txt update:**
**Fișier:** `deschide_frontend/public/robots.txt`
```txt
User-agent: *
Allow: /

# Sitemaps
Sitemap: https://deschide.md/sitemap.xml
Sitemap: https://deschide.md/sitemap-archive.xml

# Archive (allow crawling but URLs have noindex)
Allow: /arhiva
```

**Benefits:**
- Google știe de arhivă (în sitemap)
- Dar NU indexează (noindex meta tag)
- Balanced approach

#### Task 6.3: Implement Aggressive Caching

##### Frontend (Next.js)
**Fișier:** `deschide_frontend/app/[locale]/arhiva/page.tsx`

```typescript
// Archive page - static, revalidate săptămânal
export const revalidate = 604800; // 7 zile

// Archive article detail pages
export const revalidate = 604800; // 7 zile (arhiva se schimbă rar)
```

##### Backend (Symfony)
**Fișier:** `src/Controller/Api/ArchiveController.php`

```php
#[Route('/articles', name: 'articles', methods: ['GET'])]
public function getArchivedArticles(Request $request): JsonResponse
{
    // ... query logic ...

    return $this->json($data, 200, [
        // Client cache: 1 zi
        // CDN/proxy cache: 1 săptămână
        'Cache-Control' => 'public, max-age=86400, s-maxage=604800',
        'Vary' => 'Accept-Language',
    ], ['groups' => ['article:read']]);
}
```

**CDN Configuration (Cloudflare/etc):**
```
Cache Rules:
  - /api/archive/* → Cache for 7 days
  - /arhiva* → Cache for 1 day
```

**Benefits:**
- Reduced server load
- Faster page loads
- Lower bandwidth costs
- Arhiva se schimbă rar → caching agresiv OK

---

### **FAZA 7: Testing & Quality Assurance** ⏱️ 2-3 ore

#### Task 7.1: Unit Tests - ArticleArchiveService
**Fișier:** `deschide_backend/tests/Unit/Service/ArticleArchiveServiceTest.php` (nou)

**Test cases:**
```php
class ArticleArchiveServiceTest extends TestCase
{
    public function testArchiveArticle(): void
    {
        // Given: Article cu status PUBLISHED
        // When: archiveArticle() apelat
        // Then: Status devine ARCHIVED, archivedAt != null
    }

    public function testArchiveArticleAlreadyArchived(): void
    {
        // Given: Article deja ARCHIVED
        // When: archiveArticle() apelat
        // Then: Warning logat, status rămâne ARCHIVED
    }

    public function testUnarchiveArticle(): void
    {
        // Given: Article ARCHIVED
        // When: unarchiveArticle() apelat
        // Then: Status devine PUBLISHED, archivedAt = null
    }

    public function testArchiveOldArticles(): void
    {
        // Given: 10 articole mai vechi de 4 ani
        // When: archiveOldArticles(4, 100) apelat
        // Then: Return 10, toate articolele sunt ARCHIVED
    }

    public function testArchiveOldArticlesNothingToArchive(): void
    {
        // Given: Toate articolele sunt recente (<4 ani)
        // When: archiveOldArticles(4, 100) apelat
        // Then: Return 0
    }
}
```

**Run tests:**
```bash
cd /var/www/deschide_news_app/deschide_backend
vendor/bin/phpunit tests/Unit/Service/ArticleArchiveServiceTest.php
```

#### Task 7.2: Integration Tests - Archive API
**Fișier:** `deschide_backend/tests/Functional/Controller/ArchiveControllerTest.php` (nou)

**Test cases:**
```php
class ArchiveControllerTest extends WebTestCase
{
    public function testGetArchivedArticles(): void
    {
        // Given: DB cu 50 articole archived
        // When: GET /api/archive/articles?page=1&itemsPerPage=20
        // Then: Status 200, 20 items returned, total=50
    }

    public function testGetArchivedArticlesFilterByYear(): void
    {
        // Given: Articole archived din 2018 și 2019
        // When: GET /api/archive/articles?year=2018
        // Then: Doar articole din 2018 returned
    }

    public function testGetArchiveYears(): void
    {
        // Given: Articole archived din 2016-2020
        // When: GET /api/archive/years
        // Then: Array cu 5 ani, fiecare cu count
    }

    public function testGetArchiveStats(): void
    {
        // When: GET /api/archive/stats
        // Then: JSON cu total, by_year, by_category
    }

    public function testArchiveArticleAsAdmin(): void
    {
        // Given: Authenticated ca ROLE_ADMIN
        // When: POST /api/admin/articles/123/archive
        // Then: Status 200, article status=ARCHIVED
    }

    public function testArchiveArticleAsGuest(): void
    {
        // Given: Unauthenticated
        // When: POST /api/admin/articles/123/archive
        // Then: Status 401 Unauthorized
    }
}
```

**Run tests:**
```bash
vendor/bin/phpunit tests/Functional/Controller/ArchiveControllerTest.php
```

#### Task 7.3: Manual Testing Checklist

**Backend:**
```
☐ Comandă arhivare funcționează
  cd /var/www/deschide_news_app/deschide_backend
  symfony console app:archive:old-articles --years=4 --dry-run

☐ Dry-run nu modifică date
  SELECT COUNT(*) FROM articles WHERE status='archived'; -- should be 0

☐ Live run arhivează corect
  symfony console app:archive:old-articles --years=4
  SELECT COUNT(*) FROM articles WHERE status='archived'; -- should be ~67k

☐ Metadata setată corect
  SELECT id, title, archived_at, archive_reason FROM articles WHERE status='archived' LIMIT 5;

☐ API /api/archive/articles returnează doar ARCHIVED
  curl http://127.0.0.1:8081/api/archive/articles | jq '.items[0].status'
  # should output: "archived"

☐ API /api/archive/years returnează ani corecți
  curl http://127.0.0.1:8081/api/archive/years | jq '.'

☐ Admin API archive funcționează
  curl -X POST http://127.0.0.1:8081/api/admin/articles/1/archive \
    -H "Authorization: Bearer TOKEN" \
    -H "Content-Type: application/json" \
    -d '{"reason": "Test"}'

☐ Admin API unarchive funcționează
  curl -X POST http://127.0.0.1:8081/api/admin/articles/1/unarchive \
    -H "Authorization: Bearer TOKEN"
```

**Frontend:**
```
☐ Pagina /arhiva se încarcă
  Open: http://localhost:3005/ro/arhiva

☐ Banner informativ afișat
  Verify: Blue banner cu mesaj despre arhivă

☐ Filtrare după an funcționează
  Click: 2019 → Verify: Doar articole din 2019

☐ Filtrare după categorie funcționează
  Click: Politică → Verify: Doar articole din Politică

☐ Paginare funcționează
  Click: Page 2 → Verify: Următoarele 20 articole

☐ Badge "Arhivat" apare pe ArticleCard
  Verify: Badge albastru cu icon

☐ Link către arhivă în footer
  Scroll to footer → Verify: Link "📚 Arhiva (2016-2020)"

☐ Homepage NU afișează articole archived
  Open: http://localhost:3005/ro
  Verify: Toate articolele au status PUBLISHED
```

**Admin Panel:**
```
☐ Pagina admin archive se încarcă
  Open: http://localhost:3005/ro/admin/archive
  Login as admin

☐ Statistici afișate corect
  Verify: Total archived, total published

☐ Form bulk archive funcționează
  Input: 4 ani
  Click: "Arhivează Articole Vechi"
  Verify: Success message cu count

☐ Lista articole arhivate
  Verify: Tabel cu articole, paginare

☐ Unarchive button funcționează
  Click: Unarchive pe un articol
  Verify: Status devine PUBLISHED
```

**SEO:**
```
☐ Articole archived au robots noindex
  Open: http://localhost:3005/ro/articole/{archived-slug}
  View source → Verify: <meta name="robots" content="noindex, follow">

☐ Articole published au robots index
  Open: http://localhost:3005/ro/articole/{published-slug}
  View source → Verify: <meta name="robots" content="index, follow">

☐ Sitemap arhivă se generează
  Open: http://localhost:3005/sitemap-archive.xml
  Verify: XML cu articole archived, priority=0.3

☐ robots.txt referă ambele sitemaps
  Open: http://localhost:3005/robots.txt
  Verify: Sitemap: .../sitemap.xml și .../sitemap-archive.xml

☐ Cache headers setate corect
  curl -I http://127.0.0.1:8081/api/archive/articles
  Verify: Cache-Control: public, max-age=86400
```

**Performance:**
```
☐ Query speed îmbunătățit
  Before (all articles):
    SELECT COUNT(*) FROM articles; -- ~147k

  After (only published):
    SELECT COUNT(*) FROM articles WHERE status='published'; -- ~80k

  Homepage query time should be faster (~40% reduction)

☐ Archive page loading fast
  Open: http://localhost:3005/ro/arhiva
  Network tab → Verify: API response <500ms (cu cache)
```

---

## ⏱️ Timeline Implementare

### Sprint 1: Backend Foundation (1 săptămână)

| Zi | Fază | Task-uri | Ore |
|----|------|----------|-----|
| **Luni** | FAZA 1 | Update ArticleStatus enum, migration, metadata | 2-3h |
| **Marți** | FAZA 2 | ArticleArchiveService implementation | 2-3h |
| **Miercuri** | FAZA 2 | Console command, scheduler setup | 1-2h |
| **Joi** | FAZA 3 | Update ArticleProvider, ArchiveController | 2-3h |
| **Vineri** | FAZA 3 | Admin API endpoints, testing | 1-2h |

**Total Sprint 1:** ~8-13 ore

### Sprint 2: Frontend & Admin (1 săptămână)

| Zi | Fază | Task-uri | Ore |
|----|------|----------|-----|
| **Luni** | FAZA 4 | Archive page route, ArchiveBrowser component | 2-3h |
| **Marți** | FAZA 4 | YearFilter, CategoryFilter components | 2-3h |
| **Miercuri** | FAZA 4 | ArticleCard update, navigation links | 1h |
| **Joi** | FAZA 5 | Admin archive management page | 2-3h |
| **Vineri** | FAZA 5 | BulkArchiveForm, ArchiveStats, ArchivedArticlesList | 2-3h |

**Total Sprint 2:** ~9-13 ore

### Sprint 3: SEO, Testing & Deployment (3-4 zile)

| Zi | Fază | Task-uri | Ore |
|----|------|----------|-----|
| **Luni** | FAZA 6 | Robots meta tags, archive sitemap | 1-2h |
| **Marți** | FAZA 6 | Cache strategy implementation | 1h |
| **Miercuri** | FAZA 7 | Unit tests, integration tests | 2-3h |
| **Joi** | FAZA 7 | Manual testing, bug fixes | 2-3h |

**Total Sprint 3:** ~6-9 ore

**TOTAL GENERAL: 23-35 ore (3 săptămâni part-time)**

---

## 🚀 Deployment Plan

### Pre-Deployment Checklist
```bash
# 1. Backup database
pg_dump -h localhost -U deschide_user deschide_news > backup_pre_archive_$(date +%Y%m%d).sql

# 2. Create feature branch
git checkout -b feature/archive-system
git add .
git commit -m "feat: implement archive system for old articles"

# 3. Run tests
cd /var/www/deschide_news_app/deschide_backend
vendor/bin/phpunit

# 4. Code review
# Review all changes, ensure quality
```

### Deployment Steps

#### Step 1: Deploy Backend
```bash
cd /var/www/deschide_news_app/deschide_backend

# Pull latest code
git pull origin feature/archive-system

# Install dependencies (if new)
composer install --no-dev --optimize-autoloader

# Run migrations
symfony console doctrine:migrations:migrate --no-interaction

# Clear cache
symfony console cache:clear --env=prod

# Verify migration
symfony console doctrine:schema:validate
```

#### Step 2: Test Backend
```bash
# Test command (dry-run)
symfony console app:archive:old-articles --years=4 --dry-run

# Test API endpoints
curl http://127.0.0.1:8081/api/archive/years
curl http://127.0.0.1:8081/api/archive/articles?page=1

# Check database
psql -U deschide_user -d deschide_news -c "SELECT status, COUNT(*) FROM articles GROUP BY status;"
```

#### Step 3: Deploy Frontend
```bash
cd /var/www/deschide_news_app/deschide_frontend

# Pull latest code
git pull origin feature/archive-system

# Install dependencies
pnpm install

# Build production
pnpm build

# Restart application
pm2 restart deschide_frontend

# Verify build
pm2 logs deschide_frontend --lines 50
```

#### Step 4: Run Initial Archive (CRITICAL!)
```bash
cd /var/www/deschide_news_app/deschide_backend

# ⚠️ RUN DOAR O DATĂ - Archives old articles
symfony console app:archive:old-articles --years=4

# Expected output:
# Archive Old Articles
# ====================
# ...
# [OK] Archived 67,120 articles older than 4 years.

# Verify results
psql -U deschide_user -d deschide_news -c "
  SELECT status, COUNT(*) as count
  FROM articles
  GROUP BY status
  ORDER BY count DESC;
"

# Expected output:
#   status   | count
# -----------+--------
#  published | ~80000
#  archived  | ~67000
```

#### Step 5: Setup Automated Archiving
```bash
# Create cron job file
sudo nano /etc/cron.d/deschide-archive

# Add content:
# Archive old articles monthly (1st day of month, 2:00 AM)
0 2 1 * * www-data cd /var/www/deschide_news_app/deschide_backend && symfony console app:archive:old-articles --years=4 >> /var/log/deschide_archive.log 2>&1

# Set permissions
sudo chmod 644 /etc/cron.d/deschide-archive

# Create log file
sudo touch /var/log/deschide_archive.log
sudo chown www-data:www-data /var/log/deschide_archive.log
```

#### Step 6: Post-Deployment Verification
```bash
# Frontend checks
curl -I http://localhost:3005/ro/arhiva
# Expect: 200 OK

# Backend checks
curl http://127.0.0.1:8081/api/archive/stats | jq '.'
# Expect: JSON with stats

# Database integrity
psql -U deschide_user -d deschide_news -c "
  SELECT
    EXTRACT(YEAR FROM published_at) as year,
    COUNT(*) as count
  FROM articles
  WHERE status = 'archived'
  GROUP BY year
  ORDER BY year DESC;
"
# Expect: Years 2016-2020 with counts

# SEO checks
curl -s http://localhost:3005/ro/articole/{archived-slug} | grep -o 'robots" content="[^"]*"'
# Expect: robots" content="noindex, follow"
```

### Post-Deployment Monitoring (First Week)

**Day 1:**
- ✅ Verify archive page accessible
- ✅ Check Google Search Console for crawl errors
- ✅ Monitor application logs for errors

**Day 3:**
- ✅ Verify cron job ran (check `/var/log/deschide_archive.log`)
- ✅ Check performance metrics (page load times)

**Day 7:**
- ✅ Review analytics (traffic to /arhiva)
- ✅ Check for any reported issues
- ✅ Validate SEO impact (Google Search Console)

### Rollback Plan (Emergency)

If critical issues found:
```bash
# Backend rollback
cd /var/www/deschide_news_app/deschide_backend
git revert {commit-hash}
symfony console doctrine:migrations:migrate prev

# Restore from backup
psql -U deschide_user -d deschide_news < backup_pre_archive_{date}.sql

# Frontend rollback
cd /var/www/deschide_news_app/deschide_frontend
git revert {commit-hash}
pnpm build
pm2 restart deschide_frontend
```

---

## 📊 Success Metrics

### Performance Metrics
- **Homepage load time:** ↓40% (fewer articles to query)
- **API response time:** `/api/articles` → <200ms (down from ~400ms)
- **Database query time:** SELECT published → <50ms (indexed, smaller dataset)

### User Metrics
- **Archive page visits:** Track analytics
- **Archive search usage:** Monitor filter usage
- **User complaints:** Should be minimal (clear UI/UX)

### SEO Metrics
- **Indexed pages:** ↓67,000 (only active content indexed)
- **Crawl budget:** More focused on recent content
- **Search rankings:** Improved for recent articles (less dilution)

### Technical Metrics
- **Archived articles:** ~67,000 (target: 2016-2020)
- **Active articles:** ~80,000 (target: 2021-2024)
- **Archive automation:** 100% (cron job running monthly)
- **Test coverage:** >80% (unit + integration tests)

---

## ❓ Decision Points (Require Approval)

### 1. **Abordare tehnică**
- ✅ **RECOMANDAT:** Status-Based Archive (enum ARCHIVED)
- ⬜ Alternative: Flag-based (isArchived boolean)
- ⬜ Alternative: Separate table (complex, not recommended)

**Decizie:** _______________________

### 2. **Vârsta threshold**
- ⬜ 3 ani (2016-2021 archived)
- ✅ **RECOMANDAT:** 4 ani (2016-2020 archived)
- ⬜ 5 ani (2016-2019 archived)

**Decizie:** _______________________

### 3. **Metadata arhivă**
- ✅ **RECOMANDAT:** DA (archivedAt + archiveReason)
- ⬜ NU (doar status ARCHIVED)

**Decizie:** _______________________

### 4. **SEO strategy**
- ✅ **RECOMANDAT:** noindex, follow
- ⬜ index, follow (indexează tot)

**Decizie:** _______________________

### 5. **Arhivare automată**
- ✅ **RECOMANDAT:** DA (cron lunar)
- ⬜ NU (doar manual)

**Decizie:** _______________________

### 6. **Search global**
- ⬜ Include archived în rezultate (cu badge)
- ✅ **RECOMANDAT:** Exclude archived (search dedicat în arhivă)

**Decizie:** _______________________

---

## 📝 Next Steps

### După aprobare:
1. ✅ Confirmă deciziile (completează secțiunea Decision Points)
2. ⬜ Începe Sprint 1: Backend Foundation
3. ⬜ Daily standup/progress tracking
4. ⬜ Code review după fiecare fază
5. ⬜ Deploy pe staging înainte de production
6. ⬜ Final deployment + monitoring

### Contact pentru întrebări:
- Technical lead: _______________________
- Product owner: _______________________
- Deadline target: _______________________

---

**Autor:** Claude Code
**Versiune:** 1.0
**Ultima actualizare:** 2025-11-09
**Status:** ⏳ **AȘTEPTARE APROBARE**

---

## 📎 Anexe

### A. Fișiere noi create
```
Backend (Symfony):
  src/Service/ArticleArchiveService.php
  src/Command/ArchiveOldArticlesCommand.php
  src/Controller/Api/ArchiveController.php
  src/Controller/Admin/ArticleArchiveController.php
  tests/Unit/Service/ArticleArchiveServiceTest.php
  tests/Functional/Controller/ArchiveControllerTest.php

Frontend (Next.js):
  app/[locale]/arhiva/page.tsx
  app/[locale]/admin/archive/page.tsx
  app/sitemap-archive.xml/route.ts
  components/archive/ArchiveBrowser.tsx
  components/archive/YearFilter.tsx
  components/archive/CategoryFilter.tsx
  components/admin/archive/BulkArchiveForm.tsx
  components/admin/archive/ArchiveStats.tsx
  components/admin/archive/ArchivedArticlesList.tsx
```

### B. Fișiere modificate
```
Backend:
  src/Enum/ArticleStatus.php (+ ARCHIVED case)
  src/Entity/Article.php (+ archivedAt, archiveReason)
  src/State/ArticleProvider.php (+ exclude archived)
  migrations/VersionXXXXXX.php (+ migration nouă)

Frontend:
  components/articles/ArticleCard.tsx (+ isArchived prop)
  components/layout/Footer.tsx (+ arhiva link)
  app/[locale]/articole/[slug]/page.tsx (+ robots meta)
  public/robots.txt (+ sitemap arhivă)
```

### C. Comenzi utile
```bash
# Backend
symfony console app:archive:old-articles --help
symfony console app:archive:old-articles --years=4 --dry-run
symfony console app:archive:old-articles --years=4

# Database queries
psql -U deschide_user -d deschide_news
SELECT status, COUNT(*) FROM articles GROUP BY status;
SELECT * FROM articles WHERE status='archived' LIMIT 10;

# API testing
curl http://127.0.0.1:8081/api/archive/articles
curl http://127.0.0.1:8081/api/archive/years
curl http://127.0.0.1:8081/api/archive/stats

# Frontend testing
curl http://localhost:3005/ro/arhiva
curl http://localhost:3005/sitemap-archive.xml
```

### D. Referințe documentație
- [archive-strategy.md](./archive-strategy.md) - Strategie completă originală
- [Symfony Enum Types](https://www.doctrine-project.org/projects/doctrine-orm/en/current/cookbook/mysql-enums.html)
- [Next.js Metadata API](https://nextjs.org/docs/app/api-reference/functions/generate-metadata)
- [API Platform State Providers](https://api-platform.com/docs/core/state-providers/)
- [PostgreSQL Date Functions](https://www.postgresql.org/docs/current/functions-datetime.html)
