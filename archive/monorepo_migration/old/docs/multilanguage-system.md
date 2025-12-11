# Multilanguage System - Gedmo Translatable

Sistemul multilanguage pentru aplicația Deschide News cu **Gedmo Translatable** în backend și header `Accept-Language` pentru routing.

## Limbile Suportate

```php
- ro (Română) - Limba implicită/default
- en (Engleză)
- ru (Rusă)
```

**Limba default**: `ro` (Română)

## Principii Fundamentale

1. **Backend (Symfony)**: Folosește Gedmo Translatable pentru entități
2. **Frontend Request**: Header `Accept-Language` specifică limba dorită
3. **Content Filtering**: Doar conținutul tradus în limba cerută este returnat
4. **Strict Mode**: Dacă traducerea nu există → conținutul NU apare în listă
5. **Fallback la limba default**: Opțional, configurabil per endpoint

## Arhitectură

```
┌──────────────────┐
│   Client         │
│   (Frontend)     │
└────────┬─────────┘
         │ GET /api/public/articles
         │ Accept-Language: en
         ▼
┌──────────────────────────────────────┐
│   LocaleListener                     │
│   (reads Accept-Language header)     │
└────────┬─────────────────────────────┘
         │ Set TranslatableListener locale
         ▼
┌──────────────────────────────────────┐
│   Doctrine Query                     │
│   (with translatable hints)          │
└────────┬─────────────────────────────┘
         │ Filter: only entities with
         │ translation in requested locale
         ▼
┌──────────────────────────────────────┐
│   JSON Response                      │
│   (only articles with EN translation)│
└──────────────────────────────────────┘
```

## Backend Setup (Symfony)

### 1. Install Gedmo Doctrine Extensions

```bash
composer require stof/doctrine-extensions-bundle
```

### 2. Configuration

**config/packages/stof_doctrine_extensions.yaml**
```yaml
stof_doctrine_extensions:
    default_locale: ro
    translation_fallback: false  # IMPORTANT: No fallback, strict mode
    orm:
        default:
            translatable: true
            timestampable: true
            sluggable: true
```

**config/packages/doctrine.yaml**
```yaml
doctrine:
    dbal:
        url: '%env(resolve:DATABASE_URL)%'
        charset: utf8mb4
        default_table_options:
            charset: utf8mb4
            collate: utf8mb4_unicode_ci

    orm:
        auto_generate_proxy_classes: true
        enable_lazy_ghost_objects: true
        report_fields_where_declared: true
        validate_xml_mapping: true
        naming_strategy: doctrine.orm.naming_strategy.underscore_number_aware
        auto_mapping: true

        # Gedmo Translatable Filter
        filters:
            translatable:
                class: Gedmo\Translatable\Filter\TranslatableFilter
                enabled: true

        mappings:
            App:
                is_bundle: false
                dir: '%kernel.project_dir%/src/Entity'
                prefix: 'App\Entity'
                alias: App

            # Gedmo Translatable mapping
            gedmo_translatable:
                type: attribute
                prefix: Gedmo\Translatable\Entity
                dir: "%kernel.project_dir%/vendor/gedmo/doctrine-extensions/src/Translatable/Entity"
                is_bundle: false
```

**config/services.yaml**
```yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true

    # Gedmo Translatable Listener este auto-configurat de StofDoctrineExtensionsBundle
    # Nu este nevoie de configurare manuală aici
    # Listener-ul este disponibil automat prin dependency injection
```

### 3. Entity Configuration

**src/Entity/Article.php** (exemplu)
```php
<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Translatable\Translatable;
use App\Repository\ArticleRepository;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[ORM\Table(name: 'article')]
class Article implements Translatable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Translatable fields
    #[Gedmo\Translatable]
    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[Gedmo\Translatable]
    #[ORM\Column(length: 255, unique: true)]
    #[Gedmo\Slug(fields: ['title'])]
    private ?string $slug = null;

    #[Gedmo\Translatable]
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $lead = null;

    #[Gedmo\Translatable]
    #[ORM\Column(type: 'text')]
    private ?string $content = null;

    // Locale field - used to override locale per entity instance
    #[Gedmo\Locale]
    private ?string $locale = null;

    // Getters and setters...

    public function setTranslatableLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }
}
```

