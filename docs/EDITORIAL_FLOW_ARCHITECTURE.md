# Arhitectura Fluxului Editorial — Deschide News App

## Scopul acestui document

Acest document descrie cum circulă conținutul jurnalistic în sistemul Deschide News App, cine scrie și unde, și care sunt regulile de interacțiune între jurnaliști, adminul Symfony și agenții AI. Dacă dezvolți orice componentă care atinge articole, traduceri, vault sau pipeline-ul editorial, citește acest document întâi.

---

## Principiul fundamental: sursă unică de adevăr

**PostgreSQL este sursa de adevăr.** Tot conținutul editorial (articole, traduceri, categorii, autori, tag-uri) trăiește în baza de date. Orice modificare începe și se termină în DB.

**Obsidian Vault-ul este un workspace derivat, exclusiv pentru agenții AI.** Vault-ul conține copii `.md` ale articolelor, generate automat din DB. Agenții AI citesc din vault, creează note atomice, dosare, MOC-uri — dar nu modifică articolele originale prin vault.

**Direcția sincronizării: DB → Vault. Niciodată invers în fluxul editorial zilnic.**

---

## Actorii sistemului

### 1. Jurnaliștii (oameni)

- Lucrează **exclusiv** în adminul Symfony (interfața web)
- Creează, editează și publică articole prin API-ul backend-ului
- Nu au acces la Obsidian Vault și nu trebuie să știe că există
- Limba principală de lucru: română (ro)
- Pot solicita traducere automată în engleză (en) și rusă (ru)

### 2. Adminul Symfony (backend API)

- Punctul de intrare pentru tot conținutul editorial
- Gestionează entitatea `Article` (cu Gedmo Translatable pentru ro/en/ru)
- La orice operațiune CRUD pe un articol, emite automat un mesaj async pentru sincronizarea în vault
- Stochează traducerile în tabela `ext_translations` (Gedmo)

### 3. Agenții AI

Agenții AI au roluri diferite și interacționează cu sistemul prin canale diferite:

| Agent | Ce face | Unde citește | Unde scrie |
|-------|---------|-------------|------------|
| **Journalistic Translator** | Traduce articole RO→EN, RO→RU | DB (via handler) | `ext_translations` (Gedmo) |
| **Translation Evaluator** | Evaluează și optimizează calitatea traducerilor | DB (via handler) | `ext_translations` (Gedmo) |
| **Article Ingestion** | Extrage entități (persoane, instituții, evenimente) | Vault (fișiere .md) | Vault (note atomice, MOC-uri) |
| **Dossier Generator** | Creează dosare tematice din multiple articole | Vault | Vault |
| **Weekly Summary** | Sinteză săptămânală a evenimentelor | Vault + DB | Vault + NotebookLM |
| **Scraping Pipeline** | Importă articole din surse externe (RSS) | Surse externe | DB (apoi se sync-ează automat în vault) |
| **SEO Optimizer** | Generează metaTitle, metaDescription, tag-uri | DB | DB |

---

## Fluxul de date: de la creare la vault

### Flux 1: Jurnalistul creează/editează un articol

```
Jurnalist → Admin Symfony → POST/PUT /api/articles
    │
    ▼
Article entity persist/update → Doctrine flush()
    │
    ▼
ArticleVaultSyncListener (Doctrine Entity Listener)
    │  postPersist / postUpdate
    ▼
SyncArticleToVaultMessage → Messenger (coada "editorial")
    │
    ▼ (async)
SyncArticleToVaultHandler
    │
    ▼
DbToVaultSyncService.syncArticleToVault()
    │
    ▼
Scrie fișier .md în vault: articles/YYYY/MM/slug.md
    │  (cu frontmatter YAML trilingv + body Markdown)
    ▼
Fișierul e disponibil pentru Article Ingestion, MOC-uri, dosare
```

### Flux 2: Traducere automată

