# RAPORT: Analiză Infrastructură Tags + Agent SEO — Deschide News App

**Data:** 2026-04-01
**Branch:** develop
**Scop:** Audit complet al infrastructurii existente pentru Tags/Keywords și proiectarea unui Agent SEO dedicat

---

## 1. Ce Există Deja

### Verdictul Principal: Infrastructura Tag este aproape completă

| Componentă | Status | Detalii |
|-----------|--------|---------|
| **Entitate Tag** | **EXISTĂ** | `App\Entity\Tag` — name, slug, description (Gedmo Translatable), usageCount, timestamps |
| **Relație Article ↔ Tags** | **EXISTĂ** | ManyToMany bidirecțional, pivot table `article_tag` |
| **Tag Repository** | **EXISTĂ** | findPopularTags, findByNameSearch, findOrCreateByName, findUnusedTags |
| **Tag Service** | **EXISTĂ** | syncArticleTags, addTags, removeTags, mergeTags, getRelatedTags, statistics, cleanup |
| **Tag Provider** | **EXISTĂ** | API Platform State Provider cu Gedmo locale hint + cache |
| **Tag Processor** | **EXISTĂ** | API Platform State Processor cu suport traduceri |
| **Tag API** | **EXISTĂ** | `/api/tags` — GET, POST, PUT, DELETE cu search/order filters |
| **Tag Messenger** | **EXISTĂ** | 3 mesaje async: RecalculateTagCounts, CleanupUnusedTags, CheckOrphanedTags |
| **Tag Frontend Types** | **EXISTĂ** | Tag, TagCollection, TagStatistics, GetTagsParams interfaces |
| **Tag Frontend API** | **EXISTĂ** | fetchTags, searchTags, fetchPopularTags, fetchRelatedTags, fetchArticlesByTag |
| **Tag Frontend Components** | **EXISTĂ** | TagBadge, TagList, TagCloud |
| **Tag Public Pages** | **EXISTĂ** | `/[locale]/tags` (listing) + `/[locale]/tags/[slug]` (detail) |
| **metaTitle/metaDescription** | **EXISTĂ** | Câmpuri pe Article, integrate în traducere Gemini |
| **SEO Section Admin** | **EXISTĂ** | Secțiune colapsabilă în ArticleForm cu meta fields + Google Preview |
| **Agent SEO Claude Code** | **EXISTĂ** | `.claude/agents/seo-specialist.md` — agent manual, NU automat |
| **Agent SEO Gemini** | **NU EXISTĂ** | Niciun agent automat pentru generare SEO |
| Tags în RSS feed | **N/A** | Nu am verificat — RSS feed extern, nu intern |

### Date DB Actuale

| Tabel | Rânduri | Observații |
|-------|---------|------------|
| `tags` | **51** | Toate cu traduceri EN + RU (50 name + 50 slug + 44-49 description per locale) |
| `article_tag` | **0** | Niciun articol nu are tag-uri asociate |
| `articles` | **360** | Articole importate din CSV/email |
| `ext_translations` (Tag) | **293** | name×2 + slug×2 + description×2 (50+50+50+50+44+49) |

### Schema Tag Entity (actuală)

```
tags
├── id: integer (PK, auto-increment)
├── name: varchar(100) NOT NULL — @Gedmo\Translatable
├── slug: varchar(100) NOT NULL UNIQUE — @Gedmo\Translatable, @Gedmo\Slug(fields=["name"])
├── description: text NULLABLE — @Gedmo\Translatable
├── usage_count: integer DEFAULT 0
├── created_at: timestamp NOT NULL — @Gedmo\Timestampable(create)
└── updated_at: timestamp NOT NULL — @Gedmo\Timestampable(update)

article_tag (pivot)
├── article_id: integer FK → articles(id)
└── tag_id: integer FK → tags(id)

Indexes: idx_tag_slug, idx_tag_usage_count
API Groups: tag:read, tag:write, article:read
```

### Ce LIPSEȘTE (Gap Analysis)

