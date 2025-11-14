# 📋 Strategie IMPORT COMPLET Categorii - Toate Sursele

## 🎯 Obiectiv
Import COMPLET al TUTUROR categoriilor unice din toate cele 3 surse de date, consolidate într-o singură structură multilingvă.

---

## 📊 LISTA COMPLETĂ - Toate Categoriile Unice

Am identificat **30 categorii unice** din analiza tuturor surselor:

| # | Categorie (RO)    | Newscoop | Beta | Webflow | Tip               | Prioritate |
|---|-------------------|----------|------|---------|-------------------|------------|
| 1 | Politic           | ✓        | ✓    | ✓       | Core              | 🔴 ESENȚIALĂ |
| 2 | Social            | ✓        | ✓    | ✓       | Core              | 🔴 ESENȚIALĂ |
| 3 | Economic          | ✓        | ✓    | ✓       | Core              | 🔴 ESENȚIALĂ |
| 4 | Externe           | ✓        | ✓    | ✓       | Core              | 🔴 ESENȚIALĂ |
| 5 | Editorial         | ✓        | ✓    | ✓       | Core              | 🔴 ESENȚIALĂ |
| 6 | Investigații      | ✓        | ✓    | ×       | Core              | 🔴 ESENȚIALĂ |
| 7 | Cultură           | ✓        | ✓    | ✓       | Core              | 🔴 ESENȚIALĂ |
| 8 | Sport             | ✓        | ✓    | ✓       | Core              | 🔴 ESENȚIALĂ |
| 9 | Opinii            | ✓        | ✓    | ✓       | Core              | 🔴 ESENȚIALĂ |
| 10| Video             | ✓        | ×    | ×       | Media             | 🟡 MEDIE     |
| 11| Special           | ✓        | ×    | ×       | Specială          | 🟡 MEDIE     |
| 12| Interviu          | ✓        | ×    | ×       | Format            | 🟡 MEDIE     |
| 13| Anti-Fake         | ✓        | ×    | ✓       | Specială          | 🟠 IMPORTANTĂ|
| 14| România           | ×        | ✓    | ✓       | Geografică        | 🟠 IMPORTANTĂ|
| 15| Transnistria      | ✓        | ×    | ✓       | Geografică        | 🟠 IMPORTANTĂ|
| 16| Alegeri           | ✓        | ×    | ✓       | Tematică          | 🟠 IMPORTANTĂ|
| 17| Advertorial       | ✓        | ×    | ✓       | Comercială        | 🟢 OPȚIONALĂ |
| 18| Dialog deschis    | ×        | ×    | ✓       | Format            | 🟡 MEDIE     |
| 19| Bloguri           | ×        | ✓    | ×       | Format            | 🟢 OPȚIONALĂ |
| 20| Social Media      | ×        | ✓    | ×       | Format            | 🟢 OPȚIONALĂ |
| 21| No Comment        | ×        | ✓    | ×       | Format            | 🟡 MEDIE     |
| 22| Divertisment      | ×        | ✓    | ×       | Core?             | 🟡 MEDIE     |
| 23| Ucraina           | ×        | ✓    | ×       | Geografică        | 🟡 MEDIE     |
| 24| Live              | ×        | ✓    | ×       | Format            | 🟡 MEDIE     |
| 25| România 2019      | ✓        | ×    | ×       | Temporară/Arhivă  | ⚪ ARHIVĂ    |
| 26| 30 noiembrie      | ×        | ✓    | ×       | Temporară/Arhivă  | ⚪ ARHIVĂ    |
| 27| 14 iunie          | ×        | ✓    | ×       | Temporară/Arhivă  | ⚪ ARHIVĂ    |
| 28| Sondaj Imas       | ×        | ✓    | ×       | Temporară/Arhivă  | ⚪ ARHIVĂ    |
| 29| RSS               | ×        | ✓    | ×       | Tehnică           | ⛔ EXCLUDE   |
| 30| Sitemap           | ×        | ✓    | ×       | Tehnică           | ⛔ EXCLUDE   |

---

## 🔍 ANALIZA DETALIATĂ - Toate Categoriile

### 🔴 Categorii CORE (9 categorii) - ESENȚIALE

Aceste categorii apar în majoritatea surselor și sunt fundamentale pentru orice site de știri:

