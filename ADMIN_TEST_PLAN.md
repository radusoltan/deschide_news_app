### **Plan de Testare Manuală – Panou de Administrare (Admin Area)**

**Versiune:** 1.0
**Data:** 6 Decembrie 2025
**Autor:** Gemini, Senior Frontend QA

#### **1. Obiectiv și Scop**

**Obiectiv:** Asigurarea că panoul de administrare este robust, securizat, intuitiv și lipsit de defecte funcționale. Acest plan vizează validarea completă a fluxurilor de lucru pentru managementul conținutului și administrarea platformei.

**Scop (In-Scope):**
*   Testarea funcționalităților de autentificare și autorizare bazate pe roluri.
*   Validarea completă a operațiunilor CRUD (Create, Read, Update, Delete) pentru toate entitățile gestionabile (Articole, Categorii, Autori, Utilizatori etc.).
*   Testarea interfeței de utilizator (UI) pentru consistență, usabilitate și design responsiv.
*   Verificarea tuturor formularelor pentru validări corecte și gestionarea erorilor.
*   Testarea funcționalităților specifice, precum managementul "Live Text" și vizualizarea statisticilor.

#### **2. Personas și Roluri**

1.  **Editorul (ROLE_EDITOR):** Responsabil pentru crearea, editarea și publicarea conținutului (articole, imagini).
2.  **Administratorul (ROLE_ADMIN):** Are control total asupra platformei, inclusiv managementul utilizatorilor și a configurărilor.

#### **3. Unelte**

*   **Agent AI Manual Tester:** Execută pașii de testare și validează vizual rezultatele.
*   **Browser DevTools:** Pentru inspecția DOM, monitorizarea request-urilor de rețea și depanare.

---

### **Suite de Teste 1: Autentificare și Controlul Accesului (Authorization)**

**Actor:** Editor, Administrator
**Agent Principal:** Agent AI Manual Tester

| ID | Scenariu | Pași de Reproducere | Rezultat Așteptat |
| :--- | :--- | :--- | :--- |
| **ADM-AUTH-001** | **Autentificare cu succes** | 1. Navighează la `/login`.<br>2. Introdu credențiale valide pentru rolul `ROLE_EDITOR`.<br>3. Apasă "Login". | Utilizatorul este redirecționat către dashboard-ul de administrare. Un token JWT este primit și stocat. |
| **ADM-AUTH-002** | **Autentificare eșuată (parolă greșită)** | 1. Navighează la `/login`.<br>2. Introdu un username valid și o parolă incorectă.<br>3. Apasă "Login". | Un mesaj de eroare clar ("Invalid credentials.") este afișat. Utilizatorul nu este autentificat. |
| **ADM-AUTH-003** | **Deconectare (Logout)** | 1. Autentifică-te ca `ROLE_EDITOR`.<br>2. Localizează și apasă pe butonul "Logout" sau similar.<br>3. Verifică dacă ești redirecționat la pagina de login. | Sesiunea utilizatorului este încheiată. Token-urile locale sunt șterse. |
| **ADM-AUTH-004** | **Acces Interzis (Editor încearcă acces Admin)** | 1. Autentifică-te ca `ROLE_EDITOR`.<br>2. Navighează direct la un URL specific pentru admini (ex: `/admin/users`). | Sistemul afișează o pagină de eroare "Access Denied" (403 Forbidden) sau redirecționează la dashboard, blocând accesul. |
| **ADM-AUTH-005** | **Acces Permis (Admin)** | 1. Autentifică-te ca `ROLE_ADMIN`.<br>2. Navighează direct la URL-ul `/admin/users`. | Pagina de management al utilizatorilor este afișată corect. |

---

### **Suite de Teste 2: Management Articole (CRUD)**

**Actor:** Editor
**Agent Principal:** Agent AI Manual Tester

| ID | Scenariu | Pași de Reproducere | Rezultat Așteptat |
| :--- | :--- | :--- | :--- |
| **ADM-ART-001** | **Vizualizare Listă Articole** | 1. Din meniul de admin, navighează la "Articole".<br>2. Verifică afișarea tabelului cu articole, incluzând coloane precum: Titlu, Autor, Categorie, Status, Dată.<br>3. Testează funcționalitatea de paginare (dacă există >20 articole).<br>4. Testează funcționalitatea de sortare după titlu și dată. | Lista de articole se încarcă și afișează corect. Paginarea și sortarea funcționează conform așteptărilor. |
| **ADM-ART-002** | **Creare Articol Simplu (Draft)** | 1. Apasă "Adaugă Articol Nou".<br>2. Completează doar titlul și o propoziție în conținut.<br>3. Selectează o categorie.<br>4. Salvează ca "Draft". | Articolul este creat și apare în listă cu statusul "Draft". Un mesaj de succes este afișat. |
| **ADM-ART-003** | **Editare și Publicare** | 1. Deschide pentru editare articolul creat anterior.<br>2. Adaugă mai mult text în conținut, adaugă un "lead" și asociază un autor.<br>3. Schimbă statusul în "Publicat".<br>4. Salvează.<br>5. Verifică articolul pe site-ul public. | Toate modificările sunt salvate corect. Articolul este vizibil pe frontend. |
| **ADM-ART-004** | **Utilizarea Editorului Rich Text** | 1. În timpul editării unui articol, testează funcțiile de bază ale editorului: Bold, Italic, liste (bullet/numbered), adăugare link.<br>2. Inserează o imagine în corpul articolului. | Toate opțiunile de formatare funcționează și sunt reflectate corect pe site-ul public. |
| **ADM-ART-005** | **Management Imagini (Upload)** | 1. În pagina de editare a articolului, în secțiunea de imagini, încarcă o imagine nouă de pe computer.<br>2. Setează imaginea ca "Featured".<br>3. Salvează articolul. | Imaginea este încărcată, un thumbnail este afișat în admin, și imaginea apare ca imagine principală pe frontend. |
| **ADM-ART-006** | **Programarea Publicării** | 1. Editează un articol și setează statusul "Scheduled".<br>2. Selectează o dată și o oră în viitor (ex: mâine la 10:00 AM).<br>3. Salvează.<br>4. Verifică dacă articolul nu este vizibil pe site-ul public imediat. | Articolul este salvat cu statusul "Scheduled" și nu apare pe site până la data programată. |
| **ADM-ART-007** | **Arhivare Articol** | 1. Editează un articol publicat.<br>2. Schimbă statusul în "Archived" și selectează un motiv.<br>3. Salvează.<br>4. Verifică dacă articolul nu mai apare în listele principale de pe frontend (dar poate fi accesat prin URL direct sau în secțiunea de arhivă). | Articolul este marcat ca arhivat și eliminat din vizibilitatea publică principală. |
| **ADM-ART-008** | **Prevenirea Editării Concurente** | 1. Deschide același articol pentru editare în două tab-uri/browsere diferite, cu același utilizator sau cu doi utilizatori diferiți.<br>2. Încearcă să editezi în al doilea tab. | Al doilea utilizator ar trebui să primească o notificare că articolul este deja în curs de editare ("Article is locked"). Salvarea ar trebui să fie blocată pentru a preveni suprascrierea. |

