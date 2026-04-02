# RAPORT: Analiză Infrastructură SEO — Deschide News App

**Data:** 2026-04-01
**Branch:** develop (tag v1.0.0-rc3)
**Scop:** Audit read-only al infrastructurii existente pentru câmpuri SEO pe articole

---

## 1. Inventar Câmpuri Existente (Entitatea Article)

### Coloane în tabela `articles` (PostgreSQL)

| Câmp | Tip DB | Translatable Gedmo | În Formular Admin | În API Response | Scop SEO |
|------|--------|-------------------|-------------------|-----------------|----------|
| `id` | integer | Nu | Nu | Da (read) | Nu |
| `title` | varchar(255) | **Da** | Da (input text) | Da | Da — sursa pentru meta title |
| `slug` | varchar(255) | **Da** | Da (input text) | Da | Da — URL path, unique index |
| `lead` | text | **Da** | Da (TinyMCE) | Da | Da — sursa pentru meta description |
| `content` | text | **Da** | Da (TinyMCE) | Da (detail only) | Da — wordCount, fallback description |
| `category_id` | integer FK | Nu | Da (select) | Da (nested) | Da — article:section, breadcrumb |
| `status` | varchar(20) | Nu | Da (select) | Da | Da — robots index/noindex |
| `badge` | varchar(20) | Nu | Da (select) | Da | Nu |
| `is_featured` | boolean | Nu | Da (toggle) | Da | Nu |
| `view_count` | integer | Nu | Nu | Da | Nu |
| `created_at` | timestamp | Nu | Nu | Da | Da — datePublished fallback |
| `updated_at` | timestamp | Nu | Nu | Da | Da — dateModified |
| `published_at` | timestamp | Nu | Nu | Da | Da — datePublished |
| `publish_at` | timestamp | Nu | Da (datetime) | Da | Nu |
| `archived_at` | timestamp | Nu | Nu | Da | Da — robots noindex trigger |
| `archive_reason` | varchar(30) | Nu | Nu | Da | Nu |
| `webcode` | varchar(10) | Nu | Nu | Da | Nu |
| `source_email` | varchar(64) | Nu | Nu | Da | Nu |
| `request_translation` | boolean | Nu | Nu | Da | Nu |
| `translation_status` | varchar(20) | Nu | Nu | Da | Nu |
| `translated_at` | timestamp | Nu | Nu | Da | Nu |
| `translated_by` | varchar(50) | Nu | Nu | Da | Nu |

### Câmpuri traduse în `ext_translations` (Gedmo)

| Câmp | Locale disponibile | Nr. intrări |
|------|--------------------|-------------|
| `title` | en, ru | 979 |
| `slug` | en, ru | 979 |
| `lead` | en, ru | 971 |
| `content` | en, ru | 978 |

**Total articole:** 7,483
**Traduse (completion):** ~490 articole (979 intrări title / 2 locale)

### Adnotări pe Article.php

```
title:   #[Gedmo\Translatable] + #[Assert\NotBlank] + #[Assert\Length(max: 255)]
slug:    #[Gedmo\Translatable] + #[Gedmo\Slug(fields: ['title'], unique: true, updatable: true)]
lead:    #[Gedmo\Translatable] + #[Assert\Length(max: 3000)]
content: #[Gedmo\Translatable]
locale:  #[Gedmo\Locale]
```

### Grupuri de serializare

- **Read:** `article:read` — title, slug, lead, status, badge, isFeatured, viewCount, timestamps, category, authors, articleImages, tags, webcode, translation fields
- **Detail (extend read):** `article:detail` — content, relatedArticles
- **List (extend read):** `article:list` — articleImages
- **Write:** `article:write` — title, lead, content, status, badge, isFeatured, publishAt, category, authors, tags, relatedArticles, sourceEmail, requestTranslation, archiveReason

---

## 2. Câmpuri SEO Lipsă (Gap Analysis)

| Câmp Necesar | Descriere | Translatable? | Lungime Recomandată | Prioritate | Observații |
|-------------|-----------|---------------|---------------------|------------|------------|
| `metaTitle` | Titlu SEO custom (diferit de title) | Da | max 60 chars | **CRITIC** | Frontend derivează din `title` trunchiat la 55 chars. Nu permite override manual. |
| `metaDescription` | Descriere SEO custom | Da | max 160 chars | **CRITIC** | Frontend derivează din `lead` (155 chars) sau plain text din `content`. Nu permite override manual. |
| `ogTitle` | Open Graph title | Da | max 95 chars | SCĂZUT | Poate fi fallback la `metaTitle` → nu necesită câmp separat |
| `ogDescription` | Open Graph description | Da | max 200 chars | SCĂZUT | Poate fi fallback la `metaDescription` → nu necesită câmp separat |
| `ogImage` | URL imagine OG custom | Nu | URL | SCĂZUT | Deja se folosește featured image din `articleImages`. Câmp separat util doar pentru override. |
| `canonicalUrl` | URL canonical custom | Nu | URL | SCĂZUT | Frontend generează automat din route. Override necesar doar pentru cazuri speciale (content duplicat). |
| `noIndex` | Excludere din indexare | Nu | boolean | SCĂZUT | Deja gestionat automat prin `status` (archived → noindex). |
| `focusKeyword` | Cuvânt cheie principal | Da | max 100 chars | MEDIU | Util pentru SEO scoring intern, dar nu afectează output HTML direct. |

