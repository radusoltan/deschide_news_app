### **Plan de Testare Manuală – Frontend Deschide News**

**Versiune:** 1.0
**Data:** 6 Decembrie 2025
**Autor:** Gemini, Senior Frontend QA

#### **1. Obiectiv și Scop**

**Obiectiv:** Validarea completă a funcționalității, experienței utilizator (UI/UX) și performanței aplicației frontend pentru a asigura stabilitatea, consistența și o experiență de înaltă calitate pe toate dispozitivele și pentru toți utilizatorii.

**Scop (In-Scope):**
*   Testarea funcțională a tuturor componentelor publice și a panoului de administrare.
*   Validarea UI/UX pentru consistență vizuală și ușurință în utilizare.
*   Măsurarea performanței de încărcare și interacțiune (Core Web Vitals).
*   Testarea designului responsiv pe diferite rezoluții (desktop, tabletă, mobil).
*   Validarea scenariilor de utilizare pentru diferite roluri (vizitator, editor, admin).

**În Afara Scopului (Out-of-Scope):**
*   Testarea de securitate la nivel de API (penetration testing).
*   Testarea de unitate (Unit Testing) a componentelor individuale (acoperită de teste automate).
*   Testarea de încărcare (Load Testing) a serverului backend.

#### **2. Personas și Roluri**

1.  **Vizitatorul (Public):** Utilizator neautentificat care consumă conținut.
2.  **Editorul (ROLE_EDITOR):** Utilizator autentificat care creează și gestionează articole.
3.  **Administratorul (ROLE_ADMIN):** Utilizator autentificat cu drepturi extinse de management.

#### **3. Unelte și Agenți**

*   **Agent AI Manual Tester:** Un agent AI capabil să urmeze pași de testare în limbaj natural și să valideze vizual rezultatele așteptate.
*   **Playwright MCP:** Utilizat pentru automatizarea navigării, interacțiunilor și testării cross-browser/responsive.
*   **Chrome DevTools MCP:** Utilizat pentru analiza detaliată a performanței (Lighthouse, Performance), inspecția DOM și debugging.

---

### **Suite de Teste 1: UI/UX și Funcționalitate – Site Public**

**Actor:** Vizitatorul
**Agent Principal:** Agent AI Manual Tester, Playwright MCP

| ID | Scenariu | Pași de Reproducere | Rezultat Așteptat | Unelte |
| :--- | :--- | :--- | :--- | :--- |
| **UI-PUB-001** | **Homepage - Afișare Inițială** | 1. Navighează la URL-ul rădăcină (`/`).<br>2. Verifică prezența header-ului cu logo și meniu.<br>3. Verifică prezența articolului principal (Hero).<br>4. Verifică existența listei de articole recente.<br>5. Verifică prezența footer-ului cu linkuri utile. | Toate elementele cheie sunt vizibile și arată corect. Conținutul este încărcat, fără erori vizuale. | Agent AI Manual |
| **UI-PUB-002** | **Navigare - Meniu Principal** | 1. Pe homepage, apasă pe o categorie din meniul principal (ex: "Politică").<br>2. Verifică dacă URL-ul se schimbă în `/{locale}/politica`.<br>3. Verifică dacă titlul paginii reflectă categoria selectată.<br>4. Verifică dacă lista de articole afișată conține doar articole din acea categorie. | Utilizatorul este direcționat corect la pagina de categorie, iar conținutul este filtrat corespunzător. | Playwright MCP |
| **UI-PUB-003** | **Citire Articol** | 1. De pe o pagină de categorie, apasă pe titlul unui articol.<br>2. Verifică dacă URL-ul se schimbă în `/{locale}/{categorie}/{slug-articol}`.<br>3. Verifică afișarea corectă a titlului, imaginii principale, conținutului și autorului.<br>4. Derulează până la finalul articolului.<br>5. Verifică prezența secțiunii de articole similare. | Pagina articolului se încarcă complet, cu toate elementele vizibile și formatate corect. | Agent AI Manual |
| **UI-PUB-004** | **Funcționalitate Căutare** | 1. Apasă pe icon-ul de căutare din header.<br>2. Introdu un cuvânt cheie relevant (ex: "guvern") și apasă Enter.<br>3. Verifică dacă ești redirecționat către pagina de rezultate.<br>4. Verifică dacă rezultatele afișate conțin cuvântul cheie în titlu sau conținut. | Căutarea returnează o listă de rezultate relevante. Pagina de rezultate este afișată corect. | Playwright MCP |
| **UI-PUB-005** | **Internaționalizare (Schimbare Limbă)** | 1. Pe orice pagină, localizează selectorul de limbă.<br>2. Schimbă limba din Română (RO) în Engleză (EN).<br>3. Verifică dacă URL-ul se actualizează cu prefixul `/en`.<br>4. Verifică dacă elementele de interfață (meniuri, butoane) și conținutul articolului (dacă există traducere) sunt afișate în Engleză. | Întreaga interfață și conținutul se traduc în limba selectată, iar URL-ul reflectă schimbarea. | Agent AI Manual |
| **UI-PUB-006** | **Live Text - Vizualizare** | 1. Navighează la un articol marcat ca "Live Text".<br>2. Verifică afișarea în timp real a postărilor noi fără a necesita reîncărcarea paginii.<br>3. Lasă pagina deschisă timp de 2-3 minute pentru a observa actualizările (dacă există un eveniment activ). | Postările noi apar automat în partea de sus a feed-ului, indicând o conexiune Mercure funcțională. | Agent AI Manual |
| **UI-PUB-007** | **Design Responsiv - Mobil** | 1. Deschide homepage-ul într-o fereastră cu lățime de 390px (iPhone 12).<br>2. Verifică dacă meniul principal este înlocuit de un meniu "hamburger".<br>3. Apasă pe meniul hamburger și verifică dacă se deschide corect.<br>4. Navighează la un articol și verifică dacă textul este lizibil și imaginile sunt încadrate corect. | Site-ul este perfect funcțional și lizibil pe un ecran de mobil. Nu există elemente care depășesc ecranul (overflow). | Playwright MCP |

