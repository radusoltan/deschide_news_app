# Sprint D: Backfill SEO + Tag-uri pe Articolele Existente — Raport

**Data:** 2026-04-01
**Branch:** develop
**Autor:** Claude Opus 4.6

---

## Rezumat

Creat comanda `app:seo:backfill` pentru procesarea batch a tuturor articolelor prin Gemini AI. Comanda procesează articole in batch-uri de 10, generând metaTitle, metaDescription și tag-uri pentru fiecare. Backfill-ul complet (7519 articole, 752 batch-uri) a fost lansat si rulează in background (~6h estimate).

---

## Raport Acțiuni

| # | Acțiune | Status | Detalii |
|---|---------|--------|---------|
| **FAZA 1: ANALIZĂ** | | | |
| 1.1 | Citire rapoarte + cod Sprint C | ✅ | OptimizeSeoHandler, SeoResultProcessor — reuse pattern for batch |
| 1.2 | Audit date — articole eligibile | ✅ | Total: 7539, fără meta: 7534, fără tags: 7536, eligibile: 7539 |
| 1.3 | Test OptimizeSeoCommand individual | ✅ | Funcțional, ~10-15s per articol |
| **FAZA 2: BATCH SEO** | | | |
| 2.1 | SeoBackfillCommand creat | ✅ | Params: --batch-size, --force, --dry-run, --offset, --limit, --no-tags, --no-meta |
| 2.2 | SeoBatchPromptBuilder | ✅ | 10 articole/apel, 500 chars content/articol, tag-uri existente incluse |
| 2.3 | Test batch mic (5+10 articole) | ✅ | Batch 5: 36s, 22 tags linked, 3 new. Batch 10: 31s, 31 tags linked, 9 new |
| 2.4 | Backfill complet | ⏳ IN PROGRESS | Lansat, 752 batch-uri, estimat ~6h. 30/7519 procesate la raportare |
| **FAZA 3: RECALCULARE** | | | |
| 3.1 | usageCount recalculat | 🔜 AFTER BACKFILL | Comanda existentă: `app:tags:recalculate-counts`. Rulează după backfill. |
| 3.2 | Tag-uri orfane raportate | 🔜 AFTER BACKFILL | 8 tag-uri cu usage_count=0 la momentul raportării |
| **FAZA 4: TRADUCERE** | | | |
| 4.1 | Articole care necesită traducere meta | 🔜 DEFERRED | ~7534 articole vor avea meta RO fără traducere EN/RU |
| 4.2 | Traducere declanșată | 🔜 DEFERRED | Comanda: `app:translate:articles`. Costisitoare (full article). |
| 4.3 | Verificare traduceri | 🔜 DEFERRED | Se va verifica după traducere |
| **FAZA 5: VALIDARE** | | | |
| 5.1 | Statistici post-backfill | ✅ Parțial | 30 articole procesate, 0 quality gate violations |
| 5.2 | Sondaj calitate (5 articole) | ✅ | Calitate: BUNĂ — meta reformulat, tags relevante, diacritice corecte |
| 5.3 | Teste existente | ✅ | Entity: 631 OK. SEO: 16 OK. |
| 5.4 | Teste noi | ✅ | 3 teste (SeoBatchPromptBuilderTest), 13 assertions |

---

## Statistici (Snapshot la 30 articole procesate din 7539)

| Metric | Înainte | După (parțial) | Target (complet) |
|--------|---------|----------------|-------------------|
| Articole cu metaTitle | 5 | 30 | ~7539 |
| Articole cu metaDescription | 5 | 30 | ~7539 |
| Articole cu ≥1 tag | 3 | 28 | ~7539 |
| Articole cu ≥3 tags | 3 | 28 | ~7539 |
| Total tag-uri | 44 | 80 | ~200-400 (estimat) |
| Tag-uri cu articole | 36 | 72 | ~200-400 |
| Tag-uri orfane (usage=0) | 8 | 8 | ~0 după recalculare |
| metaTitle > 60 chars | 0 | 0 | 0 |
| metaDesc > 160 chars | 0 | 0 | 0 |

### Top 15 Tag-uri (snapshot parțial)

| # | Tag | Usage Count |
|---|-----|-------------|
| 1 | Securitate | 13 |
| 2 | Moldova | 12 |
| 3 | Război | 11 |
| 4 | Rusia | 11 |
| 5 | Chișinău | 11 |
| 6 | Politică | 10 |
| 7 | Economie | 10 |
| 8 | Energie | 9 |
| 9 | Ucraina | 8 |
| 10 | Poliția | 8 |
| 11 | Iran | 7 |
| 12 | Infrastructură | 7 |
| 13 | Donald Trump | 6 |
| 14 | Justiție | 6 |
| 15 | România | 5 |

