# Audit: Editorial AI Background — Infrastructure Readiness

## Executive Summary

Deschide News App are o baza solida pentru implementarea sistemului Editorial AI Background. **Majoritatea componentelor infrastructurale exista deja**: pipeline de clustering cu scoring (>0.70 threshold configurabil via `AppSetting`), provider dual AI (Gemini CLI + Anthropic CLI/API), sistem Messenger cu 11 transporturi si 24 de handleri, Mercure hub functional cu 4 publisheri, Elasticsearch cu index dedicat press releases (MLT queries), si un formular de editare Article modular (5 sectiuni extracte in Sprint 39).

**Ce lipseste critic**: (1) campul `Article.background` (entitate + migrare + API), (2) un `GenerateBackgroundMessage` + handler care sa orchestreze pipeline-ul dual-LLM, (3) un event `ClusterScoredAboveThresholdEvent` care sa declanseze generarea (scoring-ul actual nu emite events), (4) un `TopicDossierAssemblyService` care sa adune contextul cronologic din ES + cluster summaries, (5) componenta frontend `BackgroundProposalPanel` cu SSE subscription, si (6) activarea `AnthropicApiClient` (exista dar e dormant — interfata e aliased la `ClaudeCliClient`).

**Complexitate estimata**: Medium-Large (6-8 zile), cu 12 componente noi si ~15 fisiere modificate. Riscul principal e integrarea cu TinyMCE pentru inserarea blocurilor in rich text, si gestionarea pipeline-ului async cu Mercure notifications. Infrastructura de baza (Messenger, Mercure, ES, Redis, AI providers) e matura si testata.

---

## 1. Entity Layer

### Exista

**Article** (`src/Entity/Article.php`) — 45 entitati total in proiect:
- 26 de proprietati persistate + 3 non-persistate (`locale`, `translatedSlugs`)
- 6 relatii: `category` (ManyToOne), `authors` (ManyToMany), `articleImages` (OneToMany), `relatedArticles` (ManyToMany self), `tags` (ManyToMany), `topics` (ManyToMany inverse)
- Gedmo Translatable pe: `title`, `slug`, `lead`, `content`, `metaTitle`, `metaDescription`
- Camp `internalSummary` (TEXT, nullable) — precedent pentru camp editorial AI-generated
- Serialization groups: `article:read`, `article:write`, `article:detail`, `article:list`
- Enums: `ArticleStatus` (new/submitted/published/archived), `ArticleBadge` (breaking/alert/flash), `ArchiveReason`

**StoryCluster** (`src/Entity/StoryCluster.php`):
- `importanceScore` (FLOAT, default 0.0) — scorul pe care il monitorizam
- `summaryShort`, `summaryMedium`, `whyItMatters` (TEXT) — summary-uri AI generate de `ClusterSummaryService`
- `keyFacts` (JSON array)
- Relatii: `pressReleases` (ManyToMany owning), `topics` (ManyToMany unidirectional)
- `status` enum: `auto`, `reviewed`, `approved`, `rejected`, `promoted`
- Index pe `importance_score` si `status`

**PressRelease** (`src/Entity/PressRelease.php`):
- 30+ campuri inclusiv `content`, `lead`, `title`, `sourceName`, `sourceUrl`, `detectedLanguage`, `categorySlug`, `relevanceScore`, `suggestedTopics`
- `receivedAt` (nu `publishedAt` — important pt cronologie)
- Relatii: `source` (ManyToOne), `article` (OneToOne), `storyClusters` (ManyToMany inverse)
- `credibilityWeight` NU e pe PressRelease — se acceseaza via `$this->source->getCredibilityWeight()`

**Source** (`src/Entity/Source.php`):
- `credibilityWeight` (FLOAT, default 0.5), `country` (ISO 2-char), `sourceCategory` (enum: agency/generalist/business/geopolitics/regional/local/institutional/diaspora/alert)
- Nu are API Platform — accesibil doar ca relatie nested pe PressRelease

**Topic** (`src/Entity/Topic.php`):
- Gedmo Tree (nested set): `parent`, `children`, `lft`, `rgt`, `lvl`, `root`
- Gedmo Translatable: `title`, `slug`, `description`
- `weight` (FLOAT, default 0.5), `reviewStatus` (default 'approved')
- Relatie ManyToMany owning cu Article (`article_topics`)
- StoryCluster are relatie unidirectionala ManyToMany cu Topic (`story_cluster_topic`)

**AppSetting** (`src/Entity/AppSetting.php`):
- Key-value store simplu: `$key` (PK, STRING 100), `$value` (TEXT), `$updatedAt`
- Folosit de `AutoPromoteService` pentru threshold configurabil (default 0.70)
- Nu e expus via API Platform — intern only

### Lipseste

1. **Camp `background` pe Article** — NU exista. Trebuie adaugat:
   ```php
   #[ORM\Column(type: Types::TEXT, nullable: true)]
   #[Groups(['article:read', 'article:detail', 'article:write'])]
   #[Gedmo\Translatable]
   private ?string $background = null;
   ```
   Necesita: migrare Doctrine, getter/setter, adaugare in serialization groups.

