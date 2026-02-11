# Plan de Testare Manuală - Managementul Articolelor

**Versiune:** 1.0
**Data:** 17 Decembrie 2025
**Echipă:** Editori Deschide.md
**Durată estimată:** 4-6 ore pentru testare completă

---

## Obiective

Acest plan de testare are ca scop verificarea completă a funcționalităților de management al articolelor, pentru a asigura că jurnaliștii au la dispoziție un instrument puternic și funcțional pentru publicarea profesionistă a materialelor.

---

## Cerințe Preliminare

### Acces necesar
- [ ] Cont de editor/administrator în panoul admin
- [ ] Browser modern (Chrome, Firefox, Safari, Edge)
- [ ] Conexiune la internet stabilă
- [ ] Minim 5 imagini de test (diverse formate: JPG, PNG, WebP)
- [ ] Un video YouTube pentru testare embed

### URL-uri de acces
| Mediu | Admin Panel | Frontend Public |
|-------|-------------|-----------------|
| **Development** | http://localhost:3005/ro/admin | http://localhost:3005/ro |
| **Staging** | https://staging.deschide.md/ro/admin | https://staging.deschide.md |
| **Production** | https://deschide.md/ro/admin | https://deschide.md |

### Credențiale test
- **Email:** [furnizat de administrator]
- **Parolă:** [furnizat de administrator]

---

## Secțiunea 1: Autentificare și Navigare

### 1.1 Login Admin Panel

| # | Test | Pași | Rezultat Așteptat | Status |
|---|------|------|-------------------|--------|
| 1.1.1 | Login valid | 1. Navighează la /ro/admin<br>2. Introdu email valid<br>3. Introdu parola corectă<br>4. Click "Login" | Redirect la Dashboard | ☐ |
| 1.1.2 | Login invalid | 1. Introdu email greșit<br>2. Click "Login" | Mesaj eroare "Invalid credentials" | ☐ |
| 1.1.3 | Câmpuri obligatorii | 1. Lasă câmpurile goale<br>2. Click "Login" | Validare "Field required" | ☐ |
| 1.1.4 | Sesiune persistentă | 1. Login<br>2. Închide tab-ul<br>3. Redeschide /ro/admin | Utilizator rămâne autentificat | ☐ |
| 1.1.5 | Logout | 1. Click pe avatar<br>2. Click "Logout" | Redirect la pagina login | ☐ |

### 1.2 Navigare Admin Panel

| # | Test | Pași | Rezultat Așteptat | Status |
|---|------|------|-------------------|--------|
| 1.2.1 | Sidebar navigare | Click pe fiecare item din sidebar | Pagina corespunzătoare se încarcă | ☐ |
| 1.2.2 | Dashboard quicklinks | Click pe "Articol Nou" din Dashboard | Modal creare articol se deschide | ☐ |
| 1.2.3 | Breadcrumb | Verifică breadcrumb pe fiecare pagină | Navigare corectă înapoi | ☐ |
| 1.2.4 | Responsive sidebar | Redimensionează fereastra < 768px | Sidebar se ascunde/hamburger menu | ☐ |

---

## Secțiunea 2: Crearea Articolelor

### 2.1 Accesare Formular Creare

| # | Test | Pași | Rezultat Așteptat | Status |
|---|------|------|-------------------|--------|
| 2.1.1 | Modal din Articles | 1. /ro/admin/articles<br>2. Click "Create Article" | Modal se deschide | ☐ |
| 2.1.2 | Modal din Dashboard | 1. /ro/admin<br>2. Click "Articol Nou" | Modal se deschide | ☐ |
| 2.1.3 | URL direct cu param | Navighează la /ro/admin/articles?create=true | Modal se deschide automat | ☐ |
| 2.1.4 | Închidere modal | 1. Deschide modal<br>2. Click "X" sau în afara modalului | Modal se închide | ☐ |

### 2.2 Completare Formular de Bază