---

### **Suite de Teste 3: Management Conținut Adiacent**

**Actor:** Editor
**Agent Principal:** Agent AI Manual Tester

| ID | Scenariu | Pași de Reproducere | Rezultat Așteptat |
| :--- | :--- | :--- | :--- |
| **ADM-CAT-001** | **CRUD Categorii** | 1. Navighează la "Categorii".<br>2. Creează o categorie nouă.<br>3. Editează numele categoriei create.<br>4. Asociază categoria unui articol.<br>5. Șterge categoria. | Toate operațiunile CRUD pentru categorii funcționează corect. O categorie nu poate fi ștearsă dacă are articole asociate (sau se oferă o opțiune de realocare). |
| **ADM-TAG-001** | **CRUD Tag-uri** | 1. Navighează la "Tag-uri".<br>2. Creează un tag nou.<br>3. Editează tag-ul.<br>4. Asociază tag-ul la multiple articole.<br>5. Șterge tag-ul. | Toate operațiunile CRUD pentru tag-uri funcționează conform așteptărilor. |
| **ADM-AUT-001** | **CRUD Autori** | 1. Navighează la "Autori".<br>2. Creează un autor nou, inclusiv cu biografie și poză.<br>3. Editează detaliile autorului.<br>4. Verifică dacă pagina publică a autorului reflectă modificările. | Toate operațiunile CRUD pentru autori funcționează corect. |

---

### **Suite de Teste 4: Management Utilizatori și Roluri**

**Actor:** Administrator
**Agent Principal:** Agent AI Manual Tester

| ID | Scenariu | Pași de Reproducere | Rezultat Așteptat |
| :--- | :--- | :--- | :--- |
| **ADM-USR-001** | **Creare Utilizator Nou** | 1. Autentifică-te ca `ROLE_ADMIN`.<br>2. Navighează la "Utilizatori" -> "Adaugă Utilizator".<br>3. Completează detaliile și atribuie rolul `ROLE_EDITOR`.<br>4. Salvează. | Utilizatorul este creat cu succes. Un email de bun venit/setare parolă este trimis (dacă funcționalitatea există). |
| **ADM-USR-002** | **Editare Rol Utilizator** | 1. Din lista de utilizatori, editează un utilizator existent.<br>2. Schimbă-i rolul din `ROLE_EDITOR` în `ROLE_ADMIN`.<br>3. Salvează. | Rolul utilizatorului este actualizat. La următoarea autentificare, utilizatorul va avea permisiuni de admin. |
| **ADM-USR-003** | **Dezactivare/Suspendare Utilizator** | 1. Editează un utilizator.<br>2. Marchează contul ca "Inactiv" sau "Suspendat".<br>3. Salvează.<br>4. Încearcă să te autentifici cu utilizatorul suspendat. | Autentificarea pentru contul suspendat eșuează cu un mesaj corespunzător. |

---

### **Suite de Teste 5: Validări și Cazuri Negative**

**Actor:** Editor
**Agent Principal:** Agent AI Manual Tester

| ID | Scenariu | Pași de Reproducere | Rezultat Așteptat |
| :--- | :--- | :--- | :--- |
| **ADM-NEG-001** | **Validare Formular Articol** | 1. Încearcă să salvezi un articol fără titlu.<br>2. Încearcă să salvezi un articol fără categorie.<br>3. Introdu un titlu mai lung de 255 de caractere. | Pentru fiecare caz, un mesaj de eroare specific este afișat lângă câmpul invalid. Formularul nu se trimite. |
| **ADM-NEG-002** | **Upload Fișier Invalid** | 1. În secțiunea de imagini, încearcă să încarci un fișier non-imagine (ex: un `.txt` sau `.pdf`).<br>2. Încearcă să încarci o imagine mai mare decât limita acceptată (ex: >10 MB). | Sistemul refuză fișierul și afișează un mesaj de eroare prietenos ("Tip de fișier invalid" sau "Fișierul este prea mare"). |