2. **Entitate `ArticleBackgroundProposal`** (optional, pentru tracking):
   ```
   id, articleId (FK), clusterId (FK nullable), generatedAt, status (pending/accepted/rejected/edited),
   blocks (JSON: [{type: chronological|explanatory|moldova_perspective, content: string, sources: string[]}]),
   modelUsed, promptVersion, generationDurationMs
   ```
   Alternativ: stocarea propunerilor in Redis cache (TTL 24h) fara entitate dedicata.

3. **Relatie Article <-> StoryCluster**: Nu exista direct. Article <-> PressRelease (OneToOne) si PressRelease <-> StoryCluster (ManyToMany) — legatura e indirecta. Pentru background generation, path-ul e: `Article -> PressRelease -> StoryCluster -> PressReleases[] -> Source[]`.

### Riscuri

- **Migrare `background` field**: Camp TEXT nullable — migrare simpla, zero downtime. Dar Gedmo Translatable necesita si intrare in `ext_translations` — Gedmo gestioneaza automat la flush.
- **Backward compatibility**: API-ul returneaza `null` implicit — clientii existenti nu sunt afectati.
- **No FK constraints noi**: Daca nu se creeaza entitate separata, riscul e zero.

---

## 2. AI Provider Layer

### Exista

**Interfata `AnthropicClientInterface`** (`src/Service/Ai/AnthropicClientInterface.php`):
```php
interface AnthropicClientInterface {
    public function chat(array $messages, string $model, ?string $system = null): string;
    public function chatStream(array $messages, string $model, ?string $system = null): \Generator;
}
```

**3 implementari**:

| Provider | Transport | Status | Folosit de |
|----------|-----------|--------|------------|
| `ClaudeCliClient` | CLI subprocess (`claude -p`) | **ACTIV** (DI alias) | AiOrchestrator, AnthropicProvider |
| `AnthropicApiClient` | HTTP API (`api.anthropic.com/v1/messages`) | **DORMANT** | Nimeni (aliased away) |
| `MockAnthropicClient` | In-memory | Test/dev fallback | — |

**`AnthropicApiClient`** — complet functional dar neactivat:
- Direct HTTP POST la `https://api.anthropic.com/v1/messages` via Symfony HttpClient
- Suporta streaming SSE, exponential backoff retry (3 retries pe 429/529)
- DI config: `$anthropicApiKey: '%env(default::ANTHROPIC_API_KEY)%'`
- `ANTHROPIC_API_KEY` **NU e setat** in `.env.example` sau `.env.local`

**Provider Registry** (`AiProviderRegistry`):
- Tagged services (`ai.provider`): `AnthropicProvider` + `GeminiCliProvider`
- Routing: RESEARCH/CONTENT/BRIEFING -> Anthropic, TRANSLATION/SEO -> Gemini

**GeminiCliService** — workhorse-ul proiectului:
- 18+ servicii il folosesc, inclusiv `ClusterSummaryService`, `BackgroundGeneratorService`, toate serviciile de traducere
- CLI subprocess: `gemini -p <prompt>` cu JSON output mode
- Helpers: `stripFences()`, `parseJson()`, `extractJsonObject()`

**`BackgroundGeneratorService`** (`src/Service/Editorial/BackgroundGeneratorService.php`) — **EXISTA DEJA!**:
- DI: `SearchService`, `GeminiCliService`, `LoggerInterface`
- Genereaza 1 paragraf de context istoric folosind ES (5 articole similare)
- Model: Gemini CLI, timeout 60s
- Prompt: "senior editor at Deschide News"
- **Limitare**: genereaza un singur paragraf, nu 3 blocuri modulare

**`ArticleBackgroundController`** (`src/Controller/Api/ArticleBackgroundController.php`) — **EXISTA DEJA!**:
- `GET /api/articles/{id}/background` — returneaza background generat
- `POST /api/articles/{id}/background/apply` — aplica background pe articol
- **Baza pe care construim** — trebuie extins, nu creat de la zero

**Modele folosite**:
- Classifier: `claude-haiku-4-5-20251001`
- Content/Research/Briefing: `claude-sonnet-4-20250514` / `claude-haiku-4-5-20251001`
- Translation/SEO/Clustering: `gemini-2.5-flash`

**Prompts**: Toate embedded in PHP (heredoc strings in metode sau enum `AiAgentType::getSystemPrompt()`). Nu exista fisiere externe de prompt. `AiPromptTemplate` entity suporta templates din DB dar e folosit doar de orchestrator.

**Dependente composer**: Niciun SDK AI instalat. Totul e via `symfony/process` (CLI) si `symfony/http-client` (HTTP API).

### Lipseste

1. **Activarea `AnthropicApiClient`** pentru pipeline-ul de polish:
   - Setare `ANTHROPIC_API_KEY` in `.env.local`
   - Schimbarea alias-ului DI de la `ClaudeCliClient` la `AnthropicApiClient` pentru cazul de polish (sau inregistrarea unui nou provider cu tag-ul `ai.provider`)
   - Alternativ: un nou `AiAgentType::BACKGROUND_POLISH` care sa routeze explicit la `AnthropicApiClient` via `AnthropicProvider`