### Distribuție Tag-uri per Articol

| Range | Articles |
|-------|----------|
| 0 tags | 7511 |
| 3-5 tags | 27 |
| 6+ tags | 1 |

---

## Fișiere Create

| Fișier | Tip | Linii |
|--------|-----|-------|
| `src/Command/SeoBackfillCommand.php` | Command | 280 |
| `src/Service/SeoBatchPromptBuilder.php` | Service | 110 |
| `tests/Unit/Service/SeoBatchPromptBuilderTest.php` | Test | 100 |

**Total: 3 fișiere noi, ~490 linii**

## Fișiere Modificate

| Fișier | Modificare |
|--------|-----------|
| `config/services.yaml` | +5 linii — DI for SeoBackfillCommand ($geminiCliPath, $projectDir) |

**Total: 1 fișier modificat, ~5 linii**

---

## Comenzi Noi

```bash
# Dry-run — afișează ce ar procesa
symfony console app:seo:backfill --dry-run [--limit=20]

# Batch real — procesează cu batch-uri de 10
symfony console app:seo:backfill --batch-size=10

# Cu limită și offset (pentru reluare)
symfony console app:seo:backfill --limit=100 --offset=500

# Doar meta, fără tags
symfony console app:seo:backfill --no-tags

# Force overwrite
symfony console app:seo:backfill --force --batch-size=10
```

---

## Teste

| Test | Assertions | Status |
|------|-----------|--------|
| SeoBatchPromptBuilderTest::itBuildsBatchPromptWithMultipleArticles | 8 | ✅ |
| SeoBatchPromptBuilderTest::itTruncatesContentPerArticle | 1 | ✅ |
| SeoBatchPromptBuilderTest::itSkipsTagsWhenDisabled | 2 | ✅ |
| OptimizeSeoMessageTest (2 tests) | 8 | ✅ |
| SeoPromptBuilderTest (4 tests) | 7 | ✅ |
| SeoResultProcessorTest (7 tests) | 41 | ✅ |
| Entity tests (631 tests) | 1398 | ✅ |
| **Total Sprint C+D** | **19 tests, 69 assertions** | **ALL PASSING** |

---

## Performanță Batch

| Metric | Valoare |
|--------|---------|
| Batch size | 10 articole |
| Timp per batch | ~30-36s |
| Articole per minut | ~17-20 |
| Total batches | 752 |
| Estimat durată completă | ~6h 30min |
| Gemini calls total | 752 |

---

## Error Handling

Comanda este rezistentă la erori:
- **Batch timeout**: log eroare, skip batch, continuă cu următorul
- **JSON invalid**: log eroare, skip batch, continuă
- **Articol individual eșuat**: log, skip articolul, procesează restul din batch
- **EntityManager clear**: după fiecare batch, previne memory leaks
- **Offset/limit**: permite reluarea de la punctul de întrerupere

---

## Pași Post-Backfill (TODO)

Aceste comenzi trebuie rulate DUPĂ ce backfill-ul se finalizează:

### 1. Recalculare usageCount
```bash
# Recalculează direct via SQL (rapid):
symfony console dbal:run-sql "UPDATE tags t SET usage_count = (SELECT COUNT(*) FROM article_tag at WHERE at.tag_id = t.id)"

# Sau via comanda existentă:
symfony console app:tags:recalculate-counts
```

### 2. Verificare tag-uri orfane
```bash
symfony console dbal:run-sql "SELECT id, name, usage_count FROM tags WHERE usage_count = 0 ORDER BY name"
```

### 3. Traducere meta EN/RU (opțional, costisitor)
```bash
# Traducerea completă (title+slug+lead+content+metaTitle+metaDescription) per articol
# Estimare: 7539 articole × 2 locale × ~30s = ~125h
# Recomandare: rulează in batch-uri mici, de 20-30 articole
symfony console app:translate:articles --limit=30 --force
```

---

## Decizii / Devieri de la Plan

1. **7539 articole vs. ~360 din spec** — baza de date a crescut semnificativ de la analiza inițială. Comanda funcționează identic, doar durează mai mult.
2. **SeoBatchPromptBuilder separat** — nu am extins SeoPromptBuilder existent (per specificație: "NU modifica"). Am creat un builder dedicat pentru batch.
3. **SeoResultProcessor refolosit** — procesarea per-articol din batch refolosește exact SeoResultProcessor existent.
4. **Traducere DEFERRED** — traducerea meta EN/RU este costisitoare (traduce tot articolul, nu doar meta). Recomand rularea in batch-uri mici, nu masiv.
5. **Backfill lansat in background** — datorită duratei (~6h), a fost lansat in background. Progresul poate fi monitorizat cu `tail -f /tmp/seo-backfill.log`.
