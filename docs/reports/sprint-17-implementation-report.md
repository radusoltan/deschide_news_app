# Sprint 17 — Raport implementare

**Dată**: 2026-04-02
**Branch**: `feature/sprint-17-ai-agents-knowledge`
**Temă**: Agenți AI Autonomi + Knowledge Management + NotebookLM Integration

---

## Rezumat

Sprint-ul 17 construiește stratul de inteligență editorială al ecosistemului Deschide News: extragere automată de entități din articole, creare note atomice în Obsidian Vault, detectare conexiuni noi între entități, generare dosare narative, sinteze săptămânale și briefing-uri zilnice — toate cu suport opțional Audio Overview via NotebookLM.

---

## Deliverables

| # | Task | Status | Fișiere |
|---|------|--------|---------|
| T1 | notebooklm-py + config Symfony | ✅ | `config/packages/notebooklm.yaml`, `.env.example` |
| T2 | NotebookLMService wrapper | ✅ | `src/Service/NotebookLM/NotebookLMService.php` |
| T3 | ArticleIngestionService | ✅ | `src/Service/Editorial/ArticleIngestionService.php`, `src/Dto/Editorial/EntityExtractionResult.php`, `src/Message/Editorial/IngestArticleMessage.php`, `src/MessageHandler/Editorial/IngestArticleHandler.php`, `src/Command/Editorial/IngestArticleCommand.php` |
| T4 | DossierGenerationService | ✅ | `src/Service/Editorial/DossierGenerationService.php`, `src/Command/Editorial/GenerateDossiersCommand.php` |
| T5 | ConnectionDetectionService | ✅ | `src/Service/Editorial/ConnectionDetectionService.php` |
| T6 | WeeklySummaryService | ✅ | `src/Service/Editorial/WeeklySummaryService.php`, `src/Command/Editorial/WeeklySummaryCommand.php` |
| T7 | DailyBriefingService | ✅ | `src/Service/Editorial/DailyBriefingService.php`, `src/Command/Editorial/DailyBriefingCommand.php` |
| T8 | Teste + E2E | ✅ | 5 fișiere test, 30 teste noi |

---

## Comenzi noi

```bash
# Extragere entități dintr-un articol (sync sau async)
symfony console app:editorial:ingest-article --article-id=N [--dry-run] [--async]

# Generare dosare narative pentru MOC-uri cu suficiente articole noi
symfony console app:editorial:generate-dossiers --all [--threshold=5] [--days=30] [--dry-run]

# Sinteză săptămânală + Audio Overview
symfony console app:editorial:weekly-summary [--week=2026-W14] [--with-audio] [--dry-run]

# Briefing zilnic + Audio Overview
symfony console app:editorial:daily-briefing [--date=2026-04-02] [--with-audio] [--dry-run]
```

---

## Teste

| Suită | Teste | Aserțiuni | Status |
|-------|-------|-----------|--------|
| NotebookLMServiceTest | 11 | 11 | ✅ |
| ArticleIngestionServiceTest | 8 | 32 | ✅ |
| DossierGenerationServiceTest | 3 | 5 | ✅ |
| ConnectionDetectionServiceTest | 5 | 13 | ✅ |
| WeeklySummaryServiceTest | 3 | 5 | ✅ |
| **Total Sprint 17** | **30** | **66** | **✅** |
| **Total editorial (incl. Sprint 15/16)** | **57** | **124** | **✅** |

---

## E2E Validare

| Comandă | Rezultat |
|---------|----------|
| `ingest-article --article-id=10175 --dry-run` | ✅ 1 persoană, 2 instituții, 1 eveniment, 4 locații, 5 topics (confidence 0.95) |
| `generate-dossiers --all --dry-run` | ✅ 0 MOC-uri (vault neconfigurait) |
| `weekly-summary --dry-run` | ✅ 135 articole, 9 categorii |
| `daily-briefing --dry-run` | ✅ 0 articole (zi curentă) |

---

## Arhitectură

### Pipeline AI Ingestion

```
Article Created (Scraper/Import)
    │
    ▼
IngestArticleMessage (editorial queue)
    │
    ▼
IngestArticleHandler
    ├── extractEntities (Gemini CLI → JSON)
    ├── createAtomicNotes (PER/INST/EVT → vault)
    ├── updateMOCs (cronologie links)
    ├── detectNewConnections (Elasticsearch cross-ref)
    ├── saveConnectionAlerts (vault/alerts/)
    └── feedNotebookLM (graceful, optional)
```

### Fallback graceful

- **NotebookLM**: toate metodele verifică `isAvailable()` — dacă CLI-ul nu e disponibil, serviciile continuă fără Audio Overview
- **Elasticsearch**: dacă disabled, `detectNewConnections` returnează array gol
- **Vault**: dacă `VAULT_PATH` gol, note atomice și MOC updates sunt skipped

### Servicii noi

| Serviciu | Dependențe |
|----------|-----------|
| NotebookLMService | CLI notebooklm-py |
| ArticleIngestionService | Gemini CLI, NotebookLMService |
| DossierGenerationService | Gemini CLI, EntityManager, NotebookLMService |
| ConnectionDetectionService | SearchService (Elasticsearch) |
| WeeklySummaryService | Gemini CLI, EntityManager, NotebookLMService |
| DailyBriefingService | Gemini CLI, EntityManager, NotebookLMService |

---

## Messenger Routing

```yaml
# Sprint 17 addition
'App\Message\Editorial\IngestArticleMessage': editorial
```

---

## Regula de aur

Toate output-urile AI au frontmatter cu:
```yaml
ai:
  auto_generated: true
  reviewed: false
```

Niciun conținut generat de AI nu se publică fără validare umană.
