# Sprint 16 — Implementation Report

**Pipeline Scraping Surse Moldovenești + Conversie Markdown + Elasticsearch Trilingv**

**Data**: 2026-04-02
**Branch**: `feature/sprint-16-scraping-pipeline`
**Status**: COMPLETAT

---

## Rezumat

Sprint 16 implementează pipeline-ul complet de agregare editorială:
- Scraping surse oficiale moldovenești (Moldpres, IPN, Gov.md)
- Conversie HTML→Markdown cu normalizare UTF-8 NFC
- Deduplicare conținut via SHA-256 hash
- Procesare asincronă via Symfony Messenger
- Traducere structurată via Gemini CLI (JSON schema-validated)
- Indexare trilingvă Elasticsearch (RO/EN/RU) cu analyzers custom

---

## Task-uri implementate

### Task 1: Instalare dependențe
- **trafilatura 2.0.0** (Python) — extragere conținut principal din pagini web
- **league/html-to-markdown 5.1** (PHP) — conversie HTML→Markdown
- **Commit**: `[sprint-16][task-1]`

### Task 2: ScraperService + RssFeedParser
- `config/packages/scraping.yaml` — configurare 3 surse (Moldpres, IPN, Gov.md)
- `src/Service/Scraping/ScraperService.php` — extragere conținut cu Trafilatura + fallback
- `src/Service/Scraping/RssFeedParser.php` — parsare RSS 2.0 + Atom
- `src/Dto/Scraping/ScrapedContent.php`, `FeedItem.php` — DTOs
- Rate limiting per domeniu
- **Commit**: `[sprint-16][task-2]`

### Task 3: HtmlToMarkdownConverter + FrontmatterGenerator + ContentDeduplicator
- `src/Service/Scraping/HtmlToMarkdownConverter.php` — conversie cu curățare HTML + NFC
- `src/Service/Scraping/FrontmatterGenerator.php` — YAML frontmatter automat
- `src/Service/Scraping/ContentDeduplicator.php` — SHA-256 hash pe conținut normalizat
- `Article.contentHash` — câmp nou (VARCHAR 64) + index + migrație
- **Commit**: `[sprint-16][task-3]`

### Task 4: Symfony Messenger pipeline
- `src/Message/Editorial/ScrapeSourceMessage.php` — trigger scraping
- `src/Message/Editorial/ProcessScrapedArticleMessage.php` — procesare articol
- `src/MessageHandler/Editorial/ScrapeSourceHandler.php` — feed→scrape→dedup→dispatch
- `src/MessageHandler/Editorial/ProcessScrapedArticleHandler.php` — creare Article + vault + traducere
- `messenger.yaml` — transport-uri `scraping` + `editorial`
- `src/Command/ScrapeSourcesCommand.php` — CLI cu --source, --all, --language, --limit, --dry-run
- **Commit**: `[sprint-16][task-4]`

### Task 5: GeminiStructuredTranslator
- `src/Service/Translation/GeminiStructuredTranslator.php` — traducere cu JSON output structurat
- Validare schema: headline, body, description, slug, SEO, entities, topics
- Retry automat la JSON invalid, strip markdown code blocks
- **Commit**: `[sprint-16][task-5]`

### Task 6: Elasticsearch indexare trilingvă
- `src/Service/Search/ElasticsearchIndexManager.php` — index unificat cu analyzers RO/EN/RU
- `src/Service/Search/ArticleIndexer.php` — construiește document ES din Article + traduceri Gedmo
- `src/Service/Search/SearchService.php` — multi_match cu boost + highlighting + paginare
- `src/Command/ElasticsearchReindexCommand.php` — reindexare batch cu progress bar
- **Rezultat E2E**: 7,539 articole indexate cu succes
- **Commit**: `[sprint-16][task-6]`

### Task 7: Teste
- **44 teste noi, toate green (116 assertions)**
- ScraperServiceTest (4 teste)
- RssFeedParserTest (6 teste)
- HtmlToMarkdownConverterTest (9 teste)
- ContentDeduplicatorTest (8 teste)
- FrontmatterGeneratorTest (4 teste)
- GeminiStructuredTranslatorTest (7 teste)
- SearchServiceTest (2 teste)
- EditorialMessagesTest (4 teste)
- **Commit**: `[sprint-16][task-7]`

---

## Fișiere create/modificate