2. **Prompt templates pentru 3 blocuri**:
   - `background_chronological` — cronologia evenimentelor (Gemini crunch)
   - `background_explanatory` — context explicativ (Gemini crunch)
   - `background_moldova_perspective` — perspectiva Moldova (Gemini crunch)
   - `background_polish` — refinement final (Claude Sonnet polish)
   - Recomandat: stocate in `AiPromptTemplate` entities (exista deja suportul)

3. **`DualLlmBackgroundPipeline` service** — orchestreaza:
   - Step 1: Context assembly din ES + cluster data
   - Step 2: Gemini CLI genereaza draft-urile celor 3 blocuri (paralel sau secvential)
   - Step 3: Claude Sonnet API face polish pe output combinat
   - Step 4: Publish rezultat via Mercure

### Riscuri

- **`AnthropicApiClient` netestat in productie**: Exista dar nu a fost niciodata activat. Are retry logic dar trebuie testat cu trafic real.
- **Rate limits Anthropic API**: Claude Sonnet are rate limit per org. La 50 articole/zi, estimam ~50 cereri/zi — well within limits.
- **Fallback strategy**: Daca Claude API e down, pipeline-ul trebuie sa ofere varianta Gemini-only (fara polish step).
- **Cost estimate**: Claude Sonnet 4 pricing: ~$3/M input, ~$15/M output tokens. Per background (~2K input, ~1K output): ~$0.021/request. La 50/zi: **~$1.05/zi** sau **~$31.5/luna**.
- **Gemini CLI subprocess timeout**: Default 60s in `BackgroundGeneratorService`. Pentru 3 blocuri, trebuie 120-180s.

---

## 3. Async Pipeline (Messenger + RabbitMQ)

### Exista

**11 transporturi** configurate in `config/packages/messenger.yaml`:

| Transport | Queue | Retry | Purpose |
|-----------|-------|-------|---------|
| `async` | default | default | General |
| `ai_async` | `ai_async` | 3 retries, 5s delay, 3x multiplier, 120s max | **AI operations** |
| `editorial` | `editorial` | 3 retries, 3s delay, 2x, 45s max | Editorial processing |
| `translations_critical` | custom | 3 retries, 2s, 2x, 30s max | Breaking translations |
| `translations_urgent/high/normal` | custom | 3 retries | Priority translations |
| `scraping` | `scraping` | 2 retries | RSS/web scraping |
| `cache_async` | default | default | Cache ops |
| `stats_async` | default | default | Statistics |
| `failed` | `failed` | — | Dead letter |

**24 message classes** + **24 handlers** — inclusiv clustering: `ClusterPressReleaseMessage`, `TriggerClusterRunMessage`, `TriggerClusterScoringMessage`, `SummarizeClusterMessage`.

**Routing relevant**:
- `SummarizeClusterMessage` -> `ai_async`
- `TranslateArticleMessage` -> `ai_async`
- `OptimizeSeoMessage` -> `ai_async`
- `ClusterPressReleaseMessage` -> `editorial`
- `TriggerClusterScoringMessage` -> `editorial`

**Supervisor**: 4 programe configurate:
- `messenger-async`: consuma `async editorial scraping`
- `messenger-translations`: consuma cele 4 queue-uri de traducere
- `messenger-scheduler`: consuma `scheduler_editorial`
- `mercure`: hub-ul Mercure

**Scheduler**: `EditorialScheduleProvider` ruleaza `TriggerClusterScoringMessage` la fiecare 15 minute cu `autoPromote=true`.

**Transport DSN**: `doctrine://default?auto_setup=0` — toate transporturile folosesc **Doctrine (PostgreSQL)**, NU RabbitMQ. Functioneaza dar cu throughput inferior.

### Lipseste

1. **`GenerateBackgroundMessage`** (`src/Message/Editorial/`):
   ```php
   final class GenerateBackgroundMessage {
       public function __construct(
           public readonly int $clusterId,
           public readonly ?int $articleId = null,
           public readonly float $importanceScore = 0.0,
       ) {}
   }
   ```

2. **`GenerateBackgroundHandler`** (`src/MessageHandler/Editorial/`):
   - Incarca cluster + press releases + topics din DB
   - Apeleaza `TopicDossierAssemblyService` pentru context
   - Apeleaza Gemini CLI pentru generare 3 blocuri
   - Apeleaza Claude Sonnet API pentru polish
   - Publica rezultat via Mercure
   - Stocheaza in Redis cache (TTL 24h) sau in entitate dedicata

3. **Routing**: `GenerateBackgroundMessage` -> `ai_async`

4. **Event dispatch** la scoring: `TriggerClusterScoringHandler` trebuie modificat sa dispatch `GenerateBackgroundMessage` cand `importanceScore >= threshold` (currently nu emite nimic — seteaza scor + apeleaza `AutoPromoteService` inline).

### Riscuri