---

### **Suite de Teste 2: UI/UX și Funcționalitate – Panou de Administrare**

**Actor:** Editorul, Administratorul
**Agent Principal:** Agent AI Manual Tester

| ID | Scenariu | Pași de Reproducere | Rezultat Așteptat | Unelte |
| :--- | :--- | :--- | :--- | :--- |
| **UI-ADM-001** | **Autentificare Editor** | 1. Navighează la `/login`.<br>2. Introdu credențialele unui utilizator cu rol `ROLE_EDITOR`.<br>3. Apasă pe butonul "Login". | Utilizatorul este autentificat cu succes și redirecționat către dashboard-ul de administrare. | Agent AI Manual |
| **UI-ADM-002** | **Creare Articol Nou** | 1. Din dashboard, navighează la "Articole" -> "Adaugă Articol Nou".<br>2. Completează câmpurile obligatorii: Titlu, Conținut, Categorie.<br>3. Încarcă o imagine principală.<br>4. Salvează articolul ca "Draft" (Ciornă).<br>5. Verifică dacă articolul apare în lista de articole cu statusul "Draft". | Articolul este salvat corect în sistem. Toate datele introduse sunt persistate. Nu apar erori. | Agent AI Manual |
| **UI-ADM-003** | **Editare și Publicare Articol** | 1. Din lista de articole, deschide pentru editare articolul creat la `UI-ADM-002`.<br>2. Modifică titlul.<br>3. Schimbă statusul din "Draft" în "Publicat".<br>4. Salvează modificările.<br>5. Navighează la versiunea publică a site-ului și verifică dacă articolul este vizibil cu noul titlu. | Modificările sunt salvate, iar articolul devine vizibil pe site-ul public după publicare. | Agent AI Manual |
| **UI-ADM-004** | **Validare Câmpuri (Caz Negativ)** | 1. Încearcă să creezi un articol nou fără a completa titlul.<br>2. Apasă pe butonul de salvare. | Sistemul afișează un mesaj de eroare de validare clar, indicând că titlul este obligatoriu. Formularul nu este trimis. | Agent AI Manual |

---

### **Suite de Teste 3: Performanță**

**Actor:** Vizitatorul
**Agent Principal:** Chrome DevTools MCP, Playwright Performance Trace

| ID | Scenariu | Pași de Reproducere | Metrici de Colectat (Acceptanță) | Unelte |
| :--- | :--- | :--- | :--- | :--- |
| **PERF-001** | **Performanță Încărcare Homepage** | 1. Golește cache-ul browser-ului.<br>2. Navighează la URL-ul rădăcină (`/`).<br>3. Înregistrează un profil de performanță până la încărcarea completă a paginii. | **LCP:** < 2.5s<br>**INP:** < 200ms<br>**CLS:** < 0.1<br>**Load Time:** < 3s | Chrome DevTools |
| **PERF-002** | **Performanță Încărcare Articol** | 1. Golește cache-ul.<br>2. Navighează direct la URL-ul unui articol cu multe imagini.<br>3. Înregistrează profilul de performanță. | **LCP:** < 2.5s (imaginea principală)<br>**INP:** < 200ms<br>**CLS:** < 0.1<br>**Nr. Request-uri:** < 100 | Chrome DevTools |
| **PERF-003** | **Impactul Navigării (Client-Side)** | 1. Încarcă homepage-ul.<br>2. Folosind Playwright, măsoară timpul de la click pe un link de categorie până la afișarea completă a paginii de categorie.<br>3. Repetă pentru navigarea de la categorie la articol. | Timpul de tranziție între pagini (navigare client-side) trebuie să fie sub 500ms. | Playwright Trace|
| **PERF-004** | **Greutatea Paginilor** | 1. Folosind tab-ul "Network" din DevTools, încarcă homepage-ul cu cache-ul dezactivat.<br>2. Notează dimensiunea totală a resurselor transferate (Total Transferred Size). | **Homepage:** < 1.5 MB<br>**Pagină Articol:** < 2 MB | Chrome DevTools |

---

### **Suite de Teste 4: Responsive și Cross-Browser**

**Actor:** Vizitatorul
**Agent Principal:** Playwright MCP

| ID | Scenariu | Pași de Reproducere | Rezultat Așteptat | Unelte |
| :--- | :--- | :--- | :--- | :--- |
| **RES-001** | **Validare Layout Tabletă** | 1. Setează viewport-ul la 768x1024 (iPad).<br>2. Navighează pe homepage, o pagină de categorie și un articol.<br>3. Verifică vizual layout-ul pentru orice suprapunere, text tăiat sau elemente nealiniate. | Layout-ul se adaptează corect la dimensiunea de tabletă, fiind complet utilizabil. | Playwright |
| **RES-002** | **Funcționalitate Cross-Browser** | 1. Rulează scenariile `UI-PUB-001` până la `UI-PUB-004` pe ultimele versiuni de Chrome, Firefox și WebKit (Safari). | Toate funcționalitățile de bază (navigare, căutare, citire articol) funcționează identic și fără erori vizuale pe toate cele trei motoare de browser. | Playwright |