**Important Notes:**
- Implementează interfața `Gedmo\Translatable\Translatable` (opțional, dar recomandat)
- Folosește atributul `#[Gedmo\Translatable]` pe fiecare field care trebuie tradus
- Definește un field `$locale` cu atributul `#[Gedmo\Locale]` pentru override de locale per entitate
- Method-ul `setTranslatableLocale()` permite setarea locale-ului înainte de persist/flush pentru a salva traducerea

### 4. Locale Event Listener

**src/EventListener/LocaleListener.php**
```php
<?php

namespace App\EventListener;

use Gedmo\Translatable\TranslatableListener;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 20)]
class LocaleListener
{
    private const SUPPORTED_LOCALES = ['ro', 'en', 'ru'];
    private const DEFAULT_LOCALE = 'ro';

    public function __construct(
        private TranslatableListener $translatableListener
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Get locale from Accept-Language header
        $locale = $this->getLocaleFromHeader($request->headers->get('Accept-Language'));

        // Set locale in request
        $request->setLocale($locale);

        // Set locale for Gedmo Translatable
        $this->translatableListener->setTranslatableLocale($locale);
    }

    private function getLocaleFromHeader(?string $acceptLanguageHeader): string
    {
        if (!$acceptLanguageHeader) {
            return self::DEFAULT_LOCALE;
        }

        // Parse Accept-Language header (e.g., "en-US,en;q=0.9,ro;q=0.8")
        $locales = [];
        foreach (explode(',', $acceptLanguageHeader) as $lang) {
            $parts = explode(';', $lang);
            $locale = trim($parts[0]);

            // Get only language code (en from en-US)
            $languageCode = substr($locale, 0, 2);

            // Get quality factor (default 1.0)
            $quality = 1.0;
            if (isset($parts[1]) && strpos($parts[1], 'q=') !== false) {
                $quality = (float) substr($parts[1], 2);
            }

            if (in_array($languageCode, self::SUPPORTED_LOCALES)) {
                $locales[$languageCode] = $quality;
            }
        }

        // Sort by quality (descending)
        arsort($locales);

        // Return first supported locale or default
        return !empty($locales) ? array_key_first($locales) : self::DEFAULT_LOCALE;
    }
}
```

### 5. Repository with Translation Support

**src/Repository/ArticleRepository.php**
```php
<?php

namespace App\Repository;

use App\Entity\Article;
use App\Enum\ArticleStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Gedmo\Translatable\TranslatableListener;

class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    /**
     * Find published articles in specific locale
     * Uses Gedmo query hint to load translations automatically
     */
    public function findPublishedByLocale(string $locale, int $limit = 10, int $offset = 0): array
    {
        $query = $this->createQueryBuilder('a')
            ->leftJoin('a.category', 'c')
            ->leftJoin('a.authors', 'authors')
            ->addSelect('c', 'authors')
            ->where('a.status = :status')
            ->setParameter('status', ArticleStatus::PUBLISHED)
            ->orderBy('a.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery();

        // Set Gedmo hints for translation
        $query->setHint(
            \Doctrine\ORM\Query::HINT_CUSTOM_OUTPUT_WALKER,
            'Gedmo\\Translatable\\Query\\TreeWalker\\TranslationWalker'
        );

        // Set locale for this query
        $query->setHint(
            TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $locale
        );

        // STRICT MODE: Use INNER JOIN to only return entities with translations
        $query->setHint(
            TranslatableListener::HINT_INNER_JOIN,
            true
        );

        return $query->getResult();
    }

    /**
     * Find article by slug in specific locale
     * Returns null if article doesn't exist or translation is missing
     */
    public function findOneBySlugAndLocale(string $slug, string $locale): ?Article
    {
        $query = $this->createQueryBuilder('a')
            ->leftJoin('a.category', 'c')
            ->leftJoin('a.authors', 'authors')
            ->addSelect('c', 'authors')
            ->where('a.slug = :slug')
            ->andWhere('a.status = :status')
            ->setParameter('slug', $slug)
            ->setParameter('status', ArticleStatus::PUBLISHED)
            ->getQuery();

        // Set Gedmo hints
        $query->setHint(
            \Doctrine\ORM\Query::HINT_CUSTOM_OUTPUT_WALKER,
            'Gedmo\\Translatable\\Query\\TreeWalker\\TranslationWalker'
        );

        $query->setHint(
            TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $locale
        );

        // STRICT MODE: Only return if translation exists
        $query->setHint(
            TranslatableListener::HINT_INNER_JOIN,
            true
        );

        return $query->getOneOrNullResult();
    }

    /**
     * Count published articles (all locales)
     * Note: Pentru strict mode count, folosește findPublishedByLocale() și count() pe result
     */
    public function countPublished(): int
    {
        return $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.status = :status')
            ->setParameter('status', ArticleStatus::PUBLISHED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find all translations for a specific article
     * Folosește Translation Repository
     */
    public function findTranslations(Article $article): array
    {
        $translationRepo = $this->getEntityManager()
            ->getRepository('Gedmo\Translatable\Entity\Translation');

        return $translationRepo->findTranslations($article);
    }
}
```