| # | Test | Pași | Rezultat Așteptat | Status |
|---|------|------|-------------------|--------|
| 2.2.1 | Titlu articol | Introdu titlu "Test Articol Manual" | Slug se generează automat | ☐ |
| 2.2.2 | Slug personalizat | Modifică manual slug-ul | Slug acceptă doar caractere valide | ☐ |
| 2.2.3 | Caractere speciale în titlu | Introdu "Șțăîâ - Test!" | Slug convertește corect diacriticele | ☐ |
| 2.2.4 | Titlu lung (200+ caractere) | Introdu titlu foarte lung | Se afișează complet, slug trunchiat | ☐ |
| 2.2.5 | Selectare categorie | Deschide dropdown categorii | Lista categoriilor disponibile | ☐ |
| 2.2.6 | Selectare autor | Deschide dropdown autori | Lista autorilor disponibili | ☐ |
| 2.2.7 | Autori multipli | Adaugă 3 autori la articol | Toți autorii afișați, ordine corectă | ☐ |
| 2.2.8 | Ștergere autor | Click "X" pe autor adăugat | Autorul este eliminat | ☐ |
| 2.2.9 | Validare autor obligatoriu | Salvează fără autor | Eroare validare | ☐ |

### 2.3 Editor Lead/Chapeau

| # | Test | Pași | Rezultat Așteptat | Status |
|---|------|------|-------------------|--------|
| 2.3.1 | Scriere text simplu | Scrie 2-3 propoziții în Lead | Text afișat corect | ☐ |
| 2.3.2 | Formatare bold | Selectează text, click Bold | Text îngroșat | ☐ |
| 2.3.3 | Formatare italic | Selectează text, click Italic | Text înclinat | ☐ |
| 2.3.4 | Limită caractere | Scrie > 500 caractere | Contor afișează apropierea de limită | ☐ |
| 2.3.5 | Copy-paste din Word | Copy text formatat din Word | Text se curăță de formatare externă | ☐ |

### 2.4 Editor Conținut Principal (TinyMCE)

#### 2.4.1 Formatare Text

| # | Test | Pași | Rezultat Așteptat | Status |
|---|------|------|-------------------|--------|
| 2.4.1.1 | Heading H2 | Selectează text, alege "Titlu 2" | Heading H2 aplicat | ☐ |
| 2.4.1.2 | Heading H3 | Selectează text, alege "Titlu 3" | Heading H3 aplicat | ☐ |
| 2.4.1.3 | Bold (Ctrl+B) | Selectează text, Ctrl+B | Text bold | ☐ |
| 2.4.1.4 | Italic (Ctrl+I) | Selectează text, Ctrl+I | Text italic | ☐ |
| 2.4.1.5 | Underline (Ctrl+U) | Selectează text, Ctrl+U | Text subliniat | ☐ |
| 2.4.1.6 | Font size | Schimbă dimensiunea fontului | Text redimensionat | ☐ |
| 2.4.1.7 | Text color | Aplică culoare textului | Culoare aplicată | ☐ |
| 2.4.1.8 | Clear formatting | Aplică formatare, apoi "Clear" | Formatare eliminată | ☐ |

#### 2.4.2 Aliniere și Liste

| # | Test | Pași | Rezultat Așteptat | Status |
|---|------|------|-------------------|--------|
| 2.4.2.1 | Align left | Click Align Left | Text aliniat stânga | ☐ |
| 2.4.2.2 | Align center | Click Align Center | Text centrat | ☐ |
| 2.4.2.3 | Align right | Click Align Right | Text aliniat dreapta | ☐ |
| 2.4.2.4 | Bullet list | Click Bullet List | Listă cu puncte | ☐ |
| 2.4.2.5 | Numbered list | Click Numbered List | Listă numerotată | ☐ |
| 2.4.2.6 | Nested list | Tab în listă existentă | Sub-nivel creat | ☐ |
| 2.4.2.7 | List indent/outdent | Shift+Tab în listă | Nivel schimbat | ☐ |

#### 2.4.3 Blockquote și Citate

| # | Test | Pași | Rezultat Așteptat | Status |
|---|------|------|-------------------|--------|
| 2.4.3.1 | Inserare blockquote | Selectează text, click Blockquote | Citat formatat | ☐ |
| 2.4.3.2 | Blockquote cu autor | Adaugă `<cite>— Autor</cite>` | Autor afișat corect | ☐ |
| 2.4.3.3 | Ieșire din blockquote | Enter de 2 ori la final | Revenire la paragraf normal | ☐ |

#### 2.4.4 Tabele