```
Articol creat/actualizat → TranslateArticleMessage
    │
    ▼ (async, coada "translations")
TranslateArticleHandler
    │  Apelează Gemini CLI cu agentul journalistic-translator
    │  Primește JSON cu traduceri
    ▼
TranslationResultProcessor
    │  Salvează în ext_translations via Gedmo
    │  (postUpdate pe Article NU se declanșează — Gedmo scrie în altă tabelă)
    ▼
Dispatch explicit: SyncArticleToVaultMessage (action: sync)
    │
    ▼ (async)
DbToVaultSyncService rescrie .md-ul cu traducerile actualizate
```

**De ce dispatch-ul explicit?** Gedmo Translatable scrie direct în tabela `ext_translations`, nu în tabela `articles`. Doctrine Entity Listener-ul pe Article nu detectează această modificare. De aceea, `TranslateArticleHandler` și `EvaluateTranslationHandler` emit manual un `SyncArticleToVaultMessage` după ce traducerea e salvată.

### Flux 3: Scraping din surse externe

```
ScrapeSourceMessage → RSS feed parse → articole noi detectate
    │
    ▼
ProcessScrapedArticleMessage (per articol)
    │
    ▼
ProcessScrapedArticleHandler
    │  Creează Article entity în DB
    │  Doctrine flush() → ArticleVaultSyncListener se activează automat
    │  Dispatch TranslateArticleMessage (pentru locales lipsă)
    │  Dispatch IngestArticleMessage (pentru extragere entități)
    ▼
Articolul ajunge în vault automat (prin listener)
+ se traduce async
+ se procesează de Article Ingestion
```

### Flux 4: Ștergere articol

```
DELETE /api/articles/{id}
    │
    ▼
ArticleVaultSyncListener.preRemove()
    │  Capturează slug + createdAt ÎNAINTE de ștergere
    │  (entitatea nu mai există după flush)
    ▼
SyncArticleToVaultMessage (action: archive, slug: "...", createdAt: "...")
    │
    ▼ (async)
DbToVaultSyncService.archiveArticleFromVault()
    │  Mută fișierul din articles/ → _archived/
    │  NU șterge fizic — păstrează istoric
    ▼
Fișierul arhivat: _archived/YYYY/MM/slug-{timestamp}.md
```

---

## Structura fișierului .md din vault

Fiecare articol sincronizat produce un fișier Markdown cu frontmatter YAML:

```markdown
---
id: art-2026-04-03-reforma-economica
type: news
language: ro
title:
  ro: Reforma economică în Republica Moldova
  en: Economic Reform in the Republic of Moldova
  ru: Экономическая реформа в Республике Молдова
description:
  ro: Lead-ul articolului în română...
  en: Article lead in English...
  ru: ~
slug:
  ro: reforma-economica-in-republica-moldova
  en: economic-reform-in-the-republic-of-moldova
  ru: ~
seo:
  meta_title:
    ro: Reforma economică | Deschide News
    en: ~
    ru: ~
  meta_description:
    ro: Descriere SEO...
    en: ~
    ru: ~
  og_type: article
  twitter_card: summary_large_image
author:
  - ion-popescu
date_created: '2026-04-03T10:00:00+00:00'
date_published: '2026-04-03T12:00:00+00:00'
date_modified: '2026-04-03T14:30:00+00:00'
status: published
badge: ~
is_featured: false
categories:
  - economie
tags:
  - reforma
  - moldova
source:
  name: Deschide News
  source_id: ~
  content_hash: a1b2c3d4...
  webcode: xK9mQ2
ai:
  translation_status:
    ro: complete
    en: complete
    ru: pending
  auto_generated: false
  reviewed: true
---

# Reforma economică în Republica Moldova

Conținutul articolului convertit din HTML în Markdown...
```

### Convenții frontmatter

