# 📚 Strategie ARHIVĂ - Deschide News App

## 🎯 Obiectiv

Implementarea unui sistem complet de **arhivare articole** pentru platforma Deschide News, cu:
- Delimitare clară între articole active și arhivate
- Workflow automat de arhivare bazat pe vârstă
- Interface dedicată pentru browsing arhivă
- Optimizări performance pentru volume mari (150,000+ articole)
- Păstrarea integrității SEO și redirects

---

## 📊 ANALIZA SITUAȚIEI ACTUALE

### Volume Estimate După Import

| Categorie | Volume | Perioada | Status |
|-----------|--------|----------|--------|
| **Articole active (2023-2024)** | ~35,000 | Ultimele 2 ani | Prioritate înaltă |
| **Articole semi-recente (2021-2022)** | ~45,000 | 2-4 ani vechime | Prioritate medie |
| **Articole vechi (2016-2020)** | ~67,000 | 4-9 ani vechime | **Candidați arhivă** |
| **TOTAL** | **~147,000** | 2016-2024 | - |

### Problema Actuală

**Fără delimitare arhivă:**
- ❌ Listări încete (query peste 147k articole)
- ❌ Conținut vechi "îneacă" conținutul recent
- ❌ Confuzie utilizatori (articole din 2016 alături de cele din 2024)
- ❌ SEO diluat (prea multe URL-uri indexate)
- ❌ Costuri storage/cache pentru conținut rar accesat

**Cu sistem arhivă:**
- ✅ Query-uri rapide (doar ~35k articole active)
- ✅ Focus pe conținut recent
- ✅ Arhivă separată, browsable
- ✅ SEO optimizat (noindex pentru arhivă veche)
- ✅ Storage tiering posibil (arhivă → cold storage)

---

## 🏗️ ARHITECTURĂ SOLUȚIE

### Opțiunea 1: Status-Based Archive (RECOMANDAT) ⭐

**Concept:** Adăugăm status `ARCHIVED` în `ArticleStatus` enum.

**Avantaje:**
- ✅ Simplu de implementat (doar 1 enum value nou)
- ✅ Compatibil cu logica existentă
- ✅ Ușor de query-uit (`WHERE status != 'archived'`)
- ✅ Reversibil (de-arhivare simplă)
- ✅ Index-uri existente funcționează (idx_article_status)

**Dezavantaje:**
- ⚠️ Nu are metadata arhivare (când, de ce, de cine)

**Implementare:**

```php
// src/Enum/ArticleStatus.php
enum ArticleStatus: string
{
    case NEW = 'new';
    case SUBMITTED = 'submitted';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';  // NOU
}
```

**Migration:**
```sql
-- Migrația va modifica enum-ul automat
ALTER TABLE articles
    ALTER COLUMN status TYPE VARCHAR(20);
-- Doctrine va regenera constraint-ul enum
```

---

### Opțiunea 2: Flag `isArchived` (Simplă)

**Concept:** Adăugăm boolean `isArchived` pe `Article`.

**Avantaje:**
- ✅ Foarte simplu
- ✅ Compatibil cu status existent
- ✅ Index rapid (boolean)

**Dezavantaje:**
- ❌ Redundanță cu status
- ❌ Confuzie: articol poate fi `published` + `archived`?

**Implementare:**

```php
// src/Entity/Article.php
#[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
#[ORM\Index(name: 'idx_article_archived', columns: ['is_archived'])]
#[Groups(['article:read', 'article:write'])]
private bool $isArchived = false;
```

---

### Opțiunea 3: Separate Archive Table (Complex)

**Concept:** Mutăm articolele vechi în `archived_articles` table.

**Avantaje:**
- ✅ Performance maxim (articole active în tabelă mică)
- ✅ Storage tiering (archived_articles pe disk mai lent)

**Dezavantaje:**
- ❌ Foarte complex (migrare date, duplicare entities)
- ❌ Risc pierdere date
- ❌ Query-uri complicate (UNION pentru search cross-archive)
- ❌ **NU RECOMANDAT** pentru acest use case

---

## 🎨 DECIZIA: Opțiunea 1 (Status-Based Archive)

**Justificare:**
1. ✅ **Simplu și elegant** - doar 1 enum value nou
2. ✅ **Compatibil** cu codul existent
3. ✅ **Flexibil** - putem adăuga metadata mai târziu dacă e necesar
4. ✅ **Performance OK** - index-uri existente funcționează
5. ✅ **Reversibil** - ușor de de-arhivat

---

## 🛠️ PLAN DE IMPLEMENTARE

### FAZA 1: Backend - Database & Entity (2-3 ore)

#### Task 1.1: Update ArticleStatus Enum

**Fișier:** `src/Enum/ArticleStatus.php`

```php
<?php

declare(strict_types=1);

namespace App\Enum;

enum ArticleStatus: string
{
    case NEW = 'new';
    case SUBMITTED = 'submitted';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';  // NOU

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match($this) {
            self::NEW => 'Draft',
            self::SUBMITTED => 'Submitted',
            self::PUBLISHED => 'Published',
            self::ARCHIVED => 'Archived',
        };
    }

    /**
     * Get color for UI
     */
    public function color(): string
    {
        return match($this) {
            self::NEW => 'gray',
            self::SUBMITTED => 'yellow',
            self::PUBLISHED => 'green',
            self::ARCHIVED => 'blue',
        };
    }

    /**
     * Check if status is public-facing
     */
    public function isPublic(): bool
    {
        return $this === self::PUBLISHED || $this === self::ARCHIVED;
    }

    /**
     * Check if archived
     */
    public function isArchived(): bool
    {
        return $this === self::ARCHIVED;
    }
}
```