**Important Notes despre Query Hints:**

- **`HINT_CUSTOM_OUTPUT_WALKER`**: Activează TranslationWalker care modifică query-ul pentru a încărca traducerile
- **`HINT_TRANSLATABLE_LOCALE`**: Specifică locale-ul pentru care se încarcă traducerile
- **`HINT_INNER_JOIN`**: Când este `true`, folosește INNER JOIN în loc de LEFT JOIN → returnează doar entități cu traducere în locale-ul cerut (STRICT MODE)
- **`HINT_FALLBACK`**: Poate specifica array de fallback locales (ex: `['en' => 'ro']`) - nu folosim în strict mode

### 6. API Controller with Locale Support

**src/Controller/Api/Public/ArticleController.php**
```php
<?php

namespace App\Controller\Api\Public;

use App\Repository\ArticleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/public/articles')]
class ArticleController extends AbstractController
{
    public function __construct(
        private ArticleRepository $articleRepository
    ) {}

    /**
     * Get published articles
     * GET /api/public/articles
     * Header: Accept-Language: en
     *
     * Returns ONLY articles with translation in requested language
     */
    #[Route('', name: 'api_public_articles_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        // Locale is set by LocaleListener from Accept-Language header
        $locale = $request->getLocale();
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 20);
        $offset = ($page - 1) * $limit;

        // Repository uses Gedmo hints to load only articles with translations in requested locale
        $articles = $this->articleRepository->findPublishedByLocale($locale, $limit, $offset);

        // Count total - in strict mode, count rezultatele returnate
        $totalResults = count($articles);

        return new JsonResponse([
            'articles' => array_map(fn($article) => [
                'id' => $article->getId(),
                'title' => $article->getTitle(),  // Already in requested locale
                'slug' => $article->getSlug(),    // Already in requested locale
                'lead' => $article->getLead(),    // Already in requested locale
                'category' => [
                    'id' => $article->getCategory()->getId(),
                    'name' => $article->getCategory()->getName(),
                    'slug' => $article->getCategory()->getSlug(),
                ],
                'authors' => array_map(fn($author) => [
                    'id' => $author->getId(),
                    'fullName' => $author->getFullName(),
                    'slug' => $author->getSlug(),
                ], $article->getAuthors()->toArray()),
                'badge' => $article->getBadge()?->value,
                'featured' => $article->isFeatured(),
                'publishedAt' => $article->getPublishedAt()?->format('c'),
            ], $articles),
            'meta' => [
                'total' => $totalResults,
                'page' => $page,
                'limit' => $limit,
                'locale' => $locale,
            ]
        ]);
    }

    /**
     * Get article by slug
     * GET /api/public/articles/{slug}
     * Header: Accept-Language: en
     *
     * Returns 404 if translation doesn't exist
     */
    #[Route('/{slug}', name: 'api_public_articles_get', methods: ['GET'])]
    public function get(string $slug, Request $request): JsonResponse
    {
        $locale = $request->getLocale();

        $article = $this->articleRepository->findOneBySlugAndLocale($slug, $locale);

        if (!$article) {
            return new JsonResponse([
                'error' => 'Article not found or not available in requested language',
                'locale' => $locale
            ], 404);
        }

        // Get available locales using Translation repository
        $availableLocales = $this->articleRepository->findTranslations($article);

        return new JsonResponse([
            'id' => $article->getId(),
            'title' => $article->getTitle(),      // Already in requested locale
            'slug' => $article->getSlug(),        // Already in requested locale
            'lead' => $article->getLead(),        // Already in requested locale
            'content' => $article->getContent(),  // Already in requested locale
            'category' => [
                'id' => $article->getCategory()->getId(),
                'name' => $article->getCategory()->getName(),
                'slug' => $article->getCategory()->getSlug(),
            ],
            'authors' => array_map(fn($author) => [
                'id' => $author->getId(),
                'fullName' => $author->getFullName(),
                'slug' => $author->getSlug(),
                'bio' => $author->getBio(),
            ], $article->getAuthors()->toArray()),
            'images' => [], // TODO: add images
            'badge' => $article->getBadge()?->value,
            'featured' => $article->isFeatured(),
            'viewCount' => $article->getViewCount(),
            'readingTime' => $article->getReadingTime(),
            'publishedAt' => $article->getPublishedAt()?->format('c'),
            'locale' => $locale,
            'availableLocales' => array_keys($availableLocales),
        ]);
    }
}
```

