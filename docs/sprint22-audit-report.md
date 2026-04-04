# Sprint 22 — Topic System Audit Report

**Date**: 2026-04-04
**Branch**: develop
**Status**: Complete

---

## 1. CE AVEM — Infrastructură Reutilizabilă

### 1.1 Entity Patterns (Modele de Referință)

**Article Entity** (`src/Entity/Article.php`):
- Implements `Translatable` interface
- Uses `#[Gedmo\Translatable]` attributes on: title, slug, lead, content, metaTitle, metaDescription
- Uses `#[Gedmo\Slug]`, `#[Gedmo\Timestampable]`, `#[Gedmo\Locale]`
- ManyToMany with Tag (owning side): `#[ORM\JoinTable(name: 'article_tag')]`
- ManyToOne with Category (nullable)
- Serialization groups: `article:read`, `article:write`, `article:detail`, `article:list`
- `#[MaxDepth(2)]` on collections
- `translatedSlugs` non-persisted field pattern
- Constructor initializes `ArrayCollection` for all collections
- Has adder/remover methods for all collections

**Tag Entity** (`src/Entity/Tag.php`):
- Implements `Translatable`
- Translatable fields: name, slug, description
- ManyToMany with Article (inverse side, `mappedBy: 'tags'`)
- Serialization groups: `tag:read`, `tag:write`, `article:read` (on id, name, slug)
- `usageCount` field for denormalized count
- `translatedSlugs` non-persisted field
- API Platform: custom `TagProvider` + `TagProcessor`
- Has `SearchFilter` and `OrderFilter`

**Category Entity** (`src/Entity/Category.php`):
- Implements `Translatable`
- Has parent-child hierarchy via `ManyToOne`/`OneToMany` self-references (simple, NOT Gedmo Tree)
- Serialization groups: `category:read`, `category:write`, `category:detail`, `article:read`
- Custom `CategoryProvider` + `CategoryProcessor`
- `translatedSlugs` non-persisted field
- Translation tracking fields: translationStatus, translatedAt, translatedBy

### 1.2 Repository Pattern

**TagRepository** (`src/Repository/TagRepository.php`):
- Extends `ServiceEntityRepository<Tag>` (standard Doctrine)
- Methods: `findPopularTags()`, `findByNameSearch()`, `findOrCreateByName()`, `findUnusedTags()`
- Uses `HINT_TRANSLATABLE_LOCALE` on all queries

### 1.3 Controller Pattern

**TagController** (`src/Controller/TagController.php`):
- Route prefix: `#[Route('/api/tags')]`
- All endpoints return Hydra/JSON-LD formatted JSON
- Cache-Control headers on all responses
- `extractLocale()` helper method (Accept-Language + query param)
- `serializeTag()` helper for consistent output
- Endpoints: `/popular`, `/search`, `/{id}/related`, `/{id}/stats`, `/unused`, `/{id}/merge`

### 1.4 State Provider/Processor Pattern

**TagProvider** (`src/State/TagProvider.php`):
- Handles single item (by id) and collection queries
- Applies `HINT_TRANSLATABLE_LOCALE` on all queries
- Result caching with locale-aware cache keys
- `populateTranslatedSlugs()` batch-populates slugs via raw SQL for all locales

**ArticleProcessor** (`src/State/ArticleProcessor.php`):
- Tag sync pattern (lines 193-221): extracts incoming tag IDs, removes old, adds new with managed entities
- Usage count increment/decrement during sync
- `getManagedTag()` helper to fetch managed entity from DB
- Cache invalidation after mutations
- Mercure SSE notification pattern
- Event dispatching pattern (ArticlePublishedEvent, etc.)

### 1.5 Gedmo Extensions

**Installed**: `gedmo/doctrine-extensions` v3.22.0 (includes Tree support)

**Config** (`config/packages/stof_doctrine_extensions.yaml`):
```yaml
stof_doctrine_extensions:
    default_locale: ro
    orm:
        default:
            translatable: true
            timestampable: true
            sluggable: true
            # tree: NOT configured yet!
```

**ext_translations** table contains classes:
- `App\Entity\Image`
- `App\Entity\AiPromptTemplate`
- `App\Entity\Category`
- `App\Entity\LiveText`
- (Tag and Article translations also use this table)

### 1.6 CategoryDetectorService

**File**: `src/Service/CategoryDetectorService.php`
- Simple keyword-based detection (no AI)
- Uses `CATEGORY_KEYWORDS` constant with slug => keywords mapping
- `detect(title, body)` returns Category entity
- `detectSlug(text)` returns slug string
- Helper: `removeDiacritics()` for Romanian text normalization
- **NOT** a model for TopicDetector (too simple — Topics need Gemini CLI AI)

### 1.7 Gemini CLI Integration Pattern