#### Task 1.2: Create Migration

**Comandă:**
```bash
symfony console make:migration
```

**Migration generată automat** (Doctrine detectează enum change):

```php
// migrations/Version20251109XXXXXX.php

public function up(Schema $schema): void
{
    // Doctrine va regenera constraint-ul enum cu noul value
    // Nu e nevoie de modificări manuale
}

public function down(Schema $schema): void
{
    // Verificare: nu există articole archived înainte de rollback
    $this->addSql('SELECT COUNT(*) FROM articles WHERE status = \'archived\'');
}
```

**Run migration:**
```bash
symfony console doctrine:migrations:migrate
```

#### Task 1.3: Add Archive-Specific Metadata (Optional)

**Fișier:** `src/Entity/Article.php`

```php
// OPȚIONAL: Dacă vrem metadata despre arhivare

#[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
#[Groups(['article:read'])]
private ?DateTimeImmutable $archivedAt = null;

#[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
#[Groups(['article:read'])]
private ?string $archiveReason = null;

public function getArchivedAt(): ?DateTimeImmutable
{
    return $this->archivedAt;
}

public function setArchivedAt(?DateTimeImmutable $archivedAt): self
{
    $this->archivedAt = $archivedAt;
    return $this;
}

public function getArchiveReason(): ?string
{
    return $this->archiveReason;
}

public function setArchiveReason(?string $archiveReason): self
{
    $this->archiveReason = $archiveReason;
    return $this;
}

/**
 * Archive this article
 */
public function archive(?string $reason = null): self
{
    $this->status = ArticleStatus::ARCHIVED;
    $this->archivedAt = new DateTimeImmutable();
    $this->archiveReason = $reason;
    return $this;
}

/**
 * Unarchive (restore) this article
 */
public function unarchive(): self
{
    $this->status = ArticleStatus::PUBLISHED;
    $this->archivedAt = null;
    $this->archiveReason = null;
    return $this;
}
```

**Migration pentru metadata:**
```sql
ALTER TABLE articles
    ADD COLUMN archived_at TIMESTAMP NULL,
    ADD COLUMN archive_reason VARCHAR(255) NULL;

CREATE INDEX idx_article_archived_at ON articles(archived_at);
```

---

### FAZA 2: Backend - Business Logic (3-4 ore)

#### Task 2.1: Archive Service

**Fișier:** `src/Service/ArticleArchiveService.php`

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Article;
use App\Enum\ArticleStatus;
use App\Repository\ArticleRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class ArticleArchiveService
{
    public function __construct(
        private readonly ArticleRepository $articleRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Archive a single article
     */
    public function archiveArticle(Article $article, ?string $reason = null): void
    {
        if ($article->getStatus() === ArticleStatus::ARCHIVED) {
            $this->logger->warning('Article already archived', ['article_id' => $article->getId()]);
            return;
        }

        $article->archive($reason);
        $this->entityManager->flush();

        $this->logger->info('Article archived', [
            'article_id' => $article->getId(),
            'title' => $article->getTitle(),
            'reason' => $reason,
        ]);
    }

    /**
     * Unarchive (restore) a single article
     */
    public function unarchiveArticle(Article $article): void
    {
        if ($article->getStatus() !== ArticleStatus::ARCHIVED) {
            $this->logger->warning('Article not archived', ['article_id' => $article->getId()]);
            return;
        }

        $article->unarchive();
        $this->entityManager->flush();

        $this->logger->info('Article unarchived', [
            'article_id' => $article->getId(),
            'title' => $article->getTitle(),
        ]);
    }

    /**
     * Archive articles older than X years
     *
     * @param int $yearsOld Articles older than this will be archived
     * @param int $batchSize Number of articles to process per batch
     * @return int Number of articles archived
     */
    public function archiveOldArticles(int $yearsOld = 4, int $batchSize = 100): int
    {
        $cutoffDate = (new DateTimeImmutable())->modify("-{$yearsOld} years");

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

            // Flush every 50 articles to avoid memory issues
            if ($count % 50 === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->logger->info('Batch archive completed', [
            'articles_archived' => $count,
            'cutoff_date' => $cutoffDate->format('Y-m-d'),
        ]);

        return $count;
    }

    /**
     * Get archive statistics
     */
    public function getArchiveStats(): array
    {
        $conn = $this->entityManager->getConnection();

        // Total by status
        $statusStats = $conn->fetchAllAssociative(
            "SELECT status, COUNT(*) as count
             FROM articles
             GROUP BY status"
        );

        // Archived by year
        $archivedByYear = $conn->fetchAllAssociative(
            "SELECT EXTRACT(YEAR FROM published_at) as year, COUNT(*) as count
             FROM articles
             WHERE status = 'archived'
             GROUP BY year
             ORDER BY year DESC"
        );

        // Oldest/Newest archived
        $oldestArchived = $conn->fetchAssociative(
            "SELECT MIN(published_at) as oldest
             FROM articles
             WHERE status = 'archived'"
        );

        return [
            'by_status' => $statusStats,
            'archived_by_year' => $archivedByYear,
            'oldest_archived' => $oldestArchived['oldest'] ?? null,
            'total_archived' => $this->articleRepository->count(['status' => ArticleStatus::ARCHIVED]),
            'total_published' => $this->articleRepository->count(['status' => ArticleStatus::PUBLISHED]),
        ];
    }
}
```

#### Task 2.2: Console Command pentru Arhivare Automată

**Fișier:** `src/Command/ArchiveOldArticlesCommand.php`

```php
<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ArticleArchiveService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:archive:old-articles',
    description: 'Archive articles older than X years'
)]
class ArchiveOldArticlesCommand extends Command
{
    public function __construct(
        private readonly ArticleArchiveService $archiveService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('years', 'y', InputOption::VALUE_OPTIONAL, 'Archive articles older than X years', 4)
            ->addOption('batch-size', 'b', InputOption::VALUE_OPTIONAL, 'Batch size', 100)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Dry run (no actual archiving)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $yearsOld = (int) $input->getOption('years');
        $batchSize = (int) $input->getOption('batch-size');
        $dryRun = $input->getOption('dry-run');

        $io->title('Archive Old Articles');

        if ($dryRun) {
            $io->warning('DRY RUN MODE - No articles will be archived');
        }

        $io->section('Configuration');
        $io->definitionList(
            ['Years old' => $yearsOld],
            ['Batch size' => $batchSize],
            ['Mode' => $dryRun ? 'DRY RUN' : 'LIVE']
        );

        if (!$dryRun && !$io->confirm('Do you want to proceed with archiving?', false)) {
            $io->info('Operation cancelled.');
            return Command::SUCCESS;
        }

        $io->section('Archiving...');

        if ($dryRun) {
            // TODO: Implement dry-run logic (count only)
            $io->info('Dry run completed. Would have archived X articles.');
        } else {
            $totalArchived = 0;
            $io->progressStart();

            do {
                $archivedCount = $this->archiveService->archiveOldArticles($yearsOld, $batchSize);
                $totalArchived += $archivedCount;
                $io->progressAdvance($archivedCount);

                // Continue until no more articles to archive
            } while ($archivedCount > 0);

            $io->progressFinish();

            $io->success("Archived {$totalArchived} articles older than {$yearsOld} years.");
        }

        // Show stats
        $io->section('Archive Statistics');
        $stats = $this->archiveService->getArchiveStats();

        $io->table(
            ['Status', 'Count'],
            array_map(fn($row) => [$row['status'], $row['count']], $stats['by_status'])
        );

        return Command::SUCCESS;
    }
}
```

**Programare automată** (cron job):

```yaml
# config/packages/scheduler.yaml (Symfony Scheduler)
framework:
    scheduler:
        default_transport: doctrine
        transports:
            doctrine:
                dsn: 'doctrine://default'

