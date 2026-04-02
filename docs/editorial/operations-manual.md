# Manual operațional — Deschide News Editorial AI

**Ultima actualizare**: 2026-04-02
**Sprint-uri acoperite**: 15–18

---

## Arhitectura sistemului

```
Surse externe (Moldpres/IPN/Gov.md)
    │
    ▼
┌─────────────────────────────────────────────┐
│  Scraping (RSS + Trafilatura)               │
│  app:scrape:sources --all                   │
└─────────────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────────────┐
│  HTML → Markdown (HtmlToMarkdownConverter)  │
│  ContentDeduplicator (hash-based)           │
└─────────────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────────────┐
│  Messenger Queue (RabbitMQ)                 │
│  ProcessScrapedArticleMessage               │
└─────────────────────────────────────────────┘
    │
    ├──▶ Clasificare (Gemini CLI)
    ├──▶ Traducere (GeminiStructuredTranslator)
    ├──▶ Evaluare calitate (TranslationEvaluatorService)
    │         └── Optimizer loop (max 2 iterații)
    │
    ▼
┌─────────────────────────────────────────────┐
│  PostgreSQL (SSOT — Single Source of Truth)  │
│  Gedmo ext_translations (ro/en/ru)          │
└─────────────────────────────────────────────┘
    │
    ├──▶ Elasticsearch index (trilingv + fuzzy + sinonime)
    ├──▶ Vault sync (Obsidian via MCPVault)
    ├──▶ NotebookLM feed → Audio Overviews
    │
    ▼
┌─────────────────────────────────────────────┐
│  AI Ingestion Pipeline                       │
│  - Extragere entități (PER/INST/EVT)        │
│  - Note atomice Obsidian                     │
│  - Actualizare MOC-uri                       │
│  - Detecție conexiuni (Elasticsearch)        │
│  - Alertă conexiuni → alerts/connections.md  │
└─────────────────────────────────────────────┘
    │
    ├──▶ Dosare tematice (DossierGenerationService)
    ├──▶ Sinteză săptămânală (WeeklySummaryService)
    └──▶ Briefing zilnic (DailyBriefingService)
```

---

## Comenzi CLI complete

### Scraping & Import

| Comandă | Descriere | Exemplu |
|---------|-----------|---------|
| `app:scrape:sources` | Scraping surse editoriale | `--all --limit=30` |
| `app:vault:sync` | Sincronizare vault → DB | `--direction=vault-to-db` |
| `app:sample-import` | Import rapid demo | (fără opțiuni) |

### Traduceri

| Comandă | Descriere | Exemplu |
|---------|-----------|---------|
| `app:translate:articles` | Traducere articole | `--locale=en --batch=10` |
| `app:translate:entities` | Traducere categorii/autori | `--type=category` |
| `app:translation:evaluate` | Evaluare + optimizare calitate | `--article-id=123 --lang=en` |

### Elasticsearch

| Comandă | Descriere | Exemplu |
|---------|-----------|---------|
| `app:search:reindex` | Reindexare completă | `--force --batch-size=100` |
| `app:search:quality-test` | Test calitate căutare | (fără opțiuni) |

### Editorial AI

| Comandă | Descriere | Exemplu |
|---------|-----------|---------|
| `app:editorial:ingest-article` | Ingestion AI cu extragere entități | `--article-id=123` |
| `app:editorial:generate-dossiers` | Generare dosare tematice | `--all` |
| `app:editorial:weekly-summary` | Sinteză săptămânală | `--with-audio` |
| `app:editorial:daily-briefing` | Briefing zilnic | `--with-audio` |

### Metrici & Monitorizare

| Comandă | Descriere | Exemplu |
|---------|-----------|---------|
| `app:metrics:dashboard` | Dashboard metrici editoriale | `--period=30 --json` |

---

## Configurare cron producție

