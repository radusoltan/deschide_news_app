Iată o listă comprehensivă a ministerelor și principalelor instituții publice din Republica Moldova, structurată și formatată pentru a facilita integrarea în sistemul tău de agregare a știrilor. 

Majoritatea site-urilor guvernamentale (cele cu domeniul `.gov.md`) au fost migrate pe o platformă web standardizată gestionată de stat, ceea ce înseamnă că structura linkurilor RSS este, de cele mai multe ori, predictibilă (de obicei `[domeniu]/ro/rss.xml` pentru secțiunea de comunicate/știri).

### Instituții de Stat Centrale

| Instituție | Pagina Oficială | Disponibilitate RSS / Endpoint Tipic |
| :--- | :--- | :--- |
| **Președinția Republicii Moldova** | [presedinte.md](https://presedinte.md/) | **Da:** `https://presedinte.md/rom/rss` |
| **Parlamentul Republicii Moldova** | [parlament.md](https://parlament.md/) | **Da:** `https://parlament.md/rss.aspx` (pentru comunicate) |
| **Guvernul Republicii Moldova** | [gov.md](https://gov.md/) | **Da:** `https://gov.md/ro/rss.xml` |
| **Cancelaria de Stat** | [cancelaria.gov.md](https://cancelaria.gov.md/) | **Da:** `https://cancelaria.gov.md/ro/rss.xml` |

---

### Ministerele Guvernului Republicii Moldova

| Minister | Pagina Oficială | Disponibilitate RSS |
| :--- | :--- | :--- |
| **Ministerul Afacerilor Externe (MAE)** | [mfa.gov.md](https://mfa.gov.md/) | **Da:** `https://mfa.gov.md/ro/rss.xml` |
| **Ministerul Afacerilor Interne (MAI)** | [mai.gov.md](https://mai.gov.md/) | **Da:** `https://mai.gov.md/ro/rss.xml` |
| **Ministerul Agriculturii și Industriei Alimentare (MAIA)** | [maia.gov.md](https://maia.gov.md/) | **Da:** `https://maia.gov.md/ro/rss.xml` |
| **Ministerul Apărării** | [army.md](https://www.army.md/) | **Da:** (Platformă non-standard, de obicei expune RSS pe secțiunea /news) |
| **Ministerul Culturii** | [mc.gov.md](https://mc.gov.md/) | **Da:** `https://mc.gov.md/ro/rss.xml` |
| **Ministerul Dezvoltării Economice și Digitalizării (MDED)** | [mded.gov.md](https://mded.gov.md/) | **Da:** `https://mded.gov.md/ro/rss.xml` |
| **Ministerul Educației și Cercetării (MEC)** | [mec.gov.md](https://mec.gov.md/) | **Da:** `https://mec.gov.md/ro/rss.xml` |
| **Ministerul Energiei** | [energie.gov.md](https://energie.gov.md/) | **Da:** `https://energie.gov.md/ro/rss.xml` |
| **Ministerul Finanțelor** | [mf.gov.md](https://mf.gov.md/) | **Da:** `https://mf.gov.md/ro/rss.xml` |
| **Ministerul Infrastructurii și Dezvoltării Regionale (MIDR)** | [midr.gov.md](https://midr.gov.md/) | **Da:** `https://midr.gov.md/ro/rss.xml` |
| **Ministerul Justiției** | [justice.gov.md](https://justice.gov.md/) | **Da:** `https://justice.gov.md/ro/rss.xml` |
| **Ministerul Mediului** | [mediu.gov.md](https://mediu.gov.md/) | **Da:** `https://mediu.gov.md/ro/rss.xml` |
| **Ministerul Muncii și Protecției Sociale (MMPS)** | [social.gov.md](https://social.gov.md/) | **Da:** `https://social.gov.md/ro/rss.xml` |
| **Ministerul Sănătății** | [ms.gov.md](https://ms.gov.md/) | **Da:** `https://ms.gov.md/ro/rss.xml` |

---

### Alte Instituții Cheie / Autorități Independente

Pentru fluxurile de știri investigative, juridice sau economice, următoarele instituții sunt surse primare critice:

| Instituție | Pagina Oficială | Disponibilitate RSS |
| :--- | :--- | :--- |
| **Banca Națională a Moldovei (BNM)** | [bnm.md](https://www.bnm.md/) | **Da:** `https://www.bnm.md/ro/rss.xml` |
| **Centrul Național Anticorupție (CNA)** | [cna.md](https://cna.md/) | **Da:** (Feed generat de obicei din secțiunea comunicate) |
| **Serviciul de Informații și Securitate (SIS)** | [sis.md](https://sis.md/) | **Da:** `https://sis.md/ro/rss.xml` (sau similar pe noua platformă) |
| **Procuratura Generală** | [procuratura.md](https://procuratura.md/) | **Da:** (Feed disponibil pe secțiunea de presă) |
| **Comisia Electorală Centrală (CEC)** | [cec.md](https://cec.md/) | **Da:** (Secțiunea de noutăți are de obicei suport RSS) |
| **Curtea Constituțională** | [constcourt.md](https://www.constcourt.md/) | **Da:** (Platformă specifică) |
| **Consiliul Audiovizualului (CA)** | [consiliuaudiovizual.md](https://consiliuaudiovizual.md/) | **Da** |

---

### Recomandări de Integrare pentru Agregator

1. **Validarea URL-urilor:** Chiar dacă platformele guvernamentale standard au RSS-ul predictibil, unele instituții publică știrile prin feed-uri agregate pe portaluri terțe sau via Google News. Pentru stabilitatea sistemului de crawling, atunci când parsezi URL-uri ce ar putea fi mascate sau redirectate (de exemplu, dacă folosești feed-uri Google News pentru anumite instituții care nu au RSS nativ stabil), asigură-te că folosești un mecanism de tip fallback `resolveUrlViaHttp` la nivel de backend pentru a extrage linkul final curat înainte de a salva articolele în baza de date.
2. **Standardizarea Datelor:** Feed-urile XML guvernamentale din Republica Moldova uneori omit standardele stricte RFC (de exemplu, formatarea datei de publicare `pubDate` sau utilizarea tag-urilor `<content:encoded>`). Este util ca pipeline-ul de agregare să includă un sanitizator pentru câmpurile de text.

Completarea bazei de surse cu agențiile subordonate și structurile deconcentrate este esențială, deoarece acestea generează adesea știri de impact direct pentru cetățeni (avertizări meteo, controale vamale, decizii fiscale, poliție etc.). 

Iată lista principalelor agenții și autorități administrative, grupate pe ministerele sau instituțiile cărora li se subordonează. Pentru agențiile migrate pe platforma guvernamentală standardizat, endpoint-ul RSS urmează de obicei același șablon (`/ro/rss.xml`).

### Subordonate Ministerului Finanțelor (MF)

| Instituție | Pagina Oficială | Disponibilitate RSS / Endpoint |
| :--- | :--- | :--- |
| **Serviciul Fiscal de Stat (SFS)** | [sfs.md](https://sfs.md/) | **Da:** (Secțiunea de comunicate/noutăți oferă feed) |
| **Serviciul Vamal al Republicii Moldova** | [customs.gov.md](https://customs.gov.md/) | **Da:** `https://customs.gov.md/ro/rss.xml` |

### Subordonate Ministerului Afacerilor Interne (MAI)

Aceste instituții sunt surse critice pentru fluxurile de știri tip "breaking news" și alerte.

| Instituție | Pagina Oficială | Disponibilitate RSS / Endpoint |
| :--- | :--- | :--- |
| **Inspectoratul General al Poliției (IGP)** | [politia.md](https://politia.md/) | **Da:** `https://politia.md/ro/rss.xml` |
| **Inspectoratul General pentru Situații de Urgență (IGSU)** | [dse.md](https://dse.md/) | **Da:** (Feed generat din secțiunea noutăți) |
| **Inspectoratul General al Poliției de Frontieră (IGPF)** | [border.gov.md](https://border.gov.md/) | **Da:** `https://border.gov.md/ro/rss.xml` |
| **Inspectoratul General pentru Migrație (IGM)** | [igm.gov.md](https://igm.gov.md/) | **Da:** `https://igm.gov.md/ro/rss.xml` |
| **Inspectoratul General de Carabinieri (IGC)** | [carabinier.gov.md](https://carabinier.gov.md/) | **Da:** `https://carabinier.gov.md/ro/rss.xml` |

### Subordonate Ministerului Sănătății (MS) & Muncii (MMPS)

| Instituție | Pagina Oficială | Disponibilitate RSS / Endpoint |
| :--- | :--- | :--- |
| **Agenția Națională pentru Sănătate Publică (ANSP)** | [ansp.md](https://ansp.md/) | **Da:** (Platformă proprie, RSS în sectiunea presă) |
| **Casa Națională de Asigurări Sociale (CNAS)** | [cnas.gov.md](https://cnas.gov.md/) | **Da:** `https://cnas.gov.md/ro/rss.xml` |
| **Compania Națională de Asigurări în Medicină (CNAM)** | [cnam.md](https://cnam.md/) | **Da:** (Platformă proprie) |
| **Agenția Națională pentru Ocuparea Forței de Muncă (ANOFM)** | [anofm.md](https://anofm.md/) | **Da:** `https://anofm.md/ro/rss.xml` |
| **Inspectoratul de Stat al Muncii** | [ism.gov.md](https://ism.gov.md/) | **Da:** `https://ism.gov.md/ro/rss.xml` |

### Subordonate Ministerului Agriculturii (MAIA) & Mediului

| Instituție | Pagina Oficială | Disponibilitate RSS / Endpoint |
| :--- | :--- | :--- |
| **Agenția Națională pentru Siguranța Alimentelor (ANSA)** | [ansa.gov.md](https://ansa.gov.md/) | **Da:** `https://ansa.gov.md/ro/rss.xml` |
| **Agenția de Intervenție și Plăți pentru Agricultură (AIPA)** | [aipa.gov.md](https://aipa.gov.md/) | **Da:** `https://aipa.gov.md/ro/rss.xml` |
| **Agenția de Mediu** | [am.gov.md](https://am.gov.md/) | **Da:** `https://am.gov.md/ro/rss.xml` |
| **Inspectoratul pentru Protecția Mediului** | [ipm.gov.md](https://ipm.gov.md/) | **Da:** `https://ipm.gov.md/ro/rss.xml` |
| **Agenția "Moldsilva"** | [moldsilva.gov.md](https://moldsilva.gov.md/) | **Da:** `https://moldsilva.gov.md/ro/rss.xml` |
| **Serviciul Hidrometeorologic de Stat (SHS)** | [meteo.md](http://www.meteo.md/) | **Nu standard:** (Necesită crawling special pentru alerte cod galben/portocaliu) |

### Subordonate Ministerului Infrastructurii (MIDR) & Economiei (MDED)

| Instituție | Pagina Oficială | Disponibilitate RSS / Endpoint |
| :--- | :--- | :--- |
| **Agenția Națională Transport Auto (ANTA)** | [anta.gov.md](https://anta.gov.md/) | **Da:** `https://anta.gov.md/ro/rss.xml` |
| **Administrația de Stat a Drumurilor (ASD)** | [asd.md](https://asd.md/) | **Da:** (Din secțiunea noutăți) |
| **Autoritatea Aeronautică Civilă (AAC)** | [caa.md](https://www.caa.md/) | **Da:** (Din secțiunea noutăți) |
| **Organizația pentru Dezvoltarea Antreprenoriatului (ODA)** | [oda.md](https://oda.md/) | **Da:** (Din secțiunea presă) |

### Subordonate Guvernului / Cancelariei de Stat

Instituții cu un grad mare de autonomie operativă, subordonate direct structurii centrale.

| Instituție | Pagina Oficială | Disponibilitate RSS / Endpoint |
| :--- | :--- | :--- |
| **Agenția Servicii Publice (ASP)** | [asp.gov.md](https://asp.gov.md/) | **Da:** `https://asp.gov.md/ro/rss.xml` |
| **Biroul Național de Statistică (BNS)** | [statistica.gov.md](https://statistica.gov.md/) | **Da:** `https://statistica.gov.md/ro/rss.xml` |
| **Agenția Proprietății Publice (APP)** | [app.gov.md](https://app.gov.md/) | **Da:** `https://app.gov.md/ro/rss.xml` |
| **Agenția Relații Funciare și Cadastru** | [arfc.gov.md](https://arfc.gov.md/) | **Da:** `https://arfc.gov.md/ro/rss.xml` |

---

### Observații Tehnice pentru Agregator:

* **SHS (Vremea/Alerte):** `meteo.md` folosește o structură veche și nu oferă un feed RSS bine formatat. Pentru preluarea alertelor meteo (foarte importante într-o redacție), va fi necesar un scraper specific care să urmărească schimbările de DOM pe pagina principală (în special `div`-urile destinate codurilor de avertizare).
* **Atașamente și PDF-uri:** Instituții precum CNA, Procuratura sau MAI publică frecvent documentele importante (rezoluții, decizii) sub formă de fișiere `.pdf` atașate la articol, uneori fără a include textul complet în corpul XML-ului. Crawler-ul ar trebui să identifice tag-urile `enclosure` din RSS și să indexeze și eventualele link-uri PDF pentru referință.



### Surse Locale

| Instituție / Sursă | Pagina Oficială | Status Verificare |
| :--- | :--- | :--- |
| **Deschide.md** | [deschide.md](https://deschide.md/) | ✅ Funcțional |
| **Ziua.md** | [ziua.md](https://ziua.md/) | ✅ Funcțional |
| **Bani.md** | [bani.md](https://bani.md/) | ✅ Funcțional |
| **RLive.md** | [rlive.md](https://rlive.md/) | ✅ Funcțional |
| **Realitatea.md** | [realitatea.md](https://realitatea.md/) | ✅ Funcțional |
| **Vocea Basarabiei** | [voceabasarabiei.md](https://voceabasarabiei.md/) | ✅ Funcțional |
| **Europa Liberă Moldova** | [moldova.europalibera.org](https://moldova.europalibera.org/) | ✅ Funcțional |
| **Jurnal TV** | [jurnaltv.md](https://www.jurnaltv.md/) | ✅ Funcțional |
| **TVR Moldova** | [tvrmoldova.md](https://tvrmoldova.md/) | ✅ Funcțional |
| **Mold-Street** | [mold-street.com](https://www.mold-street.com/) | ✅ Funcțional |
| **DW Moldova** | [dw.com](https://www.dw.com/ro/moldova/s-11630) | ✅ Funcțional |

---

### Surse din România (Știri Externe și Republica Moldova)

| Instituție / Sursă | Pagina Oficială | Status Verificare |
| :--- | :--- | :--- |
| **Agerpres** | [agerpres.ro](https://www.agerpres.ro/) | ✅ Funcțional |
| **News.ro** | [news.ro](https://www.news.ro/) | ✅ Funcțional |
| **Digi24** | [digi24.ro](https://www.digi24.ro/) | ✅ Funcțional |

---

### Surse din Ucraina

*Resurse ucrainene, verificate cu informații despre conflictul ucrainean (pentru rapiditate) - [Urmează a fi completat]*

---

### Surse din Rusia

| Instituție / Sursă | Pagina Oficială | Status Verificare |
| :--- | :--- | :--- |
| **Meduza** | [meduza.io](https://meduza.io/) | ✅ Funcțional |
| **Novaya Gazeta** | [novayagazeta.ru](https://novayagazeta.ru/) | ✅ Funcțional |
| **Kommersant** | [kommersant.ru](https://www.kommersant.ru/) | ✅ Funcțional |
| **Forbes Rusia** | [forbes.ru](https://www.forbes.ru/) | ✅ Funcțional |