# Schedule pentru arhivare automată (rulează lunar)
when@prod:
    framework:
        scheduler:
            schedules:
                archive_old_articles:
                    task: 'app:archive:old-articles --years=4'
                    frequency: 'first monday of month at 02:00'
```

**SAU cu cron tradițional:**
```bash
# /etc/cron.d/deschide-archive
# Rulează în prima lună a lunii la 2:00 AM
0 2 1 * * www-data cd /var/www/deschide_news_app/deschide_backend && symfony console app:archive:old-articles --years=4 >> /var/log/archive.log 2>&1
```

---

### FAZA 3: Backend - API Endpoints (2-3 ore)

#### Task 3.1: Update ArticleProvider pentru Filtrare Arhivă

**Fișier:** `src/State/ArticleProvider.php`

```php
// În ArticleProvider::provide() method

// Default: exclude archived articles din listări normale
if ($operation instanceof GetCollection) {
    // Check dacă e request pentru arhivă
    $context = $operation->getContext();
    $includeArchived = $context['filters']['include_archived'] ?? false;

    if (!$includeArchived) {
        // Exclude archived by default
        $queryBuilder->andWhere('a.status != :archived')
            ->setParameter('archived', ArticleStatus::ARCHIVED);
    }
}
```

#### Task 3.2: Endpoint dedicat pentru Arhivă

**Fișier:** `src/Controller/Api/ArchiveController.php`

```php
<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Article;
use App\Enum\ArticleStatus;
use App\Repository\ArticleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route('/api/archive', name: 'api_archive_')]
class ArchiveController extends AbstractController
{
    public function __construct(
        private readonly ArticleRepository $articleRepository,
    ) {
    }