**Important Notes:**
- Locale-ul este setat automat de `LocaleListener` din header-ul `Accept-Language`
- Field-urile translatabile (`title`, `slug`, `lead`, `content`) sunt încărcate automat în locale-ul cerut datorită query hints
- Nu este nevoie de refresh sau setări suplimentare - Gedmo face totul transparent
- `findTranslations()` returnează array cu toate traducerile disponibile pentru acel articol

### 7. Admin Translation Management Controller

**src/Controller/Api/Admin/TranslationController.php**
```php
<?php

namespace App\Controller\Api\Admin;

use App\Entity\Article;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Translation;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/translations')]
#[IsGranted('ROLE_ADMIN')]
class TranslationController extends AbstractController
{
    private const SUPPORTED_LOCALES = ['ro', 'en', 'ru'];

    public function __construct(
        private EntityManagerInterface $em
    ) {}

    /**
     * Get all translations for an article
     * GET /api/admin/translations/article/{id}
     *
     * Folosește Translation Repository pentru a găsi toate traducerile
     */
    #[Route('/article/{id}', name: 'api_admin_translations_article', methods: ['GET'])]
    public function getArticleTranslations(int $id): JsonResponse
    {
        $article = $this->em->getRepository(Article::class)->find($id);

        if (!$article) {
            return new JsonResponse(['error' => 'Article not found'], Response::HTTP_NOT_FOUND);
        }

        // Folosește Translation Repository - metoda oficială Gedmo
        $translationRepo = $this->em->getRepository(Translation::class);
        $translations = $translationRepo->findTranslations($article);

        return new JsonResponse([
            'articleId' => $id,
            'translations' => $translations,
            'supportedLocales' => self::SUPPORTED_LOCALES,
        ]);
    }

    /**
     * Update translation for article
     * PUT /api/admin/translations/article/{id}/{locale}
     *
     * Folosește Translation Repository->translate() - metoda oficială Gedmo
     */
    #[Route('/article/{id}/{locale}', name: 'api_admin_translations_update', methods: ['PUT'])]
    public function updateTranslation(int $id, string $locale, Request $request): JsonResponse
    {
        if (!in_array($locale, self::SUPPORTED_LOCALES)) {
            return new JsonResponse([
                'error' => 'Unsupported locale',
                'supported' => self::SUPPORTED_LOCALES
            ], Response::HTTP_BAD_REQUEST);
        }

        $article = $this->em->getRepository(Article::class)->find($id);

        if (!$article) {
            return new JsonResponse(['error' => 'Article not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        // Folosește Translation Repository pentru a salva traducerile
        $translationRepo = $this->em->getRepository(Translation::class);

        // Setează traducerile folosind metoda fluent translate()
        if (isset($data['title'])) {
            $translationRepo->translate($article, 'title', $locale, $data['title']);
        }
        if (isset($data['slug'])) {
            $translationRepo->translate($article, 'slug', $locale, $data['slug']);
        }
        if (isset($data['lead'])) {
            $translationRepo->translate($article, 'lead', $locale, $data['lead']);
        }
        if (isset($data['content'])) {
            $translationRepo->translate($article, 'content', $locale, $data['content']);
        }

        // Persist și flush
        $this->em->persist($article);
        $this->em->flush();

        return new JsonResponse([
            'message' => 'Translation updated successfully',
            'articleId' => $id,
            'locale' => $locale,
        ]);
    }

    /**
     * Delete translation
     * DELETE /api/admin/translations/article/{id}/{locale}
     */
    #[Route('/article/{id}/{locale}', name: 'api_admin_translations_delete', methods: ['DELETE'])]
    public function deleteTranslation(int $id, string $locale): JsonResponse
    {
        if ($locale === 'ro') {
            return new JsonResponse([
                'error' => 'Cannot delete default locale (ro)'
            ], Response::HTTP_FORBIDDEN);
        }

        if (!in_array($locale, self::SUPPORTED_LOCALES)) {
            return new JsonResponse([
                'error' => 'Unsupported locale'
            ], Response::HTTP_BAD_REQUEST);
        }

        $article = $this->em->getRepository(Article::class)->find($id);

        if (!$article) {
            return new JsonResponse(['error' => 'Article not found'], Response::HTTP_NOT_FOUND);
        }

        // Șterge toate traducerile pentru acest locale
        $this->em->createQuery(
            'DELETE FROM Gedmo\Translatable\Entity\Translation t
             WHERE t.foreignKey = :id
             AND t.locale = :locale
             AND t.objectClass = :class'
        )
        ->setParameter('id', (string)$id)
        ->setParameter('locale', $locale)
        ->setParameter('class', Article::class)
        ->execute();

        return new JsonResponse([
            'message' => 'Translation deleted successfully',
            'articleId' => $id,
            'locale' => $locale,
        ]);
    }

    /**
     * Get translation status for all articles
     * GET /api/admin/translations/status
     */
    #[Route('/status', name: 'api_admin_translations_status', methods: ['GET'])]
    public function getTranslationStatus(): JsonResponse
    {
        $articles = $this->em->getRepository(Article::class)->findAll();
        $translationRepo = $this->em->getRepository(Translation::class);

        $status = [];

        foreach ($articles as $article) {
            // Obține toate traducerile pentru articol
            $translations = $translationRepo->findTranslations($article);

            $articleStatus = [
                'id' => $article->getId(),
                'title' => $article->getTitle(),  // Default locale (ro)
                'translations' => [],
            ];

            // Check fiecare locale dacă există traducere
            foreach (self::SUPPORTED_LOCALES as $locale) {
                $articleStatus['translations'][$locale] = isset($translations[$locale]);
            }

            $status[] = $articleStatus;
        }

        return new JsonResponse([
            'articles' => $status,
            'supportedLocales' => self::SUPPORTED_LOCALES,
        ]);
    }
}
```

