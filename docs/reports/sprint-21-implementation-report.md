# Sprint 21 — Sentinel Internațional: Implementation Report

**Data:** 2026-04-03
**Branch:** `feature/sprint-21-sentinel-international` → merged to `develop`

---

## Obiectiv

Extinderea radar-ului editorial cu surse internaționale. Când la Chișinău e noapte, Sentinel monitorizează Reuters, AP, AFP, Agerpres, Comisia Europeană, UNIAN/Ukrinform și importă DOAR știrile relevante pentru Republica Moldova.

## Deliverables

### Task 1: RelevanceFilterService

| Fișier | Descriere |
|--------|-----------|
| `config/packages/relevance.yaml` | 47 cuvinte cheie în 3 tier-uri |
| `src/Dto/Scraping/RelevanceResult.php` | DTO cu scor, matches, contoare per tier |
| `src/Service/Scraping/RelevanceFilterService.php` | Serviciu de filtrare cu 3 tier-uri |

**Sistemul de scoring:**

| Tier | Descriere | Scor | Condiție |
|------|-----------|------|----------|
| Tier 1 | Mențiune directă Moldova | +3 per match | Orice keyword găsit |
| Tier 2 | Context regional | +1 per match | Minim 2 keywords |
| Tier 3 | Persoane/instituții cheie | +2 per match | Orice keyword găsit |

Prag minim: scor >= 1 (configurable via `relevance.min_score`).

### Task 2: Surse Internaționale

6 surse noi adăugate în `config/packages/scraping.yaml`:

| Sursă | Limbă | Priority | Filtru |
|-------|-------|----------|--------|
| Reuters | en | 3 | Da |
| AP News | en | 3 | Da |
| Agerpres | ro | 4 | Da |
| Comisia Europeană | en | 4 | Da |
| UNIAN | en | 3 | Da |
| Ukrinform | en | 3 | Da |

Surse locale (Moldpres, IPN, Gov.md) actualizate cu `is_international: false`, `relevance_filter: false`.

### Task 3: Integrare Pipeline

`ScrapeSourceHandler.php` modificat:
- Injectare `RelevanceFilterService`
- Filtrul se aplică DOAR pe surse cu `relevance_filter: true`
- Try-catch per item (un item eșuat nu blochează restul)
- Handler returnează statistici: `total/accepted/filtered/duplicates/errors`
- Logging detaliat: `Sentinel: articol ACCEPTAT/FILTRAT` cu scor și tier-uri

### Task 4: CLI Update

`ScrapeSourcesCommand.php` extins:

| Opțiune | Descriere |
|---------|-----------|
| `--international` / `-i` | Doar surse internaționale |
| `--local` | Doar surse locale (Moldova) |
| `--async` | Dispatch în coadă (nu sync) |
| `--all` / `-a` | Toate sursele |
| `--dry-run` | Afișează surse fără execuție |

Execuția sincronă (implicit) afișează tabel cu statistici per sursă cu subtotaluri TOTAL int. / TOTAL local / GRAND TOTAL.

### Task 5: Teste

**12 teste noi, toate trec:**

| Test Suite | Teste | Aserțiuni |
|------------|-------|-----------|
| `RelevanceFilterServiceTest` | 8 | 34 |
| `ScrapeSourceHandlerFilterTest` | 4 | 15 |
| **Total** | **12** | **49** |

**RelevanceFilterServiceTest (8 teste):**
1. Tier 1: „Moldova" → relevant (scor >= 3)
2. Tier 1: „Chișinău" → relevant
3. Tier 1: „Sandu" → relevant
4. Tier 2: 1 keyword regional → irelevant (sub prag)
5. Tier 2: 2+ keywords regionale → relevant
6. Tier 3: „Maia Sandu" → relevant (scor 2)
7. Articol complet irelevant (Kansas weather) → scor 0
8. Keywords chirilice (Молдова, Красносельский) → detectate

**ScrapeSourceHandlerFilterTest (4 teste):**
1. Sursă internațională + articol relevant → trece, dispatch efectuat
2. Sursă internațională + articol irelevant → filtrat, fără dispatch
3. Sursă locală (moldpres) → filtrul NU se aplică, totul trece
4. Statistici corecte pentru rezultate mixte (accepted/filtered/duplicates)

## Pipeline complet după Sprint 21

```
Cron (*/15 min noapte) → app:scrape:sources --international
    │
    ▼
RSS feed → RssFeedParser → ScraperService (Trafilatura) →
    │
    ├─ ContentDeduplicator → skip dacă duplicate
    │
    ├─ RelevanceFilterService → skip dacă irelevant (surse int.)     ← NOU
    │
    └─ ProcessScrapedArticleHandler → Article(DB) →
        ├─ InternalSummaryService (TL;DR)
        ├─ GeminiStructuredTranslator (traducere RO)
        ├─ DbToVaultSyncService (vault)
        └─ ArticleIngestionService (entități, MOC-uri)
```

## Comenzi utile

```bash
# Dry-run surse internaționale
symfony console app:scrape:sources --international --dry-run

# Scraping sincron doar internațional (cu tabel statistici)
symfony console app:scrape:sources --international --limit=10

# Scraping doar local
symfony console app:scrape:sources --local --limit=30

# Toate sursele, dispatch async
symfony console app:scrape:sources --all --async

# O sursă specifică
symfony console app:scrape:sources --source=agerpres --limit=5
```

## Cron recomandat (producție)

```bash
# Surse locale — la fiecare 30 min
*/30 * * * * symfony console app:scrape:sources --local --limit=30

# Surse internaționale — la fiecare 15 min noaptea (22:00-07:00 EET)
*/15 22-23,0-6 * * * symfony console app:scrape:sources --international --limit=50

# Surse internaționale — la fiecare 60 min ziua (07:00-22:00 EET)
0 7-21 * * * symfony console app:scrape:sources --international --limit=30
```

## Fișiere modificate/create

| Fișier | Acțiune | Linii |
|--------|---------|-------|
| `config/packages/relevance.yaml` | Creat | 47 |
| `config/packages/scraping.yaml` | Modificat | +83 |
| `src/Dto/Scraping/RelevanceResult.php` | Creat | 20 |
| `src/Service/Scraping/RelevanceFilterService.php` | Creat | 87 |
| `src/MessageHandler/Editorial/ScrapeSourceHandler.php` | Modificat | +60 |
| `src/Command/ScrapeSourcesCommand.php` | Modificat | +193 |
| `tests/Unit/Service/Scraping/RelevanceFilterServiceTest.php` | Creat | 153 |
| `tests/Unit/Service/Scraping/ScrapeSourceHandlerFilterTest.php` | Creat | 214 |
| **Total** | | **+906 linii** |

## Commits

1. `[sprint-21][task-1]` feat: RelevanceFilterService cu 3 tier-uri + config relevance.yaml
2. `[sprint-21][task-2]` feat: configurare 6 surse internaționale
3. `[sprint-21][task-3]` feat: integrare RelevanceFilter în ScrapeSourceHandler + logging
4. `[sprint-21][task-4]` feat: CLI opțiuni --international/--local + statistici filtrare
5. `[sprint-21][task-5]` test: teste RelevanceFilter (8) + ScrapeSourceHandler filter (4)