| # | RO            | EN              | RU              | Slug         | Sources       |
|---|---------------|-----------------|-----------------|--------------|---------------|
| 1 | Politic       | Political       | Политика        | politic      | N + B + W     |
| 2 | Social        | Social          | Общество        | social       | N + B + W     |
| 3 | Economic      | Business        | Экономика       | economic     | N + B + W     |
| 4 | Externe       | International   | В мире          | externe      | N + B + W     |
| 5 | Editorial     | Editorials      | Мнения          | editorial    | N + B + W     |
| 6 | Investigații  | Investigations  | Расследования   | investigatii | N + B         |
| 7 | Cultură       | Culture         | Культура        | cultura      | N + B + W     |
| 8 | Sport         | Sport           | Спорт           | sport        | N + B + W     |
| 9 | Opinii        | Opinions        | Мнения          | opinii       | N + B + W     |

**Decizie:** ✅ **IMPORT OBLIGATORIU** - Toate 9

---

### 🟠 Categorii SPECIALE/TEMATICE (4 categorii) - IMPORTANTE

Categorii specifice contextului local sau teme importante:

| # | RO            | EN              | RU              | Slug         | Sources | Descriere |
|---|---------------|-----------------|-----------------|--------------|---------|-----------|
| 13| Anti-Fake     | Anti-Fake       | Анти-фейк       | anti-fake    | N + W   | Fact-checking, luptă cu fake news |
| 14| România       | Romania         | Румыния         | romania      | B + W   | Știri despre România |
| 15| Transnistria  | Transnistria    | Приднестровье   | transnistria | N + W   | Problemă regională importantă |
| 16| Alegeri       | Elections       | Выборы          | alegeri      | N + W   | Campanii electorale (recurente) |

**Decizie:** ✅ **IMPORT RECOMANDAT** - Toate 4 (context local important)

---

### 🟡 Categorii MEDIA/FORMAT (7 categorii) - MEDIE prioritate

Categorii care descriu formatul conținutului, nu tema:

| # | RO              | EN              | RU                | Slug           | Sources | Observații |
|---|-----------------|-----------------|-------------------|----------------|---------|------------|
| 10| Video           | Video           | Видео             | video          | N       | Format media - poate fi tag |
| 12| Interviu        | Interview       | Интервью          | interviu       | N       | Format - poate merge în "Dialog deschis" |
| 18| Dialog deschis  | Open Dialog     | Открытый диалог   | dialog-deschis | W       | Interviuri, dezbateri |
| 19| Bloguri         | Blogs           | Блоги             | bloguri        | B       | Format specific - poate fi tag |
| 20| Social Media    | Social Media    | Социальные сети   | social-media   | B       | Agregator content - poate fi tag |
| 21| No Comment      | No Comment      | Без комментариев  | no-comment     | B       | Rubrica fără comentariu editorial |
| 24| Live            | Live            | Прямой эфир       | live           | B       | Reportaj live - poate fi tag/format |

**Opțiuni:**
- 🅰️ **Import ca categorii separate** (maxim flexibilitate)
- 🅱️ **Import doar "Dialog deschis" + "Video"** (consolidare)
- 🅲️ **Transform în TAGS** (nu categorii)

**Recomandare:** 🅱️ Import doar **Dialog deschis** + **Video** (importante), restul → tags

---

### 🟡 Categorii GEOGRAFICE ADIȚIONALE (1 categorie)

| # | RO       | EN      | RU       | Slug    | Sources | Observații |
|---|----------|---------|----------|---------|---------|------------|
| 23| Ucraina  | Ukraine | Украина  | ucraina | B       | Context regional important (război) |

**Opțiuni:**
- 🅰️ **Import ca categorie separată**
- 🅱️ **Subcategorie sub "Externe"** (ierarhie)
- 🅲️ **Tag geografic**

**Recomandare:** 🅰️ Import ca **categorie separată** (context actual important)

---

### 🟢 Categorii OPȚIONALE/DUBIOASE (3 categorii)

| # | RO            | EN              | RU              | Slug         | Sources | Justificare |
|---|---------------|-----------------|-----------------|--------------|---------|-------------|
| 11| Special       | Special         | Специальный     | special      | N       | Prea generic - poate fi tag |
| 17| Advertorial   | Advertorial     | Advertorial     | advertorial  | N + W   | Conținut sponsorizat - discutabil |
| 22| Divertisment  | Entertainment   | Развлечения     | divertisment | B       | Poate merge în "Cultură" |