| # | Test | Pași | Rezultat Așteptat | Status |
|---|------|------|-------------------|--------|
| 2.4.4.1 | Inserare tabel | Click Table > Insert Table 3x3 | Tabel creat | ☐ |
| 2.4.4.2 | Adăugare rând | Click dreapta > Insert Row | Rând nou adăugat | ☐ |
| 2.4.4.3 | Adăugare coloană | Click dreapta > Insert Column | Coloană nouă | ☐ |
| 2.4.4.4 | Ștergere rând | Click dreapta > Delete Row | Rând eliminat | ☐ |
| 2.4.4.5 | Merge cells | Selectează celule > Merge | Celule unite | ☐ |
| 2.4.4.6 | Table header | Setează primul rând ca header | Header stilizat diferit | ☐ |
| 2.4.4.7 | Redimensionare coloane | Drag marginea coloanei | Lățime ajustată | ☐ |

#### 2.4.5 Link-uri

| # | Test | Pași | Rezultat Așteptat | Status |
|---|------|------|-------------------|--------|
| 2.4.5.1 | Inserare link | Selectează text > Insert Link | Dialog link | ☐ |
| 2.4.5.2 | Link extern | Adaugă URL extern | Link cu target="_blank" | ☐ |
| 2.4.5.3 | Link intern | Adaugă URL intern site | Link fără target | ☐ |
| 2.4.5.4 | Editare link | Click pe link > Edit | Modificare posibilă | ☐ |
| 2.4.5.5 | Ștergere link | Click pe link > Remove | Link eliminat, text păstrat | ☐ |

#### 2.4.6 Inserare Media

| # | Test | Pași | Rezultat Așteptat | Status |
|---|------|------|-------------------|--------|
| 2.4.6.1 | YouTube via buton custom | Click "Inserează video YouTube" | Dialog URL YouTube | ☐ |
| 2.4.6.2 | YouTube URL valid | Introdu URL YouTube valid | Iframe inserat responsive | ☐ |
| 2.4.6.3 | YouTube URL invalid | Introdu URL invalid | Mesaj eroare | ☐ |
| 2.4.6.4 | YouTube shorts | Introdu URL YouTube Shorts | Convertit la embed normal | ☐ |
| 2.4.6.5 | Vimeo (dacă suportat) | Introdu URL Vimeo | Embed sau mesaj unsupported | ☐ |

#### 2.4.7 Quick Insert Menu

| # | Test | Pași | Rezultat Așteptat | Status |
|---|------|------|-------------------|--------|
| 2.4.7.1 | Deschidere Quick Insert | Click buton "Inserează rapid" | Meniu cu opțiuni | ☐ |
| 2.4.7.2 | Insert Separator | Selectează "Separator" | Linie orizontală inserată | ☐ |
| 2.4.7.3 | Insert YouTube | Selectează "Video YouTube" | Dialog YouTube | ☐ |
| 2.4.7.4 | Insert Image | Selectează "Imagine din articol" | Dialog imagini articol | ☐ |

#### 2.4.8 Source Code View

| # | Test | Pași | Rezultat Așteptat | Status |
|---|------|------|-------------------|--------|
| 2.4.8.1 | Vizualizare HTML | Click "Source code" | Modal cu HTML | ☐ |
| 2.4.8.2 | Editare HTML | Modifică HTML manual | Modificări salvate | ☐ |
| 2.4.8.3 | HTML invalid | Introdu HTML invalid | Sanitizare automată | ☐ |
| 2.4.8.4 | Script injection | Încearcă `<script>` tag | Tag eliminat | ☐ |

#### 2.4.9 Fullscreen Mode

| # | Test | Pași | Rezultat Așteptat | Status |
|---|------|------|-------------------|--------|
| 2.4.9.1 | Activare fullscreen | Click buton Fullscreen | Editor pe tot ecranul | ☐ |
| 2.4.9.2 | Editare în fullscreen | Scrie și formatează text | Funcționează normal | ☐ |
| 2.4.9.3 | Ieșire fullscreen | ESC sau click Fullscreen | Revenire la normal | ☐ |

#### 2.4.10 Find and Replace