    /**
     * GET /api/archive/articles
     *
     * Lista articole arhivate (paginată)
     */
    #[Route('/articles', name: 'articles', methods: ['GET'])]
    public function getArchivedArticles(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $itemsPerPage = min(50, max(1, (int) $request->query->get('itemsPerPage', 20)));
        $year = $request->query->get('year');
        $category = $request->query->get('category');

        $qb = $this->articleRepository->createQueryBuilder('a')
            ->where('a.status = :archived')
            ->setParameter('archived', ArticleStatus::ARCHIVED)
            ->orderBy('a.publishedAt', 'DESC')
            ->setFirstResult(($page - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage);

        // Filter by year
        if ($year) {
            $qb->andWhere('EXTRACT(YEAR FROM a.publishedAt) = :year')
                ->setParameter('year', $year);
        }

        // Filter by category
        if ($category) {
            $qb->andWhere('a.category = :category')
                ->setParameter('category', $category);
        }

        $articles = $qb->getQuery()->getResult();

        // Get total count
        $totalQb = clone $qb;
        $totalQb->select('COUNT(a.id)');
        $total = $totalQb->getQuery()->getSingleScalarResult();

        return $this->json([
            'items' => $articles,
            'total' => $total,
            'page' => $page,
            'itemsPerPage' => $itemsPerPage,
            'totalPages' => (int) ceil($total / $itemsPerPage),
        ], 200, [], ['groups' => ['article:read', 'article:list']]);
    }

    /**
     * GET /api/archive/years
     *
     * Lista ani disponibili în arhivă
     */
    #[Route('/years', name: 'years', methods: ['GET'])]
    public function getArchiveYears(): JsonResponse
    {
        $conn = $this->articleRepository->getEntityManager()->getConnection();

        $years = $conn->fetchAllAssociative(
            "SELECT
                EXTRACT(YEAR FROM published_at) as year,
                COUNT(*) as count
             FROM articles
             WHERE status = 'archived'
             GROUP BY year
             ORDER BY year DESC"
        );

        return $this->json($years);
    }

    /**
     * GET /api/archive/stats
     *
     * Statistici arhivă
     */
    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function getArchiveStats(): JsonResponse
    {
        $conn = $this->articleRepository->getEntityManager()->getConnection();

        // By year
        $byYear = $conn->fetchAllAssociative(
            "SELECT
                EXTRACT(YEAR FROM published_at) as year,
                COUNT(*) as count
             FROM articles
             WHERE status = 'archived'
             GROUP BY year
             ORDER BY year DESC"
        );

        // By category
        $byCategory = $conn->fetchAllAssociative(
            "SELECT
                c.name as category,
                COUNT(a.id) as count
             FROM articles a
             INNER JOIN categories c ON a.category_id = c.id
             WHERE a.status = 'archived'
             GROUP BY c.id, c.name
             ORDER BY count DESC
             LIMIT 10"
        );

        // Total
        $total = $this->articleRepository->count(['status' => ArticleStatus::ARCHIVED]);

        return $this->json([
            'total' => $total,
            'by_year' => $byYear,
            'by_category' => $byCategory,
        ]);
    }
}
```

#### Task 3.3: API Endpoint pentru Admin - Archive/Unarchive

**Fișier:** `src/Controller/Admin/ArticleArchiveController.php`

```php
<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Article;
use App\Service\ArticleArchiveService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[Route('/api/admin/articles', name: 'api_admin_article_')]
#[IsGranted('ROLE_ADMIN')]
class ArticleArchiveController extends AbstractController
{
    public function __construct(
        private readonly ArticleArchiveService $archiveService,
    ) {
    }

    /**
     * POST /api/admin/articles/{id}/archive
     *
     * Archive a specific article
     */
    #[Route('/{id}/archive', name: 'archive', methods: ['POST'])]
    public function archive(Article $article, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $reason = $data['reason'] ?? null;

        $this->archiveService->archiveArticle($article, $reason);

        return $this->json([
            'message' => 'Article archived successfully',
            'article' => $article,
        ], 200, [], ['groups' => ['article:read']]);
    }

    /**
     * POST /api/admin/articles/{id}/unarchive
     *
     * Unarchive (restore) a specific article
     */
    #[Route('/{id}/unarchive', name: 'unarchive', methods: ['POST'])]
    public function unarchive(Article $article): JsonResponse
    {
        $this->archiveService->unarchiveArticle($article);

        return $this->json([
            'message' => 'Article unarchived successfully',
            'article' => $article,
        ], 200, [], ['groups' => ['article:read']]);
    }