| Componentă | Status | Impact |
|-----------|--------|--------|
| **Tag selector în ArticleForm** | **LIPSEȘTE** | Redactorii nu pot asocia tag-uri la articole din admin |
| **Admin tag management page** | **LIPSEȘTE** | Nu există `/admin/tags` CRUD |
| **Article-Tag linking** | **GOL** | 0 din 360 articole au tag-uri — tag-urile sunt orfane |
| **Tag-uri pe pagina de articol** | **LIPSEȘTE** | ArticleHeader nu afișează tag-uri |
| **Câmp `type` pe Tag** | **LIPSEȘTE** | Nu se distinge editorial/seo/auto |
| **Câmp `color` pe Tag** | **LIPSEȘTE** | Nu se pot colora badge-urile per tag |
| **Agent SEO automat (Gemini)** | **LIPSEȘTE** | Nu există generare automată metaTitle/metaDescription/tags RO |
| **ArticleProvider eager load tags** | **DE VERIFICAT** | Posibil N+1 la listare articole cu tags |

---

## 2. Evaluare Propunere: Entitate Tag

### Ce trebuie adăugat vs. ce există

Propunerea din prompt includea crearea unei entități Tag de la zero. **Nu este necesar** — entitatea există deja și este bine structurată. Evaluez doar câmpurile lipsă propuse:

| Câmp Propus | Evaluare | Recomandare |
|------------|----------|-------------|
| `name` (translatable) | **EXISTĂ** | ✅ Nici o acțiune |
| `slug` (translatable, auto) | **EXISTĂ** | ✅ Nici o acțiune |
| `description` (translatable) | **EXISTĂ** | ✅ Nici o acțiune |
| `usageCount` (denormalized) | **EXISTĂ** | ✅ Nici o acțiune |
| `type: editorial/seo/auto` | **NU EXISTĂ** | ⚠️ **OPȚIONAL** — vezi evaluare mai jos |
| `color: varchar(7)` | **NU EXISTĂ** | ❌ **NU RECOMAND** — culoarea se derivă din categorie, nu din tag |
| `isPrimary` pe pivot | **NU EXISTĂ** | ❌ **NU RECOMAND** — adaugă complexitate fără beneficiu clar |
| `addedBy` pe pivot | **NU EXISTĂ** | ⚠️ **OPȚIONAL** — util doar dacă agentul SEO creează tag-uri |

### Evaluare câmp `type` pe Tag

**Pro:**
- Permite filtrare: redactorii văd doar tag-urile editoriale, agentul SEO gestionează cele auto
- Previne poluarea: tag-urile auto-generate nu apar pe site până nu sunt aprobate

**Contra:**
- Adaugă complexitate: toate query-urile trebuie filtrate pe type
- Redundant cu workflow-ul: dacă agentul SEO doar sugerează (nu creează automat), `type` nu e necesar
- 51 tag-uri existente ar trebui toate clasificate retroactiv

**Verdict:** **Amână.** Implementează agentul SEO fără `type`. Dacă după 1 lună redactorii cer filtrare, adaugă atunci. YAGNI.

### Propunere pivot table actualizat

**NU recomand** modificări la pivot table. `article_tag` cu 2 coloane (article_id, tag_id) este suficient. Câmpurile `isPrimary` și `addedBy` adaugă complexitate fără beneficiu SEO real — Google nu face distincție.

---

## 3. Arhitectura Propusă: Agent SEO Dedicat

### Analiză Interacțiune cu Pipeline-ul de Traducere

**Stare actuală:**
1. Redactor scrie articol RO → status = new/draft
2. Status → `published` → `ArticleTranslationTriggerSubscriber` (Doctrine preUpdate)
3. Dispatch `TranslateArticleMessage` → queue `translations`
4. `TranslateArticleHandler` → Gemini CLI → traduce title, slug, lead, content, **metaTitle**, **metaDescription** din RO → EN + RU
5. `TranslationResultProcessor` → salvează traduceri în ext_translations

**Problemă cu propunerea inițială:**
Propunerea sugera **scoaterea** metaTitle/metaDescription din `TranslateArticleHandler` și mutarea într-un agent SEO separat. **Aceasta este o idee proastă** pentru că:
- Traducerea metaTitle/metaDescription este _parte integrantă_ a traducerii articolului — ar fi contraproductiv să trimiți un al doilea request Gemini doar pentru 2 câmpuri
- Agentul de traducere deja generează metaTitle/metaDescription optimizate per locale
- Separarea ar dubla costurile API și ar adăuga latență