| # | Test | Pași | Rezultat Așteptat | Status |
|---|------|------|-------------------|--------|
| 2.4.10.1 | Find text | Click Find > caută cuvânt | Cuvinte evidențiate | ☐ |
| 2.4.10.2 | Replace single | Find > Replace > Replace | O instanță înlocuită | ☐ |
| 2.4.10.3 | Replace all | Find > Replace > Replace All | Toate instanțele înlocuite | ☐ |
| 2.4.10.4 | Case sensitive | Activează case sensitive | Doar potriviri exacte | ☐ |

---

## Secțiunea 3: Managementul Imaginilor

### 3.1 Atașare Imagini la Articol

| # | Test | Pași | Rezultat Așteptat | Status |
|---|------|------|-------------------|--------|
| 3.1.1 | Deschidere selector | Click "Select from List" | Modal cu biblioteca de imagini | ☐ |
| 3.1.2 | Căutare imagine | Scrie în search box | Filtrare în timp real | ☐ |
| 3.1.3 | Selectare multiplă | Click pe mai multe imagini | Imagini selectate evidențiate | ☐ |
| 3.1.4 | Deselectare | Click pe imagine selectată | Imagine deselectată | ☐ |
| 3.1.5 | Paginare | Navighează între pagini | Imagini diferite pe fiecare pagină | ☐ |
| 3.1.6 | Atașare imagini | Click "Attach Selected (N)" | Imagini apar în articol | ☐ |
| 3.1.7 | Limită 100 imagini | Încearcă să atașezi > 100 | Mesaj limită atinsă | ☐ |

### 3.2 Upload Imagini Noi

| # | Test | Pași | Rezultat Așteptat | Status |
|---|------|------|-------------------|--------|
| 3.2.1 | Upload JPG | Click "Upload New" > selectează JPG | Imagine încărcată | ☐ |
| 3.2.2 | Upload PNG | Upload fișier PNG | Imagine încărcată | ☐ |
| 3.2.3 | Upload WebP | Upload fișier WebP | Imagine încărcată | ☐ |
| 3.2.4 | Upload multiplu | Selectează mai multe fișiere | Toate încărcate | ☐ |
| 3.2.5 | Fișier prea mare | Upload imagine > 10MB | Eroare dimensiune | ☐ |
| 3.2.6 | Format invalid | Upload PDF/DOC | Eroare format | ☐ |
| 3.2.7 | Drag & drop | Trage imagine în zonă | Upload inițiat | ☐ |

### 3.3 Gestionare Imagini Atașate

| # | Test | Pași | Rezultat Așteptat | Status |
|---|------|------|-------------------|--------|
| 3.3.1 | Reordonare drag | Drag imagine într-o altă poziție | Ordine schimbată | ☐ |
| 3.3.2 | Set as Featured | Hover > Click "Set as Featured" | Imagine devine featured | ☐ |
| 3.3.3 | Crop thumbnail | Hover > Click "Crop thumbnail" | Dialog crop se deschide | ☐ |
| 3.3.4 | Detach image | Hover > Click "Detach" | Imagine eliminată din articol | ☐ |
| 3.3.5 | Contor imagini | Verifică "N / 100 images" | Contor actualizat corect | ☐ |

### 3.4 Inserare Imagini în Conținut

| # | Test | Pași | Rezultat Așteptat | Status |
|---|------|------|-------------------|--------|
| 3.4.1 | Buton "Inserează imagine" | Click buton în toolbar | Dialog cu imagini atașate | ☐ |
| 3.4.2 | Selectare imagine | Dropdown cu imaginile articolului | Lista corectă afișată | ☐ |
| 3.4.3 | Aliniere Block | Selectează "În linie (block)" | Imagine centrată, full width | ☐ |
| 3.4.4 | Aliniere Left | Selectează "Stânga (float)" | Imagine float left | ☐ |
| 3.4.5 | Aliniere Right | Selectează "Dreapta (float)" | Imagine float right | ☐ |
| 3.4.6 | Aliniere Center | Selectează "Centrat" | Imagine centrată, 80% width | ☐ |
| 3.4.7 | Alt text | Completează "Text alternativ" | Alt text setat pe imagine | ☐ |
| 3.4.8 | Caption/Legendă | Completează "Legendă" | Figcaption afișat sub imagine | ☐ |
| 3.4.9 | Inserare fără imagini | Click buton când 0 imagini | Notificare "Nu există imagini" | ☐ |