**Important Notes despre Translation Management:**

1. **Translation Repository**: Folosește `$em->getRepository(Translation::class)` pentru operații pe traduceri
2. **findTranslations($entity)**: Returnează array cu toate traducerile pentru o entitate:
   ```php
   [
       'en' => ['title' => 'English title', 'content' => 'English content'],
       'ru' => ['title' => 'Russian title', 'content' => 'Russian content']
   ]
   ```
3. **translate() Method**: Fluent interface pentru setarea traducerilor:
   ```php
   $repo->translate($article, 'title', 'en', 'English Title')
        ->translate($article, 'content', 'en', 'English Content');
   ```
4. **foreignKey**: În DQL queries pentru ștergere, foreignKey trebuie cast la string

## Cum Funcționează Gedmo Translatable

### Database Storage

Gedmo Translatable folosește un tabel separat `ext_translations` pentru a stoca traducerile:

**Tabelul Principal (article)**:
```
| id | title          | slug           | content            | status    |
|----|----------------|----------------|--------------------|-----------|
| 1  | Articol RO     | articol-ro     | Conținut RO...     | published |
```

**Tabelul de Traduceri (ext_translations)**:
```
| id | locale | object_class  | field    | foreign_key | content              |
|----|--------|---------------|----------|-------------|----------------------|
| 1  | en     | App\Entity\Article | title    | 1           | English Article      |
| 2  | en     | App\Entity\Article | slug     | 1           | english-article      |
| 3  | en     | App\Entity\Article | content  | 1           | English content...   |
| 4  | ru     | App\Entity\Article | title    | 1           | Русская статья       |
| 5  | ru     | App\Entity\Article | slug     | 1           | russkaya-statya      |
| 6  | ru     | App\Entity\Article | content  | 1           | Русский контент...   |
```

### Cum se Încarcă Traducerile

1. **Fără Query Hint** (default locale):
   ```php
   $article = $em->find(Article::class, 1);
   echo $article->getTitle(); // "Articol RO" (din tabelul article)
   ```