```bash
# ═══════════════════════════════════════
# Deschide News — Cron Jobs (producție)
# ═══════════════════════════════════════

# Scraping surse la fiecare 30 minute
*/30 * * * * cd /var/www/deschide_news_app/apps/backend && symfony console app:scrape:sources --all --limit=30 >> /var/log/deschide/scrape.log 2>&1

# Publicare articole programate (la fiecare 5 minute)
*/5 * * * * cd /var/www/deschide_news_app/apps/backend && symfony console app:publish-scheduled-articles >> /var/log/deschide/publish.log 2>&1

# Evaluare traduceri la fiecare oră (batch de 20)
0 * * * * cd /var/www/deschide_news_app/apps/backend && symfony console app:translation:evaluate --batch=20 --lang=en >> /var/log/deschide/eval-en.log 2>&1
30 * * * * cd /var/www/deschide_news_app/apps/backend && symfony console app:translation:evaluate --batch=20 --lang=ru >> /var/log/deschide/eval-ru.log 2>&1

# Briefing zilnic la 22:00
0 22 * * * cd /var/www/deschide_news_app/apps/backend && symfony console app:editorial:daily-briefing --with-audio >> /var/log/deschide/briefing.log 2>&1

# Sinteză săptămânală duminica la 20:00
0 20 * * 0 cd /var/www/deschide_news_app/apps/backend && symfony console app:editorial:weekly-summary --with-audio >> /var/log/deschide/weekly.log 2>&1

# Generare dosare zilnic la 23:00
0 23 * * * cd /var/www/deschide_news_app/apps/backend && symfony console app:editorial:generate-dossiers --all >> /var/log/deschide/dossiers.log 2>&1

# Dashboard metrici zilnic la 06:00
0 6 * * * cd /var/www/deschide_news_app/apps/backend && symfony console app:metrics:dashboard --json >> /var/log/deschide/metrics.log 2>&1

# Cleanup lock-uri expirate (zilnic 03:00)
0 3 * * * cd /var/www/deschide_news_app/apps/backend && symfony console app:cleanup-expired-locks >> /var/log/deschide/cleanup.log 2>&1

# Cleanup statistici vechi (săptămânal luni 04:00)
0 4 * * 1 cd /var/www/deschide_news_app/apps/backend && symfony console app:cleanup-stats --days=90 >> /var/log/deschide/cleanup.log 2>&1
```

---

## Supervisor workers

```ini
; /etc/supervisor/conf.d/deschide-workers.conf

[program:deschide-messenger-scraping]
command=php bin/console messenger:consume scraping --time-limit=3600 --memory-limit=256M
directory=/var/www/deschide_news_app/apps/backend
user=www-data
numprocs=1
autostart=true
autorestart=true
startsecs=5
stopwaitsecs=60
stderr_logfile=/var/log/deschide/messenger-scraping.err.log
stdout_logfile=/var/log/deschide/messenger-scraping.log

[program:deschide-messenger-editorial]
command=php bin/console messenger:consume editorial --time-limit=3600 --memory-limit=256M
directory=/var/www/deschide_news_app/apps/backend
user=www-data
numprocs=1
autostart=true
autorestart=true
startsecs=5
stopwaitsecs=60
stderr_logfile=/var/log/deschide/messenger-editorial.err.log
stdout_logfile=/var/log/deschide/messenger-editorial.log

[program:deschide-messenger-translations]
command=php bin/console messenger:consume translations_critical translations_urgent translations_high translations --time-limit=3600 --memory-limit=256M
directory=/var/www/deschide_news_app/apps/backend
user=www-data
numprocs=1
autostart=true
autorestart=true
startsecs=5
stopwaitsecs=60
stderr_logfile=/var/log/deschide/messenger-translations.err.log
stdout_logfile=/var/log/deschide/messenger-translations.log

[program:deschide-messenger-async]
command=php bin/console messenger:consume async stats_async --time-limit=3600 --memory-limit=128M
directory=/var/www/deschide_news_app/apps/backend
user=www-data
numprocs=1
autostart=true
autorestart=true
startsecs=5
stopwaitsecs=60
stderr_logfile=/var/log/deschide/messenger-async.err.log
stdout_logfile=/var/log/deschide/messenger-async.log

[group:deschide-workers]
programs=deschide-messenger-scraping,deschide-messenger-editorial,deschide-messenger-translations,deschide-messenger-async
```

**Comenzi Supervisor:**
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start deschide-workers:*
sudo supervisorctl status deschide-workers:*
sudo supervisorctl restart deschide-workers:*
```

---

## Troubleshooting

### NotebookLM auth expirat
```bash
# 1. Pe Windows, rulează:
notebooklm login

# 2. Copiază cookies în WSL:
cp /mnt/c/Users/Radu/.notebooklm/cookies.json ~/.notebooklm/cookies.json

# 3. Verificare:
notebooklm auth check
```
**Notă**: Dacă autentificarea eșuează, toate funcționalitățile NotebookLM se degradează graceful — dosarele și sintezele se generează exclusiv cu Gemini CLI.

### Gemini CLI timeout
```bash
# Verificare Gemini CLI:
/usr/bin/gemini --version