---

## Secțiunea 4: Statusuri și Publicare

### 4.1 Statusuri Articol

| # | Test | Pași | Rezultat Așteptat | Status |
|---|------|------|-------------------|--------|
| 4.1.1 | Status New | Creează articol, lasă "New" | Articol salvat, nepublicat | ☐ |
| 4.1.2 | Status Submitted | Schimbă în "Submitted" | Articol în așteptare aprobare | ☐ |
| 4.1.3 | Status Published | Schimbă în "Published" | Articol vizibil public | ☐ |
| 4.1.4 | Unpublish | De la Published la New | Articol dispare din public | ☐ |

### 4.2 Salvare Articol

| # | Test | Pași | Rezultat Așteptat | Status |
|---|------|------|-------------------|--------|
| 4.2.1 | Save (rămâi pe pagină) | Click "Save" | Articol salvat, rămâi în editor | ☐ |
| 4.2.2 | Save & Close | Click "Save & Close" | Salvare + redirect la listă | ☐ |
| 4.2.3 | Close fără save | Click "Close" cu modificări | Confirmare "Unsaved changes" | ☐ |
| 4.2.4 | Validare la save | Salvează fără titlu | Eroare validare | ☐ |

### 4.3 Article Lock (Editare Exclusivă)

| # | Test | Pași | Rezultat Așteptat | Status |
|---|------|------|-------------------|--------|
| 4.3.1 | Lock la deschidere | Deschide articol în edit | Mesaj "exclusive editing access" | ☐ |
| 4.3.2 | Lock blocking | Alt user încearcă să editeze | Mesaj "locked by [user]" | ☐ |
| 4.3.3 | Lock refresh | Așteaptă 1 minut | Lock se reînoiește automat | ☐ |
| 4.3.4 | Lock release | Închide editorul | Lock eliberat, alții pot edita | ☐ |

---

## Secțiunea 5: Lista și Filtrarea Articolelor

### 5.1 Vizualizare Listă

| # | Test | Pași | Rezultat Așteptat | Status |
|---|------|------|-------------------|--------|
| 5.1.1 | Încărcare listă | Navighează la /ro/admin/articles | Lista articolelor afișată | ☐ |
| 5.1.2 | Coloane vizibile | Verifică: Titlu, Autor, Status, Data | Toate coloanele prezente | ☐ |
| 5.1.3 | Sortare by date | Click pe header "Date" | Sortare cronologică | ☐ |
| 5.1.4 | Sortare by title | Click pe header "Title" | Sortare alfabetică | ☐ |
| 5.1.5 | Paginare | Navighează între pagini | Articole diferite pe fiecare | ☐ |

### 5.2 Căutare și Filtrare

| # | Test | Pași | Rezultat Așteptat | Status |
|---|------|------|-------------------|--------|
| 5.2.1 | Căutare by title | Scrie în search "test" | Doar articole cu "test" | ☐ |
| 5.2.2 | Filtru by category | Selectează categorie din dropdown | Filtrare corectă | ☐ |
| 5.2.3 | Filtru by status | Selectează "Published" | Doar articole publicate | ☐ |
| 5.2.4 | Combinare filtre | Categorie + Status + Search | Toate filtrele aplicate | ☐ |
| 5.2.5 | Reset filtre | Click "Clear filters" | Toate articolele vizibile | ☐ |

### 5.3 Acțiuni pe Listă

| # | Test | Pași | Rezultat Așteptat | Status |
|---|------|------|-------------------|--------|
| 5.3.1 | Edit articol | Click pe titlu sau "Edit" | Redirect la editor | ☐ |
| 5.3.2 | View public | Click "View" (dacă Published) | Deschide articol public | ☐ |
| 5.3.3 | Delete articol | Click "Delete" > Confirm | Articol șters | ☐ |
| 5.3.4 | Bulk selection | Checkbox pe mai multe articole | Acțiuni bulk disponibile | ☐ |

---

## Secțiunea 6: Verificare Afișare Publică

### 6.1 Pagina Articol