### Flux Corect: Agent SEO Complementar

```
Redactor scrie articol RO (title, lead, content)
    │
    ├── [MANUAL sau AUTO] Agent SEO RO
    │   └── Gemini: generează metaTitle, metaDescription, tag-uri (doar RO)
    │   └── Salvează pe Article: metaTitle, metaDescription
    │   └── Asociază tag-uri existente, creează tag-uri noi
    │
    ▼ Redactor publică articolul (status → published)
    │
    └── [EXISTENT] Translation Pipeline
        └── TranslateArticleHandler
            └── Primește metaTitle/metaDescription RO (dacă au fost setate de SEO agent)
            └── Traduce tot (title, slug, lead, content, metaTitle, metaDescription) → EN + RU
            └── Tag-urile NU se traduc automat (tag name e same-named; slug se generează per locale la creare)
```

### Componente Noi Necesare

#### Backend:

| Componentă | Tip | Descriere |
|-----------|-----|-----------|
| `OptimizeSeoMessage.php` | Message | `{articleId: int, generateMeta: bool, suggestTags: bool}` |
| `OptimizeSeoHandler.php` | MessageHandler | Orchestrează Gemini pentru SEO RO |
| `SeoPromptBuilder.php` | Service | Construiește prompt-ul Gemini (reuzează pattern-ul din TranslateArticleHandler) |
| `SeoResultProcessor.php` | Service | Parsează JSON → salvează meta + asociază/creează tag-uri |
| `ArticleSeoTriggerSubscriber.php` | EventSubscriber | Auto-trigger SEO la publicare (opțional) |
| Migrare | Migration | Niciuna necesară — schema tags este completă |

#### Frontend:

| Componentă | Tip | Descriere |
|-----------|-----|-----------|
| `TagSelector.tsx` | Component | Autocomplete multi-select în ArticleForm (search API `/api/tags/search`) |
| `Admin Tags page` | Pages | `/admin/tags` — CRUD admin (list, create, edit, delete, merge) |
| Tags pe ArticleHeader | Component | Afișare tag-uri pe pagina publică de articol |

#### Agent Claude Code:

| Fișier | Descriere |
|--------|-----------|
| `.claude/agents/seo-optimizer.md` | NU necesar — agentul SEO este un handler Symfony, nu un agent Claude Code |

### Prompt Gemini pentru SEO (propunere)

```json
{
  "articleId": 123,
  "locale": "ro",
  "title": "Titlul articolului",
  "lead": "Lead-ul articolului...",
  "content": "<p>Conținutul HTML...</p>",
  "category": "politica",
  "existingTags": ["alegeri", "parlament", "PAS"],
  "existingMetaTitle": null,
  "existingMetaDescription": null
}
```

**Răspuns așteptat:**
```json
{
  "metaTitle": "Max 60 chars, optimizat SEO",
  "metaDescription": "Max 160 chars, click-worthy",
  "suggestedTags": [
    {"name": "alegeri 2025", "isExisting": false},
    {"name": "parlament", "isExisting": true},
    {"name": "vot", "isExisting": false}
  ],
  "focusKeyword": "alegeri prezidențiale 2025"
}
```

### Un singur apel Gemini sau două?

**Recomandare: UN SINGUR APEL.** Motivele:
- MetaTitle/MetaDescription + tag-uri sunt semantic legate — contextul articolului e același
- Două apeluri = 2× latență + 2× cost
- JSON response e mic (<1KB) — nu riscă limita de 64KB

### Tag-urile noi — create automat sau sugerate?

**Recomandare: Create automat cu flag `type = 'auto'`... DAR FĂRĂ câmpul type.**

Alternativă pragmatică: **Create automat**, dar cu `usageCount = 0` până nu sunt folosite pe alte articole. Agentul de cleanup (`CheckOrphanedTagsHandler`) le va șterge după 30 zile dacă rămân nefolosite.

Argumente:
- Tag-urile sugerate necesită UI suplimentar de aprobare — complexitate adăugată
- Tag-urile create automat funcționează imediat cu infra existentă
- Dacă redactorul nu e de acord, le poate șterge din ArticleForm