    /**
     * POST /api/admin/articles/archive-old
     *
     * Trigger bulk archive of old articles
     */
    #[Route('/archive-old', name: 'archive_old', methods: ['POST'])]
    public function archiveOld(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $yearsOld = $data['years_old'] ?? 4;
        $batchSize = $data['batch_size'] ?? 100;

        $totalArchived = 0;
        do {
            $archivedCount = $this->archiveService->archiveOldArticles($yearsOld, $batchSize);
            $totalArchived += $archivedCount;
        } while ($archivedCount > 0);

        return $this->json([
            'message' => 'Bulk archive completed',
            'total_archived' => $totalArchived,
        ]);
    }
}
```

---

### FAZA 4: Frontend - UI/UX (4-5 ore)

#### Task 4.1: Archive Page Route

**Fișier:** `deschide_frontend/app/[locale]/arhiva/page.tsx`

```tsx
import { Metadata } from 'next';
import ArchiveBrowser from '@/components/archive/ArchiveBrowser';

export const metadata: Metadata = {
  title: 'Arhiva - Deschide.md',
  description: 'Arhiva articolelor publicate între 2016-2020',
  robots: {
    index: false, // Nu indexa arhiva veche în Google
    follow: true,
  },
};

export default function ArchivePage() {
  return (
    <div className="container mx-auto px-4 py-8">
      <h1 className="text-4xl font-bold mb-8">Arhiva Deschide.md</h1>

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

#### Task 4.2: Archive Browser Component

**Fișier:** `deschide_frontend/components/archive/ArchiveBrowser.tsx`

```tsx
'use client';

import { useState, useEffect } from 'react';
import { useSearchParams } from 'next/navigation';
import ArticleCard from '@/components/articles/ArticleCard';
import Pagination from '@/components/common/Pagination';
import YearFilter from './YearFilter';
import CategoryFilter from './CategoryFilter';

interface ArchiveBrowserProps {
  initialYear?: number;
  initialCategory?: string;
}

export default function ArchiveBrowser({
  initialYear,
  initialCategory
}: ArchiveBrowserProps) {
  const searchParams = useSearchParams();
  const [articles, setArticles] = useState([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [selectedYear, setSelectedYear] = useState<number | null>(initialYear || null);
  const [selectedCategory, setSelectedCategory] = useState<string | null>(initialCategory || null);

  useEffect(() => {
    fetchArchiveArticles();
  }, [page, selectedYear, selectedCategory]);

  const fetchArchiveArticles = async () => {
    setLoading(true);

    const params = new URLSearchParams({
      page: page.toString(),
      itemsPerPage: '20',
    });

    if (selectedYear) {
      params.append('year', selectedYear.toString());
    }

    if (selectedCategory) {
      params.append('category', selectedCategory);
    }

    try {
      const response = await fetch(
        `${process.env.NEXT_PUBLIC_API_URL}/api/archive/articles?${params}`
      );
      const data = await response.json();

      setArticles(data.items);
      setTotalPages(data.totalPages);
    } catch (error) {
      console.error('Failed to fetch archive articles:', error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="grid grid-cols-1 lg:grid-cols-4 gap-8">
      {/* Sidebar Filters */}
      <aside className="lg:col-span-1">
        <div className="sticky top-4 space-y-6">
          <YearFilter
            selectedYear={selectedYear}
            onYearChange={setSelectedYear}
          />

          <CategoryFilter
            selectedCategory={selectedCategory}
            onCategoryChange={setSelectedCategory}
          />
        </div>
      </aside>

      {/* Articles Grid */}
      <main className="lg:col-span-3">
        {loading ? (
          <div className="flex justify-center items-center h-64">
            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600" />
          </div>
        ) : articles.length === 0 ? (
          <div className="text-center py-12">
            <p className="text-gray-500 text-lg">
              Nu au fost găsite articole pentru filtrele selectate.
            </p>
          </div>
        ) : (
          <>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
              {articles.map((article) => (
                <ArticleCard
                  key={article.id}
                  article={article}
                  isArchived={true}
                />
              ))}
            </div>

            <Pagination
              currentPage={page}
              totalPages={totalPages}
              onPageChange={setPage}
            />
          </>
        )}
      </main>
    </div>
  );
}
```

#### Task 4.3: Year Filter Component

**Fișier:** `deschide_frontend/components/archive/YearFilter.tsx`

```tsx
'use client';

import { useState, useEffect } from 'react';

interface YearFilterProps {
  selectedYear: number | null;
  onYearChange: (year: number | null) => void;
}

export default function YearFilter({ selectedYear, onYearChange }: YearFilterProps) {
  const [years, setYears] = useState<Array<{ year: number; count: number }>>([]);

  useEffect(() => {
    fetchYears();
  }, []);

  const fetchYears = async () => {
    try {
      const response = await fetch(
        `${process.env.NEXT_PUBLIC_API_URL}/api/archive/years`
      );
      const data = await response.json();
      setYears(data);
    } catch (error) {
      console.error('Failed to fetch archive years:', error);
    }
  };

  return (
    <div className="bg-white rounded-lg shadow p-4">
      <h3 className="font-bold text-lg mb-4">Filtrare după an</h3>

      <div className="space-y-2">
        <button
          onClick={() => onYearChange(null)}
          className={`w-full text-left px-3 py-2 rounded transition-colors ${
            selectedYear === null
              ? 'bg-blue-100 text-blue-900 font-medium'
              : 'hover:bg-gray-100'
          }`}
        >
          Toate perioadele
        </button>

        {years.map(({ year, count }) => (
          <button
            key={year}
            onClick={() => onYearChange(year)}
            className={`w-full text-left px-3 py-2 rounded transition-colors flex justify-between items-center ${
              selectedYear === year
                ? 'bg-blue-100 text-blue-900 font-medium'
                : 'hover:bg-gray-100'
            }`}
          >
            <span>{year}</span>
            <span className="text-sm text-gray-500">({count})</span>
          </button>
        ))}
      </div>
    </div>
  );
}
```

#### Task 4.4: Archive Badge în ArticleCard

**Fișier:** `deschide_frontend/components/articles/ArticleCard.tsx`

```tsx
// Modificare ArticleCard pentru a afișa badge "Arhivat"

interface ArticleCardProps {
  article: Article;
  isArchived?: boolean; // NOU
}

export default function ArticleCard({ article, isArchived = false }: ArticleCardProps) {
  return (
    <article className="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
      {/* ... existing code ... */}

      {/* Archive Badge */}
      {isArchived && (
        <div className="absolute top-2 right-2">
          <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
            <svg className="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
              <path d="M4 3a2 2 0 100 4h12a2 2 0 100-4H4z" />
              <path fillRule="evenodd" d="M3 8h14v7a2 2 0 01-2 2H5a2 2 0 01-2-2V8zm5 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z" clipRule="evenodd" />
            </svg>
            Arhivat
          </span>
        </div>
      )}

      {/* ... rest of card ... */}
    </article>
  );
}
```

#### Task 4.5: Link către Arhivă în Footer/Navigation

**Fișier:** `deschide_frontend/components/layout/Footer.tsx`

```tsx
export default function Footer() {
  return (
    <footer className="bg-gray-900 text-white">
      <div className="container mx-auto px-4 py-12">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-8">
          {/* ... existing footer columns ... */}

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
        </div>
      </div>
    </footer>
  );
}
```

---

### FAZA 5: Admin Panel - Archive Management (3-4 ore)

#### Task 5.1: Archive Management Page

**Fișier:** `deschide_frontend/app/[locale]/admin/archive/page.tsx`

```tsx
'use client';

import { useState } from 'react';
import ArchiveStats from '@/components/admin/archive/ArchiveStats';
import BulkArchiveForm from '@/components/admin/archive/BulkArchiveForm';
import ArchivedArticlesList from '@/components/admin/archive/ArchivedArticlesList';

export default function ArchiveManagementPage() {
  const [refreshKey, setRefreshKey] = useState(0);

  const handleArchiveComplete = () => {
    // Refresh stats și lista
    setRefreshKey((prev) => prev + 1);
  };

  return (
    <div className="container mx-auto px-4 py-8">
      <h1 className="text-3xl font-bold mb-8">Gestionare Arhivă</h1>

      {/* Statistics */}
      <div className="mb-8">
        <ArchiveStats key={`stats-${refreshKey}`} />
      </div>

      {/* Bulk Archive Form */}
      <div className="mb-8">
        <BulkArchiveForm onComplete={handleArchiveComplete} />
      </div>

      {/* Archived Articles List */}
      <div>
        <ArchivedArticlesList key={`list-${refreshKey}`} />
      </div>
    </div>
  );
}
```

#### Task 5.2: Bulk Archive Form Component

**Fișier:** `deschide_frontend/components/admin/archive/BulkArchiveForm.tsx`

```tsx
'use client';

import { useState } from 'react';

interface BulkArchiveFormProps {
  onComplete: () => void;
}

export default function BulkArchiveForm({ onComplete }: BulkArchiveFormProps) {
  const [yearsOld, setYearsOld] = useState(4);
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);

    try {
      const response = await fetch(
        `${process.env.NEXT_PUBLIC_API_URL}/api/admin/articles/archive-old`,
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${getAuthToken()}`, // Helper function
          },
          body: JSON.stringify({
            years_old: yearsOld,
            batch_size: 100,
          }),
        }
      );

      const data = await response.json();

      alert(`Arhivare completă! ${data.total_archived} articole arhivate.`);
      onComplete();
    } catch (error) {
      console.error('Bulk archive failed:', error);
      alert('Eroare la arhivare. Verificați consola.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="bg-white rounded-lg shadow p-6">
      <h2 className="text-xl font-bold mb-4">Arhivare în Masă</h2>

      <form onSubmit={handleSubmit} className="space-y-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Arhivează articole mai vechi de:
          </label>
          <div className="flex items-center space-x-4">
            <input
              type="number"
              min="1"
              max="10"
              value={yearsOld}
              onChange={(e) => setYearsOld(parseInt(e.target.value))}
              className="w-20 px-3 py-2 border border-gray-300 rounded-md"
            />
            <span className="text-gray-700">ani</span>
          </div>
          <p className="text-sm text-gray-500 mt-1">
            Vor fi arhivate articole publicate înainte de{' '}
            {new Date().getFullYear() - yearsOld}
          </p>
        </div>

        <button
          type="submit"
          disabled={loading}
          className="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          {loading ? 'Arhivare în curs...' : 'Arhivează Articole Vechi'}
        </button>
      </form>
    </div>
  );
}
```

---

### FAZA 6: SEO & Performance (2-3 ore)

#### Task 6.1: Robots Meta Tag pentru Arhivă

**Configurare:** Articolele arhivate să aibă `noindex` pentru a nu dilua SEO.

```tsx
// În ArticleDetail page pentru articole archived
export async function generateMetadata({ params }): Promise<Metadata> {
  const article = await fetchArticle(params.slug);

  if (article.status === 'archived') {
    return {
      title: `${article.title} - Arhivat`,
      robots: {
        index: false,  // NU indexa în Google
        follow: true,  // DAR urmărește link-urile
      },
    };
  }

  // ... normal metadata pentru articole published
}
```

#### Task 6.2: Sitemap Separat pentru Arhivă

**Fișier:** `deschide_frontend/app/sitemap-archive.xml/route.ts`

```typescript
import { NextResponse } from 'next/server';