| # | Test | Pași | Rezultat Așteptat | Status |
|---|------|------|-------------------|--------|
| 6.1.1 | Accesare URL | Navighează la URL articol | Pagina se încarcă | ☐ |
| 6.1.2 | Titlu H1 | Verifică titlul | Afișat corect, font mare | ☐ |
| 6.1.3 | Lead/Chapeau | Verifică lead | Sub titlu, font distinct | ☐ |
| 6.1.4 | Autor | Verifică numele autorului | Link funcțional către profil | ☐ |
| 6.1.5 | Data publicării | Verifică data | Format "DD MMMM YYYY" | ☐ |
| 6.1.6 | Reading time | Verifică timpul de citire | "N min read" calculat | ☐ |
| 6.1.7 | Category badge | Verifică badge categorie | Link funcțional | ☐ |
| 6.1.8 | Breadcrumb | Verifică navigarea | Acasă > Categorie > Titlu | ☐ |

### 6.2 Featured Image

| # | Test | Pași | Rezultat Așteptat | Status |
|---|------|------|-------------------|--------|
| 6.2.1 | Imagine afișată | Verifică imaginea featured | Dimensiune corectă, calitate bună | ☐ |
| 6.2.2 | Caption imagine | Verifică legenda | Afișată sub imagine | ☐ |
| 6.2.3 | Lightbox | Click pe imagine | Deschide în fullscreen | ☐ |
| 6.2.4 | Alt text | Inspect > verifică alt | Alt text prezent | ☐ |

### 6.3 Conținut Articol

| # | Test | Pași | Rezultat Așteptat | Status |
|---|------|------|-------------------|--------|
| 6.3.1 | Headings H2 | Verifică secțiunile | Font mare, spațiere corectă | ☐ |
| 6.3.2 | Headings H3 | Verifică subsecțiunile | Distinct de H2 | ☐ |
| 6.3.3 | Paragrafe | Verifică textul | Line-height, spacing corecte | ☐ |
| 6.3.4 | Bold text | Verifică text bold | Font-weight vizibil | ☐ |
| 6.3.5 | Italic text | Verifică text italic | Stil italic aplicat | ☐ |
| 6.3.6 | Link-uri | Click pe link | Navigare corectă | ☐ |
| 6.3.7 | Liste bullet | Verifică liste | Puncte vizibile, indent corect | ☐ |
| 6.3.8 | Liste numerotate | Verifică liste | Numere vizibile | ☐ |
| 6.3.9 | Blockquote | Verifică citate | Stil distinct, fundal diferit | ☐ |
| 6.3.10 | Tabele | Verifică tabele | Borduri, header distinct | ☐ |
| 6.3.11 | Imagini inline | Verifică imaginile | Aliniere corectă | ☐ |
| 6.3.12 | Video YouTube | Verifică embed | Player funcțional | ☐ |

### 6.4 Table of Contents (ToC)

| # | Test | Pași | Rezultat Așteptat | Status |
|---|------|------|-------------------|--------|
| 6.4.1 | Afișare ToC | Verifică sidebar/inline | Lista heading-urilor H2/H3 | ☐ |
| 6.4.2 | Click pe ToC item | Click pe un item | Scroll la secțiune | ☐ |
| 6.4.3 | Active state | Scroll prin articol | Item activ evidențiat | ☐ |

### 6.5 Reading Progress

| # | Test | Pași | Rezultat Așteptat | Status |
|---|------|------|-------------------|--------|
| 6.5.1 | Progress bar | Scroll prin articol | Bara progresează | ☐ |
| 6.5.2 | 0% la început | Verifică la top | Bara goală/minimă | ☐ |
| 6.5.3 | 100% la final | Scroll la final | Bara completă | ☐ |

### 6.6 Share și Social

| # | Test | Pași | Rezultat Așteptat | Status |
|---|------|------|-------------------|--------|
| 6.6.1 | Share buttons | Verifică prezența | Facebook, Twitter, LinkedIn | ☐ |
| 6.6.2 | Facebook share | Click Facebook | Dialog share corect | ☐ |
| 6.6.3 | Twitter share | Click Twitter | Tweet pre-completat | ☐ |
| 6.6.4 | LinkedIn share | Click LinkedIn | Share dialog | ☐ |

### 6.7 Related Articles

| # | Test | Pași | Rezultat Așteptat | Status |
|---|------|------|-------------------|--------|
| 6.7.1 | Afișare related | Verifică sidebar | Articole din aceeași categorie | ☐ |
| 6.7.2 | Link funcțional | Click pe related | Navigare la articol | ☐ |
| 6.7.3 | Număr articole | Numără articolele | 5-6 articole afișate | ☐ |