**TranslateArticleHandler** (`src/MessageHandler/TranslateArticleHandler.php`):
- Uses `Symfony\Component\Process\Process` for Gemini CLI
- Path: injected via `$geminiCliPath` parameter (default: `/usr/bin/gemini`)
- Pattern: write prompt to temp file, pass via stdin
- Options: `-p` for short instruction, `-o json` for JSON output
- Environment: `HOME=/home/radu`, `PATH` from system
- Timeout: configurable constant
- Error handling: checks `$process->isSuccessful()`, throws RuntimeException
- Output: `trim($process->getOutput())`

### 1.8 VaultSync Service

**File**: `src/Service/Editorial/DbToVaultSyncService.php`
- Builds YAML frontmatter + Markdown content
- Already includes: categories (as slug array), tags (as slug array)
- Has `getTranslations()` method for multilingual data
- Will need `topics` added to frontmatter (line ~286: after tags)

### 1.9 Frontend Structure

**Admin tags page**: `app/[locale]/admin/tags/page.tsx` + `TagsPageClient.tsx`
**TagSelector component**: `components/admin/tags/TagSelector.tsx`
- Dropdown with search, popular tags, keyboard navigation
- Multi-select with pills/chips and X remove
- Uses API endpoints: `/api/tags/popular`, `/api/tags/search`

**Public tags page**: `app/[locale]/(public)/tags/page.tsx` + `tags/[slug]/page.tsx`

**Admin Sidebar** (`app/[locale]/admin/components/Sidebar.tsx`):
- Tags link at line 175-195 (between Categories and Menu Builder)
- Uses SVG icons (not lucide-react)
- Pattern: `linkClass()` and `iconClass()` helpers for active state

**ArticleForm** (`app/[locale]/admin/articles/components/ArticleForm.tsx`):
- 1149 lines
- TagSelector imported via `dynamic()` (no SSR)
- Tags submitted as JSON string in FormData: `formData.get('tags')` → parsed as string[] of IRIs
- Article interface includes `tags?: Tag[]`

**DAL** (`lib/dal.ts`):
- Server-only module with session verification
- Uses `API_BASE_URL` from env
- Pattern for authenticated API requests

### 1.10 Elasticsearch

**No Elasticsearch service files** found in `src/Service/Elasticsearch/`.
ES commands exist in `src/Command/Elasticsearch/` but no article-level ES services were found.
Will need to check if ES indexing is handled elsewhere or skip ES integration for topics.

---

## 2. CE LIPSEȘTE — Ce Trebuie Creat

### 2.1 Backend

| Component | File Path | Priority |
|-----------|-----------|----------|
| **Topic Entity** | `src/Entity/Topic.php` | P0 |
| **Gedmo Tree activation** | `config/packages/stof_doctrine_extensions.yaml` → `tree: true` | P0 |
| **DB Migration** | `migrations/VersionXXXXXX.php` (auto-generated) | P0 |
| **TopicRepository** | `src/Repository/TopicRepository.php` (extends NestedTreeRepository) | P0 |
| **TopicService** | `src/Service/TopicService.php` | P0 |
| **TopicController** | `src/Controller/TopicController.php` | P0 |
| **TopicDetectorService** | `src/Service/TopicDetectorService.php` | P1 |
| **Article Entity update** | Add `$topics` ManyToMany (inverse side) | P0 |
| **ArticleProcessor update** | Add topics sync (same pattern as tags) | P0 |
| **VaultSync update** | Add topics to frontmatter | P2 |
| **SeedTopicsCommand** | `src/Command/SeedTopicsCommand.php` | P2 |

### 2.2 Frontend

| Component | File Path | Priority |
|-----------|-----------|----------|
| **Topic types** | `lib/types/topic.ts` | P0 |
| **DAL functions** | `lib/dal.ts` additions | P0 |
| **TopicTreeView** | `components/admin/topics/TopicTreeView.tsx` | P1 |
| **TopicFormModal** | `components/admin/topics/TopicFormModal.tsx` | P1 |
| **TopicSelector** | `components/admin/topics/TopicSelector.tsx` | P1 |
| **Admin topics page** | `app/[locale]/admin/topics/page.tsx` | P1 |
| **Sidebar update** | Add Topics link in Sidebar.tsx | P1 |
| **ArticleForm update** | Add TopicSelector section | P1 |
| **Article actions update** | `app/actions/articles.ts` — add topics | P1 |
| **Public topics page** | `app/[locale]/(public)/topics/page.tsx` | P2 |
| **Public topic detail** | `app/[locale]/(public)/topics/[slug]/page.tsx` | P2 |

---

## 3. RISCURI — Conflicte Potențiale

### 3.1 Gedmo Tree Activation
- **Risc**: Activarea `tree: true` în stof_doctrine_extensions.yaml ar putea afecta entitățile existente
- **Mitigare**: Gedmo Tree annotations sunt explicit per-entity — Category NU folosește Tree annotations, deci nu va fi afectată. Doar entitățile cu `#[Gedmo\TreeLeft]` etc. vor fi procesate.

### 3.2 Join Table Naming
- **Risc**: `article_topics` join table name collision cu alte tabele
- **Mitigare**: Verificat — nu există tabela `article_topics` în DB. Numele e sigur.