- Câmpurile trilingve (`title`, `description`, `slug`, `seo.*`) au structura `{ro: ..., en: ..., ru: ...}`
- Valoarea `~` (YAML null) = traducerea nu există încă
- `ai.translation_status` indică starea fiecărei limbi: `complete` sau `pending`
- `status` mapează din ArticleStatus enum: `new`→`draft`, `submitted`→`review`, `published`→`published`, `archived`→`archived`
- `id` format: `art-{YYYY-MM-DD}-{slug}` (max 128 caractere)

---

## Structura vault-ului Obsidian

```
DeschideVault/
├── articles/                    ← Articole sincronizate din DB (READ-ONLY pentru agenți)
│   ├── 2026/
│   │   ├── 01/
│   │   │   ├── articol-1.md
│   │   │   └── articol-2.md
│   │   ├── 02/
│   │   └── ...
│   └── ...
├── _archived/                   ← Articole șterse din DB (mutate, nu șterse fizic)
│   └── 2026/04/slug-1712345678.md
├── knowledge/                   ← Note atomice generate de Article Ingestion
│   ├── persons/                 ← PER-ion-popescu.md
│   ├── institutions/            ← INST-parlamentul-rm.md
│   └── events/                  ← EVT-2026-04-03-sedinta-guvern.md
├── mocs/                        ← Maps of Content (index tematic per categorie)
│   ├── MOC-Politica-Interna.md
│   ├── MOC-Economie.md
│   ├── MOC-Integrare-UE.md
│   └── ...
├── dossiers/                    ← Dosare tematice generate de Dossier Generator
└── AGENTS.md                    ← Reguli pentru toți agenții AI
```

### Reguli de scriere în vault

| Director | Cine scrie | Mecanism |
|----------|-----------|----------|
| `articles/` | DbToVaultSyncService | Automat din DB, prin Messenger |
| `_archived/` | DbToVaultSyncService | La ștergerea articolului din DB |
| `knowledge/` | ArticleIngestionService | După sync-ul articolului |
| `mocs/` | ArticleIngestionService | Append la MOC-ul categoriei |
| `dossiers/` | DossierGenerationService | La cerere sau programat |

**Regula de aur: agenții AI nu modifică fișierele din `articles/`.** Acele fișiere sunt generate automat din DB și se suprascriu la fiecare sync. Orice modificare manuală se pierde.

---

## Componente backend relevante

### Servicii

| Clasă | Locație | Rol |
|-------|---------|-----|
| `DbToVaultSyncService` | `src/Service/Editorial/` | Generează .md din Article entity, scrie în vault |
| `VaultSyncService` | `src/Service/Editorial/` | Inversul: parsează .md → DB. Doar pentru import batch inițial |
| `ArticleIngestionService` | `src/Service/Editorial/` | Extragere entități, note atomice, MOC-uri |
| `GeminiStructuredTranslator` | `src/Service/Translation/` | Traducere structurată via Gemini CLI |
| `TranslationEvaluatorService` | `src/Service/Translation/` | Evaluare + optimizare calitate traduceri |

### Mesaje Messenger (async)

| Mesaj | Transport | Handler |
|-------|-----------|---------|
| `SyncArticleToVaultMessage` | editorial | `SyncArticleToVaultHandler` |
| `ProcessScrapedArticleMessage` | editorial | `ProcessScrapedArticleHandler` |
| `IngestArticleMessage` | editorial | `IngestArticleHandler` |
| `TranslateArticleMessage` | translations | `TranslateArticleHandler` |
| `EvaluateTranslationMessage` | editorial | `EvaluateTranslationHandler` |

### Doctrine Entity Listener

`ArticleVaultSyncListener` — asculță evenimentele Doctrine pe entitatea Article:
- `postPersist` → dispatch sync
- `postUpdate` → dispatch sync
- `preRemove` → dispatch archive (captează slug + createdAt înainte de ștergere)

### Comanda CLI