export async function GET() {
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
    },
  });
}
```

**Referință în robots.txt:**
```txt
# /public/robots.txt
User-agent: *
Allow: /

# Sitemap-uri
Sitemap: https://deschide.md/sitemap.xml
Sitemap: https://deschide.md/sitemap-archive.xml
```

#### Task 6.3: Cache Strategy pentru Arhivă

**Configurare Next.js:**

```typescript
// În ArticlePage pentru articole archived
export const revalidate = 604800; // 1 săptămână (7 zile)
// Arhiva se schimbă rar, cache agresiv
```

**Configurare API Platform:**

```php
// În ArchiveController
#[Route('/articles', name: 'articles', methods: ['GET'])]
public function getArchivedArticles(Request $request): JsonResponse
{
    // ... existing code ...

    return $this->json($data, 200, [
        'Cache-Control' => 'public, max-age=86400, s-maxage=604800', // 1 zi client, 1 săptămână CDN
        'Vary' => 'Accept-Language',
    ], ['groups' => ['article:read']]);
}
```

---

### FAZA 7: Testing & Quality Assurance (2-3 ore)

#### Task 7.1: Unit Tests pentru ArticleArchiveService

**Fișier:** `deschide_backend/tests/Unit/Service/ArticleArchiveServiceTest.php`

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Article;
use App\Enum\ArticleStatus;
use App\Repository\ArticleRepository;
use App\Service\ArticleArchiveService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ArticleArchiveServiceTest extends TestCase
{
    private ArticleArchiveService $service;
    private ArticleRepository $repository;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ArticleRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new ArticleArchiveService(
            $this->repository,
            $this->entityManager,
            $this->logger
        );
    }

    public function testArchiveArticle(): void
    {
        $article = new Article();
        $article->setStatus(ArticleStatus::PUBLISHED);
        $article->setTitle('Test Article');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->service->archiveArticle($article, 'Test reason');

        $this->assertSame(ArticleStatus::ARCHIVED, $article->getStatus());
        $this->assertNotNull($article->getArchivedAt());
        $this->assertSame('Test reason', $article->getArchiveReason());
    }

    public function testArchiveArticleAlreadyArchived(): void
    {
        $article = new Article();
        $article->setStatus(ArticleStatus::ARCHIVED);

        $this->entityManager->expects($this->never())
            ->method('flush');

        $this->logger->expects($this->once())
            ->method('warning');

        $this->service->archiveArticle($article);
    }

    public function testUnarchiveArticle(): void
    {
        $article = new Article();
        $article->setStatus(ArticleStatus::ARCHIVED);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->service->unarchiveArticle($article);

        $this->assertSame(ArticleStatus::PUBLISHED, $article->getStatus());
        $this->assertNull($article->getArchivedAt());
        $this->assertNull($article->getArchiveReason());
    }
}
```