2. **Cu Query Hint** (alt locale):
   ```php
   $query = $em->createQuery('SELECT a FROM Article a WHERE a.id = 1');
   $query->setHint(TranslatableListener::HINT_TRANSLATABLE_LOCALE, 'en');
   $query->setHint(\Doctrine\ORM\Query::HINT_CUSTOM_OUTPUT_WALKER, 'Gedmo\\Translatable\\Query\\TreeWalker\\TranslationWalker');

   $article = $query->getSingleResult();
   echo $article->getTitle(); // "English Article" (din ext_translations)
   ```

3. **Cu setTranslatableLocale()** (pentru salvare):
   ```php
   $article = $em->find(Article::class, 1);
   $article->setTranslatableLocale('ru');
   $article->setTitle('Новый заголовок');
   $em->flush();
   // Salvează "Новый заголовок" în ext_translations pentru locale 'ru'
   ```

### Default Locale Behavior

Când `default_locale: ro` și `translation_fallback: false`:

- **Articol creat în RO** → valorile sunt salvate direct în tabel, FĂRĂ rând în ext_translations pentru 'ro'
- **Articol tradus în EN** → valorile sunt salvate în ext_translations cu locale='en'
- **Query cu locale='ro'** → returnează valorile din tabelul principal
- **Query cu locale='en'** → returnează valorile din ext_translations (sau NULL dacă nu există și fallback=false)

## Translation Workflow

### 1. Creating Article in Default Language (RO)

```bash
POST /api/admin/articles
Content-Type: application/json

{
  "title": "Articol important",
  "lead": "Descriere scurtă",
  "content": "Conținut în română...",
  "categoryId": 5,
  "status": "published"
}
```

**Ce se întâmplă:**
- Articolul se salvează direct în tabelul `article`
- NU se creează rânduri în `ext_translations` pentru RO (e limba default)
- Slug-ul se generează automat din titlu: "articol-important"

### 2. Adding English Translation

**Metoda 1: Folosind Translation Repository (RECOMANDAT)**

```php
$article = $em->find(Article::class, 123);
$translationRepo = $em->getRepository(Translation::class);

$translationRepo->translate($article, 'title', 'en', 'Important Article')
    ->translate($article, 'slug', 'en', 'important-article')
    ->translate($article, 'lead', 'en', 'Short description')
    ->translate($article, 'content', 'en', 'Content in English...');

$em->persist($article);
$em->flush();
```

**Metoda 2: Via API**

```bash
PUT /api/admin/translations/article/123/en
Content-Type: application/json

{
  "title": "Important Article",
  "slug": "important-article",
  "lead": "Short description",
  "content": "Content in English..."
}
```

**Ce se întâmplă:**
- Se creează 4 rânduri în `ext_translations` (title, slug, lead, content)
- Toate au `locale='en'`, `foreign_key='123'`, `object_class='App\Entity\Article'`

### 3. Adding Russian Translation

```bash
PUT /api/admin/translations/article/123/ru
Content-Type: application/json

{
  "title": "Важная статья",
  "slug": "vazhnaya-statya",
  "lead": "Краткое описание",
  "content": "Содержание на русском..."
}
```

### Query Behavior Examples

**Request with RO (has translation)**:
```
GET /api/public/articles
Accept-Language: ro

Response: [article1_ro, article2_ro, article3_ro]
```

**Request with EN (only article1 and article3 have EN translation)**:
```
GET /api/public/articles
Accept-Language: en

Response: [article1_en, article3_en]
// article2 NOT included (no EN translation)
```

**Request with RU (only article1 has RU translation)**:
```
GET /api/public/articles
Accept-Language: ru

Response: [article1_ru]
// article2 and article3 NOT included
```

## Database Schema for Translations