### 3.3 ManyToMany Direction (Owning Side)
- **Decision**: Topic va fi owning side (are `#[ORM\JoinTable]`), Article va fi inverse (mappedBy)
- **Motiv**: Consistență cu Tag pattern (Article este owning side pentru tags) — DAR pentru topics, e mai logic ca Topic să fie owning side deoarece topics sunt mai structurate. 
- **ATENȚIE**: Prompt-ul specifică Article ca inverse side (`mappedBy: 'articles'` pe Article). Aceasta înseamnă Topic este owning side. Conform cu designul.

### 3.4 NestedTreeRepository vs ServiceEntityRepository
- **Risc**: TopicRepository trebuie să extindă `Gedmo\Tree\Entity\Repository\NestedTreeRepository`, NU `ServiceEntityRepository`
- **Mitigare**: Configurare corectă a entity repository class în `#[ORM\Entity]`
- **Atenție**: NestedTreeRepository nu este o `ServiceEntityRepository`, deci DI va necesita configurare manuală sau tag-uri Doctrine

### 3.5 Serialization Circular References
- **Risc**: Topic->children->parent loop
- **Mitigare**: `#[MaxDepth(2)]` pe children + serialization groups separate

### 3.6 Frontend: Sidebar Uses SVG Icons, NOT Lucide
- **Risc**: Prompt-ul menționează `Network` din lucide-react, dar Sidebar-ul actual folosește SVG inline
- **Mitigare**: Folosim SVG inline consistent cu restul sidebar-ului, sau adăugăm lucide-react ca dependență

### 3.7 Admin Tags Page Structure
- **Observație**: Admin tags page are `page.tsx` + `TagsPageClient.tsx` (server + client split)
- Topic admin page va urma același pattern

---

## 4. RECOMANDĂRI — Ordine de Implementare

### 4.1 Ordine Optimizată

1. **T22.2** — Entity + Migration (fundația, nu depinde de nimic)
2. **T22.3** — Repository + Service + Controller (depinde de entity)
3. **T22.6** — TopicDetectorService (depinde de repository, dar nu de frontend)
4. **T22.4** — Admin Tree UI (depinde de controller endpoints)
5. **T22.5** — TopicSelector în ArticleForm (depinde de T22.4 + T22.6)
6. **T22.7** — Frontend public pages (depinde de controller)
7. **T22.8** — Teste + Seed + VaultSync (finalizare)

### 4.2 Ajustări față de Plan

1. **Sidebar icons**: Folosim SVG inline ca restul sidebar-ului (nu lucide-react), sau adăugăm lucide ca dependență dacă e deja instalat
2. **NestedTreeRepository DI**: Va necesita probabil `services.yaml` configurare
3. **Topic DAL functions**: Mai degrabă în `lib/api/topics.ts` (fișier dedicat) decât adăugate în `lib/dal.ts` — dal.ts este server-only, dar funcțiile topic sunt folosite și client-side
4. **ext_translations**: Tag NU apare în ext_translations query — verifică dacă Tag folosește alt mecanism de stocare traduceri sau dacă nu are traduceri salvate încă
5. **Elasticsearch**: Nu există servicii ES dedicate — skip integrarea ES la T22.8 dacă nu e critică

### 4.3 Fișiere de Referință Cheie

| Referință | Fișier | Ce reutilizăm |
|-----------|--------|--------------|
| Entity pattern | `src/Entity/Tag.php` | Translatable + Slug + Groups |
| Repository pattern | `src/Repository/TagRepository.php` | Query structure |
| Controller pattern | `src/Controller/TagController.php` | Hydra JSON-LD responses |
| Provider pattern | `src/State/TagProvider.php` | Locale-aware queries + translatedSlugs |
| Processor pattern | `src/State/ArticleProcessor.php:193-221` | Collection sync |
| Gemini CLI pattern | `src/MessageHandler/TranslateArticleHandler.php:195-218` | Process + stdin |
| Frontend selector | `components/admin/tags/TagSelector.tsx` | Multi-select UI |
| Frontend page | `app/[locale]/admin/tags/page.tsx` | Admin page structure |
| Frontend sidebar | `app/[locale]/admin/components/Sidebar.tsx:175-195` | Navigation link |
| Article form | `app/[locale]/admin/articles/components/ArticleForm.tsx` | Form integration |
| VaultSync | `src/Service/Editorial/DbToVaultSyncService.php:286` | Frontmatter location |

---

## 5. VERIFICĂRI RUNTIME

```
✅ Gedmo extensions v3.22.0 installed (Tree support available)
✅ Tree NOT activated in config (needs tree: true)
✅ No existing Tree annotations in codebase
✅ ext_translations table exists and functional
✅ CategoryDetectorService exists (simple keyword-based)
✅ Gemini CLI available at /usr/bin/gemini
✅ ArticleProcessor has tag sync pattern to replicate
✅ VaultSync has clear location for topics addition
✅ Frontend admin structure documented
✅ No conflicts with existing tables/namespaces
```
