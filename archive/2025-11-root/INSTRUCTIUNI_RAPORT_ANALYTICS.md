# Instrucțiuni: Creare Raport Analytics (Google Analytics 4 + Bing)

## 📊 Obiectiv
Generare raport HTML pentru **deschide.md** cu date despre:
- Vizitatori unici
- Vizite totale (sesiuni)
- Pagini vizualizate
- Surse de trafic
- Dispozitive
- Locații geografice

**Perioada**: 1 Mai 2015 - 31 Octombrie 2025

---

## 🔧 PARTEA 1: Configurare Google Analytics 4 (GA4)

### Pasul 1: Crearea Service Account în Google Cloud Console

1. **Accesați Google Cloud Console**
   - URL: https://console.cloud.google.com/

2. **Selectați sau creați un proiect**
   - Click pe dropdown-ul de proiect (sus în navbar)
   - Selectați proiectul existent SAU
   - Click "New Project" → Introduceți numele → "Create"

3. **Activați Google Analytics Data API**
   - Navigați la: **APIs & Services** → **Library**
   - Căutați: "Google Analytics Data API"
   - Click pe rezultat → Click "**ENABLE**"

4. **Creați Service Account**
   - Navigați la: **APIs & Services** → **Credentials**
   - Click "**+ CREATE CREDENTIALS**" → "**Service account**"
   - Completați:
     - **Service account name**: `analytics-reporter`
     - **Service account ID**: (se generează automat)
     - **Description**: "Service account pentru rapoarte analytics"
   - Click "**CREATE AND CONTINUE**"
   - La "Grant this service account access to project":
     - Role: **Viewer** (sau lăsați gol)
   - Click "**CONTINUE**" → "**DONE**"

5. **Descărcați fișierul JSON cu credențiale**
   - În lista de Service Accounts, găsiți service account-ul creat
   - Click pe email-ul service account-ului
   - Tab "**KEYS**"
   - Click "**ADD KEY**" → "**Create new key**"
   - Selectați "**JSON**"
   - Click "**CREATE**"
   - **Fișierul JSON se va descărca automat** → Salvați-l în siguranță!

### Pasul 2: Adăugarea Service Account în Google Analytics

1. **Accesați Google Analytics**
   - URL: https://analytics.google.com/

2. **Selectați Property-ul pentru deschide.md**
   - Click pe butonul "Admin" (roată dințată, jos-stânga)
   - Selectați property-ul corect din dropdown

3. **Notați Property ID**
   - În pagina "Property Settings"
   - Veți vedea **Property ID** (format: 123456789)
   - **NOTAȚI acest ID** - îl veți folosi mai târziu

4. **Adăugați Service Account ca utilizator**
   - În "Admin" → "Property" → "**Property access management**"
   - Click "**+**" (Add users)
   - În "Email address", introduceți **email-ul service account-ului** din fișierul JSON
     - Format: `analytics-reporter@project-name.iam.gserviceaccount.com`
   - Role: **Viewer**
   - Click "**Add**"

---

## 🔧 PARTEA 2: Configurare Bing Webmaster Tools

### Pasul 1: Obținere API Key pentru Bing Webmaster Tools

1. **Accesați Bing Webmaster Tools**
   - URL: https://www.bing.com/webmasters

2. **Login** cu contul asociat cu deschide.md

3. **Accesați Settings**
   - Click pe iconița de setări (roată dințată) în partea dreaptă sus
   - Selectați "**API Access**" din meniu

4. **Generați API Key**
   - Click "**Generate API Key**"
   - **Copiați și salvați API Key-ul** (va arăta ca un string lung)
   - **IMPORTANT**: Nu veți mai putea vedea acest key după închiderea paginii!

5. **Notați Site URL**
   - Pe pagina principală Webmaster Tools
   - Veți vedea site-ul listat (ex: `https://deschide.md`)
   - **NOTAȚI URL-ul exact** cum apare în Bing Webmaster

---

## 🔧 PARTEA 3: Configurare Microsoft Clarity (opțional, dar recomandat)

### Pasul 1: Obținere Project ID

1. **Accesați Microsoft Clarity**
   - URL: https://clarity.microsoft.com/

2. **Login** cu contul Microsoft

3. **Selectați proiectul deschide.md**
   - Veți vedea proiectele în dashboard

4. **Găsiți Project ID**
   - Click pe proiect
   - În URL-ul din browser veți vedea:
     - `https://clarity.microsoft.com/projects/view/{PROJECT_ID}/dashboard`
   - **NOTAȚI acest PROJECT_ID**

---

## 📝 PARTEA 4: Informații necesare pentru script

După parcurgerea pașilor de mai sus, veți avea:

### ✅ Google Analytics 4:
- [ ] **Fișier JSON** cu credențiale service account (ex: `service-account-key.json`)
- [ ] **Property ID** (ex: `123456789`)

### ✅ Bing Webmaster Tools:
- [ ] **API Key** (string lung)
- [ ] **Site URL** (ex: `https://deschide.md`)

### ✅ Microsoft Clarity (opțional):
- [ ] **Project ID** (GUID format)

---

## 🚀 PARTEA 5: Plasarea fișierelor pe server

1. **Încărcați fișierul JSON pe server**
   ```bash
   # Exemplu de locație:
   /home/radu/google-analytics-key.json
   ```

2. **Notați calea completă** către fișier

---

## 📧 PARTEA 6: Furnizare informații către mine

Trimiteți-mi următoarele informații:

```
1. Google Analytics 4:
   - Property ID: _________________
   - Cale către fișierul JSON: _________________

2. Bing Webmaster Tools:
   - API Key: _________________
   - Site URL: _________________

3. Microsoft Clarity (opțional):
   - Project ID: _________________
```

---

## 🎯 Ce va genera scriptul

Odată ce am aceste informații, voi crea un script Python care va genera un **raport HTML** cu:

### Metrici Google Analytics:
- Vizitatori unici
- Sesiuni totale
- Pagini vizualizate
- Durată medie sesiune
- Rata de respingere
- Grafice pe luni/ani
- Top pagini
- Surse de trafic
- Dispozitive (desktop/mobile/tablet)
- Țări de proveniență

### Metrici Bing Webmaster:
- Impresii în căutare
- Click-uri
- Click-through rate (CTR)
- Poziție medie
- Top query-uri de căutare

### Metrici Microsoft Clarity (dacă e configurat):
- Heatmaps summary
- Session recordings count
- Rage clicks
- Dead clicks

---

## ⚠️ Note importante:

1. **Securitate**:
   - Nu distribuiți fișierul JSON sau API keys public
   - Păstrați-le în locații sigure pe server
   - Setați permisiuni restrictive: `chmod 600 google-analytics-key.json`

2. **Limitări API**:
   - Google Analytics: 10,000 cereri/zi
   - Bing Webmaster: Rate limits aplică, dar sunt generoase

3. **Perioada lungă**:
   - Pentru perioada 2015-2025 (10 ani), datele vor fi agreate lunar pentru performanță
   - Raportul va genera grafice interactive

---

## 📞 Suport

Dacă întâmpinați probleme la oricare din pași, opriți-vă și cereți asistență înainte de a continua.

---

**Data creării**: 04 Noiembrie 2025
**Versiune**: 1.0
