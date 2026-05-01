# ANALIZA: EXTINDEREA TRADUCERII AUTOMATE LA CATEGORII SI AUTORI

**Data**: 2026-04-01
**Status**: Analiza (read-only, fara modificari)

---

## 1. CATEGORY — Campuri traducibile

| Camp | Tip DB | Translatable | Candidat Gemini | Note |
|------|--------|:------------:|:---------------:|------|
| `title` | `VARCHAR(255)` | **DA** (`#[Gedmo\Translatable]`) | **DA** | Titlu categorie, 1-3 cuvinte |
| `slug` | `VARCHAR(255)` unique | **DA** (via `#[Gedmo\Slug]` + `#[Gedmo\Translatable]`) | **NU** (auto-gen) | Generat automat din `title` tradus de Gedmo Sluggable |
| `description` | - | **NU** (nu exista) | N/A | Entitatea Category **NU** are camp `description` |

**Observatie importanta**: Category are doar **2 campuri traducibile** (`title` si `slug`), iar `slug`-ul se genereaza automat. Efectiv, singurul camp care trebuie tradus este `title` (1-3 cuvinte).

**Traduceri existente in DB**:
- Total categorii: **22**
- EN title: **21/22** (95%) — aproape complet
- RU title: **21/22** (95%) — aproape complet
- EN slug: **17/22** (77%)
- RU slug: **17/22** (77%)

**Concluzie**: Categoriile sunt deja traduse aproape complet (importate din Newscoop). Traducerea Gemini ar fi utila doar pentru categorii **noi** adaugate manual.

---

## 2. AUTHOR — Campuri traducibile

| Camp | Tip DB | Translatable | Candidat Gemini | Note |
|------|--------|:------------:|:---------------:|------|
| `bio` | `TEXT` (nullable, max 2000) | **DA** (`#[Gedmo\Translatable]`) | **DA** | Biografie autor, cateva propozitii |
| `firstName` | `VARCHAR(100)` | **NU** | **NU** | Numele raman identice in toate limbile |
| `lastName` | `VARCHAR(100)` | **NU** | **NU** | Numele raman identice in toate limbile |
| `slug` | `VARCHAR(255)` | **NU** (generat din `firstName+lastName` via Gedmo Sluggable) | **NU** | Nu e `#[Gedmo\Translatable]`, identic in toate locale |

**Observatie importanta**: Author are doar **1 camp traducibil** (`bio`). `firstName`/`lastName` NU sunt traducibile (corect — numele ranam la fel). `slug`-ul nu e traducibil — identic in toate locale.

**Traduceri existente in DB**:
- Total autori: **64**
- EN bio: **32/64** (50%)
- RU bio: **32/64** (50%)

**Nota**: `TranslationController` are deja un comentariu explicit: "Authors are not translatable - same slug used for all locales" — dar de fapt `bio` **este** traducibil. Controller-ul returneaza acelasi slug (corect), dar nu mentiona traducerea `bio`.

**Concluzie**: 50% din autori au bio tradusa. Traducerea Gemini ar fi utila pentru restul de 32 autori + autori noi.

---

## 3. INFRASTRUCTURA EXISTENTA (Article)

### Message DTO: `TranslateArticleMessage`
```php
final readonly class TranslateArticleMessage {
    public function __construct(
        public int $articleId,
        public array $locales = ['ru', 'en'],
        public bool $forceRetranslate = false,
        public TranslationPriority $priority = TranslationPriority::NORMAL,
    ) {}
}
```
**Specific article**: Campul se numeste `articleId`, nu `entityId`. NU e generic.

### Handler flow (`TranslateArticleHandler`):
1. **Load**: Incarca articolul din `ArticleRepository`, verifica ca exista RO content (title + content non-empty)
2. **Status**: Seteaza `translationStatus = 'in_progress'`
3. **Per-locale loop**: Pentru fiecare locale, construieste prompt JSON → ruleaza Gemini CLI → proceseaza rezultatul
4. **Finalize**: Seteaza status final (`completed` / `needs_review` / `failed`), seteaza `translatedAt` si `translatedBy`, trimite notificare

