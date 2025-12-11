# Plan de Remediere - Deschide News App

**Data:** 29 Noiembrie 2025
**Versiune:** 1.0
**Status:** ACTIV

---

## Executive Summary

Acest document prezinta planul detaliat de remediere pentru problemele identificate in auditurile de securitate, baza de date si analiza codului aplicatiei Deschide News.

### Sumar Probleme

| Categorie | Critice | High | Medium | Low | Total |
|-----------|---------|------|--------|-----|-------|
| Securitate | 1 | 5 | 4 | 0 | 10 |
| Baza de Date | 2 | 4 | 3 | 1 | 10 |
| Performanta Cod | 1 | 0 | 0 | 0 | 1 |
| **TOTAL** | **4** | **9** | **7** | **1** | **21** |

### Estimare Efort Total

- **Sprint 1 (Urgente - P0/P1):** 5 zile
- **Sprint 2 (Importante - P1/P2):** 5 zile
- **Sprint 3 (Optimizari - P2/P3):** 5 zile
- **Sprint 4 (Finalizare - P3/P4):** 3 zile

**Total:** ~18 zile lucratoare (3.5 saptamani)

---

## Categorii de Prioritati

| Prioritate | Descriere | Timeline | SLA |
|------------|-----------|----------|-----|
| **P0** | Critic - Vulnerabilitate activa, date expuse | 0-24 ore | Imediat |
| **P1** | High - Risc semnificativ de securitate | 1-3 zile | Sprint 1 |
| **P2** | Medium - Risc moderat, impact functional | 1-2 saptamani | Sprint 2 |
| **P3** | Low - Optimizari, best practices | 2-4 saptamani | Sprint 3 |
| **P4** | Nice-to-have - Imbunatatiri minore | Backlog | Sprint 4+ |

---

## Sprint 1: Urgente de Securitate (Ziua 1-5)

### Obiectiv
Remedierea tuturor vulnerabilitatilor critice si high-priority care expun aplicatia la atacuri imediate.

---

### Sarcina 1.1: Rotire Secrete si Eliminare .env din Git

**Agent:** @security-auditor
**Prioritate:** P0 (CRITIC)
**Estimare:** 4 ore
**Dependente:** Niciuna (prima sarcina)

**Prompt pentru Agent:**

```
## Context
Fisierul `.env` cu credentiale PRODUCTION a fost comis in repository-ul git.
Locatie: /var/www/deschide_news_app/apps/backend/.env

Credentialele EXPUSE includ:
- APP_SECRET=Kc2qO6piNIVfiHN8koLrX7TiTfo+xudoXsS+xF3nwc4=
- DATABASE_URL cu parola: sr324395
- JWT_PASSPHRASE=KMFTYGh5Dye6MwzpCejeZ3oOTodwtzbhhLvF+I3WjD8=
- NEWSCOOP_DATABASE_URL cu parola: sr324395
- MERCURE_JWT_SECRET=!ChangeThisMercureHubJWTSecretKey!

## Sarcini

### Pas 1: Backup configuratie actuala
```bash
cd /var/www/deschide_news_app/apps/backend
cp .env .env.backup.$(date +%Y%m%d_%H%M%S)
```

### Pas 2: Genereaza noi credentiale

1. **APP_SECRET nou** (foloseste Symfony):
```bash
symfony console secrets:generate-keys
# SAU genereaza manual:
php -r "echo bin2hex(random_bytes(32));"
```

2. **JWT keys noi**:
```bash
rm -f config/jwt/private.pem config/jwt/public.pem
symfony console lexik:jwt:generate-keypair
# Noteaza noua passphrase generata
```

3. **Parola PostgreSQL noua** (genereaza 24+ caractere):
```bash
openssl rand -base64 24
```

4. **MERCURE_JWT_SECRET nou**:
```bash
openssl rand -base64 32
```

### Pas 3: Actualizeaza PostgreSQL cu noua parola
```bash
sudo -u postgres psql -c "ALTER USER deschide_admin WITH PASSWORD 'NOUA_PAROLA_GENERATA';"
```

### Pas 4: Sterge .env din git si actualizeaza .gitignore
```bash
cd /var/www/deschide_news_app/apps/backend

# Verifica .gitignore contine .env
grep -q "^/.env$" .gitignore || echo "/.env" >> .gitignore

# Sterge din git history (foloseste git filter-repo sau BFG)
# Pentru simplitate, sterge doar din index:
git rm --cached .env
git commit -m "security: remove .env with exposed credentials"
```

### Pas 5: Creeaza .env.local cu noile credentiale
```bash
cat > .env.local << 'EOF'
APP_ENV=prod
APP_SECRET=NOUL_APP_SECRET_GENERAT

DATABASE_URL="postgresql://deschide_admin:NOUA_PAROLA_DB@127.0.0.1:5432/deschide?serverVersion=18&charset=utf8"

JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=NOUA_PASSPHRASE_JWT

MERCURE_JWT_SECRET=NOUL_MERCURE_SECRET
EOF

chmod 600 .env.local
```

### Pas 6: Verifica aplicatia functioneaza
```bash
symfony console cache:clear
symfony console doctrine:query:sql "SELECT 1"
curl http://127.0.0.1:8081/api
```

### Pas 7: Documenteaza noile credentiale
Salveaza intr-un password manager (nu in git!):
- APP_SECRET
- DATABASE_URL (cu noua parola)
- JWT_PASSPHRASE
- MERCURE_JWT_SECRET

## Criterii de Acceptare
- [ ] Fisierul .env NU mai contine credentiale reale
- [ ] .env este in .gitignore
- [ ] .env.local exista cu noile credentiale (chmod 600)
- [ ] PostgreSQL accepta noua parola
- [ ] JWT authentication functioneaza
- [ ] API raspunde corect la /api
- [ ] Toate secretele vechi sunt invalidate
```

**Verificare:**
- [ ] Fisierul .env eliminat din git index
- [ ] Noile credentiale generate si aplicate
- [ ] Parola PostgreSQL schimbata
- [ ] JWT keys regenerate
- [ ] Aplicatia functioneaza cu noile credentiale

---

### Sarcina 1.2: Corectare Permisiuni JWT Keys

**Agent:** @devops-engineer
**Prioritate:** P1 (HIGH)
**Estimare:** 30 minute
**Dependente:** 1.1 (JWT keys regenerate)

**Prompt pentru Agent:**

```
## Context
Cheile JWT au permisiuni 666 (world-readable/writable) in loc de 600.
Locatie: /var/www/deschide_news_app/apps/backend/config/jwt/

## Sarcini

### Pas 1: Verifica permisiunile actuale
```bash
ls -la /var/www/deschide_news_app/apps/backend/config/jwt/
```

### Pas 2: Corecteaza permisiunile
```bash
cd /var/www/deschide_news_app/apps/backend/config/jwt

# Private key - doar owner poate citi
chmod 600 private.pem

# Public key - poate fi citita de grupul web server
chmod 640 public.pem

# Verifica owner-ul
chown www-data:www-data private.pem public.pem
# SAU daca rulezi ca alt user:
# chown $USER:$USER private.pem public.pem
```

### Pas 3: Verifica rezultatul
```bash
ls -la /var/www/deschide_news_app/apps/backend/config/jwt/
# Trebuie sa vezi:
# -rw------- 1 www-data www-data ... private.pem
# -rw-r----- 1 www-data www-data ... public.pem
```

### Pas 4: Testeaza JWT functioneaza
```bash
cd /var/www/deschide_news_app/apps/backend
symfony console lexik:jwt:check-config
```

## Criterii de Acceptare
- [ ] private.pem are permisiuni 600
- [ ] public.pem are permisiuni 640 sau 644
- [ ] JWT authentication functioneaza corect
```

**Verificare:**
- [ ] `ls -la config/jwt/` arata permisiuni corecte
- [ ] JWT token generation functioneaza

---

### Sarcina 1.3: Upgrade symfony/http-foundation (CVE-2025-64500)

**Agent:** @backend-developer
**Prioritate:** P1 (HIGH)
**Estimare:** 2 ore
**Dependente:** Niciuna

**Prompt pentru Agent:**

```
## Context
CVE-2025-64500 in symfony/http-foundation permite Authorization Bypass.
Versiunea curenta trebuie actualizata la cea mai recenta versiune patch.

Locatie: /var/www/deschide_news_app/apps/backend/

## Sarcini

### Pas 1: Verifica versiunea curenta
```bash
cd /var/www/deschide_news_app/apps/backend
composer show symfony/http-foundation
```

### Pas 2: Verifica CVE-ul si versiunea patch
```bash
composer audit
```

### Pas 3: Actualizeaza pachetul
```bash
composer update symfony/http-foundation --with-dependencies
```

### Pas 4: Daca exista conflicte de versiune, actualizeaza toate pachetele Symfony
```bash
composer update "symfony/*" --with-dependencies
```

### Pas 5: Verifica nu mai exista vulnerabilitati
```bash
composer audit
```

### Pas 6: Ruleaza teste
```bash
# Daca exista teste:
vendor/bin/phpunit

# Testeaza manual API-ul:
symfony serve -d --port=8081
curl http://127.0.0.1:8081/api
curl -X POST http://127.0.0.1:8081/api/login -H "Content-Type: application/json" -d '{}'
```

### Pas 7: Verifica compatibilitate
```bash
symfony console cache:clear
symfony console doctrine:schema:validate
```

## Criterii de Acceptare
- [ ] composer audit nu raporteaza CVE-2025-64500
- [ ] Toate testele trec
- [ ] API functioneaza corect
- [ ] Authentication flow functioneaza
```

**Verificare:**
- [ ] `composer audit` nu arata vulnerabilitati critice
- [ ] Aplicatia porneste fara erori
- [ ] Testele trec (daca exista)

---

### Sarcina 1.4: Implementare Content Security Policy (CSP)

**Agent:** @backend-developer
**Prioritate:** P1 (HIGH)
**Estimare:** 3 ore
**Dependente:** Niciuna

**Prompt pentru Agent:**

```
## Context
Aplicatia nu are Content Security Policy header implementat.
Aceasta expune aplicatia la atacuri XSS si data injection.

Backend: /var/www/deschide_news_app/apps/backend/
Frontend: /var/www/deschide_news_app/apps/frontend/

## Sarcini

### BACKEND - Symfony

#### Pas 1: Creeaza CSP EventSubscriber
```bash
cd /var/www/deschide_news_app/apps/backend
```

Creeaza fisierul `src/EventSubscriber/SecurityHeadersSubscriber.php`:

```php
<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();

        // Content Security Policy
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'", // Adjust based on needs
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https: http://127.0.0.1:8082",
            "font-src 'self' data:",
            "connect-src 'self' http://127.0.0.1:8081 ws://localhost:3000",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);

        // Additional security headers
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
    }
}
```

#### Pas 2: Verifica subscriber-ul este inregistrat
```bash
symfony console debug:event-dispatcher kernel.response
```

### FRONTEND - Next.js

#### Pas 3: Adauga security headers in next.config.mjs

Modifica `/var/www/deschide_news_app/apps/frontend/next.config.mjs`:

```javascript
// Adauga dupa const nextConfig = { ...

  // Security headers
  async headers() {
    return [
      {
        source: '/:path*',
        headers: [
          {
            key: 'Content-Security-Policy',
            value: [
              "default-src 'self'",
              "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
              "style-src 'self' 'unsafe-inline'",
              "img-src 'self' data: https: http://127.0.0.1:8082 http://127.0.0.1:8081",
              "font-src 'self' data:",
              "connect-src 'self' http://127.0.0.1:8081 ws://localhost:3000",
              "frame-ancestors 'none'",
              "base-uri 'self'",
              "form-action 'self'",
            ].join('; '),
          },
          {
            key: 'X-Content-Type-Options',
            value: 'nosniff',
          },
          {
            key: 'X-Frame-Options',
            value: 'DENY',
          },
          {
            key: 'X-XSS-Protection',
            value: '1; mode=block',
          },
          {
            key: 'Referrer-Policy',
            value: 'strict-origin-when-cross-origin',
          },
          {
            key: 'Permissions-Policy',
            value: 'geolocation=(), microphone=(), camera=()',
          },
        ],
      },
    ];
  },