**Verdict:** Doar `metaTitle` și `metaDescription` sunt cu adevărat critice. Celelalte câmpuri au deja mecanisme automate funcționale.

---

## 3. Starea Slug-urilor

### Mecanism actual
- **Generare:** Gedmo Sluggable pe câmpul `title` — `#[Gedmo\Slug(fields: ['title'], unique: true, updatable: true)]`
- **Translatable:** Da — slug-ul e marcat `#[Gedmo\Translatable]`, deci Gedmo stochează slug-uri per locale în `ext_translations`
- **Pipeline traduceri:** Gemini generează slug din titlul tradus (EN: english slug, RU: transliterare BGN/PCGN din chirilică). Fallback AsciiSlugger dacă Gemini omite.
- **Salvare:** Via `TranslationResultProcessor` → `$translationRepo->translate($article, 'slug', $locale, $slug)`

### Index unic DB
- **Da** — `uniq_bfdd3168989d9b62 ON articles USING btree (slug)` — dar doar pe coloana principală (locale RO)
- **Nu există index unic pe ext_translations** pentru slug-uri EN/RU per locale — risc teoretic de duplicate, dar Gedmo gestionează update-or-insert intern

### Statistici
- Slug-uri RO: 7,483 (toate articolele, coloana principală)
- Slug-uri traduse (EN+RU): 979 intrări (corespund ~490 articole traduse)
- **Fix recent aplicat:** 973 slug-uri backfill-uite din titluri traduse existente (commit `ea670e5`)

### Probleme identificate
1. **Nici o problemă critică.** Slug-urile funcționează corect post-fix.
2. Articolele netraduse (6,504) nu au slug-uri EN/RU — normal, se generează la traducere.
3. Index unic doar pe coloana `slug` din `articles` (RO). Slug-urile EN/RU din `ext_translations` nu au constraint de unicitate la nivel DB — Gedmo le gestionează via composite key `(object_class, field, locale, foreign_key)`.

---

## 4. Starea Formularului Admin

### Câmpuri existente
1. **Basic Information:** title, slug, lead (TinyMCE), content (TinyMCE)
2. **Category & Publishing:** category (select), status (select), publishAt (datetime, condiționat), badge (select), isFeatured (toggle), authors (multi-select dinamic, min 1, max 5)
3. **Attached Images:** upload, pick, reorder, set featured, detach (doar în edit mode)

### Organizare
- 3 secțiuni colapsabile + butoane acțiune (Close, Save & Close, Save)
- Fără tab-uri de tip SEO / Metadata / Advanced

### Suport multilingv
- Componenta `TranslationTabs` afișează tab-uri RO/EN/RU pe pagina de edit
- Navigare între tab-uri via URL: `/[locale]/admin/articles/[id]/edit`
- Avertisment la editare non-RO: "Non-translatable fields (status, category, authors) are shared across all languages"
- Datele se trimit cu header `Accept-Language` per locale activ

### Bibliotecă forms
- **Native React state** (`useState` + `useCallback`) — nu react-hook-form, nu formik
- Validare server-side prin Next.js server actions
- Editor TinyMCE 8.2.2 pentru lead și content

### Spațiu pentru secțiune SEO
- **Ușor de adăugat** — o nouă secțiune colapsabilă "SEO" sub "Category & Publishing" cu câmpuri metaTitle, metaDescription, focusKeyword
- Pattern-ul existent de secțiuni colapsabile se poate reutiliza
- Trebuie adăugate câmpurile și în server action (`createArticleAction`, `updateArticleAction`)

---

## 5. Starea Metadata Frontend

### generateMetadata implementat: **DA**
- Pagina articol: `app/[locale]/(public)/[categorySlug]/[articleSlug]/page.tsx`
- Funcție `generateMetadata()` completă cu ISR revalidate: 120s
- Folosește utilitar centralizat `generateArticleMetadata()` din `lib/seo/metadata-generator.ts`

### Sursă date metadata
- **Title SEO:** derivat din `article.title` — trunchiat la 55 chars + suffix site name
- **Description SEO:** derivat din `article.lead` (155 chars) → fallback plain text din `article.content`
- **NU se consumă câmpuri metaTitle/metaDescription din API** — nu există în response

### hreflang implementat: **DA**
- Alternates pentru ro, en, ru + x-default
- Funcția `buildAlternateUrls()` în `lib/seo/metadata-generator.ts`

### Open Graph tags: **DA**
- Type: `article`
- Imagine: featured image din `articleImages` (1200x630)
- Locale mapping: ro→ro_RO, en→en_US, ru→ru_RU
- published_time, modified_time, section, author