### Ordinea: traducere → SEO, sau SEO → traducere?

**Recomandare: SEO (RO) → Traducere (EN + RU)**

```
1. [Auto sau manual] SEO agent: generează metaTitle/metaDescription/tags RO
2. [Auto la publicare] Traducere: traduce tot (inclusiv metaTitle/metaDescription) → EN + RU
3. Tag-urile NU se traduc automat — Gedmo le traduce la creare prin TagProcessor
```

Motivul: traducerea are nevoie de metaTitle/metaDescription RO ca input. Dacă rulează prima, traduce null → generează de la zero (mai puțin consistent).

### Trebuie decuplat metaTitle/metaDescription din TranslateArticleHandler?

**NU.** Lasă-le acolo. Handler-ul de traducere deja face:
1. Trimite metaTitle/metaDescription RO la Gemini (dacă non-null)
2. Gemini le traduce optimizat per locale
3. TranslationResultProcessor salvează traducerile

Agentul SEO complementează prin generarea valorilor RO care lipsesc. Pipeline-ul de traducere le preia automat.

---

## 4. Impact pe Codul Existent

| Fișier/Componentă | Modificare Necesară | Risc Regresie |
|-------------------|--------------------|----|
| `TranslateArticleHandler.php` | **NICIUNA** — rămâne intact | ZERO |
| `TranslationResultProcessor.php` | **NICIUNA** — rămâne intact | ZERO |
| `Article.php` | **NICIUNA** — relația tags deja există | ZERO |
| `Tag.php` | **NICIUNA** — entitatea este completă | ZERO |
| `ArticleForm.tsx` | **ADĂUGARE** — secțiune TagSelector | SCĂZUT |
| `ArticleProvider.php` | **VERIFICARE** — eager load tags dacă lipsește | SCĂZUT |
| `ArticleProcessor.php` | **ADĂUGARE** — handle tags din request body | MEDIU |
| `messenger.yaml` | **ADĂUGARE** — routing OptimizeSeoMessage | SCĂZUT |
| `ArticleHeader.tsx` | **ADĂUGARE** — afișare tags pe pagina publică | SCĂZUT |
| `lib/types/article.ts` | **VERIFICARE** — tags[] pe Article interface | SCĂZUT |

---

## 5. Componente Noi Necesare

### Backend — 5 fișiere noi

| Componentă | Tip | Linii Est. | Descriere |
|-----------|-----|-----------|-----------|
| `src/Message/OptimizeSeoMessage.php` | Message | ~25 | articleId, generateMeta, suggestTags, locale |
| `src/MessageHandler/OptimizeSeoHandler.php` | Handler | ~120 | Orchestrează Gemini CLI pentru SEO (pattern TranslateArticleHandler) |
| `src/Service/SeoPromptBuilder.php` | Service | ~60 | Construiește JSON prompt cu context articol + tag-uri existente |
| `src/Service/SeoResultProcessor.php` | Service | ~90 | Parsează răspuns Gemini, salvează meta, asociază/creează tags |
| `.gemini/agents/seo-optimizer.md` | Gemini Agent | ~100 | Prompt sistem pentru generare SEO RO |

### Backend — Modificări la fișiere existente

| Fișier | Modificare | Linii Est. |
|--------|-----------|-----------|
| `config/packages/messenger.yaml` | Routing OptimizeSeoMessage → async | +3 |
| `src/State/ArticleProcessor.php` | Handle tags din request body (tag IDs/names) | +30 |
| `src/State/ArticleProvider.php` | Eager load tags (leftJoin + addSelect) | +5 |

### Frontend — 3-4 fișiere noi + modificări

| Componentă | Tip | Linii Est. | Descriere |
|-----------|-----|-----------|-----------|
| `components/admin/articles/TagSelector.tsx` | Component | ~150 | Autocomplete multi-select cu debounce search |
| `app/[locale]/admin/tags/page.tsx` | Page | ~200 | Admin tag CRUD (list, search, stats) |
| `app/[locale]/admin/tags/[id]/edit/page.tsx` | Page | ~150 | Edit tag form |