**Opțiuni:**
- Import toate
- Import doar Advertorial (există în Webflow)
- Exclude toate

**Recomandare:** Import doar **Advertorial** (există în Webflow, probabil folosit)

---

### ⚪ Categorii TEMPORARE/ARHIVĂ (4 categorii) - Doar pentru import istoric

Categorii legate de evenimente specifice (nu mai sunt relevante):

| # | RO             | Slug         | Sources | Eveniment |
|---|----------------|--------------|---------|-----------|
| 25| România 2019   | romania-2019 | N       | Alegeri România 2019 |
| 26| 30 noiembrie   | 30-noiembrie | B       | Referendum 30 noiembrie (probabil 2014) |
| 27| 14 iunie       | 14-iunie     | B       | Alegeri 14 iunie (locale?) |
| 28| Sondaj Imas    | sondaj-imas  | B       | Sondaje specifice |

**Decizie:**
- ✅ **Import pentru arhivă** (dacă importăm articole vechi cu aceste categorii)
- ⛔ **Exclude** (dacă nu importăm conținut arhivat)
- 🔄 **Redirect** către categorii actuale (Romania → România, etc)

**Recomandare:** ✅ Import cu **flag "archived"** pentru compatibilitate istorică

---

### ⛔ Categorii TEHNICE (2 categorii) - EXCLUDE

| # | RO      | Slug    | Sources | Motiv |
|---|---------|---------|---------|-------|
| 29| RSS     | rss     | B       | Feed tehnic, nu categorie reală |
| 30| Sitemap | sitemap | B       | Pagină tehnică, nu categorie |

**Decizie:** ⛔ **EXCLUDE** definitiv

---

## 🎨 PROPUNERI DE STRATEGIE - Import Complet

### Opțiunea A: IMPORT TOTAL - Toate categoriile (26 categorii)

**Include:**
- ✅ 9 Core
- ✅ 4 Speciale/Tematice
- ✅ 7 Media/Format
- ✅ 1 Geografică (Ucraina)
- ✅ 3 Opționale
- ✅ 4 Arhivă (cu flag)
- ⛔ 0 Tehnice (exclude)

**Total: 28 categorii**

**Avantaje:**
- ✅ Compatibilitate 100% cu toate sursele
- ✅ Maxim de flexibilitate
- ✅ Păstrează toată istoria

**Dezavantaje:**
- ❌ Multe categorii (confuzie pentru utilizatori)
- ❌ Overlap între categorii (Video vs Dialog deschis vs Interviu)
- ❌ Complexitate în administrare

---

### Opțiunea B: IMPORT SELECTIV - Core + Important + Câteva Format (20 categorii)

**Include:**
- ✅ 9 Core
- ✅ 4 Speciale/Tematice
- ✅ 3 Media/Format (Video, Dialog deschis, No Comment)
- ✅ 1 Geografică (Ucraina)
- ✅ 1 Opțională (Advertorial)
- ✅ 4 Arhivă (cu flag)
- ⛔ 6 Exclude (restul format → tags)

**Total: 22 categorii**

**Avantaje:**
- ✅ Acoperire bună a tuturor tipurilor de conținut
- ✅ Nu prea multe categorii
- ✅ Păstrează compatibilitatea istorică

**Dezavantaje:**
- ⚠️ Unele categorii Beta (bloguri, social media) → tags
- ⚠️ Necesită mapping pentru articole vechi

---

### Opțiunea C: IMPORT CURAT - Core + Speciale + Minimal (18 categorii)

**Include:**
- ✅ 9 Core
- ✅ 4 Speciale/Tematice
- ✅ 2 Media/Format (Video, Dialog deschis)
- ✅ 1 Geografică (Ucraina)
- ✅ 1 Opțională (Advertorial)
- ✅ 4 Arhivă (cu flag)
- ⛔ 9 Exclude (format → tags)

**Total: 21 categorii active + 4 arhivate**

**Avantaje:**
- ✅ Structură curată, logică
- ✅ Separare clară: categorii tematice vs tags format
- ✅ Ușor de navigat pentru utilizatori