```

#### Pas 4: Testeaza headers
```bash
# Backend
curl -I http://127.0.0.1:8081/api

# Frontend
curl -I http://localhost:3005
```

## Criterii de Acceptare
- [ ] CSP header prezent in raspunsurile backend
- [ ] CSP header prezent in raspunsurile frontend
- [ ] X-Content-Type-Options: nosniff
- [ ] X-Frame-Options: DENY
- [ ] Aplicatia functioneaza normal cu CSP activ
```

**Verificare:**
- [ ] `curl -I` arata CSP header
- [ ] Aplicatia nu are erori in consola browser
- [ ] Toate resursele se incarca corect

---

### Sarcina 1.5: Sanitizare XSS in ArticleBody (Frontend)

**Agent:** @frontend-developer
**Prioritate:** P1 (HIGH)
**Estimare:** 3 ore
**Dependente:** Niciuna

**Prompt pentru Agent:**

```
## Context
Exista risc XSS prin folosirea `dangerouslySetInnerHTML` fara sanitizare.
Trebuie identificate toate utilizarile si adaugata sanitizare cu DOMPurify.

Locatie: /var/www/deschide_news_app/apps/frontend/

## Sarcini

### Pas 1: Identifica toate utilizarile dangerouslySetInnerHTML
```bash
cd /var/www/deschide_news_app/apps/frontend
grep -r "dangerouslySetInnerHTML" --include="*.tsx" --include="*.jsx" --include="*.ts" --include="*.js"
```

### Pas 2: Instaleaza DOMPurify
```bash
pnpm add dompurify
pnpm add -D @types/dompurify
```

### Pas 3: Creeaza helper de sanitizare

Creeaza `lib/sanitize.ts`:

```typescript
import DOMPurify from 'dompurify';

// Configure DOMPurify
const config: DOMPurify.Config = {
  ALLOWED_TAGS: [
    'p', 'br', 'b', 'i', 'em', 'strong', 'a', 'ul', 'ol', 'li',
    'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
    'blockquote', 'pre', 'code',
    'img', 'figure', 'figcaption',
    'table', 'thead', 'tbody', 'tr', 'th', 'td',
    'div', 'span', 'iframe'
  ],
  ALLOWED_ATTR: [
    'href', 'target', 'rel', 'src', 'alt', 'title', 'class', 'id',
    'width', 'height', 'style', 'data-*',
    'frameborder', 'allowfullscreen', 'allow'
  ],
  ALLOW_DATA_ATTR: true,
  ADD_ATTR: ['target'],
  // Force all links to open in new tab and have secure attributes
  FORBID_TAGS: ['script', 'style'],
  FORBID_ATTR: ['onerror', 'onclick', 'onload', 'onmouseover'],
};

/**
 * Sanitize HTML content to prevent XSS attacks
 */
export function sanitizeHtml(dirty: string): string {
  if (typeof window === 'undefined') {
    // Server-side: return empty or use server-side sanitizer
    // For SSR, we should sanitize on the server too
    return dirty; // TODO: Add server-side sanitization
  }

  return DOMPurify.sanitize(dirty, config);
}

/**
 * Create safe HTML for dangerouslySetInnerHTML
 */
export function createSafeHtml(dirty: string): { __html: string } {
  return { __html: sanitizeHtml(dirty) };
}
```

### Pas 4: Pentru SSR, instaleaza si isomorphic-dompurify
```bash
pnpm add isomorphic-dompurify
```

Actualizeaza `lib/sanitize.ts`:

```typescript
import DOMPurify from 'isomorphic-dompurify';

// ... rest of the code remains the same but now works on server too
```

### Pas 5: Actualizeaza componentele care folosesc dangerouslySetInnerHTML

Exemplu pentru ArticleBody.tsx (sau echivalent):

```typescript
import { createSafeHtml } from '@/lib/sanitize';

// INAINTE (NESIGUR):
// <div dangerouslySetInnerHTML={{ __html: article.content }} />

// DUPA (SIGUR):
<div dangerouslySetInnerHTML={createSafeHtml(article.content)} />
```

### Pas 6: Creeaza componenta wrapper pentru HTML nesigur

Creeaza `components/SafeHtml.tsx`:

```typescript
'use client';

import { createSafeHtml } from '@/lib/sanitize';

interface SafeHtmlProps {
  html: string;
  className?: string;
  as?: keyof JSX.IntrinsicElements;
}

export function SafeHtml({ html, className, as: Component = 'div' }: SafeHtmlProps) {
  return (
    <Component
      className={className}
      dangerouslySetInnerHTML={createSafeHtml(html)}
    />
  );
}
```

### Pas 7: Inlocuieste toate utilizarile
```bash
# Gaseste si inlocuieste toate utilizarile nesigure
grep -rn "dangerouslySetInnerHTML" --include="*.tsx" .
```

Inlocuieste fiecare cu componenta SafeHtml sau createSafeHtml().

### Pas 8: Testeaza sanitizarea
```typescript
// Test in consola browser:
import { sanitizeHtml } from '@/lib/sanitize';

// Test XSS payload
const malicious = '<script>alert("XSS")</script><p>Safe content</p>';
console.log(sanitizeHtml(malicious));
// Output: <p>Safe content</p>

// Test onclick handler
const onclick = '<button onclick="alert(1)">Click</button>';
console.log(sanitizeHtml(onclick));
// Output: <button>Click</button>
```

## Criterii de Acceptare
- [ ] DOMPurify instalat si configurat
- [ ] Toate dangerouslySetInnerHTML folosesc sanitizare
- [ ] Componentele arata corect dupa sanitizare
- [ ] XSS payloads sunt blocate (testat manual)
- [ ] SSR functioneaza cu isomorphic-dompurify
```

**Verificare:**
- [ ] `pnpm build` reuseste
- [ ] Articolele se afiseaza corect
- [ ] Script tags sunt eliminate din content
- [ ] Atributele on* sunt eliminate

---

### Sarcina 1.6: Configurare Backup PostgreSQL

**Agent:** @database-engineer
**Prioritate:** P0 (CRITIC)
**Estimare:** 4 ore
**Dependente:** Niciuna

**Prompt pentru Agent:**

```
## Context
PostgreSQL nu are strategie de backup configurata.
archive_mode=off, ceea ce inseamna ca nu se pot face point-in-time recovery.

Database: deschide_news pe PostgreSQL 18
Server: localhost:5432

## Sarcini

### Pas 1: Verifica configuratia curenta
```bash
sudo -u postgres psql -c "SHOW archive_mode;"
sudo -u postgres psql -c "SHOW wal_level;"
sudo -u postgres psql -c "SHOW archive_command;"
```

### Pas 2: Creeaza directorul pentru archive
```bash
sudo mkdir -p /var/lib/postgresql/archive/deschide_news
sudo chown postgres:postgres /var/lib/postgresql/archive/deschide_news
sudo chmod 700 /var/lib/postgresql/archive/deschide_news
```

### Pas 3: Creeaza directorul pentru backup-uri
```bash
sudo mkdir -p /var/backups/postgresql/deschide_news/{daily,weekly,monthly}
sudo chown postgres:postgres /var/backups/postgresql/deschide_news -R
sudo chmod 700 /var/backups/postgresql/deschide_news
```

### Pas 4: Configureaza WAL archiving
Editeaza postgresql.conf (locatia poate varia):
```bash
sudo nano /etc/postgresql/18/main/postgresql.conf
```

Adauga/modifica:
```conf
# WAL Archiving for Point-in-Time Recovery
wal_level = replica
archive_mode = on
archive_command = 'test ! -f /var/lib/postgresql/archive/deschide_news/%f && cp %p /var/lib/postgresql/archive/deschide_news/%f'
archive_timeout = 300  # Archive every 5 minutes if no activity

# Optional: WAL retention
max_wal_size = 2GB
min_wal_size = 80MB
```

### Pas 5: Restart PostgreSQL
```bash
sudo systemctl restart postgresql
```

### Pas 6: Verifica archive_mode este activ
```bash
sudo -u postgres psql -c "SHOW archive_mode;"
# Trebuie sa returneze: on
```

### Pas 7: Creeaza script de backup zilnic

Creeaza `/usr/local/bin/backup-deschide-db.sh`:

```bash
#!/bin/bash
set -e

# Configuration
DB_NAME="deschide_news"
DB_USER="deschide_admin"
BACKUP_DIR="/var/backups/postgresql/deschide_news"
DATE=$(date +%Y%m%d_%H%M%S)
DAY_OF_WEEK=$(date +%u)
DAY_OF_MONTH=$(date +%d)

# Logging
LOG_FILE="/var/log/postgresql/backup_deschide.log"
exec 1>> "$LOG_FILE" 2>&1

echo "=========================================="
echo "Backup started at $(date)"
echo "=========================================="

# Daily backup (pg_dump)
DAILY_BACKUP="$BACKUP_DIR/daily/${DB_NAME}_${DATE}.sql.gz"
echo "Creating daily backup: $DAILY_BACKUP"
sudo -u postgres pg_dump -Fc "$DB_NAME" | gzip > "$DAILY_BACKUP"

# Verify backup
if [ -f "$DAILY_BACKUP" ] && [ -s "$DAILY_BACKUP" ]; then
    echo "Daily backup created successfully: $(du -h $DAILY_BACKUP)"
else
    echo "ERROR: Daily backup failed!"
    exit 1
fi

# Weekly backup (Sunday = 7)
if [ "$DAY_OF_WEEK" -eq 7 ]; then
    WEEKLY_BACKUP="$BACKUP_DIR/weekly/${DB_NAME}_week_$(date +%Y%W).sql.gz"
    cp "$DAILY_BACKUP" "$WEEKLY_BACKUP"
    echo "Weekly backup created: $WEEKLY_BACKUP"
fi

# Monthly backup (1st of month)
if [ "$DAY_OF_MONTH" -eq "01" ]; then
    MONTHLY_BACKUP="$BACKUP_DIR/monthly/${DB_NAME}_$(date +%Y%m).sql.gz"
    cp "$DAILY_BACKUP" "$MONTHLY_BACKUP"
    echo "Monthly backup created: $MONTHLY_BACKUP"