# Test simplu:
echo "Hello" | /usr/bin/gemini -p "Respond with OK"

# Dacă timeout persistent, crește TIMEOUT în servicii (120s default)
```

### Elasticsearch down
```bash
# Verificare status:
curl -k https://localhost:9200

# Restart:
sudo systemctl restart elasticsearch

# Reindexare după restart:
symfony console app:search:reindex --force
```

### Vault sync eșuat
```bash
# Verificare path vault:
ls -la $VAULT_PATH

# Verificare permisiuni:
stat $VAULT_PATH

# Rulare manuală:
symfony console app:vault:sync --dry-run
```

### Messenger queue blocked
```bash
# Verificare mesaje eșuate:
symfony console messenger:failed:show

# Retry mesaje eșuate:
symfony console messenger:failed:retry

# Ștergere mesaje eșuate (cu atenție):
symfony console messenger:failed:remove [id]

# Verificare RabbitMQ Management:
# http://localhost:15672 (guest/guest)
```

### Traducere eșuată / scor mic
```bash
# Evaluare manuală:
symfony console app:translation:evaluate --article-id=123 --lang=en --dry-run

# Re-traducere forțată:
symfony console app:translate:articles --article-id=123 --force
```

---

## Backup strategy

### PostgreSQL
```bash
# Backup zilnic:
pg_dump -h localhost -U deschide_admin deschide_news | gzip > /backup/db/deschide_$(date +%Y%m%d).sql.gz

# Retenție: 30 zile locale, 90 zile offsite
# Restore:
gunzip -c backup.sql.gz | psql -h localhost -U deschide_admin deschide_news
```

### Vault Obsidian
- **Obsidian File Recovery**: snapshot la 5 minute (plugin integrat)
- **Copie periodică** (zilnic):
```bash
rsync -av --delete /mnt/c/Users/Radu/DeschideVault/ /backup/vault/$(date +%Y%m%d)/
```

### Elasticsearch
```bash
# Snapshot repository (configurare o singură dată):
curl -X PUT "localhost:9200/_snapshot/backup" -H 'Content-Type: application/json' -d'
{
  "type": "fs",
  "settings": { "location": "/backup/elasticsearch" }
}'

# Creare snapshot:
curl -X PUT "localhost:9200/_snapshot/backup/snap_$(date +%Y%m%d)"

# Restore:
curl -X POST "localhost:9200/_snapshot/backup/snap_YYYYMMDD/_restore"
```

### Redis
```bash
# Redis persistence: RDB + AOF activat
# Backup manual:
redis-cli BGSAVE
cp /var/lib/redis/dump.rdb /backup/redis/dump_$(date +%Y%m%d).rdb
```

---

## Arhitectură servicii (Sprint 15–18)

| # | Serviciu | Sprint | Rol |
|---|----------|--------|-----|
| 1 | VaultSyncService | 15 | Sincronizare vault ↔ DB |
| 2 | MarkdownParser | 15 | Parser markdown cu frontmatter |
| 3 | FrontmatterValidator | 15 | Validare frontmatter JSON Schema |
| 4 | ScraperService | 16 | Scraping web (Trafilatura) |
| 5 | RssFeedParser | 16 | Parser feed-uri RSS/Atom |
| 6 | HtmlToMarkdownConverter | 16 | Conversie HTML→Markdown |
| 7 | ContentDeduplicator | 16 | Deduplicare hash-based |
| 8 | FrontmatterGenerator | 16 | Generare frontmatter articole |
| 9 | GeminiStructuredTranslator | 16 | Traducere structurată (Gemini) |
| 10 | ArticleIndexer | 16 | Indexare Elasticsearch trilingvă |
| 11 | ElasticsearchIndexManager | 16+18 | Management index ES + fuzzy/sinonime |
| 12 | NotebookLMService | 17 | Integrare NotebookLM |
| 13 | ArticleIngestionService | 17 | Ingestion AI (entități, note, MOC) |
| 14 | ConnectionDetectionService | 17 | Detecție conexiuni entități |
| 15 | DossierGenerationService | 17 | Dosare tematice automatizate |
| 16 | WeeklySummaryService | 17 | Sinteză editorială săptămânală |
| 17 | DailyBriefingService | 17 | Briefing zilnic editorial |
| 18 | TranslationEvaluatorService | 18 | Evaluare + optimizare traduceri |
| 19 | EditorialMetricsService | 18 | Dashboard metrici editoriale |