### Prompt JSON trimis la Gemini (`buildPrompt()`):
```json
{
  "articleId": 123,
  "sourceLocale": "ro",
  "locales": ["en"],
  "title": "...",
  "slug": "...",
  "lead": "...",
  "content": "<p>HTML...</p>",
  "category": "politica",
  "authorName": "Ion Popescu",
  "metaTitle": "...",
  "metaDescription": "..."
}
```
**Specific article**: Include `lead`, `content` (HTML), `category`, `authorName`, `metaTitle`, `metaDescription`.

### Result JSON asteptat de la Gemini:
```json
{
  "translations": {
    "en": {
      "title": "...",
      "slug": "...",
      "lead": "...",
      "content": "<p>...</p>",
      "metaTitle": "max 60 chars",
      "metaDescription": "max 160 chars"
    }
  },
  "qualityNotes": {
    "en": "..."
  }
}
```

### Result Processor (`TranslationResultProcessor`):
- Parseaza JSON (cu handling Gemini CLI envelope `{ session_id, response, stats }`)
- Salveaza via Gedmo `$translationRepo->translate($article, 'field', $locale, $value)`
- Quality gates: paragraph count audit (±30%), length sanity (±35%), qualityNotes flagging
- **Specific article**: Proceseaza `title`, `lead`, `content`, `slug`, `metaTitle`, `metaDescription`
- **Finalize**: Seteaza `translationStatus`, `translatedAt`, `translatedBy` pe Article entity + notificare

### Gemini Agent: `journalistic-translator`
- **Model**: `gemini-2.5-pro`
- **Specific articole**: DA — promptul e 100% specializat pentru jurnalism
  - Mentioneaza "news article" explicit
  - Include reguli AP Style, transliterare chirilic, conventii toponimic Moldova
  - Include reguli HTML preservation (paragrafe, tag-uri)
  - Quality self-check specific articole (paragraph count, metaTitle/metaDescription limits)
- **Input format**: Asteapta `title`, `slug`, `lead`, `content`, `category`, `authorName`, `badge`
- **Output format**: `translations.{locale}.{title,slug,lead,content,metaTitle,metaDescription}`
- **NU e generic** — nu poate procesa categorii/autori fara modificari

### Transport Messenger: `doctrine://default`
- Queue dedicata: `translations` (normal priority)
- Priority queues: `translations_critical`, `translations_urgent`, `translations_high`, `translations`
- Routing: `TranslateArticleMessage` → `translations` (default, override via `TranslationPriorityDispatcher`)
- Worker command: `messenger:consume translations_critical translations_urgent translations_high translations -vv`

### Dispatch mecanism:
- **CLI**: `symfony console app:translate:articles {IDs} --force --locales=ru,en`
- **Auto (Doctrine event)**: `ArticleTranslationTriggerSubscriber` — dispatcheaza la publicare sau cand `requestTranslation` devine `true`
- **Priority routing**: `TranslationPriorityDispatcher` + `TranslationPriorityResolver`

---

## 4. FRONTEND — Stare formulare

### Buton traducere pe articole: **NU EXISTA** in ArticleForm
- Articolul **NU** are un buton "Traducere automata" in formularul de editare
- Traducerea se face doar prin:
  - CLI: `app:translate:articles {id}`
  - Auto-trigger: la publicare (via `ArticleTranslationTriggerSubscriber`)
  - Flag `requestTranslation: true` setat pe Article → trigger subscriber
- Nu exista nicio referinta la `requestTranslation`, `translationStatus`, `translate`, `dispatch` in `ArticleForm.tsx`
- Nu exista server actions sau API endpoints pentru declansarea on-demand a traducerii din frontend

### Formular editare categorie: `CategoryForm.tsx`
- **Componente**: Flowbite React (`Label`, `TextInput`, `Select`, `Button`, `Spinner`)
- **Campuri**: title, slug, parent, status, onFrontPage, frontPageLayout, inMenu, inFooterMenu
- **Server actions**: `createCategoryAction`, `updateCategoryAction`
- **Loc natural pentru buton traducere**: Dupa sectiunea Slug, sau la sfarsit inainte de "Form Actions" — zona de vizibilitate sau ca sectiune separata "Translations"
- **NU exista** buton sau logica de traducere

### Formular editare autor: `AuthorForm.tsx`
- **Componente**: Flowbite React (`Label`, `TextInput`, `Textarea`, `Select`, `Button`, `Spinner`, `Checkbox`)
- **Campuri**: firstName, lastName, email, slug, bio, status, isActive, twitter, facebook, linkedin, website
- **Server actions**: `createAuthorAction`, `updateAuthorAction`
- **Loc natural pentru buton traducere**: Sub campul Bio, sau ca sectiune separata "Translations" dupa "Basic Information"
- **NU exista** buton sau logica de traducere