- **CRITICAL: `ai_async` transport NU e consumat de niciun supervisor worker**. `messenger-async` consuma doar `async editorial scraping`. Messages rutate la `ai_async` (TranslateArticle, OptimizeSeo, SummarizeCluster) se acumuleaza fara a fi procesate. **Trebuie adaugat un worker supervisor dedicat** sau extins `messenger-async` cu `ai_async`.
- **Queue congestion**: Daca 50+ clustere trec threshold-ul simultan (ex: eveniment major), pipeline-ul AI va genera 50+ cereri concurente Gemini + Claude. Trebuie rate limiting pe handler (ex: `sleep(2)` intre cereri) sau concurrency limit pe worker.
- **Doctrine transport throughput**: Pentru volumul actual (~50 msg/zi pe `ai_async`), Doctrine e suficient. Dar daca creste, migrare la RabbitMQ e recomandata.

---

## 4. Context Assembly (ES + Redis)

### Exista

**Elasticsearch — Press Releases Index** (`deschide_press_releases`):
- Mapping: `press_release_id`, `title`, `content`, `lead` (text, multilingual analyzer), `source_name`, `source_hostname`, `category_slug`, `detected_language` (keyword), `created_at`, `received_at` (date)
- Indexare automata: `PressReleaseIndexListener` (Doctrine postPersist/postUpdate)
- MLT queries: `ElasticsearchClusterFinder` — cauta PR-uri similare (threshold 0.40, window 48h)

**Elasticsearch — Articles Index** (`deschide_articles_{ro|en|ru}`):
- Full-text search cu analyzer locale-specific (Romanian/English/Russian)
- Campuri: `title`, `lead`, `content`, `topic_ids`, `topic_titles`, `topic_slugs`, `tags`, `authors` (nested)
- `ArticleSearchService` — `search()`, `searchArchivedArticles()`, `suggest()` (autocomplete)
- `BackgroundGeneratorService` deja foloseste `SearchService` (ES) pentru 5 articole similare

**Redis Cache** — 8 pool-uri configurate:

| Pool | TTL | Purpose |
|------|-----|---------|
| `deschide.cache` | 300s | API responses (articles, categories) |
| `deschide.stats` | 60s | Real-time statistics |
| `doctrine.result_cache_pool` | 600s | Doctrine query results |
| `doctrine.query_cache_pool` | 86400s | DQL parsing cache |
| `cache.metadata` (APCu) | 86400s | Entity metadata |

Toate Redis pools pe `redis://localhost:6379/1`, prefix `deschide_news`.

### Lipseste

1. **`TopicDossierAssemblyService`** — serviciu nou care aduna context din multiple surse:
   - **ES query**: Articole anterioare pe acelasi topic/cluster (sorted by `published_at DESC`, limit 20)
   - **ES query**: Press releases din cluster (deja disponibil via `StoryCluster.pressReleases`)
   - **DB query**: `StoryCluster.summaryMedium` + `whyItMatters` + `keyFacts`
   - **DB query**: Source credibility weights pentru prioritizare
   - Output: structured context document (Markdown) cu cronologie, surse, scor credibilitate

2. **Redis cache pool dedicat** pentru background proposals:
   ```yaml
   deschide.ai_background:
       adapter: cache.adapter.redis_tag_aware
       provider: 'redis://localhost:6379/1'
       default_lifetime: 86400  # 24h TTL
   ```

3. **ES query pt cronologie topic**: Query care returneaza articolele anterioare ordonate cronologic pe un topic/cluster, cu aggregare pe source_name si timeline. `ArticleSearchService` nu are o metoda dedicata.

### Riscuri

- **Query performance**: Index `deschide_press_releases` are shard-uri configurate pt dev (1 shard, 0 replicas). La 14K+ documente, MLT queries raman rapide (<100ms). Dar cand se adauga query-uri de cronologie cu aggregations, trebuie monitorizat.
- **Cache invalidation**: Daca background-ul e cached in Redis, trebuie invalidat cand: (a) se adauga noi PR-uri la cluster, (b) scorul clusterului se schimba semnificativ, (c) editorul modifica articolul.
- **Context window limits**: Daca un topic are 50+ articole anterioare, context-ul trimis la AI depaseste limita. Trebuie truncat inteligent (top 15-20 articole, sorted by recency + importance).

---

## 5. Event System

### Exista

**3 domain events**:
- `ArticlePublishedEvent` — dispatched in `ArticleCreateProcessor` si `ArticleUpdateProcessor`
- `ArticleUpdatedEvent` — dispatched in `ArticleUpdateProcessor`
- `ArticleAutoCreatedEvent` — dispatched in `ArticleCreateProcessor`

**16 EventSubscribers** + **8 EventListeners** — inclusiv:
- `ArticlePostPersistListener` — dispatches `IngestArticleMessage` la fiecare Article nou (relevant: trigger pt background dupa creare din PR)
- `ArticleTranslationTriggerSubscriber` — auto-triggers translation la publish
- `PressReleaseIndexListener` — indexeaza PR-urile in ES la persist/update (input pt clustering)

**Clustering scoring flow** (actual):
```
EditorialScheduleProvider (every 15 min)
  -> TriggerClusterScoringMessage
  -> TriggerClusterScoringHandler
    -> ImportanceScoreCalculator.calculate(cluster) // returneaza float
    -> cluster.setImportanceScore(score) // seteaza pe entitate
    -> AutoPromoteService.isEligible(cluster) // verifica threshold
    -> AutoPromoteService.promoteCluster(cluster) // creeaza PressRelease din cluster
    -> entityManager.flush() // persist
    // *** NU EMITE NICIUN EVENT ***
```