```sql
-- Gedmo Translation table (auto-created)
CREATE TABLE ext_translations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    locale VARCHAR(8) NOT NULL,
    object_class VARCHAR(255) NOT NULL,
    field VARCHAR(32) NOT NULL,
    foreign_key VARCHAR(64) NOT NULL,
    content LONGTEXT,
    INDEX idx_translations_lookup (locale, object_class, foreign_key, field),
    UNIQUE INDEX lookup_unique (locale, object_class, field, foreign_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Testing Translation System

### Backend Tests

```php
// tests/Repository/ArticleRepositoryTest.php
public function testFindPublishedByLocale(): void
{
    // Create article with RO translation only
    $article = new Article();
    $article->setTitle('Test RO');
    $article->setStatus(ArticleStatus::PUBLISHED);
    $this->em->persist($article);
    $this->em->flush();

    // Query with RO - should find
    $articlesRo = $this->articleRepository->findPublishedByLocale('ro');
    $this->assertCount(1, $articlesRo);

    // Query with EN - should NOT find (no translation)
    $articlesEn = $this->articleRepository->findPublishedByLocale('en');
    $this->assertCount(0, $articlesEn);

    // Add EN translation
    $article->setTranslatableLocale('en');
    $article->setTitle('Test EN');
    $this->em->persist($article);
    $this->em->flush();

    // Query with EN - should find now
    $articlesEn = $this->articleRepository->findPublishedByLocale('en');
    $this->assertCount(1, $articlesEn);
}
```

## Best Practices (Gedmo Translatable)

### 1. Folosește Query Hints pentru Performance

**❌ Greșit** (generează N+1 queries):
```php
$articles = $articleRepository->findAll();
foreach ($articles as $article) {
    echo $article->getTitle(); // Query separat pentru fiecare traducere
}
```

**✅ Corect** (un singur query cu JOIN):
```php
$query = $articleRepository->createQueryBuilder('a')->getQuery();
$query->setHint(\Doctrine\ORM\Query::HINT_CUSTOM_OUTPUT_WALKER, 'Gedmo\\Translatable\\Query\\TreeWalker\\TranslationWalker');
$query->setHint(TranslatableListener::HINT_TRANSLATABLE_LOCALE, 'en');

$articles = $query->getResult();
```

### 2. Folosește Translation Repository pentru Batch Operations

**✅ Recomandat** pentru a salva multiple traduceri:
```php
$translationRepo = $em->getRepository(Translation::class);
$translationRepo->translate($article, 'title', 'en', 'English Title')
    ->translate($article, 'slug', 'en', 'english-title')
    ->translate($article, 'content', 'en', 'English content...');

$em->persist($article);
$em->flush();
```

### 3. Default Locale = No Translation Records

- Articolele create în `ro` (default locale) NU au rânduri în `ext_translations`
- Valorile default sunt stocate direct în tabelul principal
- Doar traducerile non-default (`en`, `ru`) sunt în `ext_translations`

### 4. Strict Mode cu HINT_INNER_JOIN

**Pentru a returna doar conținut tradus:**
```php
$query->setHint(TranslatableListener::HINT_INNER_JOIN, true);
// Returnează doar entități care AU traducere în locale-ul cerut
```

**Fără strict mode** (LEFT JOIN):
```php
// Returnează toate entitățile, cu fallback la default locale dacă nu există traducere
```

### 5. Validare Înaintea Publicării

```php
$translationRepo = $em->getRepository(Translation::class);
$translations = $translationRepo->findTranslations($article);

$requiredLocales = ['ro', 'en', 'ru'];
$missingLocales = array_diff($requiredLocales, array_keys($translations));

if (!empty($missingLocales)) {
    throw new \Exception('Missing translations for: ' . implode(', ', $missingLocales));
}
```

### 6. Sluggable + Translatable Compatibility

**Important:** Când folosești `translate()` pentru slug, Sluggable NU se execută automat:

```php
// ❌ Slug-ul nu se generează automat
$translationRepo->translate($article, 'title', 'en', 'English Title');
// Trebuie să setezi slug-ul manual:
$translationRepo->translate($article, 'slug', 'en', 'english-title');
```

### 7. Cache Translation Availability

```php
// Cache disponibilitatea traducerilor pentru articole populare
$translations = $translationRepo->findTranslations($article);
$cache->set("article_{$id}_locales", array_keys($translations), 3600);
```

### 8. Logging și Monitoring

```php
// Log când o traducere lipsește
if (!isset($translations[$requestedLocale])) {
    $logger->warning('Missing translation', [
        'entity' => Article::class,
        'id' => $article->getId(),
        'locale' => $requestedLocale,
    ]);
}
```

## Troubleshooting

### Common Issues

1. **Translation not appearing**:
   - Check locale is set correctly
   - Verify translation exists in `ext_translations` table
   - Ensure TranslatableListener is registered

2. **Always getting default locale**:
   - Check LocaleListener priority
   - Verify Accept-Language header is sent
   - Check TranslatableListener is receiving locale

3. **Query returns empty**:
   - Expected behavior if translation doesn't exist
   - Check translation in database
   - Verify INNER JOIN on translations table