---

## Secțiunea 7: Responsive și Mobile

### 7.1 Admin Panel Mobile

| # | Test | Pași | Rezultat Așteptat | Status |
|---|------|------|-------------------|--------|
| 7.1.1 | Login pe mobil | Accesează admin pe telefon | Formular responsiv | ☐ |
| 7.1.2 | Sidebar mobile | Verifică navigarea | Hamburger menu funcțional | ☐ |
| 7.1.3 | Editor mobile | Încearcă să editezi | Funcționalitate de bază OK | ☐ |
| 7.1.4 | Touch pe tabele | Scroll orizontal | Tabelele scrollabile | ☐ |

### 7.2 Frontend Mobile

| # | Test | Pași | Rezultat Așteptat | Status |
|---|------|------|-------------------|--------|
| 7.2.1 | Articol pe mobil | Deschide articol pe telefon | Layout adaptat | ☐ |
| 7.2.2 | Imagini responsive | Verifică imaginile | Scalează corect | ☐ |
| 7.2.3 | Video responsive | Verifică YouTube embed | Aspect ratio păstrat | ☐ |
| 7.2.4 | Tabele responsive | Verifică tabelele | Scroll orizontal | ☐ |
| 7.2.5 | Font readability | Citește textul | Font lizibil, contrast OK | ☐ |

---

## Secțiunea 8: Performance și Erori

### 8.1 Performance

| # | Test | Pași | Rezultat Așteptat | Status |
|---|------|------|-------------------|--------|
| 8.1.1 | Load time articol | Măsoară timpul de încărcare | < 3 secunde | ☐ |
| 8.1.2 | Image lazy loading | Scroll rapid prin articol | Imagini se încarcă progresiv | ☐ |
| 8.1.3 | Editor response | Scrie rapid în editor | Fără lag vizibil | ☐ |

### 8.2 Error Handling

| # | Test | Pași | Rezultat Așteptat | Status |
|---|------|------|-------------------|--------|
| 8.2.1 | 404 articol | Accesează URL invalid | Pagină "Article Not Found" | ☐ |
| 8.2.2 | Network error | Deconectează internetul temporar | Mesaj eroare prietenos | ☐ |
| 8.2.3 | Session expired | Lasă pagina deschisă > 1h | Redirect la login sau refresh token | ☐ |

---

## Checklist Final de Verificare

Înainte de a considera testarea completă, verificați:

- [ ] Toate testele din Secțiunea 1 (Autentificare) sunt trecute
- [ ] Toate testele din Secțiunea 2 (Creare Articole) sunt trecute
- [ ] Toate testele din Secțiunea 3 (Imagini) sunt trecute
- [ ] Toate testele din Secțiunea 4 (Publicare) sunt trecute
- [ ] Toate testele din Secțiunea 5 (Lista Articole) sunt trecute
- [ ] Toate testele din Secțiunea 6 (Afișare Publică) sunt trecute
- [ ] Toate testele din Secțiunea 7 (Mobile) sunt trecute
- [ ] Toate testele din Secțiunea 8 (Performance) sunt trecute

---

## Raportare Bug-uri

Pentru orice problemă identificată, raportați folosind următorul format:

### Template Bug Report

```
**Titlu:** [Scurtă descriere a problemei]

**Severitate:** Critical / High / Medium / Low

**Pași de reproducere:**
1.
2.
3.

**Rezultat actual:**
[Ce s-a întâmplat]

**Rezultat așteptat:**
[Ce ar fi trebuit să se întâmple]

**Mediu:**
- Browser:
- Versiune:
- OS:
- Rezoluție ecran:

**Screenshot/Video:**
[Atașați dacă este relevant]

**Note adiționale:**
[Orice informație suplimentară]
```

---

## Contact și Suport

Pentru întrebări sau probleme tehnice în timpul testării:

- **Email:** dev@deschide.md
- **Slack:** #testing-channel
- **Responsabil testare:** [Nume]

---

**Document generat:** 17 Decembrie 2025
**Ultima actualizare:** 17 Decembrie 2025
**Versiune:** 1.0