### Lipseste

1. **`ClusterScoredAboveThresholdEvent`** — event dispatched cand `importanceScore >= threshold`:
   ```php
   final class ClusterScoredAboveThresholdEvent {
       public function __construct(
           public readonly int $clusterId,
           public readonly float $score,
           public readonly float $threshold,
       ) {}
   }
   ```

2. **`GenerateBackgroundOnScoreListener`** — subscriber care dispatches `GenerateBackgroundMessage`:
   ```php
   #[AsEventListener(event: ClusterScoredAboveThresholdEvent::class)]
   final class GenerateBackgroundOnScoreListener {
       public function __invoke(ClusterScoredAboveThresholdEvent $event): void {
           $this->messageBus->dispatch(new GenerateBackgroundMessage(
               clusterId: $event->clusterId,
               importanceScore: $event->score,
           ));
       }
   }
   ```

3. **Modification in `TriggerClusterScoringHandler`**: Dupa `setImportanceScore()`, dispatch event:
   ```php
   if ($newScore >= $threshold) {
       $this->eventDispatcher->dispatch(new ClusterScoredAboveThresholdEvent(
           $cluster->getId(), $newScore, $threshold
       ));
   }
   ```

4. **`BackgroundGenerationCompleteEvent`** — emis de handler cand background-ul e gata, pt Mercure notification.

### Riscuri

- **Event ordering**: `TriggerClusterScoringHandler` proceseaza multiple clustere intr-un singur run. Event-urile trebuie dispatched dupa flush, nu inainte (altfel entitatea nu are ID-ul persistat).
- **Duplicate dispatch**: Daca scoringul ruleaza la fiecare 15 minute si scorul ramane > threshold, event-ul se emite repetat. Trebuie guard: dispatch doar cand scorul **trece** threshold-ul (e.g., `wasAboveThreshold = false` -> `isAboveThreshold = true`), sau flag `backgroundGenerated` pe StoryCluster.
- **Cascade**: Event-ul nu trebuie sa blocheze scoring-ul — `GenerateBackgroundMessage` e async (Messenger), deci scoring-ul continua imediat.

---

## 6. Mercure Integration

### Exista

**Hub**: Mercure (Caddy-based) pe port 3000, supervizat de systemd/supervisor.

**Config** (`config/packages/mercure.yaml`):
```yaml
mercure:
    hubs:
        default:
            url: '%env(default::MERCURE_URL)%'
            public_url: '%env(default::MERCURE_PUBLIC_URL)%'
            jwt:
                secret: '%env(MERCURE_JWT_SECRET)%'
                publish: '*'
```

**4 publisheri existenti**:

| Service | Topic | Tip | Transport |
|---------|-------|-----|-----------|
| `AiMercureService` | `/ai/conversations/{id}` | Private | Symfony `HubInterface` |
| `LiveTextNotificationService` | `deschide_news/live_text/{id}` | Public | Raw HTTP POST |
| `NotificationService` | `deschide_news/admin/notifications/{username}` | Private | Raw HTTP POST |
| `ArticleProcessorTrait` | `deschide_news/articles` | Public | Raw HTTP POST + custom JWT |

**Frontend subscribers**:
- `useMercureSubscription` hook (generic, auto-reconnect max 5)
- `useArticleUpdates` hook (topic `deschide_news/articles`)
- AI Assistant inline EventSource (`/ai/conversations/{id}`)

**Subscriber JWT cookie**: `MercureJwtCookieListener` seteaza cookie `mercureAuthorization` la login (1h TTL, scoped `/admin/notifications/{username}`).

### Lipseste

1. **Topic nou**: `deschide_news/articles/{id}/background` — pentru notificarea editorului cand background-ul e generat.

2. **Publisher**: In `GenerateBackgroundHandler`, dupa generarea background-ului:
   ```php
   $this->aiMercureService->publishBackgroundReady($articleId, $blocks);
   ```

3. **Frontend subscriber**: Hook `useBackgroundProposal(articleId)` care asculta pe topic-ul specific si actualizeaza UI-ul:
   ```typescript
   const { proposal, status } = useBackgroundProposal(articleId);
   ```

4. **Subscriber JWT scope**: Cookie-ul actual e scoped doar pe `deschide_news/admin/notifications/{username}`. Trebuie extins cu `deschide_news/articles/*/background` sau setat un cookie separat.

### Riscuri

- **Auth tokens**: Cookie-ul Mercure actual (1h TTL) acopera doar notifications. Editorul trebuie sa aiba subscribe permission pe topic-ul de background. Solutie: extindere claim in `MercureJwtCookieListener` sau topic public.
- **Connection management**: `useMercureSubscription` hook are max 5 reconnect attempts cu 3s delay. Daca editorul are pagina deschisa mult timp, connection poate fi pierduta. Trebuie heartbeat sau reconnect infinit pt background.
- **Mixed publishers**: 2 publisheri folosesc raw HTTP POST, 1 foloseste `HubInterface`, 1 foloseste custom JWT. Recomandat: standardizare pe `AiMercureService` (care foloseste `HubInterface`).