### Twitter Cards: **DA**
- `summary_large_image`
- Title, description, image

### Canonical URL: **DA**
- Generat automat din route: `{SITE_URL}/{locale}/{categorySlug}/{articleSlug}`
- Nu suportă override manual

### Structured Data (JSON-LD): **DA — complet**
- **NewsArticle** schema — headline, description, images, dates, author, publisher, wordCount, inLanguage
- **BreadcrumbList** schema — Home → Category → Article
- **WebPage** schema — per pagină
- **Global:** NewsMediaOrganization + WebSite cu SearchAction

### Robots
- Published: `index: true, follow: true`
- Archived: `index: false, follow: true, noarchive: true`
- GoogleBot max-preview directives

---

## 6. Migrări Necesare (pentru adăugarea metaTitle + metaDescription)

### Coloane noi în `articles`
Niciuna obligatorie. Câmpurile noi pot fi nullable, deci nu necesită valori default:
- `meta_title` — varchar(60), nullable, Gedmo Translatable
- `meta_description` — varchar(160), nullable, Gedmo Translatable

### Intrări noi Gedmo Translatable
- `metaTitle` — salvat automat în `ext_translations` la traducere
- `metaDescription` — salvat automat în `ext_translations` la traducere

### Indexuri noi
- Niciun index necesar — câmpurile SEO nu sunt filtrate/sortate

### Migrări Doctrine pending: **NU** (schema in sync)

---

## 7. Dependențe și Riscuri

1. **Gedmo Sluggable + metaTitle:** Dacă adăugăm `metaTitle` ca translatable, trebuie verificat că Gedmo Slug nu interferează. **Risc scăzut** — Slug e configurat doar pe câmpul `title`, nu pe `metaTitle`.

2. **Pipeline traduceri Gemini:** Dacă adăugăm `metaTitle` și `metaDescription`, agentul Gemini și `TranslationResultProcessor` trebuie actualizate să genereze/salveze aceste câmpuri. **Effort mediu** — similar cu fix-ul slug.

3. **Formularul admin nativ React:** Adăugarea câmpurilor SEO necesită modificări manuale în `ArticleForm.tsx` + server actions. Nu există form library care să gestioneze automat field registration. **Effort mediu.**

4. **Frontend metadata-generator:** Trebuie actualizat `generateArticleMetadata()` să prefere `metaTitle`/`metaDescription` din API dacă există, cu fallback la logica actuală (title trunchiat / lead). **Effort scăzut.**

5. **API serializare:** Câmpurile noi trebuie adăugate în grupurile `article:read` + `article:write` + `article:detail`. **Effort scăzut.**

6. **Backward compatibility:** Câmpurile sunt nullable, deci articolele existente rămân neafectate. Logica de fallback din frontend asigură continuitate. **Risc zero.**

---

## 8. Recomandări Arhitecturale

### 8.1 Adaugă doar `metaTitle` și `metaDescription`
Celelalte câmpuri OG sunt deja gestionate automat de frontend (ogTitle = metaTitle fallback, ogImage = featured image, canonical = route-based). Nu crea câmpuri redundante.

### 8.2 Implementează ca Gedmo Translatable pe Article
Nu crea entitate separată. Folosește exact pattern-ul existent — adnotare `#[Gedmo\Translatable]` pe proprietatea din Article, salvare automată în `ext_translations`.

### 8.3 Actualizează pipeline-ul Gemini
Adaugă `metaTitle` (max 60 chars) și `metaDescription` (max 160 chars) în:
1. Agent prompt (`.gemini/agents/journalistic-translator.md`) — deja le cere
2. `TranslateArticleHandler::buildPrompt()` — trimite valorile RO dacă există
3. `TranslationResultProcessor::process()` — salvează via `$translationRepo->translate()`

### 8.4 Frontend: fallback chain
```
metaTitle → title (trunchiat)
metaDescription → lead (155 chars) → plain text content
```
Actualizează `generateArticleMetadata()` să verifice întâi câmpurile SEO dedicate.

### 8.5 Formularul admin: secțiune colapsabilă SEO
Adaugă sub "Category & Publishing" o secțiune "SEO" cu:
- metaTitle (input text, counter chars, placeholder auto-generat din title)
- metaDescription (textarea, counter chars, placeholder auto-generat din lead)
- Preview snippet Google (opțional, read-only, generat din metaTitle + metaDescription + slug)

### 8.6 Command backfill
Similar cu `app:fix:translation-slugs`, creează `app:seo:generate-meta` care populează metaTitle/metaDescription din title/lead pentru articolele existente — dar doar ca valori inițiale, editabile ulterior.

### 8.7 NU adăuga focusKeyword/seoScore acum
Sunt nice-to-have dar adaugă complexitate fără beneficiu SEO real. Google nu folosește meta keywords. Adaugă-le într-un sprint dedicat SEO dacă redacția le cere.