#### Task 7.2: Integration Test pentru Archive API

**Fișier:** `deschide_backend/tests/Functional/Controller/ArchiveControllerTest.php`

```php
<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Article;
use App\Enum\ArticleStatus;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ArchiveControllerTest extends WebTestCase
{
    public function testGetArchivedArticles(): void
    {
        $client = static::createClient();

        // Create test archived article
        // ... (use fixtures or factory)

        $client->request('GET', '/api/archive/articles');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('page', $data);
    }

    public function testGetArchiveYears(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/archive/years');

        $this->assertResponseIsSuccessful();

        $years = json_decode($client->getResponse()->getContent(), true);

        $this->assertIsArray($years);
        $this->assertNotEmpty($years);
        $this->assertArrayHasKey('year', $years[0]);
        $this->assertArrayHasKey('count', $years[0]);
    }
}
```

#### Task 7.3: Manual Testing Checklist

**Checklist testare manuală:**

- [ ] **Backend:**
  - [ ] Comandă `app:archive:old-articles` funcționează
  - [ ] Articolele se arhivează corect (status ARCHIVED)
  - [ ] Metadata `archivedAt` se setează corect
  - [ ] API `/api/archive/articles` returnează doar articole archived
  - [ ] API `/api/archive/years` returnează ani corecți
  - [ ] Admin API `/api/admin/articles/{id}/archive` funcționează
  - [ ] Admin API `/api/admin/articles/{id}/unarchive` funcționează

- [ ] **Frontend:**
  - [ ] Pagina `/arhiva` se încarcă corect
  - [ ] Filtrare după an funcționează
  - [ ] Filtrare după categorie funcționează
  - [ ] Paginare funcționează
  - [ ] Badge "Arhivat" apare pe articole
  - [ ] Link către arhivă în footer funcționează

- [ ] **Admin Panel:**
  - [ ] Pagina admin archive management se încarcă
  - [ ] Form bulk archive funcționează
  - [ ] Statistici arhivă sunt corecte
  - [ ] Lista articole arhivate se afișează

- [ ] **SEO:**
  - [ ] Articole archived au `robots: noindex`
  - [ ] Sitemap arhivă se generează
  - [ ] Cache headers sunt setate corect

---

## 📊 TIMELINE IMPLEMENTARE

### Sprint 1: Backend Foundation (1 săptămână)

| Zi | Fază | Task-uri | Ore |
|----|------|----------|-----|
| **Ziua 1** | FAZA 1 | Update ArticleStatus enum, migration, metadata | 2-3h |
| **Ziua 2** | FAZA 2 | ArticleArchiveService, console command | 3-4h |
| **Ziua 3** | FAZA 2 | Scheduler setup, testing service | 2h |
| **Ziua 4** | FAZA 3 | Update ArticleProvider, ArchiveController | 2-3h |
| **Ziua 5** | FAZA 3 | Admin API endpoints, testing | 2h |

**Total Sprint 1:** ~11-14 ore

### Sprint 2: Frontend & Admin (1 săptămână)

| Zi | Fază | Task-uri | Ore |
|----|------|----------|-----|
| **Ziua 1** | FAZA 4 | Archive page, ArchiveBrowser component | 3-4h |
| **Ziua 2** | FAZA 4 | YearFilter, CategoryFilter, ArticleCard update | 2-3h |
| **Ziua 3** | FAZA 4 | Footer/navigation links, styling | 1h |
| **Ziua 4** | FAZA 5 | Admin archive management page | 2-3h |
| **Ziua 5** | FAZA 5 | BulkArchiveForm, stats components | 2h |

**Total Sprint 2:** ~10-13 ore

### Sprint 3: SEO, Testing & Polish (3-4 zile)

| Zi | Fază | Task-uri | Ore |
|----|------|----------|-----|
| **Ziua 1** | FAZA 6 | Robots meta, sitemap archive, cache strategy | 2-3h |
| **Ziua 2** | FAZA 7 | Unit tests, integration tests | 2-3h |
| **Ziua 3** | FAZA 7 | Manual testing, bug fixes | 2h |
| **Ziua 4** | - | Documentation, deployment | 1-2h |

**Total Sprint 3:** ~7-10 ore