---

## 7. Frontend

### Exista

**Article Form** — modular dupa Sprint 39:

| Component | Lines | Responsabilitate |
|-----------|-------|-----------------|
| `ArticleForm.tsx` | 174 | Orchestrator |
| `useArticleForm.ts` | 548 | Form state, validation, submission |
| `ArticleContentSection.tsx` | 145 | Title, slug, lead (TinyMCE), content (TinyMCE) |
| `ArticleMetadataSection.tsx` | 271 | Category, status, badge, authors |
| `ArticleMediaSection.tsx` | 90 | Images |
| `ArticleRelationsSection.tsx` | 136 | Tags + Topics (collapsible) |
| `ArticleSeoSection.tsx` | 175 | Meta, preview, AI SEO (collapsible) |

**Rich text**: TinyMCE 8.2.2 (self-hosted GPL), lazy-loaded, 13 plugins, image insertion from attached images.

**State management**: Pure React `useState` (30+ state vars in hook). `@tanstack/react-query` ^5.90.12 instalat dar partial adoptat.

**Collapsible section pattern**: Bine stabilit in SEO, Tags, Topics — button cu chevron rotativ + conditional render. Replicabil pt `BackgroundProposalPanel`.

**SSE/Mercure patterns**: 3 distincte:
- `useMercureSubscription` — generic hook cu auto-reconnect
- `useArticleUpdates` — article-specific
- AI Assistant — inline EventSource

**API submission**: Server Actions (`createArticleAction`/`updateArticleAction`) -> DAL (`authenticatedFetch`) -> Symfony API.

**Article type** (`lib/types/article.ts`) — **NU contine `background`**. Interfata `ArticleFormData` de asemenea.

**Key versions**: Next.js 16.2.3, React 19.2.0, TypeScript 5.9, Tailwind CSS 4, Sentry 10.45.0, @dnd-kit/core 6.3.1, zod 4.1.13, @tanstack/react-query 5.90.12, tinymce 8.2.2, @tinymce/tinymce-react 6.3.0.

### Lipseste

1. **`BackgroundProposalPanel.tsx`** — componenta collapsible in `ArticleForm`:
   - Afiseaza statusul generarii (loading spinner / ready / not available)
   - SSE subscription pe `deschide_news/articles/{id}/background`
   - 3 blocuri cu preview: Cronologic, Explicativ, Perspectiva Moldova
   - Fiecare bloc: checkbox select + edit button + preview text
   - Buton "Insereaza selectate" care adauga in `Article.background` field
   - Buton "Regenereaza" care triggereaza re-generare
   - Design: collapsible ca SEO/Tags (pattern existent)

2. **Camp `background` in form**: Trebuie adaugat:
   - In `ArticleFormData` interfata
   - In `useArticleForm` hook (state + dirty tracking)
   - In `createArticleAction` / `updateArticleAction`
   - In `Article` type (`lib/types/article.ts`)
   - In TinyMCE section sau ca sectiune separata

3. **`useBackgroundProposal` hook** — SSE subscription + state management:
   ```typescript
   function useBackgroundProposal(articleId: number | null) {
     // Subscribe to Mercure topic
     // Return: { blocks, status, regenerate(), isLoading }
   }
   ```

4. **TinyMCE insertion**: Metoda de a insera blocuri HTML in editorul de content. TinyMCE are `editor.insertContent(html)` API — trebuie ref catre instanta editorului.

### Riscuri

- **State management complexity**: `useArticleForm` are deja 30+ state vars. Adaugarea background proposal cu SSE adauga complexitate. Recomandat: hook separat `useBackgroundProposal` cu state izolat.
- **TinyMCE insertion**: Inserarea in rich text editor e tricky — trebuie gestionat cursor position, undo history, dirty state. TinyMCE are API (`editor.insertContent`, `editor.undoManager`) dar trebuie acces la instanta editorului via ref.
- **Optimistic updates**: Daca editorul selecteaza blocuri si salveaza inainte ca background-ul sa fie complet generat, trebuie gestionat conflictul.
- **Mobile layout**: Admin panel-ul e responsive (sidebar hidden pe mobile). Background panel trebuie sa functioneze si pe mobile.

---

## 8. System Prompts

### Exista

**Prompt-uri embedded** (PHP heredoc/strings in servicii):
- `ClusterSummaryService` — "senior news editor at Deschide.md", JSON output cu `summary_short`, `summary_medium`, `why_it_matters`, `key_facts`. Max 10 PR-uri, 500 chars fiecare.
- `BackgroundGeneratorService` — "senior editor at Deschide News", 1 paragraf context, 5 articole similare din ES.
- `DailyBriefingService` — briefing zilnic/dimineata, 400-500 cuvinte, top 5 clustere.
- `DossierGenerationService` — cronologie, actori cheie, analiza, intrebari nerezolvate, recomandari editoriale.
- `InternalSummaryService` — TL;DR in 3-5 bullet points, max 3000 chars input.
- `AiAgentType` enum — 5 system prompts pt RESEARCH/CONTENT/TRANSLATION/BRIEFING/SEO.
- `AiOrchestratorService::CLASSIFIER_SYSTEM_PROMPT` — intent classification.

