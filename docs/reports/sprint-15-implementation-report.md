# Sprint 15 — Implementation Report

**Data:** 2026-04-02
**Branch:** `feature/sprint-15-editorial-ecosystem`
**Scop:** Fundație Ecosistem Editorial — Symfony Ingestion + Vault AGENTS.md + Teste

## Sumar

| Task | Status | Commit | Fișiere create/modificate | Note |
|------|--------|--------|---------------------------|------|
| T1: Dependințe + config | DONE | `2701ecd` | composer.json, composer.lock, editorial.yaml | league/commonmark 2.8 instalat |
| T2: JSON Schema | DONE | `cc96c50` | config/schemas/article-frontmatter.schema.json | Copie și în vault Obsidian |
| T3: Servicii Symfony | DONE | `99b3ae7` | 6 fișiere în src/Service/Editorial/ | MarkdownParser, FrontmatterValidator, VaultSyncService + 3 DTOs |
| T4: Controller + Command | DONE | `1f90c67` | VaultWebhookController.php, VaultSyncCommand.php | Webhook exclus din JWT firewall |
| T5: AGENTS.md + Teste | DONE | `6d81170` | AGENTS.md (vault), 4 fișiere test, .env.test, services.yaml, security.yaml | 35 teste, 75 assertions |
| T6: E2E + Schema fix | DONE | `08809d7` | Schema JSON actualizat, VaultSyncService fix date parsing | Demo article ID 10175 creat cu succes |

## Fișiere create

### Backend (src/)
- `src/Service/Editorial/MarkdownParser.php` — Parsare Markdown cu FrontMatterExtension
- `src/Service/Editorial/MarkdownParseResult.php` — DTO rezultat parsare
- `src/Service/Editorial/FrontmatterValidator.php` — Validare contra JSON Schema
- `src/Service/Editorial/ValidationResult.php` — DTO rezultat validare
- `src/Service/Editorial/VaultSyncService.php` — Sincronizare vault → Article + Gedmo Translatable
- `src/Service/Editorial/VaultSyncResult.php` — DTO rezultat sync
- `src/Controller/VaultWebhookController.php` — POST /api/webhook/vault-sync
- `src/Command/VaultSyncCommand.php` — `app:vault:sync` CLI

### Config
- `config/packages/editorial.yaml` — Parametri vault_path, vault_webhook_secret
- `config/schemas/article-frontmatter.schema.json` — Schema validare frontmatter

### Teste
- `tests/Unit/Service/Editorial/MarkdownParserTest.php` — 7 teste
- `tests/Unit/Service/Editorial/FrontmatterValidatorTest.php` — 16 teste
- `tests/Integration/Service/Editorial/VaultSyncServiceTest.php` — 6 teste
- `tests/Functional/Controller/VaultWebhookControllerTest.php` — 6 teste

### Vault Obsidian
- `AGENTS.md` — Reguli pentru agenți AI (permisiuni, zone protejate, convenții)
- `schemas/article-frontmatter.schema.json` — Copie schema

## Teste

| Suită | Teste | Assertions | Status |
|-------|-------|------------|--------|
| Unit: MarkdownParser | 7 | 14 | PASS |
| Unit: FrontmatterValidator | 16 | 30 | PASS |
| Integration: VaultSyncService | 6 | 21 | PASS |
| Functional: VaultWebhookController | 6 | 10 | PASS |
| **Total Sprint 15** | **35** | **75** | **ALL PASS** |

Teste idempotente — trec la rulări consecutive fără cleanup manual.

## E2E Verification

Demo article `articles/2026/04/demo-reforma-energetica.md` sincronizat cu succes:
- Article ID: 10175
- Title (ro): "Guvernul aprobă noul plan de reformă energetică"
- Slug (ro): "guvernul-aproba-plan-reforma-energetica"
- Traduceri en/ru: 10 câmpuri în ext_translations (title, slug, lead, metaTitle, metaDescription per limbă)

## Modificări configurare

- `security.yaml`: Webhook endpoint exclus din JWT firewall (autentificare prin X-Webhook-Secret)
- `services.yaml`: VaultSyncService marcat public în env test
- `.env.test`: Adăugate VAULT_PATH și VAULT_WEBHOOK_SECRET
- Schema JSON: date_created/published/modified acceptă atât string ISO 8601 cât și integer (Unix timestamp din YAML parser)

## Dependințe adăugate

- `league/commonmark` ^2.8 (cu FrontMatterExtension)
- `justinrainbow/json-schema` — deja instalat anterior