| Fișier Existent | Modificare | Linii Est. |
|----------------|-----------|-----------|
| `ArticleForm.tsx` | Adăugare TagSelector între Categories și SEO | +40 |
| `app/actions/articles.ts` | Handle tags în create/update actions | +15 |
| `ArticleHeader.tsx` (pagina publică articol) | Afișare TagList sub titlu | +10 |
| `lib/types/article.ts` | Verificare tags[] pe interface | +2 |

---

## 6. Dependențe și Ordine Implementare

### Sprint A: Tag-uri în Admin Form + Asociere Manuală (Backend + Frontend)

**Prioritate:** CRITICĂ — fără aceasta, cele 51 tag-uri rămân orfane

1. Verifică/adaugă eager loading tags în ArticleProvider
2. Verifică/adaugă tags handling în ArticleProcessor (accept tag IDs/names)
3. Creează TagSelector component (autocomplete multi-select)
4. Integrează TagSelector în ArticleForm
5. Actualizează server actions (create + update) să trimită tags
6. Testare: asociere manuală tag-uri la articol

**Dependențe:** Niciuna
**Fișiere:** ~7 fișiere modificate/create

### Sprint B: Tag-uri pe Pagina Publică + Admin Tags CRUD

**Prioritate:** MARE

1. Afișare tags pe pagina de articol (ArticleHeader)
2. Admin Tags management page (`/admin/tags` — list, search, create, edit, delete)
3. Merge tags functionality (admin)
4. Afișare tags pe ArticleCard (opțional)

**Dependențe:** Sprint A
**Fișiere:** ~5 fișiere noi + 2 modificate

### Sprint C: Agent SEO Gemini (Generare Automată metaTitle/metaDescription + Tag Suggestions)

**Prioritate:** MEDIE — adaugă valoare dar nu blochează nimic

1. Creează `.gemini/agents/seo-optimizer.md` (prompt system)
2. Creează `OptimizeSeoMessage` + `OptimizeSeoHandler`
3. Creează `SeoPromptBuilder` + `SeoResultProcessor`
4. Configurare Messenger routing
5. [OPȚIONAL] Auto-trigger la publicare (`ArticleSeoTriggerSubscriber`)
6. [OPȚIONAL] Buton manual "Optimize SEO" în ArticleForm
7. Testare end-to-end

**Dependențe:** Sprint A (tag-uri funcționale)
**Fișiere:** ~6 fișiere noi + 2 modificate

### Sprint D: Backfill + Asociere Tag-uri la Articolele Existente

**Prioritate:** MARE — dar depinde de Sprint A

1. Comandă console `app:seo:backfill-tags` — asociază tag-urile existente la articolele din DB (360 articole × 51 tags — fuzzy match pe titlu/content)
2. Comandă console `app:seo:generate-meta` — generează metaTitle/metaDescription RO pentru articolele existente
3. Recalculare usageCount (`RecalculateTagCountsMessage`)

**Dependențe:** Sprint A + Sprint C
**Fișiere:** 2 comenzi noi

---

## 7. Întrebări Deschise

1. **Tag-uri auto-create de agentul SEO — necesită aprobare editorială?**
   - Dacă da: tag-urile sugerate trebuie un UI de review (+ câmp `type` pe Tag)
   - Dacă nu: se creează automat și redactorul le poate edita/șterge

2. **Tag-urile ar trebui traduse automat de Gemini?**
   - Tag-urile existente au deja traduceri EN/RU (populate manual/bulk)
   - Tag-urile noi create de agentul SEO ar fi doar în RO — trebuie un mecanism de traducere
   - Opțiune: agentul SEO cere Gemini să genereze tag-uri direct în toate cele 3 locale

3. **Ordinea sprint-urilor A/B/C — sunt de acord?**
   - Sprint A (manual tags) este independent și deblocant
   - Sprint C (agent SEO) poate rula în paralel cu Sprint B

4. **Backfill tag-uri existente — ce strategie?**
   - Fuzzy match pe titlu/content vs. tag name?
   - Sau un apel Gemini per articol care sugerează tag-uri? (costisitor: 360 apeluri)
   - Sau batch: trimite 10 articole la un apel Gemini?