**Toate** in romana cu diacritice comma-below (s, t). **Toate** specifica context editorial moldovenesc (Deschide.md, R. Moldova).

**`AiPromptTemplate`** entity suporta templates din DB cu field substitution (`{field}` placeholders), dar e folosit doar de AiOrchestratorService pt chat interface.

### Lipseste

3 prompt-uri noi pentru generarea celor 3 blocuri + 1 prompt de polish.

### Recomandat

Bazat pe pattern-ul `ClusterSummaryService` (cel mai apropiat):

**Prompt 1 — Cronologic** (Gemini crunch):
```
Esti editor senior la Deschide.md. Primesti o lista de articole/comunicate pe un subiect.
Genereaza o CRONOLOGIE a evenimentelor anterioare, de la cel mai vechi la cel mai recent.
Format: lista numerotata cu data + eveniment. Max 8 evenimente. 150-200 cuvinte.
Surse: citeaza publicatia/sursa pentru fiecare eveniment.
Limba: romana, diacritice comma-below (s, t).
```

**Prompt 2 — Explicativ** (Gemini crunch):
```
Esti editor senior la Deschide.md. Primesti context despre un subiect de actualitate.
Genereaza un BACKGROUND EXPLICATIV: de ce conteaza acest subiect, ce trebuie sa stie cititorul.
Structura: 2-3 paragrafe scurte. Ton neutral-jurnalistic. Max 200 cuvinte.
Nu include opinii, doar fapte verificabile cu surse.
```

**Prompt 3 — Perspectiva Moldova** (Gemini crunch):
```
Esti editor senior la Deschide.md, specializat in contextul R. Moldova.
Genereaza un paragraf care explica IMPACTUL LOCAL al subiectului pentru cititorii din Moldova.
Include: cum afecteaza cetatenii/economia/politica moldoveneasca. Max 100 cuvinte.
Daca subiectul nu are legatura directa cu Moldova, scrie "N/A".
```

**Prompt 4 — Polish** (Claude Sonnet):
```
Esti editor-sef la Deschide.md. Primesti 3 blocuri de background generate automat.
Verifica: acuratete, coerenta, ton neutral-jurnalistic, diacritice corecte.
Corecteaza erori factuale evidente. Uniformizeaza stilul.
NU adauga informatii noi. NU modifica datele sau cifrele.
Returneaza JSON: {chronological: string, explanatory: string, moldova_perspective: string,
quality_score: float, issues: string[]}
```

---

## 9. Validation & Quality

### Exista

- Symfony Validator constraints pe Article: `UniqueEntity(slug)`, `ReservedSlug`, `Assert\Callback(validatePublishAt)`
- `ArticleProcessorTest` (43 tests), `BackgroundGeneratorServiceTest` (exists), `ClusterSummaryServiceTest` (exists)
- `TranslationEvaluatorService` — pattern de evaluare calitate AI (scor 0-1, issues list, needs_revision flag, max 2 iteratii) — **replicabil pentru background**
- 377 fisiere de test total (329 Unit, 14 Integration, 21 Functional, 3 Performance, 4 Service, 3 Smoke)
- 8 test files dedicate AI/Clustering: `AiOrchestratorServiceTest`, `AiProviderRegistryTest`, `ClusterSummaryServiceTest`, `ClusteringServiceTest`, `ElasticsearchClusterFinderTest`, `BackgroundGeneratorServiceTest`, `StoryClusterTest`
- 53 test files dedicate Article (entities, providers, processors, commands, services, controllers)

### Lipseste

1. **`BackgroundValidator`** — validator Symfony care verifica:
   - Lungime minima/maxima per bloc
   - Prezenta surselor/citatiilor
   - Limba corecta (romana)
   - Nu contine opinii (heuristic: lipsa "consider", "cred", "in opinia mea")
   - Quality score din prompt-ul de polish > threshold

2. **Source citation checker**: Verificare ca fiecare afirmatie din cronologie are o sursa citata. Pattern: regex pe `(sursa: ...)` sau structured JSON cu `sources[]` per bloc.

3. **Hallucination detection**: Verificare ca datele/evenimentele mentionate in background exista in contextul furnizat (articole ES + PR-uri cluster). Cross-reference cu input-ul original.

### Riscuri

- **Hallucination detection accuracy**: AI-ul poate genera date/evenimente care nu sunt in context. Cross-reference automata cu ES poate da false positives (articol relevat dar nu exact pe subiect).
- **False positives pe "opinii"**: Heuristic-ul simplu poate marca incorect fraze factuale ca opinii.
- **Quality score threshold**: Trebuie calibrat pe primele 50-100 generari inainte de a seta threshold automat.

---

## 10. Dependente externe noi