fi

# Cleanup old backups
echo "Cleaning up old backups..."
find "$BACKUP_DIR/daily" -name "*.sql.gz" -mtime +7 -delete
find "$BACKUP_DIR/weekly" -name "*.sql.gz" -mtime +30 -delete
find "$BACKUP_DIR/monthly" -name "*.sql.gz" -mtime +365 -delete

# Cleanup old WAL archives
find /var/lib/postgresql/archive/deschide_news -name "*.gz" -mtime +7 -delete 2>/dev/null || true

echo "Backup completed at $(date)"
echo ""
```

### Pas 8: Face script-ul executabil si configureaza cron
```bash
sudo chmod +x /usr/local/bin/backup-deschide-db.sh

# Adauga in crontab pentru postgres user
sudo crontab -u postgres -e
# Adauga:
# Daily backup at 2:00 AM
0 2 * * * /usr/local/bin/backup-deschide-db.sh
```

### Pas 9: Testeaza backup-ul manual
```bash
sudo /usr/local/bin/backup-deschide-db.sh
ls -la /var/backups/postgresql/deschide_news/daily/
```

### Pas 10: Testeaza restore
```bash
# Creeaza DB temporar pentru test
sudo -u postgres createdb deschide_news_test

# Restore
LATEST_BACKUP=$(ls -t /var/backups/postgresql/deschide_news/daily/*.sql.gz | head -1)
gunzip -c "$LATEST_BACKUP" | sudo -u postgres pg_restore -d deschide_news_test

# Verifica
sudo -u postgres psql -d deschide_news_test -c "SELECT COUNT(*) FROM article;"

# Cleanup
sudo -u postgres dropdb deschide_news_test
```

## Criterii de Acceptare
- [ ] archive_mode=on
- [ ] WAL files se arhiveaza corect
- [ ] Backup script functioneaza
- [ ] Cron job configurat
- [ ] Restore testat cu succes
- [ ] Retention policy activa (7 daily, 4 weekly, 12 monthly)
```

**Verificare:**
- [ ] `SHOW archive_mode;` returneaza `on`
- [ ] Backup-uri exista in /var/backups/postgresql/
- [ ] Restore testat cu succes

---

### Sarcina 1.7: Adaugare Index pe page_views.category_id

**Agent:** @database-engineer
**Prioritate:** P0 (CRITIC)
**Estimare:** 1 ora
**Dependente:** Niciuna

**Prompt pentru Agent:**

```
## Context
Tabela page_views lipseste index pe category_id, cauzand full table scans.
Aceasta tabela creste rapid si query-urile devin lente.

Database: deschide_news

## Sarcini

### Pas 1: Verifica structura tabelei
```bash
sudo -u postgres psql -d deschide_news -c "\d page_views"
```

### Pas 2: Verifica indexurile existente
```bash
sudo -u postgres psql -d deschide_news -c "\di page_views*"
```

### Pas 3: Analizeaza query-uri lente
```bash
sudo -u postgres psql -d deschide_news -c "
EXPLAIN ANALYZE
SELECT COUNT(*) FROM page_views WHERE category_id = 1;
"
```

### Pas 4: Creeaza migratie Doctrine

```bash
cd /var/www/deschide_news_app/apps/backend
symfony console make:migration
```

In migratia generata, adauga:

```php
public function up(Schema $schema): void
{
    // Index on category_id for analytics queries
    $this->addSql('CREATE INDEX idx_page_views_category_id ON page_views (category_id)');

    // Composite index for date range + category queries
    $this->addSql('CREATE INDEX idx_page_views_category_date ON page_views (category_id, viewed_at)');

    // Index for article lookups
    $this->addSql('CREATE INDEX idx_page_views_article_id ON page_views (article_id)');

    // Composite index for article + date queries
    $this->addSql('CREATE INDEX idx_page_views_article_date ON page_views (article_id, viewed_at)');
}

public function down(Schema $schema): void
{
    $this->addSql('DROP INDEX idx_page_views_category_id');
    $this->addSql('DROP INDEX idx_page_views_category_date');
    $this->addSql('DROP INDEX idx_page_views_article_id');
    $this->addSql('DROP INDEX idx_page_views_article_date');
}
```

### Pas 5: Ruleaza migratia (CONCURRENTLY pentru zero downtime)

Pentru tabele mari, foloseste CREATE INDEX CONCURRENTLY direct:
```bash
sudo -u postgres psql -d deschide_news -c "
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_page_views_category_id
ON page_views (category_id);
"

sudo -u postgres psql -d deschide_news -c "
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_page_views_category_date
ON page_views (category_id, viewed_at);
"
```

### Pas 6: Verifica indexurile create
```bash
sudo -u postgres psql -d deschide_news -c "\di page_views*"
```

### Pas 7: Analizeaza imbunatatirea
```bash
sudo -u postgres psql -d deschide_news -c "
EXPLAIN ANALYZE
SELECT COUNT(*) FROM page_views WHERE category_id = 1;
"
# Trebuie sa foloseasca Index Scan in loc de Seq Scan
```

### Pas 8: Actualizeaza statisticile
```bash
sudo -u postgres psql -d deschide_news -c "ANALYZE page_views;"
```

## Criterii de Acceptare
- [ ] Index idx_page_views_category_id exista
- [ ] Index idx_page_views_category_date exista
- [ ] Query-urile folosesc index (EXPLAIN ANALYZE)
- [ ] Performance imbunatatita pentru analytics queries
```

**Verificare:**
- [ ] `\di page_views*` arata noile indexuri
- [ ] EXPLAIN ANALYZE arata Index Scan

---

## Sprint 2: Securitate si Performanta (Ziua 6-10)

### Obiectiv
Implementare rate limiting, actualizare pachete, optimizare PostgreSQL.

---

### Sarcina 2.1: Implementare Rate Limiting pe API

**Agent:** @backend-developer
**Prioritate:** P2 (MEDIUM)
**Estimare:** 4 ore
**Dependente:** Niciuna

**Prompt pentru Agent:**

```
## Context
API-ul nu are rate limiting implementat.
Aceasta expune aplicatia la brute force si DoS attacks.

Locatie: /var/www/deschide_news_app/apps/backend/

## Sarcini

### Pas 1: Instaleaza symfony/rate-limiter
```bash
cd /var/www/deschide_news_app/apps/backend
composer require symfony/rate-limiter
```

### Pas 2: Configureaza rate limiter

Creeaza/modifica `config/packages/rate_limiter.yaml`:

```yaml
framework:
    rate_limiter:
        # General API rate limit
        api_general:
            policy: 'sliding_window'
            limit: 100
            interval: '1 minute'
            cache_pool: cache.app

        # Strict limit for authentication endpoints
        api_login:
            policy: 'token_bucket'
            limit: 5
            rate: { interval: '1 minute', amount: 5 }
            cache_pool: cache.app

        # Limit for article creation/modification
        api_write:
            policy: 'sliding_window'
            limit: 30
            interval: '1 minute'
            cache_pool: cache.app

        # Limit for search/heavy operations
        api_search:
            policy: 'sliding_window'
            limit: 20
            interval: '1 minute'
            cache_pool: cache.app

        # Anonymous user limits (stricter)
        api_anonymous:
            policy: 'fixed_window'
            limit: 30
            interval: '1 minute'
            cache_pool: cache.app
```

### Pas 3: Creeaza RateLimiter EventSubscriber

Creeaza `src/EventSubscriber/RateLimiterSubscriber.php`:

```php
<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class RateLimiterSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RateLimiterFactory $apiGeneralLimiter,
        private readonly RateLimiterFactory $apiLoginLimiter,
        private readonly RateLimiterFactory $apiWriteLimiter,
        private readonly RateLimiterFactory $apiAnonymousLimiter,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Skip rate limiting for non-API routes
        if (!str_starts_with($path, '/api')) {
            return;
        }

        $clientIp = $request->getClientIp() ?? 'unknown';
        $method = $request->getMethod();

        // Select appropriate limiter based on endpoint and method
        $limiter = $this->selectLimiter($path, $method, $clientIp);
        $limit = $limiter->consume();

        // Add rate limit headers
        $headers = [
            'X-RateLimit-Remaining' => $limit->getRemainingTokens(),
            'X-RateLimit-Retry-After' => $limit->getRetryAfter()?->getTimestamp(),
            'X-RateLimit-Limit' => $limit->getLimit(),
        ];

        if (!$limit->isAccepted()) {
            $response = new JsonResponse([
                '@context' => '/api/contexts/Error',
                '@type' => 'hydra:Error',
                'hydra:title' => 'Too Many Requests',
                'hydra:description' => 'Rate limit exceeded. Please try again later.',
            ], Response::HTTP_TOO_MANY_REQUESTS, $headers);

            $event->setResponse($response);
            return;
        }

        // Store headers to add to response later
        $request->attributes->set('_rate_limit_headers', $headers);
    }

    private function selectLimiter(string $path, string $method, string $clientIp): \Symfony\Component\RateLimiter\RateLimiterInterface
    {
        // Login endpoint - strictest limits
        if (str_contains($path, '/login') || str_contains($path, '/token')) {
            return $this->apiLoginLimiter->create($clientIp . '_login');
        }

        // Write operations
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $this->apiWriteLimiter->create($clientIp . '_write');
        }

        // General API
        return $this->apiGeneralLimiter->create($clientIp . '_general');
    }
}
```

### Pas 4: Creeaza subscriber pentru adaugare headers la response

Creeaza `src/EventSubscriber/RateLimitResponseSubscriber.php`:

```php
<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class RateLimitResponseSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        $headers = $request->attributes->get('_rate_limit_headers', []);
        foreach ($headers as $name => $value) {
            if ($value !== null) {
                $response->headers->set($name, (string) $value);
            }
        }
    }
}
```

### Pas 5: Configureaza Redis pentru rate limiting (optional, recomandat)

In `config/packages/cache.yaml`:

```yaml
framework:
    cache:
        pools:
            rate_limiter.cache:
                adapter: cache.adapter.redis
                provider: 'redis://localhost:6379/2'
```

Actualizeaza `rate_limiter.yaml`:
```yaml
cache_pool: rate_limiter.cache
```

### Pas 6: Testeaza rate limiting
```bash
# Test rapid - ar trebui sa vezi 429 dupa limita
for i in {1..110}; do
    curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8081/api/articles
done | grep -c 429
# Trebuie sa returneze >0

# Verifica headers
curl -I http://127.0.0.1:8081/api/articles | grep -i ratelimit
```

## Criterii de Acceptare
- [ ] Rate limiter configurat in Symfony
- [ ] Endpoint /api/login are limita 5/min
- [ ] Endpoint-uri generale au limita 100/min
- [ ] Headers X-RateLimit-* prezente in raspunsuri
- [ ] 429 returnat cand limita depasita
```

**Verificare:**
- [ ] `curl -I` arata X-RateLimit headers
- [ ] Limita functioneaza (testat cu loop)

---

### Sarcina 2.2: Schimbare APP_ENV de la dev la prod

**Agent:** @devops-engineer
**Prioritate:** P2 (MEDIUM)
**Estimare:** 1 ora
**Dependente:** 1.1 (credentiale in .env.local)

**Prompt pentru Agent:**

```
## Context
.env contine APP_ENV=dev care expune debug info si reduce performanta.
Pentru productie, trebuie APP_ENV=prod.

Locatie: /var/www/deschide_news_app/apps/backend/

## Sarcini

### Pas 1: Verifica .env.local exista
```bash
ls -la /var/www/deschide_news_app/apps/backend/.env.local
```

### Pas 2: Seteaza APP_ENV=prod in .env.local
```bash
# Daca nu exista APP_ENV in .env.local, adauga:
echo "APP_ENV=prod" >> /var/www/deschide_news_app/apps/backend/.env.local

# SAU editeaza direct:
sed -i 's/APP_ENV=dev/APP_ENV=prod/' /var/www/deschide_news_app/apps/backend/.env.local
```

### Pas 3: Actualizeaza .env pentru a avea default=prod
Editeaza `/var/www/deschide_news_app/apps/backend/.env`:
```bash
# Schimba:
APP_ENV=dev
# In:
APP_ENV=prod
```

### Pas 4: Compileaza container pentru productie
```bash
cd /var/www/deschide_news_app/apps/backend

# Compileaza .env pentru productie
composer dump-env prod

# Clear si warm cache
APP_ENV=prod APP_DEBUG=0 symfony console cache:clear
APP_ENV=prod APP_DEBUG=0 symfony console cache:warmup
```

### Pas 5: Verifica environment
```bash
# Verifica environment
APP_ENV=prod symfony console debug:container --env-vars | grep APP_ENV
# Trebuie: APP_ENV=prod

# Verifica debug este dezactivat
curl -I http://127.0.0.1:8081/api | grep -i symfony
# NU trebuie sa contina Symfony profiler headers
```

### Pas 6: Restart server
```bash
symfony server:stop
symfony serve -d --port=8081
```

### Pas 7: Verifica nu mai apar erori de debug
```bash
# Request invalid - nu trebuie sa afiseze stack trace
curl http://127.0.0.1:8081/api/nonexistent
# Raspunsul trebuie sa fie JSON generic de 404, fara stack trace
```

## Criterii de Acceptare
- [ ] APP_ENV=prod in configuratie
- [ ] Debug mode dezactivat
- [ ] Stack traces nu sunt expuse
- [ ] Symfony profiler dezactivat
- [ ] Cache compilat pentru productie
```

**Verificare:**
- [ ] `symfony console about` arata Environment: prod
- [ ] Erorile nu afiseaza stack trace

---

### Sarcina 2.3: Actualizare Pachete Backend (20 outdated)

**Agent:** @backend-developer
**Prioritate:** P2 (MEDIUM)
**Estimare:** 3 ore
**Dependente:** 1.3 (dupa CVE fix)

**Prompt pentru Agent:**

```
## Context
20 pachete backend sunt outdated conform composer outdated.
Trebuie actualizate incremental pentru a evita breaking changes.

Locatie: /var/www/deschide_news_app/apps/backend/

## Sarcini

### Pas 1: Verifica pachetele outdated
```bash
cd /var/www/deschide_news_app/apps/backend
composer outdated --direct
```

### Pas 2: Creeaza backup composer.lock
```bash
cp composer.lock composer.lock.backup.$(date +%Y%m%d)
```

### Pas 3: Actualizeaza in ordinea prioritatii

#### A) Pachete de securitate (PRIORITATE 1):
```bash
composer update symfony/security-bundle symfony/http-foundation lexik/jwt-authentication-bundle --with-dependencies
```

#### B) Doctrine si ORM (PRIORITATE 2):
```bash
composer update doctrine/orm doctrine/dbal doctrine/doctrine-bundle doctrine/doctrine-migrations-bundle --with-dependencies
```

#### C) API Platform (PRIORITATE 3):
```bash
composer update api-platform/symfony api-platform/doctrine-orm --with-dependencies
```

#### D) Alte pachete Symfony (PRIORITATE 4):
```bash
composer update "symfony/*" --with-dependencies
```

#### E) Pachete dev (PRIORITATE 5):
```bash
composer update --dev
```

### Pas 4: Dupa fiecare grup, testeaza
```bash
# Clear cache
symfony console cache:clear

# Verifica schema
symfony console doctrine:schema:validate

# Test API
curl http://127.0.0.1:8081/api
curl http://127.0.0.1:8081/api/articles

# Ruleaza teste (daca exista)
vendor/bin/phpunit
```

### Pas 5: Daca apar erori, rollback si investiga
```bash
# Rollback la backup
cp composer.lock.backup.* composer.lock
composer install
```

### Pas 6: Verifica security audit
```bash
composer audit
```

### Pas 7: Commit changes
```bash
git add composer.json composer.lock
git commit -m "chore(backend): update composer dependencies"
```

## Criterii de Acceptare
- [ ] composer outdated --direct arata mai putin de 5 pachete
- [ ] composer audit nu arata vulnerabilitati
- [ ] API functioneaza corect
- [ ] Toate testele trec
- [ ] Cache compileaza fara erori
```

**Verificare:**
- [ ] `composer outdated --direct` < 5 pachete
- [ ] `composer audit` curat
- [ ] API functioneaza

---

### Sarcina 2.4: Actualizare Pachete Frontend (24 outdated)

**Agent:** @frontend-developer
**Prioritate:** P2 (MEDIUM)
**Estimare:** 3 ore
**Dependente:** Niciuna

**Prompt pentru Agent:**

```
## Context
24 pachete frontend sunt outdated conform pnpm outdated.
Trebuie actualizate atent, mai ales pentru React 19.2 si Next.js 16.

Locatie: /var/www/deschide_news_app/apps/frontend/

## Sarcini

### Pas 1: Verifica pachetele outdated
```bash
cd /var/www/deschide_news_app/apps/frontend
pnpm outdated
```

### Pas 2: Creeaza backup
```bash
cp pnpm-lock.yaml pnpm-lock.yaml.backup.$(date +%Y%m%d)
```

### Pas 3: Actualizeaza in ordinea prioritatii

#### A) Pachete de securitate si critice:
```bash
pnpm update next react react-dom
```

#### B) Pachete @tanstack (React Query):
```bash
pnpm update @tanstack/react-query @tanstack/react-query-devtools
```

#### C) Pachete UI (@dnd-kit, lucide-react, etc):
```bash
pnpm update @dnd-kit/core @dnd-kit/sortable @dnd-kit/utilities lucide-react
```

#### D) Pachete de test:
```bash
pnpm update -D @playwright/test @testing-library/react @testing-library/jest-dom
```

#### E) Toate celelalte:
```bash
pnpm update
```

### Pas 4: Dupa fiecare grup, testeaza
```bash
# Build
pnpm build:no-generate

# Lint
pnpm lint

# Unit tests
pnpm test

# Start dev si verifica manual
pnpm dev
# Deschide http://localhost:3005 si verifica paginile principale
```

### Pas 5: Verifica vulnerabilitati
```bash
pnpm audit
```

### Pas 6: Daca apar erori, rollback
```bash
cp pnpm-lock.yaml.backup.* pnpm-lock.yaml
pnpm install
```

### Pas 7: Commit
```bash
git add package.json pnpm-lock.yaml
git commit -m "chore(frontend): update npm dependencies"
```

## Criterii de Acceptare
- [ ] pnpm outdated arata mai putin de 5 pachete
- [ ] pnpm audit nu arata vulnerabilitati high/critical
- [ ] Build reuseste fara erori
- [ ] Lint trece
- [ ] Aplicatia functioneaza corect
```

**Verificare:**
- [ ] `pnpm outdated` < 5 pachete
- [ ] `pnpm audit` fara critice
- [ ] `pnpm build` reuseste

---

### Sarcina 2.5: Optimizare PostgreSQL Configuration

**Agent:** @database-engineer
**Prioritate:** P1 (HIGH)
**Estimare:** 3 ore
**Dependente:** Niciuna

**Prompt pentru Agent:**

```
## Context
PostgreSQL are configuratie default neoptimizata:
- shared_buffers=128MB (prea mic)
- random_page_cost=100 (default pentru HDD, nu SSD)
- Query logging dezactivat

Server: PostgreSQL 18
RAM: Presupunem 16GB+ disponibil

## Sarcini

### Pas 1: Verifica configuratia curenta
```bash
sudo -u postgres psql -c "SHOW shared_buffers;"
sudo -u postgres psql -c "SHOW work_mem;"
sudo -u postgres psql -c "SHOW effective_cache_size;"
sudo -u postgres psql -c "SHOW random_page_cost;"
sudo -u postgres psql -c "SHOW log_statement;"
```

### Pas 2: Determina RAM disponibil
```bash
free -h
# Noteaza Total RAM
```

### Pas 3: Calculeaza valori optime
```
Pentru 16GB RAM:
- shared_buffers = 4GB (25% din RAM)
- effective_cache_size = 12GB (75% din RAM)
- work_mem = 64MB (pentru query-uri complexe)
- maintenance_work_mem = 512MB (pentru VACUUM, CREATE INDEX)
```

### Pas 4: Editeaza postgresql.conf
```bash
sudo nano /etc/postgresql/18/main/postgresql.conf
```

Modifica/adauga:

```conf
#------------------------------------------------------------------------------
# CONNECTIONS AND AUTHENTICATION
#------------------------------------------------------------------------------
max_connections = 200

#------------------------------------------------------------------------------
# RESOURCE USAGE (except WAL)
#------------------------------------------------------------------------------
# Memory Settings (for 16GB RAM server)
shared_buffers = 4GB                    # 25% of RAM
huge_pages = try
work_mem = 64MB                         # For complex queries
maintenance_work_mem = 512MB            # For VACUUM, INDEX
effective_cache_size = 12GB             # 75% of RAM

# Disk Settings (for SSD)
random_page_cost = 1.1                  # SSD: 1.1, HDD: 4.0
effective_io_concurrency = 200          # SSD: 200, HDD: 2

# Background Writer
bgwriter_delay = 200ms
bgwriter_lru_maxpages = 100
bgwriter_lru_multiplier = 2.0

#------------------------------------------------------------------------------
# WRITE AHEAD LOG
#------------------------------------------------------------------------------
wal_buffers = 64MB
checkpoint_completion_target = 0.9
max_wal_size = 2GB
min_wal_size = 512MB

#------------------------------------------------------------------------------
# QUERY TUNING
#------------------------------------------------------------------------------
default_statistics_target = 100
enable_partitionwise_join = on
enable_partitionwise_aggregate = on

#------------------------------------------------------------------------------
# LOGGING
#------------------------------------------------------------------------------
logging_collector = on
log_directory = 'log'
log_filename = 'postgresql-%Y-%m-%d.log'
log_rotation_age = 1d
log_rotation_size = 100MB

log_min_duration_statement = 1000       # Log queries > 1 second
log_checkpoints = on
log_connections = off                   # Reduce noise
log_disconnections = off
log_lock_waits = on
log_temp_files = 0                      # Log all temp files

# Query logging for debugging (disable in prod if too verbose)
log_statement = 'ddl'                   # Log DDL statements only

#------------------------------------------------------------------------------
# AUTOVACUUM
#------------------------------------------------------------------------------
autovacuum = on
log_autovacuum_min_duration = 1000
autovacuum_max_workers = 3
autovacuum_naptime = 60s
autovacuum_vacuum_threshold = 50
autovacuum_analyze_threshold = 50
autovacuum_vacuum_scale_factor = 0.1
autovacuum_analyze_scale_factor = 0.05
```

### Pas 5: Verifica configuratia
```bash
sudo -u postgres pg_ctlcluster 18 main checkconf
```

### Pas 6: Restart PostgreSQL
```bash
sudo systemctl restart postgresql
```

### Pas 7: Verifica noile setari
```bash
sudo -u postgres psql -c "SHOW shared_buffers;"
sudo -u postgres psql -c "SHOW random_page_cost;"
sudo -u postgres psql -c "SHOW log_min_duration_statement;"
```

### Pas 8: Verifica query logging functioneaza
```bash
# Ruleaza un query lent
sudo -u postgres psql -d deschide_news -c "SELECT pg_sleep(2);"

# Verifica log
sudo tail -f /var/lib/postgresql/18/main/log/postgresql-$(date +%Y-%m-%d).log
```

## Criterii de Acceptare
- [ ] shared_buffers = 4GB
- [ ] random_page_cost = 1.1 (pentru SSD)
- [ ] work_mem >= 64MB
- [ ] Query-urile > 1s sunt logate
- [ ] PostgreSQL porneste fara erori
- [ ] Aplicatia functioneaza normal
```

**Verificare:**
- [ ] `SHOW shared_buffers;` = 4GB
- [ ] `SHOW random_page_cost;` = 1.1
- [ ] Log files exista si sunt populate

---

### Sarcina 2.6: Reducere Conturi Superuser (6+ -> 1)

**Agent:** @database-engineer
**Prioritate:** P1 (HIGH)
**Estimare:** 2 ore
**Dependente:** Niciuna

**Prompt pentru Agent:**

```
## Context
Exista 6+ conturi cu privilegii SUPERUSER in PostgreSQL.
Best practice: doar 1 superuser (postgres), restul cu privilegii minime.

Database: deschide_news

## Sarcini

### Pas 1: Identifica toate conturile superuser
```bash
sudo -u postgres psql -c "SELECT usename, usesuper FROM pg_user WHERE usesuper = true;"
```

### Pas 2: Identifica rolurile cu privilegii excesive
```bash
sudo -u postgres psql -c "
SELECT r.rolname, r.rolsuper, r.rolcreaterole, r.rolcreatedb, r.rolreplication
FROM pg_roles r
WHERE r.rolsuper OR r.rolcreaterole OR r.rolcreatedb
ORDER BY r.rolname;
"
```

### Pas 3: Creeaza rol dedicat pentru aplicatie (daca nu exista)
```bash
sudo -u postgres psql -c "
-- Creeaza rol pentru aplicatie (fara superuser)
CREATE ROLE deschide_app WITH
    LOGIN
    PASSWORD 'PAROLA_SIGURA_NOUA'
    NOSUPERUSER
    NOCREATEDB
    NOCREATEROLE
    NOREPLICATION;

-- Grant acces la database
GRANT CONNECT ON DATABASE deschide_news TO deschide_app;

-- Grant usage pe schema
GRANT USAGE ON SCHEMA public TO deschide_app;

-- Grant acces la toate tabelele existente
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO deschide_app;

-- Grant acces la secvente
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO deschide_app;

-- Grant default pentru tabele noi
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO deschide_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO deschide_app;
"
```

### Pas 4: Actualizeaza .env.local cu noul user
```bash
# Editeaza /var/www/deschide_news_app/apps/backend/.env.local
# Schimba DATABASE_URL de la deschide_admin la deschide_app
```

### Pas 5: Testeaza conexiunea cu noul user
```bash
cd /var/www/deschide_news_app/apps/backend
symfony console doctrine:query:sql "SELECT 1"
symfony console doctrine:schema:validate
```

### Pas 6: Revoaca privilegii superuser de la conturile ne-necesare
```bash
sudo -u postgres psql -c "
-- Pentru fiecare user superuser (inafara de postgres):
-- ALTER USER username WITH NOSUPERUSER;

-- Exemplu:
-- ALTER USER deschide_admin WITH NOSUPERUSER;
"
```

### Pas 7: Sterge conturile neutilizate (optional)
```bash
# ATENTIE: Doar dupa ce confirmi ca nu sunt folosite
sudo -u postgres psql -c "
-- DROP USER IF EXISTS unused_superuser;
"
```

### Pas 8: Verifica rezultatul final
```bash
sudo -u postgres psql -c "SELECT usename, usesuper FROM pg_user WHERE usesuper = true;"
# Trebuie sa afiseze DOAR 'postgres'
```

## Criterii de Acceptare
- [ ] Doar 1 superuser (postgres)
- [ ] Aplicatia foloseste rol fara superuser
- [ ] Toate operatiile CRUD functioneaza
- [ ] Migratiile pot rula
```

**Verificare:**
- [ ] `SELECT usesuper FROM pg_user;` arata doar postgres cu true
- [ ] Aplicatia functioneaza cu noul rol

---

### Sarcina 2.7: Corectare Doctrine serverVersion

**Agent:** @backend-developer
**Prioritate:** P1 (HIGH)
**Estimare:** 30 minute
**Dependente:** Niciuna

**Prompt pentru Agent:**

```
## Context
Doctrine are serverVersion=16 dar PostgreSQL instalat este versiunea 18.
Acest mismatch poate cauza probleme de compatibilitate.

Locatie: /var/www/deschide_news_app/apps/backend/

## Sarcini

### Pas 1: Verifica versiunea PostgreSQL instalata
```bash
psql --version
# SAU
sudo -u postgres psql -c "SELECT version();"
```

### Pas 2: Actualizeaza config/packages/doctrine.yaml

Editeaza `/var/www/deschide_news_app/apps/backend/config/packages/doctrine.yaml`:

```yaml
doctrine:
    dbal:
        default_connection: default
        connections:
            default:
                url: '%env(resolve:DATABASE_URL)%'
                driver: 'pdo_pgsql'
                server_version: '18'  # SCHIMBAT de la 17
                # ... restul ramane la fel
```

### Pas 3: Actualizeaza DATABASE_URL in .env.local
```bash
# Verifica/modifica /var/www/deschide_news_app/apps/backend/.env.local
# serverVersion=18 (nu 16 sau 17)
DATABASE_URL="postgresql://deschide_app:PAROLA@127.0.0.1:5432/deschide_news?serverVersion=18&charset=utf8"
```

### Pas 4: Clear cache
```bash
cd /var/www/deschide_news_app/apps/backend
symfony console cache:clear
```

### Pas 5: Valideaza schema
```bash
symfony console doctrine:schema:validate
```

### Pas 6: Testeaza
```bash
symfony console doctrine:query:sql "SELECT 1"
curl http://127.0.0.1:8081/api/articles
```

## Criterii de Acceptare
- [ ] doctrine.yaml are server_version: '18'
- [ ] DATABASE_URL are serverVersion=18
- [ ] doctrine:schema:validate trece
- [ ] API functioneaza
```

**Verificare:**
- [ ] `grep server_version doctrine.yaml` arata 18
- [ ] Schema validation OK

---

## Sprint 3: Optimizari Performanta (Ziua 11-15)

### Obiectiv
Eliminare refresh() excesive, restrictie CORS, activare React strict mode, pg_stat_statements.

---

### Sarcina 3.1: Eliminare Apeluri Excesive refresh() din State Providers

**Agent:** @performance-optimizer
**Prioritate:** P0 (CRITIC - Performanta)
**Estimare:** 8 ore
**Dependente:** Niciuna

**Prompt pentru Agent:**

```
## Context
Providerii API Platform folosesc EntityManager::refresh() excesiv pentru a incarca traduceri.
Acest pattern cauzeaza 40-80x mai multe query-uri decat necesar.

Provideri afectati:
1. /var/www/deschide_news_app/apps/backend/src/State/ImportantArticlesListProvider.php
2. /var/www/deschide_news_app/apps/backend/src/State/LiveTextProvider.php
3. /var/www/deschide_news_app/apps/backend/src/State/ArchivedArticleProvider.php
4. /var/www/deschide_news_app/apps/backend/src/State/ImageProvider.php

## Problema
Pattern problematic (cauzeaza N+1 queries):
```php
foreach ($results as $item) {
    $this->entityManager->refresh($item);
    $item->setTranslatableLocale($locale);
    $this->entityManager->refresh($item);  // <-- DUBLU refresh!

    if ($item->getCategory()) {
        $this->entityManager->refresh($item->getCategory());  // <-- N query-uri extra
    }
}
```

## Solutie
Gedmo Translatable HINT_TRANSLATABLE_LOCALE deja incarca traducerile la query time.
NU este necesar refresh() dupa ce hint-ul este setat.

## Sarcini

### Pas 1: Analizeaza query-urile actuale

Activeaza Doctrine SQL logging temporar:
```yaml
# config/packages/doctrine.yaml - adauga temporar
doctrine:
    dbal:
        connections:
            default:
                logging: true
                profiling: true
```

```bash
# Ruleaza request si numara query-urile
curl -H "Accept-Language: ro" http://127.0.0.1:8081/api/important_articles_lists
# Verifica in profiler numarul de query-uri
```

### Pas 2: Refactorizare ImportantArticlesListProvider.php

INAINTE:
```php
public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
{
    $locale = $this->requestStack->getCurrentRequest()?->getPreferredLanguage(['ro', 'en', 'ru']) ?? 'ro';

    // Collection
    $qb = $this->repository->createQueryBuilder('ial')
        ->leftJoin('ial.article', 'a')
        ->addSelect('a')
        // ... joins
        ->orderBy('ial.position', 'ASC');

    $query = $qb->getQuery();
    $query->setHint(TranslatableListener::HINT_TRANSLATABLE_LOCALE, $locale);

    $results = $query->getResult();

    // PROBLEMA: refresh() pe fiecare entitate
    foreach ($results as $item) {
        if ($item && $item->getArticle()) {
            $this->loadArticleTranslation($item->getArticle(), $locale);
        }
    }

    return $results;
}

private function loadArticleTranslation($article, string $locale): void
{
    if (!$article) return;

    $this->entityManager->refresh($article);  // <-- ELIMINA
    $article->setTranslatableLocale($locale);
    $this->entityManager->refresh($article);  // <-- ELIMINA

    if ($article->getCategory()) {
        $category = $article->getCategory();
        $this->entityManager->refresh($category);  // <-- ELIMINA
        $category->setTranslatableLocale($locale);
        $this->entityManager->refresh($category);  // <-- ELIMINA
    }
}
```

DUPA:
```php
public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
{
    $locale = $this->requestStack->getCurrentRequest()?->getPreferredLanguage(['ro', 'en', 'ru']) ?? 'ro';

    if (isset($uriVariables['id'])) {
        // Single item
        $item = $this->repository->find($uriVariables['id']);
        if (!$item) {
            return null;
        }

        // Reload with translations using a proper query
        return $this->loadSingleItemWithTranslations($item->getId(), $locale);
    }

    // Collection - use query builder with hint
    $qb = $this->repository->createQueryBuilder('ial')
        ->leftJoin('ial.article', 'a')
        ->addSelect('a')
        ->leftJoin('a.category', 'c')
        ->addSelect('c')
        ->leftJoin('a.authors', 'auth')
        ->addSelect('auth')
        ->leftJoin('a.articleImages', 'ai')
        ->addSelect('ai')
        ->leftJoin('ai.image', 'img')
        ->addSelect('img')
        ->andWhere('a.status != :archived_status')
        ->setParameter('archived_status', 'archived')
        ->orderBy('ial.position', 'ASC')
        ->addOrderBy('ai.position', 'ASC');

    $query = $qb->getQuery();

    // Gedmo hint loads translations at query time - NO refresh needed!
    $query->setHint(TranslatableListener::HINT_TRANSLATABLE_LOCALE, $locale);
    $query->setHint(
        \Doctrine\ORM\Query::HINT_REFRESH,
        true
    );

    return $query->getResult();
}

private function loadSingleItemWithTranslations(int $id, string $locale): ?object
{
    $qb = $this->repository->createQueryBuilder('ial')
        ->leftJoin('ial.article', 'a')
        ->addSelect('a')
        ->leftJoin('a.category', 'c')
        ->addSelect('c')
        ->leftJoin('a.authors', 'auth')
        ->addSelect('auth')
        ->leftJoin('a.articleImages', 'ai')
        ->addSelect('ai')
        ->leftJoin('ai.image', 'img')
        ->addSelect('img')
        ->where('ial.id = :id')
        ->setParameter('id', $id);

    $query = $qb->getQuery();
    $query->setHint(TranslatableListener::HINT_TRANSLATABLE_LOCALE, $locale);

    return $query->getOneOrNullResult();
}
```

### Pas 3: Aplica aceeasi refactorizare pentru celelalte provideri

#### LiveTextProvider.php
- Elimina toate apelurile $this->entityManager->refresh()
- Pastreaza doar HINT_TRANSLATABLE_LOCALE pe query

#### ArchivedArticleProvider.php
- Elimina toate apelurile $this->entityManager->refresh()
- Elimina loop-ul de refresh pentru authors si tags
- Pastreaza doar HINT_TRANSLATABLE_LOCALE pe query

#### ImageProvider.php
- Elimina apelul $this->entityManager->refresh($result) din single item
- Elimina loop-ul de refresh din collection
- Pastreaza doar HINT_TRANSLATABLE_LOCALE pe query

### Pas 4: Testeaza reducerea query-urilor

```bash
# Activeaza query logging
# Apoi ruleaza:
curl -H "Accept-Language: ro" http://127.0.0.1:8081/api/important_articles_lists

# Compara numarul de query-uri:
# INAINTE: ~100+ queries pentru 10 articole
# DUPA: ~3-5 queries pentru 10 articole
```

### Pas 5: Verifica traducerile functioneaza corect

```bash
# Test Romanian
curl -H "Accept-Language: ro" http://127.0.0.1:8081/api/important_articles_lists | jq '.[0].article.title'

# Test English
curl -H "Accept-Language: en" http://127.0.0.1:8081/api/important_articles_lists | jq '.[0].article.title'

# Test Russian
curl -H "Accept-Language: ru" http://127.0.0.1:8081/api/important_articles_lists | jq '.[0].article.title'
```

### Pas 6: Benchmark performance

```bash
# Inainte de modificari, noteaza timpul:
time curl -s -o /dev/null http://127.0.0.1:8081/api/important_articles_lists

# Dupa modificari, compara:
time curl -s -o /dev/null http://127.0.0.1:8081/api/important_articles_lists

# Target: reducere 10-20x in timp de raspuns
```

## Criterii de Acceptare
- [ ] ImportantArticlesListProvider fara refresh()
- [ ] LiveTextProvider fara refresh()
- [ ] ArchivedArticleProvider fara refresh()
- [ ] ImageProvider fara refresh()
- [ ] Traducerile functioneaza corect (testat ro/en/ru)
- [ ] Query count redus cu 80%+
- [ ] Response time redus semnificativ
```

**Verificare:**
- [ ] `grep -r "entityManager->refresh" src/State/` nu returneaza rezultate
- [ ] Traducerile functioneaza in toate limbile
- [ ] Profiler arata < 10 queries per request

---

### Sarcina 3.2: Restrictie CORS pentru /media

**Agent:** @backend-developer
**Prioritate:** P2 (MEDIUM)
**Estimare:** 1 ora
**Dependente:** Niciuna

**Prompt pentru Agent:**

```
## Context
CORS permite `*` pentru `/media` endpoint, permitand hotlinking de pe orice site.

Locatie: /var/www/deschide_news_app/apps/backend/config/packages/nelmio_cors.yaml

## Sarcini

### Pas 1: Editeaza nelmio_cors.yaml

Modifica `/var/www/deschide_news_app/apps/backend/config/packages/nelmio_cors.yaml`:

INAINTE:
```yaml
'^/media':
    allow_origin: ['*']
    allow_methods: ['GET', 'OPTIONS']
    allow_headers: ['*']
    max_age: 3600
```

DUPA:
```yaml
'^/media':
    allow_origin: ['%env(CORS_ALLOW_ORIGIN)%', 'http://localhost:3005', 'https://deschide.md']
    allow_methods: ['GET', 'OPTIONS']
    allow_headers: ['Content-Type', 'Accept']
    max_age: 3600
```

### Pas 2: Clear cache
```bash
cd /var/www/deschide_news_app/apps/backend
symfony console cache:clear
```

### Pas 3: Testeaza CORS
```bash
# Request valid (din frontend)
curl -H "Origin: http://localhost:3005" \
     -H "Access-Control-Request-Method: GET" \
     -X OPTIONS \
     http://127.0.0.1:8081/media/images/test.jpg -v

# Request invalid (din alt domeniu)
curl -H "Origin: http://evil.com" \
     -H "Access-Control-Request-Method: GET" \
     -X OPTIONS \
     http://127.0.0.1:8081/media/images/test.jpg -v
# NU trebuie sa contina Access-Control-Allow-Origin
```

## Criterii de Acceptare
- [ ] /media nu mai permite allow_origin: *
- [ ] Frontend poate accesa /media
- [ ] Domenii externe nu pot accesa /media (CORS blocked)
```

**Verificare:**
- [ ] CORS headers corecte pentru localhost:3005
- [ ] CORS blocat pentru domenii externe

---

### Sarcina 3.3: Activare React Strict Mode

**Agent:** @frontend-developer
**Prioritate:** P2 (MEDIUM)
**Estimare:** 2 ore
**Dependente:** 1.5 (XSS fix - poate cauza efecte)

**Prompt pentru Agent:**

```
## Context
React Strict Mode este dezactivat in next.config.mjs.
Strict mode ajuta la detectarea problemelor in development.

Locatie: /var/www/deschide_news_app/apps/frontend/next.config.mjs

## Sarcini

### Pas 1: Activeaza strict mode

Editeaza `/var/www/deschide_news_app/apps/frontend/next.config.mjs`:

```javascript
// Schimba:
reactStrictMode: false,

// In:
reactStrictMode: true,
```

### Pas 2: Start development server
```bash
cd /var/www/deschide_news_app/apps/frontend
pnpm dev
```

### Pas 3: Verifica consola pentru warning-uri
Deschide http://localhost:3005 si verifica consola browser pentru:
- Double rendering warnings (expected in strict mode)
- useEffect cleanup issues
- Deprecated API usage
- Unsafe lifecycle methods

### Pas 4: Rezolva warning-urile gasite

Warning-uri comune si fix-uri:

#### A) useEffect fara cleanup:
```typescript
// GRESIT
useEffect(() => {
    const timer = setInterval(...);
}, []);

// CORECT
useEffect(() => {
    const timer = setInterval(...);
    return () => clearInterval(timer);  // cleanup
}, []);
```

#### B) Fetch fara abort:
```typescript
// CORECT
useEffect(() => {
    const controller = new AbortController();

    fetch('/api/data', { signal: controller.signal })
        .then(...)
        .catch(err => {
            if (err.name !== 'AbortError') throw err;
        });

    return () => controller.abort();
}, []);
```

#### C) State update dupa unmount:
```typescript
// CORECT - use isMounted ref
useEffect(() => {
    let isMounted = true;

    fetch('/api/data')
        .then(data => {
            if (isMounted) {
                setState(data);
            }
        });

    return () => { isMounted = false; };
}, []);
```

### Pas 5: Verifica build reuseste
```bash
pnpm build
```

### Pas 6: Testeaza functionalitatile principale
- [ ] Homepage se incarca
- [ ] Articole se afiseaza
- [ ] Navigare functioneaza
- [ ] Forms functioneaza

## Criterii de Acceptare
- [ ] reactStrictMode: true
- [ ] Build reuseste fara erori
- [ ] Warning-urile de consola sunt rezolvate
- [ ] Aplicatia functioneaza corect
```

**Verificare:**
- [ ] `grep reactStrictMode next.config.mjs` arata true
- [ ] `pnpm build` reuseste
- [ ] Consola browser fara warning-uri

---

### Sarcina 3.4: Instalare pg_stat_statements Extension

**Agent:** @database-engineer
**Prioritate:** P2 (MEDIUM)
**Estimare:** 1 ora
**Dependente:** Niciuna

**Prompt pentru Agent:**

```
## Context
pg_stat_statements nu este instalat, facand imposibila analiza query-urilor.
Aceasta extensie este esentiala pentru optimizare performance.

Database: PostgreSQL 18

## Sarcini

### Pas 1: Verifica extensia este disponibila
```bash
sudo -u postgres psql -c "SELECT * FROM pg_available_extensions WHERE name = 'pg_stat_statements';"
```

### Pas 2: Instaleaza pachetul contrib (daca lipseste)
```bash
# Ubuntu/Debian
sudo apt-get install postgresql-contrib-18

# sau
sudo apt-get install postgresql-18-pg-stat-statements
```

### Pas 3: Configureaza in postgresql.conf
```bash
sudo nano /etc/postgresql/18/main/postgresql.conf
```

Adauga:
```conf
# pg_stat_statements
shared_preload_libraries = 'pg_stat_statements'
pg_stat_statements.track = all
pg_stat_statements.max = 10000
pg_stat_statements.track_utility = on
```

### Pas 4: Restart PostgreSQL
```bash
sudo systemctl restart postgresql
```

### Pas 5: Creeaza extensia in database
```bash
sudo -u postgres psql -d deschide_news -c "CREATE EXTENSION IF NOT EXISTS pg_stat_statements;"
```

### Pas 6: Verifica extensia functioneaza
```bash
sudo -u postgres psql -d deschide_news -c "SELECT * FROM pg_stat_statements LIMIT 5;"
```

### Pas 7: Query pentru top 10 slow queries
```bash
sudo -u postgres psql -d deschide_news -c "
SELECT
    substring(query, 1, 100) as query_preview,
    calls,
    round(total_exec_time::numeric, 2) as total_ms,
    round(mean_exec_time::numeric, 2) as mean_ms,
    rows
FROM pg_stat_statements
ORDER BY total_exec_time DESC
LIMIT 10;
"
```

### Pas 8: Creeaza view pentru monitorizare
```bash
sudo -u postgres psql -d deschide_news -c "
CREATE OR REPLACE VIEW slow_queries AS
SELECT
    queryid,
    substring(query, 1, 200) as query_preview,
    calls,
    round(total_exec_time::numeric, 2) as total_ms,
    round(mean_exec_time::numeric, 2) as mean_ms,
    round(stddev_exec_time::numeric, 2) as stddev_ms,
    rows,
    round((100.0 * shared_blks_hit / nullif(shared_blks_hit + shared_blks_read, 0))::numeric, 2) as cache_hit_pct
FROM pg_stat_statements
WHERE calls > 10
ORDER BY mean_exec_time DESC;
"
```

## Criterii de Acceptare
- [ ] pg_stat_statements extensie instalata
- [ ] Query statistics sunt colectate
- [ ] View slow_queries creat
- [ ] PostgreSQL porneste fara erori
```

**Verificare:**
- [ ] `\dx` arata pg_stat_statements
- [ ] `SELECT * FROM pg_stat_statements LIMIT 1;` functioneaza

---

## Sprint 4: Finalizare si Documentare (Ziua 16-18)

### Obiectiv
Implementare GDPR retention, optimizare autovacuum, documentare completa.

---

### Sarcina 4.1: Implementare GDPR Data Retention pentru page_views

**Agent:** @database-engineer
**Prioritate:** P2 (MEDIUM)
**Estimare:** 3 ore
**Dependente:** Niciuna

**Prompt pentru Agent:**

```
## Context
Tabela page_views stocheaza IP-uri fara retention policy.
GDPR cere minimizarea datelor si retention limits.

Database: deschide_news, tabela: page_views

## Sarcini

### Pas 1: Analizeaza structura page_views
```bash
sudo -u postgres psql -d deschide_news -c "\d page_views"
sudo -u postgres psql -d deschide_news -c "SELECT COUNT(*), MIN(viewed_at), MAX(viewed_at) FROM page_views;"
```

### Pas 2: Creeaza functie de anonimizare IP
```bash
sudo -u postgres psql -d deschide_news -c "
CREATE OR REPLACE FUNCTION anonymize_ip(ip_address inet)
RETURNS inet AS \$\$
BEGIN
    IF ip_address IS NULL THEN
        RETURN NULL;
    END IF;

    -- IPv4: zero last octet (e.g., 192.168.1.100 -> 192.168.1.0)
    IF family(ip_address) = 4 THEN
        RETURN set_masklen(ip_address, 24)::inet;
    END IF;

    -- IPv6: zero last 80 bits
    IF family(ip_address) = 6 THEN
        RETURN set_masklen(ip_address, 48)::inet;
    END IF;

    RETURN NULL;
END;
\$\$ LANGUAGE plpgsql IMMUTABLE;
"
```

### Pas 3: Creeaza functie de cleanup (retentie 30 zile)
```bash
sudo -u postgres psql -d deschide_news -c "
CREATE OR REPLACE FUNCTION cleanup_old_page_views()
RETURNS void AS \$\$
DECLARE
    deleted_count integer;
BEGIN
    -- Anonimizeaza IP-uri mai vechi de 7 zile
    UPDATE page_views
    SET ip_address = anonymize_ip(ip_address::inet)::varchar
    WHERE viewed_at < NOW() - INTERVAL '7 days'
    AND ip_address NOT LIKE '%0';  -- nu anonimiza deja anonimizate

    -- Sterge inregistrari mai vechi de 90 zile
    DELETE FROM page_views
    WHERE viewed_at < NOW() - INTERVAL '90 days';

    GET DIAGNOSTICS deleted_count = ROW_COUNT;
    RAISE NOTICE 'Deleted % old page_views records', deleted_count;
END;
\$\$ LANGUAGE plpgsql;
"
```

### Pas 4: Creeaza job de cleanup zilnic
```bash
# Instaleaza pg_cron daca nu exista
sudo apt-get install postgresql-18-cron

# Activeaza in postgresql.conf
# shared_preload_libraries = 'pg_stat_statements,pg_cron'

# Creeaza cron job
sudo -u postgres psql -d deschide_news -c "
SELECT cron.schedule('cleanup-page-views', '0 3 * * *', 'SELECT cleanup_old_page_views();');
"
```

### Pas 5: Alternativ - Symfony Command pentru cleanup
Daca pg_cron nu este disponibil, creeaza Symfony command:

```bash
cd /var/www/deschide_news_app/apps/backend
symfony console make:command CleanupPageViews
```

In src/Command/CleanupPageViewsCommand.php:
```php
<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:cleanup-page-views',
    description: 'Cleanup old page views for GDPR compliance'
)]
class CleanupPageViewsCommand extends Command
{
    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Anonimize IPs older than 7 days
        $this->connection->executeStatement("
            UPDATE page_views
            SET ip_address = regexp_replace(ip_address, '\.\d+$', '.0')
            WHERE viewed_at < NOW() - INTERVAL '7 days'
            AND ip_address NOT LIKE '%.0'
        ");

        // Delete records older than 90 days
        $deleted = $this->connection->executeStatement("
            DELETE FROM page_views
            WHERE viewed_at < NOW() - INTERVAL '90 days'
        ");

        $output->writeln(sprintf('Deleted %d old records', $deleted));

        return Command::SUCCESS;
    }
}
```

Adauga in crontab:
```bash
# Ruleaza zilnic la 3 AM
0 3 * * * cd /var/www/deschide_news_app/apps/backend && symfony console app:cleanup-page-views
```

### Pas 6: Testeaza cleanup
```bash
symfony console app:cleanup-page-views
```

## Criterii de Acceptare
- [ ] IP-uri anonimizate dupa 7 zile
- [ ] Date sterse dupa 90 zile
- [ ] Job automat configurat
- [ ] Functia de cleanup testat
```

**Verificare:**
- [ ] `SELECT * FROM page_views WHERE viewed_at < NOW() - INTERVAL '7 days';` arata IP-uri anonimizate
- [ ] Cron job configurat

---

### Sarcina 4.2: Optimizare Autovacuum pentru High-Traffic Tables

**Agent:** @database-engineer
**Prioritate:** P3 (LOW)
**Estimare:** 2 ore
**Dependente:** Niciuna

**Prompt pentru Agent:**

```
## Context
Tabelele high-traffic (article, page_views) nu au autovacuum optimizat.
Default settings pot cauza bloat si performance degradation.

Database: deschide_news

## Sarcini

### Pas 1: Identifica tabelele high-traffic
```bash
sudo -u postgres psql -d deschide_news -c "
SELECT
    relname as table_name,
    n_live_tup as live_rows,
    n_dead_tup as dead_rows,
    last_vacuum,
    last_autovacuum,
    last_analyze,
    last_autoanalyze
FROM pg_stat_user_tables
ORDER BY n_live_tup DESC
LIMIT 10;
"
```

### Pas 2: Verifica bloat
```bash
sudo -u postgres psql -d deschide_news -c "
SELECT
    schemaname || '.' || relname as table_name,
    pg_size_pretty(pg_total_relation_size(relid)) as total_size,
    pg_size_pretty(pg_relation_size(relid)) as table_size,
    n_live_tup,
    n_dead_tup,
    ROUND(100.0 * n_dead_tup / NULLIF(n_live_tup + n_dead_tup, 0), 2) as dead_pct
FROM pg_stat_user_tables
WHERE n_live_tup > 1000
ORDER BY n_dead_tup DESC
LIMIT 10;
"
```

### Pas 3: Configureaza autovacuum per-table
```bash
sudo -u postgres psql -d deschide_news -c "
-- page_views - very high traffic
ALTER TABLE page_views SET (
    autovacuum_vacuum_threshold = 100,
    autovacuum_vacuum_scale_factor = 0.01,
    autovacuum_analyze_threshold = 50,
    autovacuum_analyze_scale_factor = 0.005,
    autovacuum_vacuum_cost_delay = 10
);

-- article - medium-high traffic
ALTER TABLE article SET (
    autovacuum_vacuum_threshold = 50,
    autovacuum_vacuum_scale_factor = 0.05,
    autovacuum_analyze_threshold = 50,
    autovacuum_analyze_scale_factor = 0.02
);

-- article_translations - medium traffic
ALTER TABLE ext_translations SET (
    autovacuum_vacuum_threshold = 100,
    autovacuum_vacuum_scale_factor = 0.05,
    autovacuum_analyze_threshold = 100,
    autovacuum_analyze_scale_factor = 0.02
);
"
```

### Pas 4: Verifica setarile aplicate
```bash
sudo -u postgres psql -d deschide_news -c "
SELECT
    relname,
    reloptions
FROM pg_class
WHERE relname IN ('page_views', 'article', 'ext_translations');
"
```

### Pas 5: Forteaza VACUUM ANALYZE pe tabelele mari
```bash
sudo -u postgres psql -d deschide_news -c "VACUUM ANALYZE page_views;"
sudo -u postgres psql -d deschide_news -c "VACUUM ANALYZE article;"
```

### Pas 6: Monitoreaza autovacuum
```bash
# Verifica autovacuum workers
sudo -u postgres psql -d deschide_news -c "
SELECT
    pid,
    datname,
    relid::regclass as table_name,
    phase,
    heap_blks_total,
    heap_blks_scanned,
    index_vacuum_count
FROM pg_stat_progress_vacuum;
"
```

## Criterii de Acceptare
- [ ] page_views are autovacuum optimizat
- [ ] article are autovacuum optimizat
- [ ] Dead tuples < 10% din live tuples
- [ ] VACUUM ANALYZE rulat recent
```

**Verificare:**
- [ ] `\d+ page_views` arata autovacuum settings
- [ ] dead_pct < 10% pentru tabelele principale

---

### Sarcina 4.3: Configurare Connection Pooling (PgBouncer)

**Agent:** @devops-engineer
**Prioritate:** P3 (LOW)
**Estimare:** 3 ore
**Dependente:** Niciuna

**Prompt pentru Agent:**

```
## Context
Aplicatia nu foloseste connection pooling, fiecare request creeaza conexiune noua.
PgBouncer reduce overhead-ul de conexiuni si imbunatateste scalabilitatea.

## Sarcini

### Pas 1: Instaleaza PgBouncer
```bash
sudo apt-get install pgbouncer
```

### Pas 2: Configureaza PgBouncer

Editeaza `/etc/pgbouncer/pgbouncer.ini`:

```ini
[databases]
deschide_news = host=127.0.0.1 port=5432 dbname=deschide_news

[pgbouncer]
listen_addr = 127.0.0.1
listen_port = 6432
auth_type = md5
auth_file = /etc/pgbouncer/userlist.txt

; Pool mode: transaction este recomandat pentru aplicatii web
pool_mode = transaction

; Pool size
default_pool_size = 20
min_pool_size = 5
reserve_pool_size = 5

; Timeouts
server_connect_timeout = 10
server_idle_timeout = 600
server_lifetime = 3600
client_idle_timeout = 0

; Logging
log_connections = 0
log_disconnections = 0
log_pooler_errors = 1

; Admin console
admin_users = postgres
stats_users = postgres
```

### Pas 3: Configureaza userlist

Creeaza `/etc/pgbouncer/userlist.txt`:
```bash
# Format: "username" "password"
# Obtine hash-ul:
sudo -u postgres psql -c "SELECT concat('\"', usename, '\" \"', passwd, '\"') FROM pg_shadow WHERE usename = 'deschide_app';"

# Adauga in userlist.txt outputul (ex:)
"deschide_app" "md5..."
```

### Pas 4: Start PgBouncer
```bash
sudo systemctl enable pgbouncer
sudo systemctl start pgbouncer
```

### Pas 5: Actualizeaza DATABASE_URL in Symfony
```bash
# Editeaza .env.local
# Schimba portul de la 5432 la 6432
DATABASE_URL="postgresql://deschide_app:PAROLA@127.0.0.1:6432/deschide_news?serverVersion=18&charset=utf8"
```

### Pas 6: Testeaza conexiunea prin PgBouncer
```bash
cd /var/www/deschide_news_app/apps/backend
symfony console cache:clear
symfony console doctrine:query:sql "SELECT 1"
```

### Pas 7: Verifica pooling functioneaza
```bash
# Conecteaza la admin console PgBouncer
psql -p 6432 -U postgres pgbouncer -c "SHOW POOLS;"
psql -p 6432 -U postgres pgbouncer -c "SHOW STATS;"
```

## Criterii de Acceptare
- [ ] PgBouncer instalat si configurat
- [ ] Aplicatia conecteaza prin PgBouncer (port 6432)
- [ ] Connection pooling activ
- [ ] API functioneaza normal
```

**Verificare:**
- [ ] `SHOW POOLS;` arata conexiuni active
- [ ] API response time stabil sub load

---

### Sarcina 4.4: Documentare Finala si Checklist de Securitate

**Agent:** @devops-engineer
**Prioritate:** P3 (LOW)
**Estimare:** 2 ore
**Dependente:** Toate sarcinile anterioare

**Prompt pentru Agent:**

```
## Context
Dupa completarea remedierii, trebuie documentate toate schimbarile
si creat un checklist de securitate pentru deployment-uri viitoare.

## Sarcini

### Pas 1: Creeaza SECURITY_CHECKLIST.md

```bash
cat > /var/www/deschide_news_app/docs/SECURITY_CHECKLIST.md << 'EOF'
# Security Checklist - Deschide News

## Pre-Deployment Checklist

### Credentials & Secrets
- [ ] APP_SECRET regenerat si unic pentru fiecare environment
- [ ] DATABASE_URL nu contine parole in git
- [ ] JWT_PASSPHRASE regenerat
- [ ] MERCURE_JWT_SECRET regenerat
- [ ] .env.local nu este in git
- [ ] .env contine doar placeholders

### File Permissions
- [ ] config/jwt/private.pem: 600
- [ ] config/jwt/public.pem: 640
- [ ] .env.local: 600
- [ ] var/: writable doar de web server

### PostgreSQL
- [ ] Doar 1 superuser (postgres)
- [ ] Aplicatia foloseste rol fara superuser
- [ ] archive_mode = on
- [ ] Backup-uri automate configurate
- [ ] pg_stat_statements activ

### Application
- [ ] APP_ENV=prod
- [ ] APP_DEBUG=0
- [ ] Rate limiting activ
- [ ] CSP headers configurate
- [ ] CORS restrictionat (nu *)
- [ ] XSS sanitization activa

### Frontend
- [ ] React strict mode: true
- [ ] DOMPurify pentru HTML nesigur
- [ ] Security headers in next.config.mjs

## Post-Deployment Verification

```bash
# Verifica environment
curl -I https://api.deschide.md/api | grep -E "(X-Frame|Content-Security)"

# Verifica rate limiting
for i in {1..110}; do curl -s -o /dev/null -w "%{http_code}\n" https://api.deschide.md/api; done | grep 429

# Verifica backup
ls -la /var/backups/postgresql/deschide_news/daily/

# Verifica PostgreSQL settings
sudo -u postgres psql -c "SHOW archive_mode; SHOW shared_buffers;"
```

## Monthly Security Tasks

- [ ] Rotate APP_SECRET
- [ ] Review slow_queries view
- [ ] Check backup integrity (test restore)
- [ ] Update dependencies (composer update, pnpm update)
- [ ] Review pg_stat_statements pentru suspicious queries
- [ ] Check autovacuum effectiveness

## Incident Response

1. **Credentials Exposed:**
   - Regenerate ALL exposed secrets immediately
   - Check git history for exposure duration
   - Review access logs for unauthorized access

2. **Data Breach:**
   - Notify DPO within 72 hours
   - Document affected data and users
   - Implement additional monitoring

3. **DDoS Attack:**
   - Enable strict rate limiting
   - Contact hosting provider
   - Enable Cloudflare protection if available
EOF
```

### Pas 2: Actualizeaza CLAUDE.md cu modificarile

Adauga in `/var/www/deschide_news_app/CLAUDE.md` sectiunea de securitate cu noile configuratii.

### Pas 3: Creeaza raport de remediere

```bash
cat > /var/www/deschide_news_app/docs/planning/REMEDIATION_REPORT.md << 'EOF'
# Raport de Remediere - Deschide News

**Data Finalizare:** [DATA]
**Responsabil:** [NUME]

## Sumar Executiv

Toate problemele identificate in auditurile de securitate si baza de date
au fost remediate conform planului din REMEDIATION_PLAN.md.

## Probleme Remediate

### Securitate
| Problema | Severitate | Status | Data |
|----------|------------|--------|------|
| .env cu credentials in git | CRITIC | REMEDIAT | |
| JWT keys 666 permissions | HIGH | REMEDIAT | |
| CVE-2025-64500 | HIGH | REMEDIAT | |
| Lipsa CSP | HIGH | REMEDIAT | |
| XSS risk | HIGH | REMEDIAT | |
| Lipsa rate limiting | MEDIUM | REMEDIAT | |
| APP_ENV=dev | MEDIUM | REMEDIAT | |

### Baza de Date
| Problema | Severitate | Status | Data |
|----------|------------|--------|------|
| Lipsa backup strategy | CRITIC | REMEDIAT | |
| Index page_views.category_id | CRITIC | REMEDIAT | |
| PostgreSQL config | HIGH | REMEDIAT | |
| 6+ superusers | HIGH | REMEDIAT | |
| Doctrine serverVersion | HIGH | REMEDIAT | |

### Performanta
| Problema | Severitate | Status | Data |
|----------|------------|--------|------|
| refresh() excesive | CRITIC | REMEDIAT | |

## Metrici Pre/Post Remediere

| Metrica | Inainte | Dupa | Imbunatatire |
|---------|---------|------|--------------|
| Queries per request | 100+ | <10 | 90%+ |
| Response time | Xs | Ys | Z% |
| Security score | X/100 | Y/100 | +Z |

## Recomandari

1. Implementare CI/CD cu security scanning
2. Audit trimestrial de securitate
3. Backup test lunar
4. Dependency updates saptamanale
EOF
```

## Criterii de Acceptare
- [ ] SECURITY_CHECKLIST.md creat
- [ ] REMEDIATION_REPORT.md creat
- [ ] CLAUDE.md actualizat
- [ ] Toate documentele in git
```

**Verificare:**
- [ ] Documentatia exista si este completa
- [ ] Checklist-ul acopera toate aspectele

---

## Diagrama Dependente

```
Sprint 1 (Ziua 1-5):
1.1 Rotire Secrete (P0) ─┬─> 1.2 JWT Permissions (P1)
                        └─> 2.2 APP_ENV=prod (P2)

1.3 CVE Fix (P1) ──────────> 2.3 Update Backend (P2)

1.4 CSP (P1) ──────────────> [Parallel]
1.5 XSS Fix (P1) ──────────> 3.3 Strict Mode (P2)
1.6 Backup (P0) ───────────> [Parallel]
1.7 Index (P0) ────────────> [Parallel]

Sprint 2 (Ziua 6-10):
2.1 Rate Limiting (P2) ────> [Parallel]
2.2 APP_ENV (P2) ──────────> [Dependent: 1.1]
2.3 Backend Update (P2) ───> [Dependent: 1.3]
2.4 Frontend Update (P2) ──> [Parallel]
2.5 PostgreSQL Config (P1) > [Parallel]
2.6 Superusers (P1) ───────> [Parallel]
2.7 serverVersion (P1) ────> [Parallel]

Sprint 3 (Ziua 11-15):
3.1 refresh() Fix (P0) ────> [Parallel]
3.2 CORS /media (P2) ──────> [Parallel]
3.3 Strict Mode (P2) ──────> [Dependent: 1.5]
3.4 pg_stat_statements (P2) > [Parallel]

Sprint 4 (Ziua 16-18):
4.1 GDPR Retention (P2) ───> [Parallel]
4.2 Autovacuum (P3) ───────> [Parallel]
4.3 PgBouncer (P3) ────────> [Parallel]
4.4 Documentare (P3) ──────> [Dependent: ALL]
```

---

## Sumar Executie

| Sprint | Zile | Sarcini | Critice | High | Medium | Low |
|--------|------|---------|---------|------|--------|-----|
| 1 | 1-5 | 7 | 3 | 4 | 0 | 0 |
| 2 | 6-10 | 7 | 0 | 3 | 4 | 0 |
| 3 | 11-15 | 4 | 1 | 0 | 3 | 0 |
| 4 | 16-18 | 4 | 0 | 0 | 2 | 2 |
| **Total** | **18** | **22** | **4** | **7** | **9** | **2** |

---

## Anexe

### A. Comenzi de Verificare Rapida

```bash
# Securitate
curl -I http://127.0.0.1:8081/api | grep -E "(Content-Security|X-Frame|X-Content-Type)"
ls -la /var/www/deschide_news_app/apps/backend/config/jwt/

# PostgreSQL
sudo -u postgres psql -c "SHOW archive_mode; SHOW shared_buffers;"
sudo -u postgres psql -c "SELECT usename, usesuper FROM pg_user WHERE usesuper;"

# Performance
curl -w "@curl-format.txt" http://127.0.0.1:8081/api/articles

# Vulnerabilitati
cd /var/www/deschide_news_app/apps/backend && composer audit
cd /var/www/deschide_news_app/apps/frontend && pnpm audit
```

### B. Rollback Procedures

In caz de probleme dupa remediere:

```bash
# Backend
cd /var/www/deschide_news_app/apps/backend
cp composer.lock.backup.YYYYMMDD composer.lock
composer install

# Frontend
cd /var/www/deschide_news_app/apps/frontend
cp pnpm-lock.yaml.backup.YYYYMMDD pnpm-lock.yaml
pnpm install

# PostgreSQL
# Restore din backup
```

---

**Document creat:** 29 Noiembrie 2025
**Ultima actualizare:** 29 Noiembrie 2025
**Autor:** AI Security Audit Team
**Review:** Pending