5. **Câmpul `color` pe Tag — încă îl vrei?**
   - Analiza arată că TagBadge folosește variante CSS (default/outline/solid), nu culori per tag
   - Categoriile au culori (politica → blue, economie → green) — tag-urile ar putea moșteni culoarea categoriei dacă e necesar
   - Recomandarea mea: **nu adăuga color** — e clutter

6. **Câmpurile `isPrimary` și `addedBy` pe pivot table — încă le vrei?**
   - `isPrimary`: util doar pentru breadcrumb SEO — dar Category e deja breadcrumb-ul
   - `addedBy`: util doar pentru audit — dar git log/logs sunt deja suficiente
   - Recomandarea mea: **nu adăuga** — simplifică

---

## 8. Estimare Efort

| Sprint | Componente | Fișiere Estimate | Complexitate |
|--------|-----------|-----------------|-------------|
| **A** — Tag-uri în Admin | TagSelector, ArticleProcessor, ArticleProvider, form integration | ~7 fișiere | **MEDIE** — component autocomplete + API integration |
| **B** — Public + Admin Tags | Tags pe articol public, admin CRUD, merge | ~7 fișiere | **MEDIE** — CRUD standard |
| **C** — Agent SEO Gemini | Message, Handler, Services, Gemini prompt, messenger config | ~6 fișiere | **MARE** — Gemini integration, JSON parsing, error handling |
| **D** — Backfill | 2 comenzi console | 2 fișiere | **SCĂZUTĂ** — scripting |
| **TOTAL** | | **~22 fișiere** | — |

---

## 9. Diagrama Arhitecturală

```
                    ┌──────────────────────┐
                    │   Redactor (Admin)   │
                    └──────────┬───────────┘
                               │
                ┌──────────────┼──────────────┐
                │              │              │
                ▼              ▼              ▼
         ┌─────────┐   ┌─────────┐   ┌─────────────┐
         │ Scrie   │   │ Alege   │   │ Click      │
         │ articol │   │ tag-uri │   │ "Optimize  │
         │ RO      │   │ manual  │   │  SEO"      │
         └────┬────┘   └────┬────┘   └──────┬──────┘
              │              │               │
              ▼              ▼               ▼
         ┌─────────────────────┐    ┌───────────────┐
         │   ArticleProcessor  │    │ OptimizeSeo   │
         │   (save article +   │    │ Message       │
         │    sync tags)       │    └──────┬────────┘
         └────────┬────────────┘           │ async
                  │                        ▼
                  │               ┌────────────────────┐
                  │ publish       │ OptimizeSeoHandler  │
                  ▼               │  1. Build prompt    │
         ┌────────────────────┐   │  2. Gemini CLI      │
         │ TranslationTrigger │   │  3. Parse response  │
         │ Subscriber         │   │  4. Save meta RO    │
         └────────┬───────────┘   │  5. Create/link     │
                  │               │     tags             │
                  │ async         └────────────────────┘
                  ▼
         ┌────────────────────┐
         │ TranslateArticle   │
         │ Handler            │
         │  - title EN/RU     │
         │  - slug EN/RU      │
         │  - lead EN/RU      │
         │  - content EN/RU   │
         │  - metaTitle EN/RU │  ← preia meta RO setat de SEO agent
         │  - metaDesc EN/RU  │
         └────────────────────┘
```

---

## 10. Rezumat Decizii

| Decizie | Recomandare | Motiv |
|---------|-------------|-------|
| Creare entitate Tag | **NU** — deja există | Completă cu traduceri, service, API |
| Modificare pivot table | **NU** | YAGNI — isPrimary/addedBy nu aduc valoare SEO |
| Câmp `type` pe Tag | **NU acum** | Evaluare după 1 lună de utilizare |
| Câmp `color` pe Tag | **NU** | CSS variants existente sunt suficiente |
| Decuplare meta din traducere | **NU** | Meta EN/RU sunt parte integrantă din traducere |
| Agent SEO separat | **DA** | Complementar, generează meta+tags RO |
| Un apel Gemini vs. două | **UN SINGUR APEL** | Cost + latență reduse |
| Tag-uri auto vs. sugerate | **AUTO** (cu cleanup) | Evită UI suplimentar de aprobare |
| Ordine: SEO → traducere | **DA** | Meta RO trebuie să existe înainte de traducere |