**Dezavantaje:**
- ⚠️ Unele categorii Beta/Newscoop → tags (bloguri, interviu, etc)

---

### Opțiunea D: IMPORT MINIM - Doar Webflow + Investigații + Ucraina (16 categorii)

**Include:**
- ✅ 14 Webflow (toate)
- ✅ 1 din Newscoop (Investigații - lipsește în Webflow)
- ✅ 1 din Beta (Ucraina - context actual)
- ⛔ 14 Exclude (arhivă, tehnice, format)

**Total: 16 categorii**

**Avantaje:**
- ✅ Foarte curat (doar esențiale + context actual)
- ✅ Bazat pe structura live actuală (Webflow)
- ✅ Ușor de administrat

**Dezavantaje:**
- ❌ Pierde compatibilitate cu arhivă (Video, Interviu, Special, etc.)
- ❌ Articole vechi vor avea categorii "lipsa"

---

## 🎯 TABEL COMPARATIV - 4 Opțiuni

| Categorie          | Opțiunea A | Opțiunea B | Opțiunea C | Opțiunea D |
|--------------------|------------|------------|------------|------------|
| **Core (9)**       | ✅ Toate   | ✅ Toate   | ✅ Toate   | ✅ Toate   |
| **Speciale (4)**   | ✅ Toate   | ✅ Toate   | ✅ Toate   | ✅ Toate   |
| Video              | ✅         | ✅         | ✅         | ⛔         |
| Interviu           | ✅         | ⛔ → tag   | ⛔ → tag   | ⛔         |
| Dialog deschis     | ✅         | ✅         | ✅         | ✅ (Webflow)|
| Bloguri            | ✅         | ⛔ → tag   | ⛔ → tag   | ⛔         |
| Social Media       | ✅         | ⛔ → tag   | ⛔ → tag   | ⛔         |
| No Comment         | ✅         | ✅         | ⛔ → tag   | ⛔         |
| Live               | ✅         | ⛔ → tag   | ⛔ → tag   | ⛔         |
| Special            | ✅         | ⛔ → tag   | ⛔ → tag   | ⛔         |
| Advertorial        | ✅         | ✅         | ✅         | ✅ (Webflow)|
| Divertisment       | ✅         | ⛔ → tag   | ⛔ → tag   | ⛔         |
| Ucraina            | ✅         | ✅         | ✅         | ✅         |
| **Arhivă (4)**     | ✅ flag    | ✅ flag    | ✅ flag    | ⛔         |
| **Tehnice (2)**    | ⛔         | ⛔         | ⛔         | ⛔         |
| **TOTAL**          | **28**     | **22**     | **21**     | **16**     |

---

## 📋 LISTA COMPLETĂ - Toate 28 Categorii cu Traduceri

### Categorii Active (24)

| # | Prioritate | RO              | EN              | RU                | Slug           | Type      |
|---|------------|-----------------|-----------------|-------------------|----------------|-----------|
| 1 | 🔴         | Politic         | Political       | Политика          | politic        | Core      |
| 2 | 🔴         | Social          | Social          | Общество          | social         | Core      |
| 3 | 🔴         | Economic        | Business        | Экономика         | economic       | Core      |
| 4 | 🔴         | Externe         | International   | В мире            | externe        | Core      |
| 5 | 🔴         | Editorial       | Editorials      | Мнения            | editorial      | Core      |
| 6 | 🔴         | Investigații    | Investigations  | Расследования     | investigatii   | Core      |
| 7 | 🔴         | Cultură         | Culture         | Культура          | cultura        | Core      |
| 8 | 🔴         | Sport           | Sport           | Спорт             | sport          | Core      |
| 9 | 🔴         | Opinii          | Opinions        | Мнения            | opinii         | Core      |
| 10| 🟡         | Video           | Video           | Видео             | video          | Media     |
| 11| 🟢         | Special         | Special         | Специальный       | special        | Special   |
| 12| 🟡         | Interviu        | Interview       | Интервью          | interviu       | Format    |
| 13| 🟠         | Anti-Fake       | Anti-Fake       | Анти-фейк         | anti-fake      | Special   |
| 14| 🟠         | România         | Romania         | Румыния           | romania        | Geographic|
| 15| 🟠         | Transnistria    | Transnistria    | Приднестровье     | transnistria   | Geographic|
| 16| 🟠         | Alegeri         | Elections       | Выборы            | alegeri        | Thematic  |
| 17| 🟢         | Advertorial     | Advertorial     | Advertorial       | advertorial    | Commercial|
| 18| 🟡         | Dialog deschis  | Open Dialog     | Открытый диалог   | dialog-deschis | Format    |
| 19| 🟡         | Bloguri         | Blogs           | Блоги             | bloguri        | Format    |
| 20| 🟡         | Social Media    | Social Media    | Социальные сети   | social-media   | Format    |
| 21| 🟡         | No Comment      | No Comment      | Без комментариев  | no-comment     | Format    |
| 22| 🟡         | Divertisment    | Entertainment   | Развлечения       | divertisment   | Core?     |
| 23| 🟡         | Ucraina         | Ukraine         | Украина           | ucraina        | Geographic|
| 24| 🟡         | Live            | Live            | Прямой эфир       | live           | Format    |

