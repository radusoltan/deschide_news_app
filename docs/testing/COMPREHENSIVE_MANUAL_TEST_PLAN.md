# Deschide News - Plan Comprehensiv de Testare Manuală

**Versiune**: 2.1
**Data Creării**: 2025-12-11
**Ultima Actualizare**: 2025-12-11
**Scripturi Diagnostice**: ✅ Implementate (4 scripturi în `__tests__/diagnostics/`)
**Aplicație**: Deschide News - Platformă Multilingvă de Știri

---

## Cuprins

1. [Prezentare Generală](#1-prezentare-generală)
2. [Configurare Mediu de Test](#2-configurare-mediu-de-test)
   - 🚀 Quick Start - Comenzi Diagnostice
   - 🔧 Scripturi Diagnoză Disponibile (IMPLEMENTATE)
   - 🤖 Metodologie pentru Agent Automatizat
3. [SECȚIUNEA A: Teste Frontend Public](#3-secțiunea-a-teste-frontend-public)
4. [SECȚIUNEA B: Teste Panou Administrare](#4-secțiunea-b-teste-panou-administrare)
   - B6a. Crop Imagini în Editor Articol (17 teste)
   - B11a. Crop Imagini în Biblioteca de Imagini (16 teste)
   - B12. LiveText Management Complet (104 teste)
     - B12a. Creare și Configurare
     - B12b. Status Management
     - B12c. Management Posturi
     - B12d. Colaboratori
     - B12e. Sport Match Management
     - B12f. Scor și Statistici Meci
     - B12g. Evenimente Meci (Match Events)
     - B12h. Analytics & Real-time
     - B12i. Reacții și Interacțiuni
     - B12j. Templates și Configurare Avansată
5. [SECȚIUNEA C: Teste API Backend](#5-secțiunea-c-teste-api-backend)
6. [SECȚIUNEA D: Teste Integrare End-to-End](#6-secțiunea-d-teste-integrare-end-to-end)
7. [SECȚIUNEA E: Teste Multilingv](#7-secțiunea-e-teste-multilingv)
8. [SECȚIUNEA F: Teste Securitate](#8-secțiunea-f-teste-securitate)
9. [SECȚIUNEA G: Teste Performance & UX](#9-secțiunea-g-teste-performance--ux)
10. [Matrice de Prioritizare](#10-matrice-de-prioritizare)
11. [Raport de Execuție](#11-raport-de-execuție)

---

## 1. Prezentare Generală

### Arhitectură Aplicație

| Component | Tehnologie | URL |
|-----------|------------|-----|
| Frontend | Next.js 16 (React 19.2 / TypeScript) | http://localhost:3005 |
| Backend API | Symfony 7.3 (PHP 8.4) cu API Platform | http://127.0.0.1:8081 |
| CDN Imagini | Server static | http://127.0.0.1:8082 |

### Localizări Suportate
- **Română (ro)** - Localizare implicită
- **Engleză (en)**
- **Rusă (ru)**

### Total Teste: 500+
- Frontend Public: 85 teste
- Admin Panel: 250+ teste (incluzând Crop Imagini și LiveText Management)
  - Articole: 45 teste
  - Crop Imagini Editor: 17 teste
  - Crop Imagini Bibliotecă: 16 teste
  - LiveText Management: 104 teste (10 sub-secțiuni)
  - Categorii, Autori, Short Links, etc.: 68+ teste
- API Backend: 75 teste
- Integrare E2E: 35 teste
- Multilingv: 20 teste
- Securitate: 15 teste
- Performance: 15 teste

---

## 2. Configurare Mediu de Test

### Prerequisite

```bash
# 1. Backend API Running
cd /var/www/deschide_news_app/apps/backend
symfony serve -d --port=8081

# 2. Frontend Running
cd /var/www/deschide_news_app/apps/frontend
pnpm dev

# 3. Servicii auxiliare
- PostgreSQL: port 5432
- Redis: port 6379
- Elasticsearch: port 9200

# 4. (RECOMANDAT) Verifică sănătatea API înainte de testare
cd /var/www/deschide_news_app/apps/frontend
pnpm diag:api
```

### 🚀 Quick Start - Comenzi Diagnostice

```bash
cd /var/www/deschide_news_app/apps/frontend

# Verifică sănătatea API (rulează ÎNAINTE de teste)
pnpm diag:api

# Diagnostic complet (rulează CÂND un test eșuează)
pnpm diag:full

# Diagnostic console doar
pnpm diag:console

# Diagnostic network/API doar
pnpm diag:network

# Rulează toate diagnosticele
pnpm diag:all
```

**Rapoartele se salvează în:** `apps/frontend/test-results/diagnostics/`

### Credențiale Test

| Rol | Email | Parolă |
|-----|-------|--------|
| Admin | admin | password |
| Editor | editor@deschide.md | TestPassword123! |

### ⚠️ IMPORTANT: Verificare Loguri și Consolă în Timpul Testelor

**La fiecare test sau când un test eșuează, OBLIGATORIU verificați logurile pentru a identifica rapid problemele.**

---

### 🤖 METODOLOGIE PENTRU AGENT AUTOMATIZAT DE TESTARE

**Capabilitățile agentului:**
- ✅ Interacțiune vizuală (click, input, scroll, navigare)
- ✅ Înregistrare video a sesiunii
- ✅ Citire structură DOM și text de pe pagină
- ✅ Capturi de ecran
- ❌ Acces direct la Console/Network în timp real

**Când un test eșuează sau comportamentul e suspect, folosește una din metodele de mai jos:**

#### Metoda 1: Script de Diagnoză Playwright (RECOMANDAT)

Solicită crearea unui script care să captureze Console + Network:

```typescript
// diagnostic-[test-name].ts
import { test } from '@playwright/test';

test('Diagnostic: [NUME_TEST]', async ({ page }) => {
  const logs: string[] = [];
  const networkErrors: string[] = [];

  // Capturează console
  page.on('console', msg => {
    if (msg.type() === 'error' || msg.type() === 'warning') {
      logs.push(`[${msg.type().toUpperCase()}] ${msg.text()}`);
    }
  });
  page.on('pageerror', err => logs.push(`[PAGE_ERROR] ${err.message}`));

  // Capturează network errors
  page.on('response', res => {
    if (res.status() >= 400) {
      networkErrors.push(`[${res.status()}] ${res.url()}`);
    }
  });

  // ═══ PAȘI TEST ═══
  await page.goto('http://localhost:3005/ro');
  // ... pași specifici testului ...
  await page.waitForTimeout(3000);

  // ═══ OUTPUT DIAGNOSTIC ═══
  console.log('\\n═══ CONSOLE LOGS ═══');
  logs.forEach(l => console.log(l));
  console.log('\\n═══ NETWORK ERRORS ═══');
  networkErrors.forEach(e => console.log(e));

  await page.screenshot({ path: 'diagnostic.png', fullPage: true });
});
```

**Comandă rulare:**
```bash
cd /var/www/deschide_news_app/apps/frontend
pnpm exec playwright test [script].ts --reporter=line
```

#### Metoda 2: Indicatori Vizuali pe Pagină

Multe erori au manifestări vizuale pe care agentul le poate observa:

| Ce vede agentul | Cauza probabilă | Acțiune |
|-----------------|-----------------|---------|
| Pagină complet albă | JS crash / Hydration error | Rulează script diagnoză |
| Spinner infinit | API blocat sau foarte lent | Script diagnoză network |
| Text "Error" sau "Something went wrong" | Exception prinse de app | Citește mesajul afișat |
| Iconiță imagine broken (⊠) | 404 pe CDN | Verifică URL în src |
| Liste/grid-uri goale | API returnează array gol sau eroare | Script diagnoză |
| Formular nu răspunde la submit | Validare JS sau API error | Script diagnoză |
| Toast/snackbar roșu | Mesaj eroare pentru user | Citește și documentează |
| Buton disabled când nu ar trebui | State management issue | Script diagnoză console |

#### Metoda 3: Backend Logs din Terminal Separat

**ÎNAINTE de sesiunea de testare**, pornește logging:

```bash
cd /var/www/deschide_news_app/apps/backend
symfony server:log 2>&1 | tee ~/test-logs/session-$(date +%Y%m%d_%H%M%S).log
```

După test eșuat, caută în fișierul log erori la timestamp-ul aproximativ.

#### Metoda 4: DevTools Screenshot (fallback)

Agentul poate:
1. Apasă F12 pentru DevTools
2. Navighează la tab Console sau Network
3. Face screenshot
4. ⚠️ Metodă greoaie, folosește doar dacă scriptul nu e fezabil

---

### 📋 PROTOCOL TESTARE CU AGENT

**SETUP (o singură dată la început):**
```bash
# Terminal 1: Backend cu logging
cd /var/www/deschide_news_app/apps/backend
symfony server:log 2>&1 | tee ~/backend-test.log

# Terminal 2: Frontend
cd /var/www/deschide_news_app/apps/frontend
pnpm dev
```

**PENTRU FIECARE TEST:**
1. Execută pașii din tabel vizual
2. Observă indicatorii vizuali
3. La succes: marchează PASS, continuă
4. La eșec: continuă cu protocolul de mai jos

**CÂND TEST EȘUEAZĂ:**
1. 📸 Screenshot imediat
2. 📝 Notează comportamentul observat
3. 🔧 Solicită/rulează script diagnoză pentru scenariul specific
4. 📊 Colectează output (console + network)
5. 📋 Completează raportul

**TEMPLATE RAPORT EROARE AGENT:**

```markdown
## ❌ [ID_TEST] - [Nume Test] - FAILED

**Pas eșuat:** #[N]
**Video timestamp:** [MM:SS]

### Ce s-a observat vizual
[descriere: pagină albă / spinner infinit / mesaj eroare / etc.]

### Screenshot
[atașat sau path]

### Diagnostic Output
Console Logs:
[ERROR] ...
[WARNING] ...

Network Errors:
[404] http://127.0.0.1:8081/api/...
[500] http://127.0.0.1:8081/api/...

### Backend Log (dacă relevant)
[2025-12-11 10:30:45] request.ERROR: ...

### Severitate estimată
[ ] Blocker [ ] Critical [ ] Major [ ] Minor
```

---

### 🔧 SCRIPTURI DIAGNOZĂ DISPONIBILE (IMPLEMENTATE)

**Directorul:** `apps/frontend/__tests__/diagnostics/`

#### Scripturi Principale

| Script | Comandă | Descriere |
|--------|---------|-----------|
| `console-capture.diag.ts` | `pnpm diag:console` | Capturează toate mesajele din Console (erori, warnings, logs) |
| `network-capture.diag.ts` | `pnpm diag:network` | Capturează traficul network și erorile API |
| `full-diagnostic.diag.ts` | `pnpm diag:full` | Diagnostic complet: console + network + performance + indicatori vizuali |
| `api-health-check.diag.ts` | `pnpm diag:api` | Testează direct endpoint-urile API (fără browser) |

#### Comenzi Rapide npm

```bash
# Rulează toate diagnosticele
pnpm diag:all

# Diagnostice individuale
pnpm diag:console   # Capturează console messages
pnpm diag:network   # Capturează network/API traffic
pnpm diag:full      # Diagnostic complet cu screenshots
pnpm diag:api       # API health check (headless)
```

#### Rapoarte Generate

Toate rapoartele sunt salvate în: `test-results/diagnostics/`

| Tip Raport | Format | Conținut |
|------------|--------|----------|
| `console-capture-*.json` | JSON | Date complete console |
| `console-summary-*.txt` | Text | Sumar lizibil |
| `network-capture-*.json` | JSON | Toate request-urile și response-urile |
| `network-summary-*.txt` | Text | Sumar cu erori API |
| `full-diagnostic-*.json` | JSON | Date complete diagnostic |
| `full-diagnostic-*.md` | Markdown | Raport formatat cu tabele |
| `api-health-*.json` | JSON | Status toate endpoint-urile |
| `api-health-*.md` | Markdown | Raport health check |
| `diag-*.png` | PNG | Screenshots pagini |

#### Workflow Recomandat pentru Agent

```
1. ÎNAINTE de sesiunea de testare:
   $ pnpm diag:api
   → Verifică că backend-ul e sănătos

2. CÂND un test eșuează:
   $ pnpm diag:full
   → Obține diagnostic complet

3. PENTRU probleme specifice Console:
   $ pnpm diag:console

4. PENTRU probleme specifice Network/API:
   $ pnpm diag:network
```

#### Ce Capturează Fiecare Script

**console-capture.diag.ts:**
- Console errors, warnings, logs
- Page errors (JS crashes)
- Screenshots când sunt erori
- Pagini testate: Homepage (ro/en/ru), Admin Dashboard, Articles, Images, LiveText, Archive

**network-capture.diag.ts:**
- Toate request-urile HTTP
- Status codes și timing
- API calls (filtrate după `/api/` patterns)
- Body-uri pentru responses cu erori
- Headers pentru debug CORS

**full-diagnostic.diag.ts:**
- Tot din console-capture + network-capture
- Metrici performance (load time, DOM content loaded, FCP)
- Detecție indicatori vizuali:
  - Elemente cu class `*error*`, `*Error*`, `text-red-*`
  - Loading spinners (`.animate-spin`, `aria-busy`)
  - Empty states
- Verdict per pagină: PASS / WARNING / FAIL

**api-health-check.diag.ts:**
- Test direct fetch (fără browser)
- Toate endpoint-urile publice
- Endpoint-uri cu autentificare
- Test locale headers (ro/en/ru)
- Paginare și filtrare
- Performance thresholds

---

### Pentru Testare Manuală Umană (referință DevTools):

#### 1. Consola Browser (F12 → Console)

Deschide DevTools (F12 sau Ctrl+Shift+I) și verifică:

| Tab | Ce să cauți |
|-----|-------------|
| **Console** | Erori JavaScript (roșu), warnings (galben), hydration errors |
| **Network** | Requests failed (roșu), status 4xx/5xx, CORS errors |

**Erori comune în Console:**
- `Hydration failed` - mismatch între server și client rendering
- `Failed to fetch` - API indisponibil sau CORS
- `401 Unauthorized` - token JWT expirat/invalid
- `500 Internal Server Error` - eroare backend

#### 1a. Tab Network - Observabilitate API (FOARTE IMPORTANT)

**Cum să folosești Network tab:**
1. Deschide DevTools (F12) → Tab **Network**
2. Bifează **☑ Preserve log** (păstrează istoricul între navigări)
3. Filtrează după **Fetch/XHR** pentru a vedea doar requests API
4. Click pe orice request pentru detalii complete

**Coloane importante în Network:**

| Coloană | Ce arată | Ce să cauți |
|---------|----------|-------------|
| **Name** | URL endpoint | `/api/articles`, `/api/images`, etc. |
| **Status** | Cod HTTP | 🟢 2xx OK, 🟡 3xx Redirect, 🔴 4xx/5xx Erori |
| **Type** | Tip resursă | fetch, xhr = API calls |
| **Time** | Durata | > 3s = problemă performanță |
| **Size** | Dimensiune | Responses mari pot încetini |

**Coduri de Status API și Semnificație:**

| Status | Nume | Cauza Probabilă | Acțiune |
|--------|------|-----------------|---------|
| 🔴 **400** | Bad Request | Payload invalid | Verifică body request |
| 🔴 **401** | Unauthorized | Token JWT lipsă/expirat | Re-login |
| 🔴 **403** | Forbidden | Rol insuficient | Verifică permisiuni |
| 🔴 **404** | Not Found | Resursă inexistentă | Verifică URL/ID |
| 🔴 **409** | Conflict | Article lock activ | Eliberează lock-ul |
| 🔴 **422** | Unprocessable | Validare eșuată | Citește `violations` din response |
| 🔴 **429** | Too Many Requests | Rate limit | Așteaptă și reîncearcă |
| 🔴 **500** | Server Error | Bug backend | Verifică `symfony server:log` |
| 🔴 **502/503** | Bad Gateway | Server down | Restart backend |

**Cum să inspectezi un Request eșuat (click pe el):**

| Tab | Ce verifici |
|-----|-------------|
| **Headers** | `Request URL`, `Method`, `Status Code`, `Authorization` header |
| **Payload** | Datele trimise (POST/PUT) - caută câmpuri invalide |
| **Response** | Mesajul de eroare JSON cu detalii |
| **Timing** | Breakdown pe faze (DNS, Connect, Wait, Download) |

**Exemple Response Erori:**

```json
// 422 Validation Error - citește violations!
{
  "@type": "ConstraintViolationList",
  "violations": [
    {"propertyPath": "title", "message": "This value should not be blank."},
    {"propertyPath": "slug", "message": "This slug is already in use."}
  ]
}

// 401 Unauthorized
{"code": 401, "message": "JWT Token not found"}

// 409 Conflict (Article Lock)
{"error": "Article is locked by user admin"}
```

**Filtre utile Network tab:**
```
status-code:500         → doar erori server
status-code:4           → toate erorile client (4xx)
domain:127.0.0.1:8081   → doar requests la backend API
-status-code:200        → exclude requests reușite
larger-than:1M          → requests mari (performanță)
method:POST             → doar POST requests
```

**CORS Errors (foarte comune):**

Dacă în Console vezi:
```
Access to fetch at 'http://127.0.0.1:8081/api/...' blocked by CORS policy
```

Verifică:
- ✅ Backend rulează pe port 8081
- ✅ Frontend rulează pe port 3005
- ✅ CORS configurat în `config/packages/nelmio_cors.yaml`

**Export pentru Raport:**
- Click dreapta pe request → **Copy as cURL** (pentru reproducere exactă)
- Click dreapta → **Copy as fetch** (pentru test în Console)
- **Save all as HAR** (export complet pentru analiză)

#### 2. Loguri Backend Symfony

```bash
# Urmărește logurile în timp real (RECOMANDAT în timpul testării)
cd /var/www/deschide_news_app/apps/backend
symfony server:log

# Sau direct fișierul
tail -f var/log/dev.log

# Caută doar erori
grep -i "error\|exception\|critical" var/log/dev.log | tail -50
```

#### 3. Loguri Frontend Next.js

```bash
# Erorile apar în terminalul unde rulează pnpm dev

# Cu PM2:
pm2 logs deschide_frontend --lines 100
```

#### 4. Matrice Simptom → Unde să Cauți

| Simptom | Console Browser | Backend Log | Soluție Rapidă |
|---------|-----------------|-------------|----------------|
| Pagină albă/goală | ✅ Hydration error | - | Clear .next, rebuild |
| Date nu se încarcă | ✅ Network 4xx/5xx | ✅ Exception | Verifică API endpoint |
| 500 Internal Error | ✅ | ✅ Stack trace | Fix backend error |
| Imagini lipsă | ✅ 404 pe CDN | - | Verifică path CDN |
| Login eșuează | ✅ 401 details | ✅ JWT errors | Regenerează token |
| Salvare eșuează | ✅ Validation | ✅ DB errors | Verifică payload |
| Real-time nu merge | ✅ SSE/WS errors | ✅ Mercure logs | Restart Mercure |

#### 5. Template Documentare Eroare la Test Eșuat

```markdown
## ❌ Test Eșuat: [ID_TEST] - [Nume Test]

**Data:** YYYY-MM-DD HH:MM
**Browser:** Chrome 120 / Firefox / Safari
**Tester:** [Nume]

### Pași Reproduși
1. ...
2. ...

### Rezultat Actual vs Așteptat
- **Așteptat:** [ce trebuia să se întâmple]
- **Actual:** [ce s-a întâmplat]

### Erori Console Browser
```
[PASTE erori din F12 → Console]
```

### Erori Backend (symfony server:log)
```
[PASTE linii relevante din log]
```

### Screenshot/Video
[Atașează dacă relevant]

### Severitate
[ ] Blocker [ ] Critical [ ] Major [ ] Minor
```

#### 6. Comenzi Debugging Rapide

```bash
# ═══ CLEAR CACHE (când ceva nu funcționează) ═══
cd /var/www/deschide_news_app/apps/backend && symfony console cache:clear
cd /var/www/deschide_news_app/apps/frontend && rm -rf .next

# ═══ VERIFICARE SERVICII ═══
curl http://127.0.0.1:8081/api/health    # Backend
curl http://localhost:3005               # Frontend
redis-cli -n 1 PING                      # Redis

# ═══ RESTART SERVICII ═══
symfony server:stop && symfony serve -d --port=8081
pm2 restart deschide_frontend

# ═══ VERIFICARE DB ═══
PGPASSWORD='iIzmHACi7+W+yq9NFRT2FeadUPAmgEna' psql -h 127.0.0.1 -p 6432 -U deschide_admin -d deschide -c "SELECT 1"
```

> **💡 TIP:** Ține deschise 2 terminale în timpul testării:
> 1. `symfony server:log` - pentru loguri backend în timp real
> 2. Terminalul cu `pnpm dev` - pentru erori frontend

---

## 3. SECȚIUNEA A: Teste Frontend Public

### A1. Homepage și Navigare

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| PUB-001 | **Încărcare Homepage** | 1. Accesează `http://localhost:3005/ro` | Pagina se încarcă fără erori. Secțiunea "Articole Importante" vizibilă. Lista articole populată. | Critical |
| PUB-002 | **Secțiune Breaking News** | 1. Verifică existența banner-ului BREAKING pe homepage | Banner roșu vizibil când există știri de ultimă oră. | High |
| PUB-003 | **Secțiune Alert** | 1. Verifică existența banner-ului ALERT | Banner galben vizibil pentru alerte. | Medium |
| PUB-004 | **Articole Importante (Hero)** | 1. Verifică secțiunea Hero cu grid Bento | Afișează max 8-12 articole importante cu imagini. Grid responsive. | Critical |
| PUB-005 | **Știri Recente** | 1. Verifică secțiunea LatestNews | Lista articole recente, sortate DESC după publishedAt. | High |
| PUB-006 | **Articole Trending** | 1. Verifică secțiunea TrendingArticles | Max 5 articole populare afișate. | High |
| PUB-007 | **Secțiuni Categorii Homepage** | 1. Verifică secțiunile de categorii cu `onFrontPage=true` | Doar categoriile marcate pentru homepage apar. Fiecare secțiune are articole. | High |
| PUB-008 | **Header Sticky** | 1. Scroll în jos pe homepage | Header rămâne vizibil sau se ascunde/afișează la scroll. | Medium |
| PUB-009 | **Footer Links** | 1. Scroll la footer. 2. Click pe "About", "Contact" | Navigare către paginile statice corespunzătoare. | Low |
| PUB-010 | **Logo Click** | 1. Click pe logo din header | Redirect la homepage. | Medium |

### A2. Navigare Meniu

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| PUB-011 | **Meniu Principal Desktop** | 1. Pe viewport >1024px, observă meniul principal | Categoriile apar ca linkuri în header. Dropdown pentru categorii secundare. | High |
| PUB-012 | **Meniu Hamburger Mobile** | 1. Redimensionează browser <768px. 2. Click pe buton hamburger | Meniul mobil se deschide. Toate categoriile vizibile. | High |
| PUB-013 | **Click Categorie din Meniu** | 1. Click pe orice categorie din meniu | Navigare la pagina categoriei. URL corect: `/ro/{categorySlug}`. | Critical |
| PUB-014 | **Dropdown Știri** | 1. Hover/Click pe "Știri" | Dropdown afișează categorii secundare (cu inMenu=false). | Medium |
| PUB-015 | **Închidere Meniu Mobile** | 1. Deschide meniul mobil. 2. Click în afara meniului | Meniul se închide. | Medium |

### A3. Schimbare Limbă

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| PUB-016 | **Schimbare RO → EN** | 1. Pe orice pagină, click pe language switcher. 2. Selectează EN | UI și conținut se schimbă în engleză. URL devine `/en/...`. | Critical |
| PUB-017 | **Schimbare RO → RU** | 1. Click pe language switcher. 2. Selectează RU | UI și conținut în rusă. URL devine `/ru/...`. Caractere chirilice afișate corect. | Critical |
| PUB-018 | **Persistența Limbii** | 1. Schimbă limba în EN. 2. Navighează pe altă pagină | Limba rămâne EN pe navigare. | High |
| PUB-019 | **Limba pe Refresh** | 1. Setează limba EN. 2. Refresh pagină | Limba se menține EN. | High |
| PUB-020 | **Fallback Traducere Lipsă** | 1. Accesează articol doar în RO în limba EN | Conținut fallback la română. Nu eroare sau pagină goală. | High |

### A4. Pagina Categorie

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| PUB-021 | **Încărcare Pagină Categorie** | 1. Navighează la `/ro/politica` | Pagina categoriei se încarcă. Header cu numele categoriei. | Critical |
| PUB-022 | **Hero Article Categorie** | 1. Verifică primul articol din categorie | Articol Hero în full-width cu gradient overlay, titlu mare, excerpt. | High |
| PUB-023 | **Grid Articole Categorie** | 1. Verifică restul articolelor | Grid 3 coloane (desktop) cu ArticleCard components. | High |
| PUB-024 | **Sidebar Most Popular** | 1. Verifică sidebar dreapta | Top 5 articole populare din categoria respectivă. | Medium |
| PUB-025 | **Paginare Categorie** | 1. Dacă >10 articole, verifică paginare | Butoane Previous/Next funcționale. Pagina curentă highlighted. | High |
| PUB-026 | **Categorie Inexistentă** | 1. Navighează la `/ro/categorie-inexistenta` | Pagină 404 custom afișată. | High |
| PUB-027 | **Categorie Inactivă** | 1. Accesează direct o categorie cu status=inactive | Redirect sau 404 (depinde de business rules). | Medium |

### A5. Pagina Articol Individual

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| PUB-028 | **Încărcare Articol** | 1. Click pe orice articol din homepage | Pagina articolului se deschide. Titlu, lead, conținut vizibile. | Critical |
| PUB-029 | **Header Articol** | 1. Verifică header-ul articolului | Data publicării, autor, badge categorie vizibile. | High |
| PUB-030 | **Imagine Featured** | 1. Verifică imaginea principală | Imagine afișată corect, aspect ratio păstrat. | High |
| PUB-031 | **Lightbox Imagini** | 1. Click pe imaginea din articol | Lightbox/Gallery se deschide. Navigare între imagini funcțională. | Medium |
| PUB-032 | **Table of Contents** | 1. Articol cu >3000 caractere | Table of contents generată automat din headings. | Low |
| PUB-033 | **Articole Relaționate** | 1. Scroll la finalul articolului | Secțiune "Articole Relaționate" cu 6 articole din aceeași categorie. | High |
| PUB-034 | **Link Autor** | 1. Click pe numele autorului | Navigare la pagina de profil autor. | High |
| PUB-035 | **Share Facebook** | 1. Click pe butonul Share Facebook | Dialog FB share se deschide cu URL și titlu corect. | High |
| PUB-036 | **Share Twitter** | 1. Click pe butonul Share Twitter | Dialog Twitter compose cu URL și titlu. | High |
| PUB-037 | **Share LinkedIn** | 1. Click pe butonul Share LinkedIn | Dialog LinkedIn share. | Medium |
| PUB-038 | **Share WhatsApp** | 1. Click pe butonul Share WhatsApp | WhatsApp se deschide (pe mobil) sau web.whatsapp.com. | Medium |
| PUB-039 | **Share Email** | 1. Click pe butonul Email | Client email cu subiect și body pre-populat. | Low |
| PUB-040 | **Articol Nepublicat Direct URL** | 1. Accesează direct URL articol draft | 404 sau redirect. Articolul nu e vizibil public. | Critical |
| PUB-041 | **Validare URL Category Mismatch** | 1. Accesează `/ro/wrong-category/correct-article-slug` | Redirect la URL corect sau 404. | High |

### A6. Pagina Autor

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| PUB-042 | **Încărcare Profil Autor** | 1. Navighează la `/ro/author/{slug}` | Pagina se încarcă cu info autor. | Critical |
| PUB-043 | **Avatar Autor** | 1. Verifică avatar | Avatar circular cu inițiale sau imagine (dacă există). | Medium |
| PUB-044 | **Bio Autor** | 1. Verifică biografia | Bio completă afișată. | Medium |
| PUB-045 | **Email Contact Autor** | 1. Click pe link email | mailto: link funcțional. | Medium |
| PUB-046 | **Lista Articole Autor** | 1. Verifică grid articole | Articole scrise de autor, 24/pagină, paginare. | High |
| PUB-047 | **Numărător Articole Autor** | 1. Verifică contorul | "X articole publicate" - număr corect. | Medium |
| PUB-048 | **Autor Inexistent** | 1. Accesează `/ro/author/inexistent` | 404 page. | High |

### A7. Pagina Căutare

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| PUB-049 | **Căutare din Header** | 1. Click pe iconiță search. 2. Scrie "economie". 3. Enter | Navigare la `/ro/search?q=economie`. Rezultate afișate. | Critical |
| PUB-050 | **Rezultate Căutare** | 1. Căutare cu termen valid | Grid cu ArticleCard pentru fiecare rezultat. | High |
| PUB-051 | **Număr Rezultate** | 1. Verifică mesajul | "Found X results for 'query'" afișat corect. | Medium |
| PUB-052 | **Căutare Fără Rezultate** | 1. Caută "xyz123abc" | Mesaj "No results found" cu sugestii. | High |
| PUB-053 | **Paginare Căutare** | 1. Căutare cu >12 rezultate | Paginare funcțională, 12 rezultate/pagină. | High |
| PUB-054 | **Căutare Min 2 Caractere** | 1. Caută cu 1 caracter | Validare: necesită min 2 caractere. | Medium |
| PUB-055 | **Căutare cu Caractere Speciale** | 1. Caută cu ghilimele, &, < | Caractere escaped corect. Nu XSS. | High |

### A8. Pagina Toate Articolele

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| PUB-056 | **Încărcare /all** | 1. Navighează la `/ro/all` | Lista paginată de articole, 24/pagină. | High |
| PUB-057 | **Filtru Categorie** | 1. Selectează categorie din dropdown | Doar articole din categoria selectată. URL: `?category=5`. | High |
| PUB-058 | **Clear Filtru** | 1. Cu filtru activ, click "Clear" | Filtru eliminat, toate articolele afișate. | Medium |
| PUB-059 | **Total și Procent** | 1. Verifică statistici | Total articole și procent per categorie afișate. | Low |

### A9. Pagina Trending

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| PUB-060 | **Încărcare Trending** | 1. Navighează la `/ro/trending` | Lista articole populare cu badge rang (auriu, argintiu, bronz top 3). | High |
| PUB-061 | **Filtru Period Today** | 1. Click pe "Today" | Doar articole populare de azi. URL: `?period=today`. | Medium |
| PUB-062 | **Filtru Period Week** | 1. Click pe "Week" | Articole populare ultima săptămână (default). | Medium |
| PUB-063 | **Filtru Period Month** | 1. Click pe "Month" | Articole populare ultima lună. | Medium |

### A10. Pagini Arhivă

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| PUB-064 | **Încărcare Arhivă** | 1. Navighează la `/ro/archive` | ArchiveBrowser cu filtre an/categorie. | High |
| PUB-065 | **Arhivă Per An** | 1. Navighează la `/ro/archive/2024` | Header cu anul, secțiuni pe luni, quick navigation. | High |
| PUB-066 | **Arhivă Per Lună** | 1. Navighează la `/ro/archive/2024/12` | Breadcrumb, secțiuni pe zile, calendar widget. | High |
| PUB-067 | **Calendar Widget** | 1. În arhiva lunii, verifică calendar | Grid 7 coloane, zile cu articole colorate diferit, hover count. | Medium |
| PUB-068 | **An Invalid** | 1. Navighează la `/ro/archive/1999` | Validare an (2000 - curent), eroare sau 404. | Medium |
| PUB-069 | **Lună Invalid** | 1. Navighează la `/ro/archive/2024/13` | Validare lună (1-12), eroare sau 404. | Medium |

### A11. Pagini Tag-uri

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| PUB-070 | **Listing Tag-uri** | 1. Navighează la `/ro/tags` | Tag cloud cu dimensiuni variate (weighted by usage). | Medium |
| PUB-071 | **Pagină Tag Specific** | 1. Navighează la `/ro/tags/economia` | Header cu "#economia", articole cu acest tag, număr articole. | High |
| PUB-072 | **Tag-uri Relaționate** | 1. În pagina tag, verifică sidebar | Related tags listate. | Low |

### A12. Live Text Public

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| PUB-073 | **Vizualizare Live Text** | 1. Navighează la `/ro/live/{slug}` | LiveTextViewer cu timeline posts, key points. | High |
| PUB-074 | **Actualizări Real-time** | 1. Păstrează pagina deschisă. 2. Admin adaugă post | Postul nou apare automat fără refresh (Mercure SSE). | Critical |
| PUB-075 | **Reacții Live** | 1. Click pe butoane emoji | Reacția se înregistrează (dacă enabled). | Medium |
| PUB-076 | **Viewer Counter** | 1. Verifică contorul de vizitatori | Număr actualizat în real-time. | Medium |
| PUB-077 | **Live Text Embed** | 1. Navighează la `/ro/embed/live/{slug}` | Versiune embeddable minimală. | Low |

### A13. Pagini Statice

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| PUB-078 | **Pagina About** | 1. Navighează la `/ro/about` | Secțiuni: Misiune, Valori (4 cards), Echipă, Contact. | Medium |
| PUB-079 | **Pagina Contact** | 1. Navighează la `/ro/contact` | Formular contact + info contact + social media. | Medium |
| PUB-080 | **Submit Formular Contact** | 1. Completează toate câmpurile. 2. Click "Trimite mesaj" | Mesaj success, formular resetat. | High |
| PUB-081 | **Validare Formular Contact** | 1. Lasă câmpuri goale. 2. Submit | Erori validare pentru câmpuri required. | High |

### A14. Erori și Edge Cases

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| PUB-082 | **Pagină 404** | 1. Navighează la `/ro/pagina-inexistenta` | Pagină 404 custom, link către homepage. | High |
| PUB-083 | **Layout Mobile** | 1. Resize browser <768px | Burger menu, layout stacked vertical, fără horizontal scroll. | High |
| PUB-084 | **Layout Tablet** | 1. Resize browser 768-1024px | Layout adaptat, 2 coloane unde potrivit. | Medium |
| PUB-085 | **Reject System Paths** | 1. Accesează `/_next/...`, `/.well-known/...` | Nu tratate ca articole, comportament default Next.js. | High |

---

## 4. SECȚIUNEA B: Teste Panou Administrare

### B1. Autentificare și Acces

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| ADM-001 | **Login Success** | 1. `/ro/admin`. 2. Email: admin. 3. Parolă: password. 4. Click Login | Redirect la Dashboard admin. | Critical |
| ADM-002 | **Login Failure** | 1. Introdu parolă greșită | Mesaj eroare "Invalid credentials". Rămâne pe login. | Critical |
| ADM-003 | **Login User Inexistent** | 1. Email: inexistent@test.com | Mesaj generic (nu dezvăluie dacă user există). | High |
| ADM-004 | **Logout** | 1. Click User menu → Logout | Redirect la login. Session/cookie ștearsă. | Critical |
| ADM-005 | **Acces Rută Protejată Neautentificat** | 1. Clear cookies. 2. Navighează direct la `/ro/admin/articles` | Redirect la login cu return URL. | Critical |
| ADM-006 | **Sesiune Expirată** | 1. Login. 2. Șterge manual cookie. 3. Acțiune admin | Redirect la login, mesaj sesiune expirată. | High |
| ADM-007 | **JWT Token Refresh** | 1. Login. 2. Așteaptă aproape expirare token. 3. Acțiune | Token refreshed automat, acțiune reușită. | High |
| ADM-008 | **Validare Formular Login** | 1. Lasă email gol → Login. 2. Email invalid → Login | Mesaje validare corespunzătoare. | Medium |
| ADM-009 | **Rol Editor - Restricții** | 1. Login ca editor. 2. Încearcă delete articol altcuiva | Acțiune blocată cu mesaj. | High |

### B2. Dashboard Admin

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| ADM-010 | **Încărcare Dashboard** | 1. După login, verifică dashboard | Statistics cards, grafice, articole trending vizibile. | Critical |
| ADM-011 | **Statistics Cards** | 1. Verifică cardurile statistici | Total articole, publicate, drafturi, categorii - numere corecte. | High |
| ADM-012 | **Grafic Traffic 7 Days** | 1. Verifică graficul de trafic | Line chart cu date ultimele 7 zile. | Medium |
| ADM-013 | **Real-time Stats** | 1. Verifică widget real-time | Update automat la 10 secunde cu vizitatori activi. | Medium |
| ADM-014 | **Articole Trending 24h** | 1. Verifică lista trending | Top 5 articole cu view count, link edit. | Medium |
| ADM-015 | **Quick Actions** | 1. Click pe fiecare Quick Action | New Article, New Category, Upload Images, Statistics - toate funcționează. | High |

### B3. Management Articole - Lista

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| ADM-016 | **Vizualizare Lista Articole** | 1. Navighează la `/ro/admin/articles` | Tabel cu: ID, Title, Status, Category, Author, Published, Actions. | Critical |
| ADM-017 | **Paginare Articole** | 1. Cu >20 articole, verifică paginare | Paginare funcțională, 20/pagină default. | High |
| ADM-018 | **Căutare Articole** | 1. Introdu termen în search box | Tabel filtrează în timp real, case-insensitive. | High |
| ADM-019 | **Filtru Status** | 1. Selectează "Published" din dropdown status | Doar articole cu status published afișate. | High |
| ADM-020 | **Filtru Categorie** | 1. Selectează o categorie | Doar articole din categoria respectivă. | High |
| ADM-021 | **Combinare Filtre** | 1. Aplică filtru status + categorie + search | Toate filtrele aplicate simultan (AND logic). | High |
| ADM-022 | **Clear Filtre** | 1. Cu filtre aplicate, click "Clear filters" | Toate filtrele resetate, toate articolele vizibile. | Medium |
| ADM-023 | **Sortare Coloane** | 1. Click pe header coloană (Title, Date) | Sortare ASC/DESC, indicator vizibil. | Medium |

### B4. Management Articole - Creare

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| ADM-024 | **Creare Articol - Câmpuri Minime** | 1. New Article. 2. Title, Slug, Content, 1 Author. 3. Save | Articol creat, redirect la listă, status "new". | Critical |
| ADM-025 | **Creare Articol - Toate Câmpurile** | 1. Completează toate câmpurile: Title, Slug, Lead, Content, Category, Authors (multiple), Status, Publish At | Toate datele salvate corect. | Critical |
| ADM-026 | **Generare Slug Automat** | 1. Introdu Title: "Test Article with Spaces!". 2. Click Generate Slug | Slug: "test-article-with-spaces". Caractere speciale eliminate. | High |
| ADM-027 | **Validare Titlu Required** | 1. Lasă titlu gol. 2. Save | Eroare: "Title is required". Formular nu se trimite. | Critical |
| ADM-028 | **Validare Slug Required** | 1. Lasă slug gol. 2. Save | Eroare: "Slug is required". | Critical |
| ADM-029 | **Validare Content Required** | 1. Lasă content gol. 2. Save | Eroare: "Content is required". | Critical |
| ADM-030 | **Validare Author Required** | 1. Nu selecta niciun autor. 2. Save | Eroare: "At least one author is required". | Critical |
| ADM-031 | **Validare Max 5 Authors** | 1. Încearcă să adaugi >5 autori | Eroare: "Maximum 5 authors allowed". | High |
| ADM-032 | **Slug Duplicat** | 1. Creează articol cu slug "test". 2. Creează altul cu același slug | Eroare: "This slug is already in use". | High |
| ADM-033 | **TinyMCE Editor** | 1. În Content editor, aplică formatări: bold, italic, liste, headings | Formatări aplicate corect. HTML generat valid. | High |
| ADM-034 | **Inserare Imagine în Content** | 1. În TinyMCE, click Insert Image. 2. Selectează imagine | Imaginea inserată în conținut. | High |

### B5. Management Articole - Editare

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| ADM-035 | **Încărcare Date Existente** | 1. Click Edit pe articol existent | Toate câmpurile populate cu date existente. | Critical |
| ADM-036 | **Actualizare Titlu** | 1. Editează titlul. 2. Save | Titlu actualizat, redirect la listă. | Critical |
| ADM-037 | **Schimbare Status** | 1. Schimbă status din "new" în "published". 2. Save | Status actualizat, publishedAt setat automat. | Critical |
| ADM-038 | **Schimbare Categorie** | 1. Selectează altă categorie. 2. Save | Categorie actualizată. Articol apare în noua categorie. | High |
| ADM-039 | **Adăugare/Ștergere Autori** | 1. Adaugă autor. 2. Șterge autor. 3. Save | Modificări salvate corect. | High |

### B6. Management Articole - Imagini

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| ADM-040 | **Vizualizare Imagini Atașate** | 1. Edit articol cu imagini | Secțiune "Attached Images" cu thumbnails. | High |
| ADM-041 | **Upload Imagine Nouă** | 1. Click "Upload Image". 2. Selectează fișier. 3. Wait | Imagine uploadată, apare în lista atașate. | Critical |
| ADM-042 | **Selectare Imagine Existentă** | 1. Click "Pick Image". 2. Selectează din galerie | Imagine asociată articolului. | High |
| ADM-043 | **Set Featured Image** | 1. Click "Set as Featured" pe o imagine | Imaginea marcată ca featured. Indicator vizibil. | High |
| ADM-044 | **Reordonare Imagini** | 1. Drag & drop imagini pentru reordonare | Poziții actualizate, salvate. | Medium |
| ADM-045 | **Dezasociere Imagine** | 1. Click "Remove" pe imagine atașată | Imaginea dezasociată (nu ștearsă din sistem). | High |

### B6a. Crop Imagini în Editor Articol

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| ADM-046 | **Deschidere Modal Crop** | 1. Edit articol cu imagine. 2. Click "Crop" pe imagine atașată | Modal crop se deschide cu imaginea și preview zone. | High |
| ADM-047 | **Selectare Profil Thumbnail** | 1. În modal crop, selectează profil (hero_big, card_medium, etc.) | Preview zone se ajustează la aspect ratio-ul profilului selectat. | High |
| ADM-048 | **Crop cu Drag** | 1. Drag zona de crop pe imagine | Zona de selecție se mută, preview real-time actualizat. | High |
| ADM-049 | **Resize Crop Area** | 1. Drag colțurile zonei de crop | Zona se redimensionează păstrând aspect ratio-ul profilului. | High |
| ADM-050 | **Crop Free Aspect Ratio** | 1. Selectează "Custom". 2. Redimensionează liber | Aspect ratio nu e constrâns pentru custom crops. | Medium |
| ADM-051 | **Zoom Imagine** | 1. Folosește slider zoom sau scroll | Imaginea se mărește/micșorează, crop area se ajustează. | Medium |
| ADM-052 | **Reset Crop** | 1. Ajustează crop. 2. Click "Reset" | Crop revine la selecția inițială (centrat). | Medium |
| ADM-053 | **Preview Multiple Profiles** | 1. Cu crop activ, vezi previews | Preview-uri pentru toate profilele afișate simultan. | Medium |
| ADM-054 | **Salvare Crop** | 1. Ajustează crop. 2. Click "Save Crop" | Crop salvat, thumbnail regenerat pentru profilul selectat. | Critical |
| ADM-055 | **Anulare Crop** | 1. Ajustează crop. 2. Click "Cancel" | Modal se închide, crop nu se salvează. | High |
| ADM-056 | **Crop pentru Featured Image** | 1. Pe featured image, click "Crop". 2. Selectează "hero_big". 3. Save | Thumbnail hero regenerat, vizibil pe frontend în hero grid. | Critical |
| ADM-057 | **Crop Imagine Inline** | 1. Pe imagine non-featured, crop pentru "article_inline" | Thumbnail inline regenerat pentru afișare în corp articol. | High |
| ADM-058 | **Crop cu Imagine Foarte Mare** | 1. Upload imagine 4000x3000px. 2. Deschide crop | Modal încarcă corect, nu crash, performanță acceptabilă. | High |
| ADM-059 | **Crop cu Imagine Foarte Mică** | 1. Imagine 200x150px. 2. Deschide crop pentru hero_big | Warning că imaginea e prea mică pentru profil sau upscaling. | High |
| ADM-060 | **Verificare Crop pe CDN** | 1. După crop salvat, verifică URL thumbnail pe CDN | Thumbnail nou disponibil la CDN, old invalidat/înlocuit. | High |
| ADM-061 | **Crop Batch Multiple Profiles** | 1. Selectează imaginea. 2. Click "Crop All Profiles" | Crop se aplică pe toate profilele configurate. | Medium |
| ADM-062 | **Crop History/Undo** | 1. Faci crop. 2. Verifică dacă există opțiune undo | Opțiune de revert la crop anterior (dacă implementat). | Low |

### B7. Management Articole - Ștergere

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| ADM-047 | **Modal Confirmare Ștergere** | 1. Click Delete pe articol | Modal cu warning, titlu articol, butoane Cancel/Delete. | Critical |
| ADM-048 | **Anulare Ștergere** | 1. Click Delete. 2. În modal click Cancel | Modal se închide, articol neschimbat. | High |
| ADM-049 | **Confirmare Ștergere** | 1. Click Delete. 2. Click "Delete Article" | Articol șters, dispare din listă, mesaj success. | Critical |

### B8. Article Locking

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| ADM-050 | **Lock Indicator în Listă** | 1. Alt user editează articol. 2. Verifică lista | Iconiță lock pe articolul blocat, tooltip cu info user. | High |
| ADM-051 | **Banner Articol Blocat** | 1. Încearcă să editezi articol blocat de altcineva | Banner warning cu mesaj cine editează. | High |
| ADM-052 | **Heartbeat Lock** | 1. Editează articol mai mult de 30s | Lock-ul se refreshează automat (heartbeat). | Medium |
| ADM-053 | **Eliberare Lock la Save** | 1. Save articol | Lock-ul se eliberează automat. | High |
| ADM-054 | **Eliberare Lock la Navigate Away** | 1. Editează articol. 2. Navighează la altă pagină | Lock eliberat. | High |

### B9. Management Categorii

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| ADM-055 | **Lista Categorii** | 1. Navighează la `/ro/admin/categories` | Tabel: Title, Slug, Status, On Front Page, Actions. | Critical |
| ADM-056 | **Creare Categorie** | 1. New Category. 2. Title, Slug. 3. Save | Categorie creată, în listă. | Critical |
| ADM-057 | **Generare Slug din Titlu** | 1. Titlu: "Politica si Afaceri". 2. Generate | Slug: "politica-si-afaceri", diacritice convertite. | High |
| ADM-058 | **Validare Titlu Required** | 1. Lasă titlu gol. 2. Save | Eroare validare. | High |
| ADM-059 | **Slug Duplicat Categorie** | 1. Crează categorie cu slug existent | Eroare duplicate slug. | High |
| ADM-060 | **Toggle On Front Page** | 1. Check "Display on front page". 2. Save | Categorie apare pe homepage. | High |
| ADM-061 | **Editare Categorie** | 1. Edit. 2. Schimbă titlu. 3. Save | Titlu actualizat, slug păstrat (dacă nu schimbat manual). | High |
| ADM-062 | **Schimbare Status Categorie** | 1. Schimbă din active în inactive. 2. Save | Status actualizat. Categorie poate fi ascunsă public. | High |
| ADM-063 | **Ștergere Categorie Goală** | 1. Delete categorie fără articole | Categorie ștearsă cu succes. | High |
| ADM-064 | **Ștergere Categorie cu Articole** | 1. Delete categorie cu articole | Warning sau eroare (depinde de business rules). | High |

### B10. Management Autori

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| ADM-065 | **Lista Autori** | 1. Navighează la `/ro/admin/authors` | Tabel: Avatar, Name, Email, Title, Article Count, Actions. | Critical |
| ADM-066 | **Creare Autor** | 1. New Author. 2. First Name, Last Name, Email. 3. Save | Autor creat, slug auto-generat. | Critical |
| ADM-067 | **Toate Câmpurile Autor** | 1. Completează: nume, email, bio, social links. 2. Save | Toate salvate. URL-uri validate. | High |
| ADM-068 | **Validare Email** | 1. Email invalid: "not-an-email". 2. Save | Eroare format email. | High |
| ADM-069 | **Email Duplicat** | 1. Crează autor cu email existent | Eroare: email must be unique. | High |
| ADM-070 | **Validare URL Social** | 1. Facebook URL invalid. 2. Save | Eroare format URL. | Medium |
| ADM-071 | **Editare Autor** | 1. Edit. 2. Schimbă nume. 3. Save | Nume actualizat, slug poate fi regenerat. | High |
| ADM-072 | **Toggle Active** | 1. Uncheck "Active". 2. Save | isActive = false. Autor poate fi ascuns din selecții. | High |
| ADM-073 | **Ștergere Autor fără Articole** | 1. Delete autor nou | Șters cu succes. | High |
| ADM-074 | **Ștergere Autor cu Articole** | 1. Delete autor cu articole asociate | Warning sau eroare. | High |

### B11. Biblioteca de Imagini

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| ADM-075 | **Vizualizare Galerie** | 1. Navighează la `/ro/admin/images` | Grid cu thumbnails, statistici (total, storage). | Critical |
| ADM-076 | **Upload Imagine - File** | 1. Click Upload. 2. Selectează fișier JPG. 3. Wait | Imagine uploadată, thumbnail generat. | Critical |
| ADM-077 | **Upload Imagine - Drag & Drop** | 1. Drag fișier în zona de upload | Upload reușit. | High |
| ADM-078 | **Upload Multiple** | 1. Selectează 5 fișiere. 2. Upload | Toate 5 uploadate cu progres individual. | High |
| ADM-079 | **Validare Tip Fișier** | 1. Upload fișier .txt sau .exe | Eroare: doar imagini acceptate. | High |
| ADM-080 | **Validare Dimensiune** | 1. Upload imagine >10MB | Eroare: file too large (max 10MB). | High |
| ADM-081 | **Upload din URL** | 1. Tab "From URL". 2. Introdu URL imagine validă. 3. Upload | Imagine descărcată și procesată. | Medium |
| ADM-082 | **Vizualizare Detalii Imagine** | 1. Click pe imagine | Preview mare, metadata: filename, size, dimensions, date. | High |
| ADM-083 | **Editare Alt Text** | 1. Edit imagine. 2. Schimbă alt text. 3. Save | Alt text actualizat. | High |
| ADM-084 | **Ștergere Imagine Nefolosită** | 1. Delete imagine nefolosită | Ștearsă din sistem și storage. | High |
| ADM-085 | **Ștergere Imagine în Uz** | 1. Delete imagine atașată la articol | Warning despre usage. Force delete sau blocat. | High |
| ADM-086 | **Verificare Thumbnail Profiles** | 1. Upload imagine nouă. 2. Verifică generarea | 10 profile generate: hero_big, hero_small, article_main, etc. | High |

### B11a. Crop Imagini în Biblioteca de Imagini

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| ADM-087 | **Acces Crop din Galerie** | 1. Navighează la `/ro/admin/images`. 2. Click pe imagine. 3. Click "Crop" | Modal crop se deschide pentru imaginea selectată. | High |
| ADM-088 | **Selectare Profil în Galerie** | 1. În modal crop, dropdown cu toate cele 10 profile | Toate profilele disponibile: hero_big, hero_small, article_main, article_inline, card_large, card_medium, card_small, list_item, mobile_hero, gallery. | High |
| ADM-089 | **Crop Individual Profile** | 1. Selectează profil "card_medium". 2. Ajustează crop. 3. Save | Doar thumbnail-ul pentru card_medium este regenerat. | High |
| ADM-090 | **Crop All Profiles Simultan** | 1. Click "Crop All Profiles". 2. Ajustează zona. 3. Save All | Toate cele 10 thumbnails sunt regenerate cu același crop. | High |
| ADM-091 | **Preview Crop în Context** | 1. În modal crop, tab "Preview" | Vezi cum arată crop-ul în diferite contexte (card, hero, list). | Medium |
| ADM-092 | **Regenerare Thumbnails** | 1. Pe imagine, click "Regenerate Thumbnails" | Toate thumbnails regenerate din original (reset crop). | High |
| ADM-093 | **Vizualizare Thumbnails Existente** | 1. Click pe imagine. 2. Tab "Thumbnails" | Grid cu toate cele 10 thumbnails generate, click pentru preview. | High |
| ADM-094 | **Crop cu Focal Point** | 1. În crop tool, marchează focal point | Focal point salvat pentru auto-crop inteligent în viitor. | Medium |
| ADM-095 | **Comparație Before/After** | 1. Ajustează crop. 2. Toggle "Compare" | Split view înainte/după crop. | Low |
| ADM-096 | **Crop Smart AI Suggestion** | 1. Click "Smart Crop" (dacă disponibil) | AI sugerează zona optimă de crop (face detection, salient area). | Low |
| ADM-097 | **Batch Crop Multiple Imagini** | 1. Selectează 5 imagini. 2. Click "Batch Crop" | Modal pentru aplicare crop pe toate imaginile selectate. | Medium |
| ADM-098 | **Download Thumbnail Specific** | 1. În view thumbnails, click download pe un profil | Descarcă thumbnail-ul în formatul respectiv. | Low |
| ADM-099 | **Verificare Format WebP** | 1. După crop, verifică extensia fișierului | Thumbnail salvat în format WebP pentru optimizare. | High |
| ADM-100 | **Verificare Dimensiuni Profil** | 1. Verifică dimensiunile thumbnail-ului generat | hero_big: 1920x1080, hero_small: 800x600, etc. conform specificațiilor. | High |
| ADM-101 | **Crop Image cu Transparență** | 1. Upload PNG cu transparență. 2. Crop | Transparența păstrată în thumbnail-ul rezultat. | Medium |
| ADM-102 | **Crop în Context Articol** | 1. Din biblioteca, "Crop for Article". 2. Selectează articol | Crop aplicat și imaginea atașată automat la articol. | Medium |

### B12. Live Texts Management - Bază

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| LT-001 | **Lista Live Texts** | 1. Navighează la `/ro/admin/live-texts` | Tabel: Title, Status (badge colorat), Category, Start Time, Viewers, Actions. | Critical |
| LT-002 | **Filtru Status** | 1. Selectează "Live" din dropdown status | Doar transmisiuni cu status LIVE afișate. | High |
| LT-003 | **Filtru Categorie** | 1. Selectează o categorie | Doar live texts din categoria respectivă. | High |
| LT-004 | **Sortare după Start Time** | 1. Click pe header "Start Time" | Sortare ASC/DESC după ora de start. | Medium |
| LT-005 | **Căutare Live Text** | 1. Introdu termen în search | Filtrare după titlu, case-insensitive. | High |
| LT-006 | **Indicator Live în Listă** | 1. Verifică rândul unui live text activ | Indicator pulsant "LIVE" pentru transmisiuni active. | High |
| LT-007 | **Viewer Count în Listă** | 1. Verifică coloana Viewers | Număr vizitatori actuali pentru fiecare transmisiune. | Medium |

### B12a. Creare și Configurare Live Text

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| LT-008 | **Creare Live Text - Câmpuri Minime** | 1. Click "New Live Text". 2. Completează Title. 3. Save | Live text creat în status "draft", redirect la editor. | Critical |
| LT-009 | **Creare cu Toate Câmpurile** | 1. Title, Category, Description, Cover Image, Start Time, Locale | Toate datele salvate corect. | Critical |
| LT-010 | **Validare Titlu Required** | 1. Lasă titlu gol. 2. Save | Eroare: "Title is required". | Critical |
| LT-011 | **Selectare Categorie** | 1. Selectează categorie din dropdown | Categorie asociată, vizibilă în pagina publică. | High |
| LT-012 | **Upload Cover Image** | 1. Click "Upload Cover". 2. Selectează imagine | Imagine de cover salvată și vizibilă în preview. | High |
| LT-013 | **Setare Start Time** | 1. Selectează dată și oră viitoare | Start time salvat, afișat în listă și pagina publică. | High |
| LT-014 | **Setare End Time (Planificat)** | 1. Setează end time după start time | End time salvat pentru live texts programate. | Medium |
| LT-015 | **Descriere Multiline** | 1. Adaugă descriere cu paragrafe | Descrierea salvată cu formatare. | Medium |
| LT-016 | **Traducere Live Text** | 1. Crează în RO. 2. Switch la EN. 3. Edit | Traduceri separate pentru Title și Description. | High |

### B12b. Status Management Live Text

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| LT-017 | **Status DRAFT → LIVE** | 1. Edit live text draft. 2. Click "Go Live" sau schimbă status | Status devine LIVE, startTime setat dacă null, vizibil public. | Critical |
| LT-018 | **Status LIVE → PAUSED** | 1. Live text activ. 2. Click "Pause" | Status PAUSED, transmisiune temporar oprită, badge schimbat. | High |
| LT-019 | **Status PAUSED → LIVE** | 1. Live text paused. 2. Click "Resume" | Status revine LIVE, transmisiune reluată. | High |
| LT-020 | **Status LIVE → ENDED** | 1. Live text activ. 2. Click "End Live" | Status ENDED, endTime setat automat, arhivat. | Critical |
| LT-021 | **Status DRAFT → ENDED** | 1. Încearcă să închei un draft | Blocat sau warning - nu poate fi ended fără a fi fost live. | Medium |
| LT-022 | **Confirmare End Live** | 1. Click "End Live" | Modal confirmare cu warning că acțiunea e permanentă. | High |
| LT-023 | **Auto-End Scheduled** | 1. Setează end time în trecut (sau așteaptă). | Cron job schimbă status în ENDED automat. | Medium |
| LT-024 | **Status Badge Colors** | 1. Verifică badge-urile în listă | DRAFT=gray, LIVE=red pulsant, PAUSED=yellow, ENDED=green. | Medium |

### B12c. Management Posturi Live Text

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| LT-025 | **Adăugare Post Simplu** | 1. Edit live text. 2. Tab "Posts". 3. Scrie text. 4. Click "Publish" | Post adăugat în timeline, vizibil instant pe frontend. | Critical |
| LT-026 | **Post cu Formatare Bold** | 1. Selectează text. 2. Click Bold sau Ctrl+B | Text bold în post. | High |
| LT-027 | **Post cu Formatare Italic** | 1. Selectează text. 2. Click Italic sau Ctrl+I | Text italic în post. | High |
| LT-028 | **Post cu Lista Bullets** | 1. Click buton liste. 2. Adaugă items | Lista formatată corect în post. | Medium |
| LT-029 | **Post cu Link** | 1. Selectează text. 2. Click Insert Link. 3. Adaugă URL | Link clickabil în post. | High |
| LT-030 | **Post cu Imagine** | 1. Click "Add Image". 2. Upload sau selectează | Imagine atașată la post, afișată inline. | High |
| LT-031 | **Post cu Video Embed** | 1. Click "Add Video". 2. Paste YouTube URL | Video embedded în post cu player. | Medium |
| LT-032 | **Post ca Key Point** | 1. Scrie post. 2. Check "Mark as Key Point". 3. Publish | Post marcat cu badge special, apare în Key Points section. | High |
| LT-033 | **Editare Post Existent** | 1. Click Edit pe post publicat. 2. Modifică text. 3. Save | Post actualizat, indicator "edited" afișat. | High |
| LT-034 | **Ștergere Post** | 1. Click Delete pe post. 2. Confirmare | Post șters din timeline și din frontend. | High |
| LT-035 | **Reordonare Posturi** | 1. Drag & drop posturi în timeline | Poziții actualizate, ordine reflectată pe frontend. | Medium |
| LT-036 | **Pin Post to Top** | 1. Click "Pin" pe post important | Post fixat în partea de sus a timeline-ului. | Medium |
| LT-037 | **Post Auto-Timestamp** | 1. Publică post fără a seta timestamp | Timestamp setat automat la ora curentă. | High |
| LT-038 | **Post cu Timestamp Manual** | 1. Click "Set Time". 2. Alege oră specifică | Post apare cu timestamp-ul setat. | Medium |
| LT-039 | **Preview Post înainte de Publish** | 1. Scrie post. 2. Click "Preview" | Preview cum va arăta postul pe frontend. | Medium |
| LT-040 | **Character Count** | 1. Scrie post lung | Counter caractere afișat, warning la aproape de limită. | Low |

### B12d. Colaboratori Live Text

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| LT-041 | **Vizualizare Colaboratori** | 1. Edit live text. 2. Tab "Collaborators" | Lista utilizatorilor cu acces la acest live text. | High |
| LT-042 | **Adăugare Colaborator** | 1. Click "Add Collaborator". 2. Selectează user. 3. Alege rol | Colaborator adăugat cu rolul specificat. | High |
| LT-043 | **Rol Editor** | 1. Adaugă colaborator cu rol "Editor" | Editor poate posta, edita propriile posturi, nu poate șterge. | High |
| LT-044 | **Rol Contributor** | 1. Adaugă colaborator cu rol "Contributor" | Contributor poate doar posta, nu poate edita/șterge. | High |
| LT-045 | **Rol Admin** | 1. Adaugă colaborator cu rol "Admin" | Admin poate toate acțiunile (posturi, colaboratori, setări). | High |
| LT-046 | **Ștergere Colaborator** | 1. Click Remove pe colaborator | Colaborator eliminat, pierde accesul. | High |
| LT-047 | **Schimbare Rol** | 1. Edit rol colaborator existent | Rolul actualizat, permisiuni schimbate imediat. | Medium |
| LT-048 | **Indicator Post Author** | 1. Verifică posturile în timeline | Fiecare post arată autorul (colaboratorul care l-a postat). | Medium |
| LT-049 | **Concurrent Editing** | 1. 2 colaboratori editează simultan | Ambii pot posta, nu există conflicte. | High |
| LT-050 | **Notificare Nou Colaborator** | 1. Adaugă colaborator | Email/notificare trimisă colaboratorului nou. | Low |

### B12e. Sport Match Management

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| LT-051 | **Creare Live Text Sport** | 1. New Live Text. 2. Check "Is Sport Match". | Secțiune Sport Match devine vizibilă în formular. | High |
| LT-052 | **Selectare Sport Type** | 1. Dropdown sport type. 2. Selectează "football" | Sport type salvat: football, basketball, tennis, handball, volleyball, hockey, rugby, other. | High |
| LT-053 | **Setare Home Team** | 1. Completează "Home Team" | Echipa gazdă salvată și afișată în scoreboard. | Critical |
| LT-054 | **Setare Away Team** | 1. Completează "Away Team" | Echipa oaspeți salvată și afișată. | Critical |
| LT-055 | **Upload Team Logos** | 1. Upload logo pentru fiecare echipă | Logo-uri afișate în scoreboard pe frontend. | Medium |
| LT-056 | **Setare Competition** | 1. Completează "Competition" (Liga Națională, Champions League) | Competiția afișată în header-ul transmisiunii. | High |
| LT-057 | **Setare Venue** | 1. Completează "Venue" (stadion, arena) | Venue afișat în informațiile meciului. | Medium |
| LT-058 | **Match Status - Not Started** | 1. Verifică status implicit | Meciul nou are status "not_started". | High |
| LT-059 | **Match Status - Live** | 1. Click "Start Match" | Status devine "live", cronometru pornește (dacă implementat). | Critical |
| LT-060 | **Match Status - Half Time** | 1. Click "Half Time" | Status "half_time", badge schimbat pe frontend. | High |
| LT-061 | **Match Status - Finished** | 1. Click "End Match" | Status "finished", scor final afișat. | Critical |
| LT-062 | **Match Status - Postponed** | 1. Click "Postpone Match" | Status "postponed", mesaj afișat pe frontend. | Medium |
| LT-063 | **Match Status - Cancelled** | 1. Click "Cancel Match" | Status "cancelled", transmisiunea încheiată cu mesaj. | Medium |

### B12f. Scor și Statistici Meci

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| LT-064 | **Actualizare Scor Home** | 1. Click "+" pe scorul echipei gazdă | Scor incrementat, actualizat instant pe frontend. | Critical |
| LT-065 | **Actualizare Scor Away** | 1. Click "+" pe scorul oaspeților | Scor incrementat, actualizat instant. | Critical |
| LT-066 | **Decrementare Scor** | 1. Click "-" pe scor (corectare greșeală) | Scor decrementat pentru corecții. | High |
| LT-067 | **Setare Scor Manual** | 1. Click pe scor. 2. Introdu valoare | Scor setat direct la valoarea introdusă. | Medium |
| LT-068 | **Current Minute (Football)** | 1. În meci de fotbal, setează minutul curent | Minutul afișat: "45'", "90+3'". | High |
| LT-069 | **Current Period** | 1. Setează "2nd Half", "4th Quarter" | Perioada curentă afișată pe scoreboard. | High |
| LT-070 | **Statistici Posesie** | 1. Setează posesie: 60%-40% | Statistică posesie afișată în widget. | Medium |
| LT-071 | **Statistici Șuturi** | 1. Actualizează shots, shots on target | Statistici șuturi afișate. | Medium |
| LT-072 | **Statistici Cornere** | 1. Actualizează corners | Cornere afișate în statistici. | Medium |
| LT-073 | **Statistici Custom** | 1. Adaugă statistică personalizată (faults, saves) | Statistică custom salvată și afișată. | Low |

### B12g. Evenimente Meci (Match Events)

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| LT-074 | **Adăugare Event Gol** | 1. Click "Add Event". 2. Tip: Goal. 3. Player, Minute | Event gol adăugat, scor actualizat automat, post auto-generat. | Critical |
| LT-075 | **Adăugare Event Penalty** | 1. Tip: Penalty Goal sau Missed Penalty | Event penalty înregistrat cu detalii. | High |
| LT-076 | **Adăugare Event Autogol** | 1. Tip: Own Goal | Gol adăugat la echipa adversă, marcat special. | High |
| LT-077 | **Adăugare Cartonaș Galben** | 1. Tip: Yellow Card. 2. Player, Minute | Cartonaș galben înregistrat, icon afișat în timeline. | High |
| LT-078 | **Adăugare Cartonaș Roșu** | 1. Tip: Red Card. 2. Player, Minute | Cartonaș roșu înregistrat, player marcat ca eliminat. | High |
| LT-079 | **Al Doilea Galben → Roșu** | 1. Adaugă al 2-lea galben la același jucător | Sistem detectează și schimbă în second_yellow_card/red. | Medium |
| LT-080 | **Adăugare Substituție** | 1. Tip: Substitution. 2. Player In, Player Out | Substituție înregistrată cu ambii jucători. | High |
| LT-081 | **Adăugare VAR Check** | 1. Tip: VAR Check | Event VAR adăugat, icon special în timeline. | Medium |
| LT-082 | **Adăugare VAR Goal Cancelled** | 1. Tip: VAR Goal Cancelled. 2. Selectează golul anulat | Gol marcat ca anulat, scor corectat. | Medium |
| LT-083 | **Event cu Extra Time** | 1. Adaugă event la minutul 45+2 | Minutul afișat ca "45+2'" în timeline. | High |
| LT-084 | **Editare Event** | 1. Click Edit pe event existent. 2. Corectează player/minute | Event actualizat în timeline. | High |
| LT-085 | **Ștergere Event** | 1. Click Delete pe event greșit | Event șters, scor recalculat dacă era gol. | High |
| LT-086 | **Timeline Evenimente** | 1. Verifică timeline evenimente | Evenimente sortate cronologic cu icons corespunzătoare. | High |
| LT-087 | **Auto-generate Post from Event** | 1. După adăugare gol | Post automat generat: "⚽ GOL! Player X marchează!" | High |

### B12h. Live Text Analytics & Real-time

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| LT-088 | **Dashboard Analytics** | 1. Click "Analytics" pe live text | Dashboard: total views, unique viewers, peak viewers. | High |
| LT-089 | **Grafic Viewers în Timp** | 1. Verifică graficul în analytics | Line chart cu număr viewers pe parcursul transmisiunii. | Medium |
| LT-090 | **Engagement Metrics** | 1. Verifică metrici engagement | Reactions count, shares, avg. time on page. | Medium |
| LT-091 | **Real-time Viewer Counter** | 1. În editor, verifică viewer counter | Număr actualizat în timp real de vizitatori activi. | High |
| LT-092 | **Mercure SSE Connection** | 1. Deschide pagina publică. 2. Admin postează | Post apare instant fără refresh (SSE push). | Critical |
| LT-093 | **Reconnect după Disconnect** | 1. Simulează pierdere conexiune. 2. Reconectare | Client se reconectează automat, sync cu ultimele posturi. | High |
| LT-094 | **Export Analytics** | 1. Click "Export" în analytics | CSV/PDF descărcat cu datele transmisiunii. | Low |

### B12i. Reacții și Interacțiuni Publice

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| LT-095 | **Enable/Disable Reactions** | 1. În setări live text, toggle "Allow Reactions" | Butoanele de reacții afișate/ascunse pe frontend. | Medium |
| LT-096 | **Vizualizare Reacții Agregate** | 1. În admin, verifică reacții | Total reacții per tip (like, love, angry, etc.). | Medium |
| LT-097 | **Live Reaction Counter** | 1. Pe frontend, fă reacție. 2. Verifică counter în admin | Counter actualizat în timp real. | Medium |
| LT-098 | **Moderation - Hide Post** | 1. Ascunde post problematic | Post nu mai e vizibil public, dar există în admin. | Medium |
| LT-099 | **Moderation - Block User** | 1. Blochează user care face spam reacții | User blocat de la interacțiuni pe acest live text. | Low |

### B12j. Templates și Configurare Avansată

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| LT-100 | **Creare din Template** | 1. Click "New from Template". 2. Selectează "Football Match" | Live text pre-configurat pentru meci de fotbal. | Medium |
| LT-101 | **Save as Template** | 1. Configurează live text. 2. Click "Save as Template" | Template salvat pentru reutilizare. | Medium |
| LT-102 | **Quick Actions Bar** | 1. În editor meci sport, verifică bara de acțiuni | Butoane rapide: Goal, Card, Substitution, Minute Update. | High |
| LT-103 | **Keyboard Shortcuts** | 1. Apasă shortcuts (G=Goal, Y=Yellow, etc.) | Acțiuni rapide cu taste (dacă implementat). | Low |
| LT-104 | **Auto-save Draft Posts** | 1. Scrie post fără a publica. 2. Refresh | Draftul postului salvat automat. | Medium |

### B13. Articole Importante

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| ADM-095 | **Lista Articole Importante** | 1. Navighează la `/ro/admin/important-articles` | Lista actuală cu poziții, reorder controls. | High |
| ADM-096 | **Adăugare Articol** | 1. Click "Add Article". 2. Selectează articol | Articol adăugat la sfârșitul listei. | High |
| ADM-097 | **Ștergere din Importante** | 1. Click Remove pe articol | Articol eliminat din featured (nu șters din sistem). | High |
| ADM-098 | **Reordonare Drag & Drop** | 1. Drag articol din poziția 3 în poziția 1 | Ordine actualizată. Reflectat pe homepage. | High |
| ADM-099 | **Limită Articole** | 1. Încearcă să adaugi >25 articole | Eroare sau oldest removed (max 25). | Medium |
| ADM-100 | **Articol Nepublicat în Importante** | 1. Adaugă articol. 2. Schimbă articolul în draft | Warning în admin că articol featured e nepublicat. | High |

### B14. Short Links

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| ADM-101 | **Lista Short Links** | 1. Navighează la `/ro/admin/short-links` | Tabel: Short URL, Target, Created, Clicks, Actions. | High |
| ADM-102 | **Creare Short Link** | 1. New Short Link. 2. Title, Code, Target URL. 3. Create | Link creat, cod unic. | High |
| ADM-103 | **Generare Cod Automat** | 1. Lasă cod gol. 2. Create | Cod generat automat. | Medium |
| ADM-104 | **Cod Duplicat** | 1. Creează cu cod existent | Eroare: code must be unique. | High |
| ADM-105 | **Copy to Clipboard** | 1. Click Copy pe link | URL scurt copiat în clipboard. | Medium |
| ADM-106 | **Statistici Link** | 1. Click Stats | Grafice: clicks over time, devices, countries, referrers. | Medium |
| ADM-107 | **Ștergere Short Link** | 1. Delete short link | Link șters, redirect nu mai funcționează. | High |

### B15. Arhivă Admin

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| ADM-108 | **Statistici Arhivă** | 1. Navighează la `/ro/admin/archive` | Stats: total arhivate, per an, per categorie. | Medium |
| ADM-109 | **Arhivare în Masă** | 1. Selectează date range. 2. Click Archive | Articole arhivate conform criteriilor. | Medium |
| ADM-110 | **Lista Articole Arhivate** | 1. Verifică lista | Articole arhivate cu opțiuni Restore/Delete. | Medium |
| ADM-111 | **Restaurare Articol** | 1. Click Restore pe articol arhivat | Articol restaurat, status anterior. | Medium |

### B16. Statistici Detaliate

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| ADM-112 | **Dashboard Statistici** | 1. Navighează la `/ro/admin/statistics` | Selector date range, grafice detaliate. | Medium |
| ADM-113 | **Filter Date Range** | 1. Selectează 30 days | Date actualizate pentru perioada selectată. | Medium |
| ADM-114 | **Export Data** | 1. Click Export CSV | Fișier CSV descărcat cu datele. | Low |

### B17. Navigare și UX Admin

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| ADM-115 | **Sidebar Navigation** | 1. Verifică sidebar | Toate secțiunile listate: Dashboard, Articles, Categories, etc. | High |
| ADM-116 | **Active State Sidebar** | 1. Navighează între secțiuni | Item activ highlighted în sidebar. | Medium |
| ADM-117 | **Responsive Admin** | 1. Resize <768px | Sidebar collapses, navigation accesibilă. | Medium |
| ADM-118 | **Loading States** | 1. Observă încărcările | Spinners/skeletons în timpul load. | Medium |
| ADM-119 | **Error Recovery** | 1. Trigger eroare. 2. Dismiss. 3. Retry | Form data preserved, retry funcționează. | High |
| ADM-120 | **Unsaved Changes Warning** | 1. Editează formular. 2. Navigate away | Warning despre modificări nesalvate. | High |

---

## 5. SECȚIUNEA C: Teste API Backend

### C1. Articole API

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| API-001 | **GET /api/articles** | `curl http://127.0.0.1:8081/api/articles` | Lista articole JSON-LD cu paginare. | Critical |
| API-002 | **GET Article by ID** | `curl http://127.0.0.1:8081/api/articles/1` | Detalii articol cu grupuri article:read, article:detail. | Critical |
| API-003 | **GET Article by Slug** | `curl http://127.0.0.1:8081/api/articles/by-slug/test-slug` | Articol după slug, eager loading relations. | Critical |
| API-004 | **GET Articles Locale RO** | `curl -H "Accept-Language: ro" http://127.0.0.1:8081/api/articles` | Articole în română. | Critical |
| API-005 | **GET Articles Locale EN** | `curl -H "Accept-Language: en" http://127.0.0.1:8081/api/articles` | Articole în engleză (traduceri sau fallback). | Critical |
| API-006 | **Paginare Articles** | `curl "http://127.0.0.1:8081/api/articles?page=2&itemsPerPage=10"` | Pagina 2, 10 items. Hydra pagination info. | High |
| API-007 | **Filtru Category** | `curl "http://127.0.0.1:8081/api/articles?category=5"` | Doar articole din categoria 5. | High |
| API-008 | **Filtru Status** | `curl "http://127.0.0.1:8081/api/articles?status=published"` | Doar articole publicate. | High |
| API-009 | **Sortare publishedAt** | `curl "http://127.0.0.1:8081/api/articles?order[publishedAt]=DESC"` | Sortate după data publicării. | High |
| API-010 | **POST Article (Auth)** | `curl -X POST -H "Authorization: Bearer TOKEN" ...` | Articol creat, 201 Created. | Critical |
| API-011 | **POST Article (No Auth)** | `curl -X POST http://127.0.0.1:8081/api/articles` | 401 Unauthorized. | Critical |
| API-012 | **PUT Article Update** | `curl -X PUT -H "Authorization: Bearer TOKEN" ...` | Articol actualizat. | Critical |
| API-013 | **DELETE Article** | `curl -X DELETE -H "Authorization: Bearer TOKEN" ...` | Articol șters, 204 No Content. | Critical |
| API-014 | **Cache Headers Articles** | Verifică response headers | Cache-Control: max-age=1800 (30 min). | Medium |

### C2. Categorii API

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| API-015 | **GET /api/categories** | `curl http://127.0.0.1:8081/api/categories` | Lista categorii. | Critical |
| API-016 | **GET Category by Slug** | `curl http://127.0.0.1:8081/api/categories/by-slug/politica` | Categorie după slug. | High |
| API-017 | **POST Category** | Cu autorizare, creare categorie | 201 Created. | High |
| API-018 | **Filtru Status Active** | `curl "http://127.0.0.1:8081/api/categories?status=active"` | Doar categorii active. | High |

### C3. Autori API

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| API-019 | **GET /api/authors** | `curl http://127.0.0.1:8081/api/authors` | Lista autori. | Critical |
| API-020 | **GET Author by Slug** | `curl http://127.0.0.1:8081/api/authors/by-slug/ion-popescu` | Autor după slug. | High |
| API-021 | **POST Author** | Creare autor cu email unic | 201 Created. | High |
| API-022 | **Email Duplicat** | POST cu email existent | 422 Unprocessable Entity. | High |

### C4. Tag-uri API

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| API-023 | **GET /api/tags** | `curl http://127.0.0.1:8081/api/tags` | Lista etichete. | High |
| API-024 | **GET Popular Tags** | `curl http://127.0.0.1:8081/api/tags/popular?limit=20` | Top 20 tag-uri după usageCount. Cache 10 min. | High |
| API-025 | **Search Tags** | `curl "http://127.0.0.1:8081/api/tags/search?q=econ"` | Autocomplete rezultate. | High |
| API-026 | **Related Tags** | `curl http://127.0.0.1:8081/api/tags/1/related` | Tag-uri corelate (co-occurring). | Medium |
| API-027 | **Tag Stats** | `curl http://127.0.0.1:8081/api/tags/1/stats` | usageCount, articleCount. | Medium |

### C5. Imagini API

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| API-028 | **GET /api/images** | `curl http://127.0.0.1:8081/api/images` | Lista imagini. | Critical |
| API-029 | **POST Upload Image** | `curl -X POST -F "file=@image.jpg" ...` | Imagine uploadată, dimensiuni extrase. | Critical |
| API-030 | **Upload Invalid Type** | Upload fișier .txt | 422 cu mesaj tip invalid. | High |
| API-031 | **Upload Too Large** | Upload imagine >10MB | 413 sau 422 dimensiune depășită. | High |
| API-032 | **Generate Thumbnails** | `POST /api/images/{id}/generate-thumbnails` | 10 thumbnail profiles generate. | High |
| API-033 | **Crop Thumbnail** | `POST /api/images/{id}/thumbnails/crop` | Crop aplicat pe profil specificat. | Medium |

### C6. Article Lock API

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| API-034 | **Acquire Lock** | `POST /api/articles/1/lock` | Lock creat, timestamp returnat. | High |
| API-035 | **Check Lock** | `GET /api/articles/1/lock/check` | Status lock: locked/unlocked, user info. | High |
| API-036 | **Lock Conflict** | Alt user încearcă lock | 409 Conflict. | High |
| API-037 | **Heartbeat** | `POST /api/articles/1/lock/heartbeat` | Timestamp refreshed. | Medium |
| API-038 | **Release Lock** | `DELETE /api/articles/1/lock` | 204 No Content, lock eliberat. | High |

### C7. Short Links API

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| API-039 | **GET /api/short_links** | `curl http://127.0.0.1:8081/api/short_links` | Lista short links. | High |
| API-040 | **Redirect /s/{code}** | `curl -I http://127.0.0.1:8081/s/abc123` | 301 Redirect la originalUrl. | Critical |
| API-041 | **Preview Link** | `GET /s/{code}/preview` | JSON cu detalii link. | Medium |
| API-042 | **Stats Link** | `GET /api/short_links/{id}/stats` | Statistici: clicks, devices, countries. | Medium |

### C8. Live Text API

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| API-043 | **GET /api/live_texts** | `curl http://127.0.0.1:8081/api/live_texts` | Lista live texts. | High |
| API-044 | **GET Key Points** | `GET /api/live_texts/{id}/key_points` | Puncte cheie din transmisie. | Medium |
| API-045 | **GET Analytics** | `GET /api/live_texts/{id}/analytics` | Views, viewers, engagement. | Medium |

### C9. Statistics API

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| API-046 | **Trending Articles** | `GET /api/admin/stats/trending` | Top articole 24h (PUBLIC). | High |
| API-047 | **Site Stats (Admin)** | `GET /api/admin/stats/site` | Total visits, bounce rate (ROLE_ADMIN). | High |
| API-048 | **Realtime Stats** | `GET /api/admin/stats/realtime` | Active sessions, trending now. | Medium |
| API-049 | **Article Counts** | `GET /api/admin/stats/article-counts` | Total, published, new, submitted. | Medium |
| API-050 | **Category Distribution** | `GET /api/admin/stats/categories` | Articole per categorie. | Medium |

### C10. Tracking API

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| API-051 | **Track Pageview** | `POST /api/track/pageview` cu article_id, visitor_id | 200 OK, counters incremented. | High |
| API-052 | **Track Reading Time** | `POST /api/track/reading-time` | Reading time stored în Redis. | Medium |
| API-053 | **Track Scroll Depth** | `POST /api/track/scroll-depth` | Scroll depth tracked. | Medium |

### C11. Health Checks

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| API-054 | **Health All** | `GET /api/health` | Status healthy/unhealthy pentru toate serviciile. | Critical |
| API-055 | **Health Database** | `GET /api/health/database` | PostgreSQL status. | High |
| API-056 | **Health Redis** | `GET /api/health/redis` | Redis status. | High |
| API-057 | **Health Elasticsearch** | `GET /api/health/elasticsearch` | ES status. | High |
| API-058 | **Liveness Probe** | `GET /api/health/live` | alive/dead pentru Kubernetes. | Medium |
| API-059 | **Readiness Probe** | `GET /api/health/ready` | ready/not_ready. | Medium |

### C12. Important Articles API

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| API-060 | **GET Important Articles** | `GET /api/important_articles` | Lista articole importante cu poziții. | High |
| API-061 | **POST Add Important** | `POST /api/important_articles` cu article, position | Articol adăugat. Position 1-25. | High |
| API-062 | **Max 25 Validation** | Încearcă să adaugi al 26-lea | 422 Unprocessable Entity. | High |
| API-063 | **DELETE Important** | `DELETE /api/important_articles/{id}` | Eliminat din listă. | High |

### C13. Error Handling

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| API-064 | **404 Resource** | `GET /api/articles/999999` | 404 Not Found, format Hydra Error. | High |
| API-065 | **422 Validation** | POST cu date invalide | 422 cu detalii validare. | High |
| API-066 | **401 Unauthorized** | Request la endpoint protejat fără token | 401 Unauthorized. | Critical |
| API-067 | **403 Forbidden** | Request cu rol insuficient | 403 Forbidden. | High |
| API-068 | **409 Conflict** | Lock conflict | 409 cu mesaj conflict. | High |

### C14. Rate Limiting

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| API-069 | **General Rate Limit** | 100+ requests/minut | 429 Too Many Requests după limită. | High |
| API-070 | **Login Rate Limit** | 6 login attempts/minut | 429 după 5 încercări. | High |
| API-071 | **Write Rate Limit** | 50+ write ops/minut | 429 după limită. | Medium |
| API-072 | **Image Upload Limit** | 11 uploads/minut | 429 după 10. | Medium |

### C15. CORS și Headers

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| API-073 | **CORS Preflight** | OPTIONS request de pe localhost:3005 | 200 cu headers CORS corecte. | High |
| API-074 | **Vary Header** | GET articole | `Vary: Accept-Language` prezent. | Medium |
| API-075 | **Cache Headers** | Verifică response | `Cache-Control`, `ETag` prezente unde aplicabil. | Medium |

---

## 6. SECȚIUNEA D: Teste Integrare End-to-End

### D1. Flux Complet Articol

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| E2E-001 | **Create → Publish → View** | 1. Admin: crează articol. 2. Publish. 3. Frontend: verifică pe homepage | Articol vizibil pe homepage cu toate detaliile. | Critical |
| E2E-002 | **Edit → Update → Verify** | 1. Admin: edit titlu articol. 2. Save. 3. Frontend: refresh articol | Titlu actualizat vizibil. | Critical |
| E2E-003 | **Delete → Verify Gone** | 1. Admin: delete articol. 2. Frontend: accesează URL direct | 404 pe frontend, nu în liste. | Critical |

### D2. Flux Traduceri

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| E2E-004 | **Create RO → Add EN → Verify** | 1. Crează articol în RO. 2. Adaugă traducere EN. 3. Verifică ambele limbi pe frontend | Conținut diferit per limbă. | Critical |
| E2E-005 | **API Locale Headers** | 1. GET article cu `Accept-Language: ro`. 2. GET cu `en`. | Response-uri diferite. | Critical |
| E2E-006 | **Fallback Translation** | 1. Articol doar în RO. 2. Frontend în EN | Fallback la RO fără erori. | High |

### D3. Flux Imagini

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| E2E-007 | **Upload → Attach → Display** | 1. Upload imagine. 2. Attach la articol ca featured. 3. Verifică pe frontend | Imagine corectă pe card și în articol. | Critical |
| E2E-008 | **Thumbnail Generation** | 1. Upload imagine nouă. 2. Verifică CDN | Toate 10 thumbnail profiles disponibile. | High |
| E2E-009 | **Image Crop → Verify** | 1. Crop imagine. 2. Verifică frontend | Crop aplicat în thumbnail afișat. | Medium |

### D4. Flux Categorii

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| E2E-010 | **Create Category → Add Articles → View** | 1. Crează categorie. 2. Crează articole în ea. 3. Verifică pagina categoriei | Articole afișate în categorie. | High |
| E2E-011 | **Category On Front Page** | 1. Toggle onFrontPage=true. 2. Verifică homepage | Secțiune categorie pe homepage. | High |
| E2E-012 | **Deactivate Category** | 1. Status=inactive. 2. Verifică frontend | Categorie nu apare în meniu. | High |

### D5. Flux Important Articles

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| E2E-013 | **Add to Featured → Verify Homepage** | 1. Adaugă articol la importante. 2. Refresh homepage | Articol în hero grid. | Critical |
| E2E-014 | **Reorder → Verify** | 1. Reordonează în admin. 2. Refresh homepage | Ordine nouă reflectată. | High |
| E2E-015 | **Remove from Featured** | 1. Elimină din importante. 2. Refresh | Articol nu mai e în hero. | High |

### D6. Flux Live Text

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| E2E-016 | **Create Live → Start → View** | 1. Crează live text. 2. Status=live. 3. Verifică pagina publică | Live text vizibil cu badge LIVE. | High |
| E2E-017 | **Post Update → Realtime** | 1. Live text deschis pe frontend. 2. Admin adaugă post | Post apare fără refresh (SSE). | Critical |
| E2E-018 | **End Live → Badge Update** | 1. Status=ended. 2. Refresh frontend | Badge schimbat din LIVE în ENDED. | High |

### D7. Flux Short Links

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| E2E-019 | **Create Link → Redirect** | 1. Crează short link. 2. Accesează /s/{code} | Redirect 301 la target URL. | High |
| E2E-020 | **Click Tracking** | 1. Click pe short link. 2. Verifică stats în admin | Click înregistrat în statistici. | High |

### D8. Flux Căutare

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| E2E-021 | **Search Article Title** | 1. Crează articol cu titlu unic. 2. Search pe frontend | Articol găsit în rezultate. | High |
| E2E-022 | **Search Content** | 1. Articol cu keyword unic în content. 2. Search | Articol găsit (Elasticsearch). | High |

### D9. Flux Autentificare

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| E2E-023 | **Login → Session → Actions** | 1. Login admin. 2. Create article. 3. Refresh. 4. Edit article | Session persistă, toate acțiunile funcționează. | Critical |
| E2E-024 | **Logout → Protected Route** | 1. Logout. 2. Direct navigate to /admin/articles | Redirect la login. | Critical |

### D10. Flux Archive

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| E2E-025 | **View Archive by Year** | 1. Verifică `/archive/2024`. 2. Click pe lună | Navigare corectă, articole afișate. | High |
| E2E-026 | **Archive Calendar** | 1. Verifică calendar în arhiva lunii. 2. Click pe zi cu articole | Articole din ziua respectivă afișate. | Medium |

### D11. Edge Cases Integration

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| E2E-027 | **Concurrent Edit Detection** | 1. Deschide articol în 2 browsere. 2. Edit în ambele | Lock system previne conflict. | High |
| E2E-028 | **API Down → Frontend Error** | 1. Oprește backend. 2. Refresh frontend | Error handling graceful, mesaj utilizator. | High |
| E2E-029 | **Cache Invalidation** | 1. Edit articol. 2. Verifică că cache-ul frontend se revalidează | Conținut nou vizibil (ISR revalidation). | High |

### D12. Multi-browser/Device

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| E2E-030 | **Chrome Desktop** | Parcurge flux standard | Funcțional complet. | Critical |
| E2E-031 | **Firefox Desktop** | Parcurge flux standard | Funcțional complet. | High |
| E2E-032 | **Safari Desktop** | Parcurge flux standard | Funcțional complet. | High |
| E2E-033 | **Mobile Chrome** | Parcurge flux standard pe mobil | Responsive, touch funcțional. | High |
| E2E-034 | **Mobile Safari** | Parcurge flux pe iOS | Responsive, funcțional. | High |
| E2E-035 | **Tablet** | Parcurge flux pe tablet | Layout tablet corect. | Medium |

---

## 7. SECȚIUNEA E: Teste Multilingv

### E1. Interface Language

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| ML-001 | **Admin UI în RO** | 1. `/ro/admin` | Labels UI în română. | High |
| ML-002 | **Admin UI în EN** | 1. `/en/admin` | Labels UI în engleză. | High |
| ML-003 | **Admin UI în RU** | 1. `/ru/admin` | Labels UI în rusă, chirilice corecte. | High |
| ML-004 | **Public UI RO** | 1. `/ro` | Tot UI-ul în română. | Critical |
| ML-005 | **Public UI EN** | 1. `/en` | Tot UI-ul în engleză. | Critical |
| ML-006 | **Public UI RU** | 1. `/ru` | Tot UI-ul în rusă. | Critical |

### E2. Content Translation

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| ML-007 | **Article Translation Save** | 1. Crează articol RO. 2. Edit în EN, add translation | Ambele versiuni salvate separat. | Critical |
| ML-008 | **Category Translation** | 1. Edit categorie în EN | Titlu tradus salvat. | High |
| ML-009 | **Tag Translation** | 1. Edit tag în EN | Nume tag tradus. | High |
| ML-010 | **Image Alt Translation** | 1. Edit alt text în EN | Alt tradus salvat. | Medium |

### E3. Slug Handling

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| ML-011 | **Romanian Diacritics in Slug** | 1. Titlu: "Știri și Afaceri" | Slug: "stiri-si-afaceri". | High |
| ML-012 | **Cyrillic in Slug** | 1. Titlu rusesc | Slug transliterat corect. | High |
| ML-013 | **Slug Cross-locale** | 1. Verifică că slug-ul e unic per locale sau global | Comportament consistent conform config. | High |

### E4. API Locale

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| ML-014 | **Accept-Language Header** | 1. API call cu header diferit | Traducere corectă returnată. | Critical |
| ML-015 | **Missing Translation Fallback** | 1. Request EN pentru articol doar RO | Fallback la RO, nu eroare. | High |
| ML-016 | **Author Not Translatable** | 1. Verifică autor în toate limbile | Same content (autori nu sunt translatable). | Medium |

### E5. URL Locale Prefix

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| ML-017 | **Navigation Preserves Locale** | 1. În /en, click pe categorie | URL rămâne cu prefix /en. | Critical |
| ML-018 | **Language Switch URL** | 1. Pe `/ro/politica/articol`, switch la EN | URL devine `/en/politica/articol`. | Critical |
| ML-019 | **Direct Locale URL** | 1. Accesează direct `/ru/` | Conținut în rusă. | Critical |
| ML-020 | **Invalid Locale** | 1. Accesează `/fr/` | 404 sau redirect la default locale. | High |

---

## 8. SECȚIUNEA F: Teste Securitate

### F1. Authentication Security

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| SEC-001 | **Brute Force Protection** | 1. 6 login attempts greșite | Rate limited după 5 încercări. | Critical |
| SEC-002 | **JWT Token Validation** | 1. Request cu token invalid | 401 Unauthorized. | Critical |
| SEC-003 | **Token Expiration** | 1. Folosește token expirat | 401, necesită refresh. | High |
| SEC-004 | **Session Hijacking Prevention** | 1. Copiază cookie în alt browser | Token bound la session. | High |

### F2. Authorization

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| SEC-005 | **RBAC Admin Only Endpoints** | 1. Request la /api/admin/stats ca user normal | 403 Forbidden. | Critical |
| SEC-006 | **Edit Own Article Only** | 1. Editor încearcă edit articol altcuiva | 403 sau blocat. | High |
| SEC-007 | **Delete Restricted** | 1. Non-admin încearcă delete | 403 Forbidden. | High |

### F3. Input Validation

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| SEC-008 | **XSS Prevention Content** | 1. Introduce `<script>alert(1)</script>` în content | Script sanitizat, nu execută. | Critical |
| SEC-009 | **XSS în Titlu** | 1. Titlu cu `<img onerror=alert(1)>` | HTML escaped. | Critical |
| SEC-010 | **SQL Injection** | 1. Search cu `'; DROP TABLE--` | Query escaped, nu eroare DB. | Critical |
| SEC-011 | **Path Traversal** | 1. Image path cu `../../etc/passwd` | Blocat, nu file disclosure. | Critical |

### F4. File Upload Security

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| SEC-012 | **Executable Upload** | 1. Upload .php sau .exe redenumit ca .jpg | Blocat, verificare MIME type. | Critical |
| SEC-013 | **File Size Limit** | 1. Upload fișier >10MB | Blocat cu eroare. | High |
| SEC-014 | **Malicious Image** | 1. Upload imagine cu payload în metadata | Metadata sanitizată. | High |

### F5. API Security

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| SEC-015 | **CORS Strict** | 1. Request din domeniu neautorizat | CORS blocat. | High |

---

## 9. SECȚIUNEA G: Teste Performance & UX

### G1. Load Times

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| PERF-001 | **Homepage LCP** | 1. Lighthouse pe homepage | LCP < 2.5s. | Critical |
| PERF-002 | **Article Page LCP** | 1. Lighthouse pe articol | LCP < 2.5s. | Critical |
| PERF-003 | **Admin Dashboard Load** | 1. Măsoară încărcare dashboard | < 3s initial render. | High |
| PERF-004 | **Articles List Load** | 1. Listă cu 100+ articole | < 3s populate. | High |
| PERF-005 | **Search Response** | 1. Măsoară timp căutare | < 500ms rezultate. | High |

### G2. Core Web Vitals

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| PERF-006 | **INP (Interaction)** | 1. Lighthouse/PageSpeed | INP < 200ms. | High |
| PERF-007 | **CLS (Layout Shift)** | 1. Verifică CLS | CLS < 0.1. | High |
| PERF-008 | **Image Optimization** | 1. Verifică imagini | WebP format, lazy loading. | High |

### G3. Responsive & Accessibility

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| PERF-009 | **Mobile Lighthouse** | 1. Lighthouse mobile | Score >= 90. | High |
| PERF-010 | **Keyboard Navigation** | 1. Tab prin pagină | Focus vizibil, ordine logică. | High |
| PERF-011 | **Screen Reader** | 1. Test cu screen reader | Alt text, ARIA labels corecte. | Medium |
| PERF-012 | **Color Contrast** | 1. Verifică contrast | WCAG AA compliant. | Medium |

### G4. Loading States

| ID | Test | Pași | Rezultat Așteptat | Prioritate |
|----|------|------|-------------------|------------|
| PERF-013 | **Skeleton Loading** | 1. Refresh cu throttled network | Skeleton/spinner vizibil. | Medium |
| PERF-014 | **Button Loading State** | 1. Submit form | Buton disabled cu indicator. | Medium |
| PERF-015 | **Progressive Loading** | 1. Verifică imagini | Placeholder → low-res → full image. | Medium |

---

## 10. Matrice de Prioritizare

### Sumar Teste pe Prioritate

| Prioritate | Frontend | Admin | Crop | LiveText | API | E2E | ML | Security | Perf | Total |
|------------|----------|-------|------|----------|-----|-----|----|---------|----|-------|
| **Critical** | 18 | 25 | 4 | 18 | 20 | 12 | 8 | 8 | 2 | 115 |
| **High** | 45 | 60 | 20 | 50 | 40 | 18 | 10 | 6 | 10 | 259 |
| **Medium** | 18 | 28 | 8 | 28 | 12 | 4 | 2 | 1 | 3 | 104 |
| **Low** | 4 | 7 | 4 | 8 | 3 | 1 | 0 | 0 | 0 | 27 |
| **Total** | 85 | 120 | 36 | 104 | 75 | 35 | 20 | 15 | 15 | **505** |

### Detaliu Teste LiveText Management

| Sub-secțiune | Teste | Critical | High | Medium | Low |
|--------------|-------|----------|------|--------|-----|
| B12. Bază | 7 | 1 | 4 | 2 | 0 |
| B12a. Creare/Config | 9 | 3 | 5 | 1 | 0 |
| B12b. Status | 8 | 2 | 3 | 3 | 0 |
| B12c. Posturi | 16 | 1 | 8 | 6 | 1 |
| B12d. Colaboratori | 10 | 0 | 7 | 2 | 1 |
| B12e. Sport Match | 13 | 2 | 7 | 4 | 0 |
| B12f. Scor/Stats | 10 | 2 | 4 | 3 | 1 |
| B12g. Match Events | 14 | 1 | 11 | 2 | 0 |
| B12h. Analytics | 7 | 1 | 3 | 2 | 1 |
| B12i. Reacții | 5 | 0 | 0 | 4 | 1 |
| B12j. Templates | 5 | 0 | 1 | 2 | 2 |
| **Total LiveText** | **104** | **13** | **53** | **31** | **7** |

### Ordinea Execuției Recomandate

1. **Critical** - Trebuie să treacă 100% înainte de release
2. **High** - Minim 95% trebuie să treacă
3. **Medium** - Minim 80% trebuie să treacă
4. **Low** - Nice to have

---

## 11. Raport de Execuție

### Template Raport

```markdown
## Raport Execuție Teste

**Data**: ___________
**Mediu**: Development / Staging / Production
**Tester**: ___________
**Browser**: Chrome / Firefox / Safari / Mobile

### Rezultate pe Secțiune

| Secțiune | Total | Pass | Fail | Blocked | Skip |
|----------|-------|------|------|---------|------|
| A: Frontend Public | 85 | | | | |
| B: Admin Panel | 120 | | | | |
| C: API Backend | 75 | | | | |
| D: Integrare E2E | 35 | | | | |
| E: Multilingv | 20 | | | | |
| F: Securitate | 15 | | | | |
| G: Performance | 15 | | | | |
| **TOTAL** | 365 | | | | |

### Defecte Critice

| ID Test | Descriere | Severitate | Status |
|---------|-----------|------------|--------|
| | | | |

### Defecte Non-Critice

| ID Test | Descriere | Severitate | Status |
|---------|-----------|------------|--------|
| | | | |

### Note și Observații

_________________________________

### Semnătură

**Tester**: _______________ **Data**: ___________
```

---

## Appendix: Comenzi Utile

### Verificare Servicii

```bash
# Backend
curl http://127.0.0.1:8081/api/health
symfony server:status

# Frontend
curl http://localhost:3005
pm2 status

# Database
PGPASSWORD='xxx' psql -h localhost -U deschide_user -d deschide_news -c "\dt"

# Redis
redis-cli -n 1 PING

# Elasticsearch
curl -k https://localhost:9200/_cluster/health -u elastic:xxx
```

### Generare Token JWT

```bash
cd /var/www/deschide_news_app/apps/backend
symfony console app:test:jwt-token admin
```

### Clear Cache

```bash
# Backend
symfony console cache:clear

# Frontend
rm -rf .next && pnpm build
```

---

**Menținut de**: QA Team
**Review Cycle**: Lunar
**Ultima Actualizare**: 2025-12-11