---

## 5. API — Endpoint-uri traducere

| Endpoint | Exista | Ruta | Scop |
|----------|:------:|------|------|
| GET articles translations | **DA** | `/api/articles/{id}/translations` | Returneaza slug-uri traduse per locale (read-only) |
| GET categories translations | **DA** | `/api/categories/{id}/translations` | Returneaza slug-uri traduse per locale (read-only) |
| GET authors translations | **DA** | `/api/authors/{id}/translations` | Returneaza acelasi slug (bio nu e inclus) |
| POST articles/{id}/translate | **NU** | — | Trebuie creat (dispatch traducere) |
| POST categories/{id}/translate | **NU** | — | Trebuie creat (dispatch traducere) |
| POST authors/{id}/translate | **NU** | — | Trebuie creat (dispatch traducere) |

**Nota**: Endpoint-urile GET existente sunt pentru **citirea** traducerilor (slug-uri per locale, folosite la language switcher). NU exista endpoint-uri POST pentru **declansarea** traducerii automate.

---

## 6. OPTIUNI ARHITECTURALE

### a) Reutilizare agent Gemini existent (`journalistic-translator`)

| PRO | CONTRA |
|-----|--------|
| Nu creem un agent nou | Agentul e 100% specializat pe articole jurnalistice |
| | Include reguli HTML preservation, AP Style, paragraphe — irelevante pentru categorii (1-3 cuvinte) |
| | Prompt overkill pentru traducerea a 1-2 cuvinte |
| | Risc de regresie pe traducerea articolelor daca modificam |
| | Quality gates (paragraph count, length ±35%) nu au sens pentru categorii |

**Recomandare**: NU reutiliza — riscul de regresie nu justifica economia.

### b) Agent Gemini nou dedicat (ex: `entity-translator`)

| PRO | CONTRA |
|-----|--------|
| Separare clara de articole, zero risc regresie | Un fisier `.md` in plus de mentinut |
| Prompt concis, optimizat pentru texte scurte | |
| Poate fi generic (categorii + autori + alte entitati viitoare) | |
| Pastreaza regulile de transliterare chirilica din agentul existent | |

**Recomandare**: Optiunea preferata — agent nou simplu, ~50 linii.

### c) Gemini CLI direct fara agent (prompt inline)

| PRO | CONTRA |
|-----|--------|
| Zero fisiere noi de agent | Prompt-ul e in PHP, mai greu de iterat |
| Implementare rapida | Nu beneficiaza de agentul Gemini (system prompt, tools) |
| | Duplicare logica daca mai adaugam entitati traducibile |

**Recomandare**: Viabila, dar agent-ul nou (opțiunea b) e mai curat si mai extensibil.

### d) Handler unic generic vs. handlere separate per entitate

| Abordare | PRO | CONTRA |
|----------|-----|--------|
| **Handler generic** (`TranslateEntityHandler`) | Un singur handler, DRY | Complexitate in `buildPrompt()` si `processResult()` — trebuie sa stie ce entitate traduce |
| **Handlere separate** (`TranslateCategoryHandler`, `TranslateAuthorHandler`) | Simplu, clar, fara conditionale | Duplicare cod (load entity, run Gemini, save) |
| **Handler generic cu Strategy Pattern** | DRY + extensibil | Overengineered pentru 2 entitati simple |

**Recomandare**: **Handler generic cu discriminator simplu** — un singur `TranslateEntityMessage` cu `entityType` enum (`article`, `category`, `author`). Handler-ul are metode private per tip. Motivatie: categorii si autori au logica trivial de simpla (1-2 campuri, fara quality gates complexe), nu justifica handlere separate.

**Alternativa pragmatica**: Daca se doreste minimal invasion, **2 handlere separate** mici (`TranslateCategoryHandler`, `TranslateAuthorHandler`) care reutilizeaza `TranslationResultProcessor` cu o metoda noua `processSimple()`.

---

## 7. DEPENDENTE SI RISCURI