### Categorii Arhivă (4) - cu flag "isArchived: true"

| # | RO             | EN              | RU              | Slug         | Event Year |
|---|----------------|-----------------|-----------------|--------------|------------|
| 25| România 2019   | Romania 2019    | Румыния 2019    | romania-2019 | 2019       |
| 26| 30 noiembrie   | November 30     | 30 ноября       | 30-noiembrie | ~2014      |
| 27| 14 iunie       | June 14         | 14 июня         | 14-iunie     | Various    |
| 28| Sondaj IMAS    | IMAS Poll       | Опрос IMAS      | sondaj-imas  | Various    |

---

## 🎯 RECOMANDAREA MEA

### **Opțiunea C: Import Curat (21 active + 4 arhivate)** ✨

**Justificare:**
1. ✅ **Core complet** (9 categorii esențiale)
2. ✅ **Context local important** (Anti-Fake, România, Transnistria, Alegeri, Ucraina)
3. ✅ **Format principal** (Video, Dialog deschis - importante)
4. ✅ **Compatibilitate arhivă** (4 categorii cu flag pentru articole vechi)
5. ✅ **Curat și logic** (categorii = teme, tags = format/stil)
6. ⚠️ **Unele formate → tags** (Bloguri, Social Media, Interviu, Live, No Comment)

**Total: 21 categorii active + 4 arhivate = 25 categorii în total**

**Structura finală:**
- 9 Core (știri clasice)
- 5 Geografice/Tematice (context local)
- 2 Media/Format principale (Video, Dialog)
- 1 Comercială (Advertorial)
- 4 Arhivă (cu flag)

---

## ❓ Întrebări pentru Decizie Finală

### 1. Care opțiune preferi?
- 🅰️ Opțiunea A - TOTAL (28 categorii - tot)
- 🅱️ Opțiunea B - SELECTIV (22 categorii)
- 🅲️ Opțiunea C - CURAT (21 active + 4 arhivă) **← RECOMANDAT**
- 🅳️ Opțiunea D - MINIM (16 categorii - doar Webflow)

### 2. Categorii format: Categorii sau Tags?
- Video, Interviu, Bloguri, Social Media, Live, No Comment
- **Opțiune:** Categorii separate SAU Tags?

### 3. Categorii arhivă - ce facem?
- ✅ Import cu flag "isArchived" (pentru compatibilitate)
- ⛔ Nu importăm (articolele vechi vor avea categorie NULL)
- 🔄 Redirect către categorii noi (30noiembrie → Alegeri)

### 4. Ierarhie categorii?
- **Flat** (toate top-level)
- **Ierarhie:** Externe → România, Transnistria, Ucraina

### 5. Categorie "Divertisment"?
- Categorie separată
- Merge în "Cultură"
- Devine tag

---

## 📅 Next Steps

După ce îmi confirmi opțiunea, voi crea:

1. **CSV/JSON cu toate categoriile** (conform opțiunii alese)
2. **Comandă Symfony:** `app:import:categories --source=complete`
3. **Script de import** cu traduceri complete (ro, en, ru)
4. **Mapare pentru articole vechi** (din Newscoop/Beta)

**Aștept decizia ta!** 🚀

---

**Autor:** Claude Code
**Data:** 2025-11-08
**Status:** ⏳ Așteptăm decizie - Import Complet