### Fișiere noi (24)
| Fișier | Tip | Linii |
|--------|-----|-------|
| `config/packages/scraping.yaml` | Config | 32 |
| `src/Dto/Scraping/ScrapedContent.php` | DTO | 18 |
| `src/Dto/Scraping/FeedItem.php` | DTO | 18 |
| `src/Service/Scraping/ScraperService.php` | Service | 145 |
| `src/Service/Scraping/RssFeedParser.php` | Service | 145 |
| `src/Service/Scraping/HtmlToMarkdownConverter.php` | Service | 77 |
| `src/Service/Scraping/FrontmatterGenerator.php` | Service | 115 |
| `src/Service/Scraping/ContentDeduplicator.php` | Service | 60 |
| `src/Service/Translation/GeminiStructuredTranslator.php` | Service | 165 |
| `src/Service/Search/ElasticsearchIndexManager.php` | Service | 155 |
| `src/Service/Search/ArticleIndexer.php` | Service | 117 |
| `src/Service/Search/SearchService.php` | Service | 105 |
| `src/Message/Editorial/ScrapeSourceMessage.php` | Message | 14 |
| `src/Message/Editorial/ProcessScrapedArticleMessage.php` | Message | 18 |
| `src/MessageHandler/Editorial/ScrapeSourceHandler.php` | Handler | 95 |
| `src/MessageHandler/Editorial/ProcessScrapedArticleHandler.php` | Handler | 115 |
| `src/Command/ScrapeSourcesCommand.php` | Command | 95 |
| `src/Command/ElasticsearchReindexCommand.php` | Command | 85 |
| `migrations/Version20260402081607.php` | Migration | 25 |
| `tests/Unit/Service/Scraping/ScraperServiceTest.php` | Test | 85 |
| `tests/Unit/Service/Scraping/RssFeedParserTest.php` | Test | 130 |
| `tests/Unit/Service/Scraping/HtmlToMarkdownConverterTest.php` | Test | 95 |
| `tests/Unit/Service/Scraping/ContentDeduplicatorTest.php` | Test | 100 |
| `tests/Unit/Service/Scraping/FrontmatterGeneratorTest.php` | Test | 85 |
| `tests/Unit/Service/Translation/GeminiStructuredTranslatorTest.php` | Test | 130 |
| `tests/Unit/Service/Search/SearchServiceTest.php` | Test | 45 |
| `tests/Unit/Message/Editorial/EditorialMessagesTest.php` | Test | 60 |

### Fișiere modificate (3)
| Fișier | Modificare |
|--------|-----------|
| `src/Entity/Article.php` | +contentHash field + getter/setter + index |
| `config/services.yaml` | +7 service definitions (Sprint 16) |
| `config/packages/messenger.yaml` | +2 transport-uri + routing |

---

## Verificare E2E

### Elasticsearch
- Index `deschide_articles_trilingual` creat cu analyzers RO/EN/RU
- 7,539 articole indexate (din 7,539 published)
- Căutare "moldova" returnează 2,979 rezultate cu scoring corect
- Analyzers funcționează: stemming românesc, rusesc, englez

### CLI Command
- `app:scrape:sources --dry-run --all` — listează corect toate sursele
- `app:scrape:sources --source=moldpres --limit=3 --dry-run` — funcțional

### Messenger
- Transport-uri `scraping` + `editorial` configurate
- Routing corect pentru ScrapeSourceMessage → scraping, ProcessScrapedArticleMessage → editorial

---

## Pregătire producție

### Cron (de configurat la deployment)
```bash
*/30 * * * * cd /var/www/deschide_news_app/apps/backend && symfony console app:scrape:sources --all --limit=30 >> /var/log/deschide/scraping.log 2>&1
```

### Supervisor worker
```ini
[program:messenger-editorial]
command=symfony console messenger:consume scraping editorial --time-limit=3600 --memory-limit=256M
directory=/var/www/deschide_news_app/apps/backend
user=www-data
autostart=true
autorestart=true
```

---

## Statistici

| Metrică | Valoare |
|---------|---------|
| Fișiere noi | 27 |
| Fișiere modificate | 3 |
| Teste noi | 44 |
| Assertions | 116 |
| Commit-uri | 7 |
| Surse configurate | 3 (Moldpres, IPN, Gov.md) |
| Articole indexate ES | 7,539 |