### Dependente tehnice
1. **Gemini CLI** (`/usr/bin/gemini`) — trebuie sa fie functional si autentificat
2. **Messenger transport** (`doctrine://default`) — trebuie worker activ
3. **Gedmo TranslationRepository** — existent, folosit de `TranslationResultProcessor`
4. **Flowbite React** — framework UI pentru butonul frontend
5. **Server Actions** (Next.js) — mecanism pentru apel API din frontend

### Riscuri identificate
1. **Overkill Gemini pentru categorii**: Apelul Gemini CLI (subprocess, timeout 300s) pentru traducerea a 1-3 cuvinte e disproportionat. O alternativa ar fi un dictionar static sau un apel API Gemini direct (nu CLI).
2. **Lipsa endpoint POST translate**: Trebuie creat endpoint API nou pentru fiecare entitate — necesita autentificare JWT + autorizare (doar admini).
3. **Lipsa `translationStatus` pe Category/Author**: Entitatea Article are `translationStatus`, `translatedAt`, `translatedBy` — Category si Author **NU** au aceste campuri. Migrare schema DB necesara.
4. **Slug auto-regenerare**: Cand traduci `title` pe Category cu Gedmo, slug-ul se regenereaza automat din titlul tradus. Trebuie verificat ca Gedmo Sluggable functioneaza corect in contextul traducerilor non-default locale.
5. **Author `bio` nullable**: Daca bio e null/empty, nu exista ce traduce. Handler-ul trebuie sa verifice.
6. **Categorii deja traduse (95%)**: Traducerea Gemini la categorii existente ar fi mostly redundanta — utila doar la creare de categorii noi.
7. **Volume mici**: 22 categorii, 64 autori — batch translation se face in secunde. Nu justifica priority queues dedicate.

---

## 8. ESTIMARE EFORT

### Varianta recomandata (agent Gemini nou + handler generic)

| Componenta | Complexitate | Detalii |
|-----------|-------------|---------|
| **Backend: Message DTO** | Mica | `TranslateEntityMessage` cu `entityType`, `entityId`, `locales`, `force` |
| **Backend: Handler** | Medie | `TranslateEntityHandler` — load entity per type, build prompt, run Gemini, save |
| **Backend: Agent Gemini** | Mica | `entity-translator.md` — ~50 linii, reguli simple, format JSON |
| **Backend: Result processor** | Mica | Metoda `processEntity()` pe `TranslationResultProcessor` (sau serviciu nou simplu) |
| **Backend: API endpoint** | Mica | `POST /api/categories/{id}/translate` + `POST /api/authors/{id}/translate` in controller |
| **Backend: DB migration** | Mica | Adauga `translationStatus`, `translatedAt`, `translatedBy` pe Category si Author (optional, poate fi evitat) |
| **Backend: Messenger routing** | Minima | Adauga routing in `messenger.yaml` |
| **Frontend: TranslateButton** | Mica | Componenta reutilizabila cu status feedback (idle → loading → success/error) |
| **Frontend: Integrare in CategoryForm** | Mica | Adauga `TranslateButton` dupa slug |
| **Frontend: Integrare in AuthorForm** | Mica | Adauga `TranslateButton` dupa bio |
| **Frontend: Server Action** | Mica | `translateEntityAction(type, id)` |
| **Teste backend** | Medie | Unit test handler + integration test endpoint |
| **Teste frontend** | Mica | Component test buton |

**Total estimat**: Complexitate globala **medie-mica**. Majoritatea componentelor sunt simple deoarece contentul traducibil e foarte scurt (1-3 cuvinte la categorii, 1-2 paragrafe la autori).

### Dependente de ordine implementare
1. Agent Gemini nou (`entity-translator.md`)
2. Message DTO + Handler + Messenger routing
3. API endpoints (POST translate)
4. Frontend: TranslateButton + integrare in forme
5. Teste

---

## 9. NOTA FINALA

Datorita volumului mic de content traducibil (1 camp la categorii, 1 camp la autori) si lungimii foarte scurte a textelor, o **alternativa mai eficienta** decat Gemini CLI (care lanseaza un subprocess cu timeout 300s) ar fi:

- **Gemini API direct** (HTTP call din PHP, nu CLI subprocess) — raspuns in <1s pentru texte scurte
- **Dictionar static** pentru categorii (22 categorii cu nume fixe — `Politica` → `Politics` / `Политика`) — zero cost AI
- **Template-based translation** pentru `bio` autori — daca sunt scurte si formulare

Aceste alternative pot fi evaluate in faza de implementare.