| Dependenta | Tip | Status | Actiune necesara |
|------------|-----|--------|-----------------|
| Anthropic API key | Credential | **LIPSESTE** | Adaugare `ANTHROPIC_API_KEY` in `.env.local` |
| `AnthropicApiClient` | PHP class | **EXISTA** dar dormant | Schimbare DI alias sau nou provider |
| Anthropic SDK PHP | Composer | **NU e necesar** | `AnthropicApiClient` foloseste Symfony HttpClient direct |
| Gemini CLI | Binary | **INSTALAT** (`/usr/bin/gemini`) | Nimic |
| Claude CLI | Binary | **INSTALAT** (`/home/radu/.local/bin/claude`) | Nimic |

**Pricing estimate** (50 articole/zi):
- Gemini 2.5 Flash (crunch, 3 blocuri x 50): ~$0 (free tier generous)
- Claude Sonnet 4 (polish, 50 cereri): ~$1.05/zi = **~$31.5/luna**
- Total: **~$32/luna**

---

## 11. Estimare efort

| # | Componenta | Complexitate | Zile | Depinde de |
|---|-----------|-------------|------|-----------|
| 1 | `Article.background` field + migrare | S | 0.5 | — |
| 2 | `ClusterScoredAboveThresholdEvent` + dispatch in scorer | S | 0.5 | — |
| 3 | Fix supervisor: `ai_async` worker | S | 0.25 | — |
| 4 | `TopicDossierAssemblyService` (context din ES + DB) | M | 1 | — |
| 5 | Prompt templates (3 blocuri + polish) | M | 0.5 | — |
| 6 | `DualLlmBackgroundPipeline` service | L | 1.5 | 4, 5 |
| 7 | `GenerateBackgroundMessage` + handler | M | 1 | 6 |
| 8 | Activare `AnthropicApiClient` + config | S | 0.5 | — |
| 9 | Mercure publisher + topic background | S | 0.5 | 7 |
| 10 | Frontend: `BackgroundProposalPanel` + SSE hook | L | 1.5 | 9 |
| 11 | Frontend: TinyMCE block insertion + form integration | M | 1 | 10 |
| 12 | Backend + frontend tests | M | 1 | 7, 10, 11 |
| **Total** | | | **~9 zile** | |

---

## 12. Ordine recomandata de implementare

```
Faza 1 — Fundatie (2 zile, paralelizabil):
+-- [1] Article.background field + migrare
+-- [2] ClusterScoredAboveThresholdEvent + dispatch
+-- [3] Fix supervisor ai_async worker
+-- [8] Activare AnthropicApiClient + ANTHROPIC_API_KEY

Faza 2 — Pipeline Backend (3 zile, secvential):
+-- [4] TopicDossierAssemblyService
+-- [5] Prompt templates
+-- [6] DualLlmBackgroundPipeline
+-- [7] GenerateBackgroundMessage + handler

Faza 3 — Real-time + Frontend (3 zile, partial paralelizabil):
+-- [9] Mercure publisher + topic
+-- [10] BackgroundProposalPanel + SSE hook  <- parallel cu 9
+-- [11] TinyMCE block insertion + form integration

Faza 4 — Quality (1 zi):
+-- [12] Backend + frontend tests
```

**Critical path**: 1 -> 4 -> 6 -> 7 -> 9 -> 10 -> 11 -> 12 (8 zile secvential pe critical path)

**Paralelizabil**: [1,2,3,8] in paralel (Faza 1), [9,10] partial in paralel (Faza 3)

---

## Appendix A: Descoperiri CRITICE care necesita actiune imediata

1. **`ai_async` transport nu e consumat** — Messages SummarizeCluster, TranslateArticle, OptimizeSeo se acumuleaza fara procesare. Fix: adaugare `ai_async` la `messenger-async` supervisor config sau worker dedicat.

2. **`ANTHROPIC_API_KEY` lipseste** din `.env.example` si `.env.local` — `AnthropicApiClient` e functional dar dormant. Fix: adaugare key + optional: schimbare alias DI.

3. **Scheduler workers lipsesc** — `scheduler_translation`, `scheduler_tag_maintenance`, `scheduler_default` nu sunt in supervisor. Doar `scheduler_editorial` e consumat.

4. **`BackgroundGeneratorService` + `ArticleBackgroundController` EXISTA deja** — nu trebuie create de la zero. Trebuie extinse pentru 3 blocuri modulare + dual-LLM pipeline.

## Appendix B: Versiuni exacte relevante

**Backend** (`composer.json`):
- `symfony/framework-bundle`: 8.0.*
- `symfony/messenger`: 8.0.*
- `symfony/mercure-bundle`: ^0.3.10
- `symfony/http-client`: 8.0.*
- `symfony/process`: 8.0.*
- `doctrine/orm`: ^3.5
- `api-platform/core`: ^3.4
- `stof/doctrine-extensions-bundle` (Gedmo): ^1.14

**Frontend** (`package.json`):
- `next`: 16.2.3
- `react`: 19.2.0
- `typescript`: ^5.9.3
- `tailwindcss`: ^4.1.18
- `@sentry/nextjs`: ^10.45.0
- `tinymce`: ^8.2.2
- `@tinymce/tinymce-react`: ^6.3.0
- `@tanstack/react-query`: ^5.90.12
- `zod`: ^4.1.13