**TOTAL IMPLEMENTARE: ~28-37 ore (3-4 săptămâni part-time)**

---

## 🚀 DEPLOYMENT PLAN

### Pas 1: Backup înainte de deployment

```bash
# Backup database
pg_dump -h localhost -U deschide_admin deschide_news > backup_pre_archive_$(date +%Y%m%d).sql

# Backup git (create branch)
git checkout -b feature/archive-system
git add .
git commit -m "feat: implement archive system"
```

### Pas 2: Deploy backend

```bash
cd /var/www/deschide_news_app/deschide_backend

# Pull latest code
git pull origin feature/archive-system

# Run migrations
symfony console doctrine:migrations:migrate --no-interaction

# Clear cache
symfony console cache:clear
```

### Pas 3: Testare backend

```bash
# Test comandă arhivare (dry-run)
symfony console app:archive:old-articles --years=4 --dry-run

# Test API arhivă
curl http://127.0.0.1:8081/api/archive/years
curl http://127.0.0.1:8081/api/archive/articles?page=1
```

### Pas 4: Deploy frontend

```bash
cd /var/www/deschide_news_app/deschide_frontend

# Pull latest code
git pull origin feature/archive-system

# Install dependencies
pnpm install

# Build production
pnpm build

# Restart PM2 (dacă folosești)
pm2 restart deschide_frontend
```

### Pas 5: Arhivare inițială (IMPORTANT!)

**RUN DOAR O DATĂ după deployment:**

```bash
# Arhivează articole vechi (4+ ani)
symfony console app:archive:old-articles --years=4

# Output așteptat:
# Archived 67,000 articles older than 4 years.
```

### Pas 6: Setup cron pentru arhivare automată

```bash
# Editează crontab
sudo crontab -e

# Adaugă job (rulează lunar)
0 2 1 * * cd /var/www/deschide_news_app/deschide_backend && symfony console app:archive:old-articles --years=4 >> /var/log/deschide_archive.log 2>&1
```

### Pas 7: Monitoring post-deployment

**Verificări:**

```sql
-- Check status distribution
SELECT status, COUNT(*) as count
FROM articles
GROUP BY status;

-- Expected output:
-- status    | count
-- ----------+--------
-- published | ~35,000
-- archived  | ~67,000
-- new       | ~100
-- submitted | ~50

-- Check archived by year
SELECT EXTRACT(YEAR FROM published_at) as year, COUNT(*)
FROM articles
WHERE status = 'archived'
GROUP BY year
ORDER BY year DESC;
```

**Verificare frontend:**
- Vizitează `/arhiva` - ar trebui să afișeze articole
- Verifică filtrare ani
- Verifică că homepage NU afișează articole archived

---

## ❓ ÎNTREBĂRI PENTRU DECIZIE

### 1. Când arhivăm articolele? (Vârstă threshold)

- 🅰️ **3 ani** (2016-2021 archived, 2022-2024 active)
  - ~80,000 archived, ~67,000 active

- 🅱️ **4 ani** (2016-2020 archived, 2021-2024 active) **← RECOMANDAT**
  - ~67,000 archived, ~80,000 active

- 🅲️ **5 ani** (2016-2019 archived, 2020-2024 active)
  - ~53,000 archived, ~94,000 active

### 2. Metadata suplimentară pentru arhivă?

- ✅ **DA** - Adaugă `archivedAt` + `archiveReason` **← RECOMANDAT**
  - Tracking complet, audit trail
  - +2 coloane în DB

- ⛔ **NU** - Doar status ARCHIVED
  - Mai simplu
  - Fără metadata

### 3. SEO pentru articole archived?

- 🅰️ **noindex, follow** (NU indexa, dar urmărește link-uri) **← RECOMANDAT**
  - Google nu indexează arhiva veche
  - Păstrează link juice

- 🅱️ **index, follow** (Indexează totul)
  - 147,000 URL-uri indexate
  - Risc dilutare SEO

### 4. Arhivare automată (cron)?

- ✅ **DA** - Monthly cron job **← RECOMANDAT**
  - Rulează automat în fiecare lună
  - Zero manual work

- ⛔ **NU** - Manual only
  - Admin decide când arhivează
  - Mai mult control

### 5. Afișare articole archived în search global?

- 🅰️ **DA** - Include în rezultate search (cu badge "Archived")
  - Utilizatori găsesc tot conținutul

- 🅱️ **NU** - Search doar în articole active **← RECOMANDAT**
  - Focus pe conținut recent
  - Arhiva are search dedicat

---

## 📝 NEXT STEPS

După confirmarea deciziilor, voi crea:

1. ✅ **Strategie completă arhivă** - COMPLETAT (acest document)
2. ⬜ **Migrații Doctrine** (ArticleStatus enum + metadata)
3. ⬜ **ArticleArchiveService** complet
4. ⬜ **Console Command** pentru arhivare
5. ⬜ **API Endpoints** (Archive + Admin)
6. ⬜ **Frontend Components** (ArchiveBrowser, filters, etc.)
7. ⬜ **Tests** (Unit + Integration + E2E)

**Aștept confirmarea deciziilor pentru a începe implementarea!** 🚀

---

**Autor:** Claude Code
**Data:** 2025-11-09
**Status:** ⏳ Așteptăm decizie - Strategie Arhivă
**Volume estimate:** 147,000 articole (67,000 archived, 80,000 active)
**Timp implementare:** 28-37 ore (3-4 săptămâni part-time)