```bash
# Sync un singur articol
symfony console app:vault:sync-from-db --article-id=123

# Sync toate articolele publicate
symfony console app:vault:sync-from-db --all --limit=500

# Articole modificate de la o dată
symfony console app:vault:sync-from-db --since=2026-04-01

# Dry-run (afișează fără a scrie)
symfony console app:vault:sync-from-db --all --dry-run
```

---

## Ce trebuie să știi când dezvolți

### Dacă modifici entitatea Article

- Verifică dacă `DbToVaultSyncService.buildFrontmatter()` trebuie actualizat cu noul câmp
- Frontmatter-ul e reconstruit integral la fiecare sync — adaugă getter-ul în metoda `buildFrontmatter()`

### Dacă adaugi un handler nou care modifică traduceri via Gedmo

- **Trebuie** să adaugi dispatch explicit `SyncArticleToVaultMessage` după `$em->flush()`
- Motivul: Gedmo scrie în `ext_translations`, nu în tabela `articles` — Doctrine listener-ul pe Article nu se activează

### Dacă adaugi un nou tip de agent AI care citește din vault

- Agentul trebuie să citească din `articles/` (fișiere .md cu frontmatter)
- Poate scrie în `knowledge/`, `mocs/`, `dossiers/` — dar NICIODATĂ în `articles/`

### Dacă modifici structura frontmatter

- Actualizează acest document
- Verifică dacă `VaultSyncService` (vault→DB, import batch) trebuie actualizat pentru a înțelege noile câmpuri
- Verifică dacă `FrontmatterGenerator` (scraping pipeline) trebuie aliniat

### Dacă adaugi o limbă nouă

- Adaugă limba în array-urile trilingve din `buildFrontmatter()` (`title`, `description`, `slug`, `seo.*`, `ai.translation_status`)
- Actualizează `TranslateArticleHandler` pentru a include noua limbă în `targetLocales`

---

## Diagrama rezumativă

```
┌─────────────────────────────────────────────────────────────┐
│                    SURSE DE CONȚINUT                         │
│                                                             │
│  Jurnalist → Admin Symfony    Scraping RSS    Email presă   │
│       │              │              │              │        │
│       └──────────────┴──────────────┴──────────────┘        │
│                          │                                  │
│                          ▼                                  │
│              ┌──────────────────────┐                       │
│              │   PostgreSQL (DB)    │  ← Sursa de adevăr    │
│              │   articles +         │                       │
│              │   ext_translations   │                       │
│              └──────────┬───────────┘                       │
│                         │                                   │
│           ┌─────────────┼──────────────┐                    │
│           │             │              │                    │
│           ▼             ▼              ▼                    │
│    Doctrine Listener  Traducere   SEO Optimizer             │
│    (auto pe flush)    (explicit)  (direct în DB)            │
│           │             │                                   │
│           └──────┬──────┘                                   │
│                  ▼                                          │
│     SyncArticleToVaultMessage                               │
│           │  (Messenger, coada "editorial")                 │
│           ▼                                                 │
│     DbToVaultSyncService                                    │
│           │  Generează .md cu frontmatter trilingv           │
│           ▼                                                 │
│  ┌─────────────────────────────────────────────────┐        │
│  │           Obsidian Vault                         │       │
│  │                                                  │       │
│  │  articles/  ← copii .md (auto-generate, R/O)    │       │
│  │  knowledge/ ← note atomice (scrise de AI)        │       │
│  │  mocs/      ← Maps of Content (scrise de AI)     │       │
│  │  dossiers/  ← dosare tematice (scrise de AI)     │       │
│  │  _archived/ ← articole șterse (mutate, nu delete)│       │
│  └─────────────────────────────────────────────────┘        │
│           │                                                 │
│           ▼                                                 │
│     Agenți AI citesc vault-ul:                              │
│     - Article Ingestion (entități, note atomice)            │
│     - Dossier Generator (dosare tematice)                   │
│     - Weekly Summary (sinteză săptămânală)                  │
│     - NotebookLM (feed extern)                              │
└─────────────────────────────────────────────────────────────┘
```
