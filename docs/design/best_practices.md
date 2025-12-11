# Ghid de Cele Mai Bune Practici de Design pentru Deschide News App

Acest document stabilește standardele de design UI/UX pentru aplicația Deschide News, sintetizând arhitectura tehnică (Next.js 16, Tailwind CSS 4) cu cele mai bune practici din industria de media digitală pentru 2025.

---

## 1. Filozofia de Design: "Premium, Dinamic, Clar"

Obiectivul este un design care inspiră **încredere**, **viteză** și **modernitate**.
- **First Impression**: Utilizatorul trebuie să fie impresionat vizual în primele 3 secunde.
- **Content-First**: Designul nu trebuie să distragă, ci să pună în valoare conținutul editorial.
- **Micro-Interacțiuni**: Utilizarea animațiilor subtile pentru a face interfața să se simtă "vie" și receptivă.

---

## 2. Tipografie și Lizibilitate (Readability)

Lizibilitatea este metrica supremă pentru un site de știri.

### 2.1. Selecția Fonturilor (BRAND MANDATORY)
Conform Brandbook-ului oficial, se utilizează exclusiv următoarele fonturi:

- **Titluri (Headings)**: **League Spartan**
  - *Weights*: **Bold (700)**
  - *Transform*: **OBLIGATORIU UPPERCASE (Majuscule)**
  - *Utilizare*: Titluri de articole, secțiuni, nume de categorii.
  - *Interzis*: Lowercase pentru titluri mari.

- **Corp de text (Body)**: **Poppins**
  - *Weights*: **Regular (400)** pentru text, **Medium (500)** pentru accente/meniuri.
  - *Utilizare*: Excerpte, conținut articol, metadate, butoane.

### 2.2. Dimensiuni și Spațiere
- **Body Text**: Minimum **16px** (Poppins Regular).
- **Line Height (Leading)**: 150% - 160% (ex: `leading-relaxed`).
- **Ierarhie Vizuală**:
  - `H1`: 40px (League Spartan Bold UPPERCASE)
  - `H2`: 32px (League Spartan Bold UPPERCASE)
  - `H3`: 24px (League Spartan Bold UPPERCASE)

---

## 3. Sistemul de Culori și Tematizare

Culorile sunt strict definite în Brandbook. Orice deviere este interzisă.

### 3.1. Paleta Cromatică Oficială

| Nume | Hex | Utilizare | Procentaj Ideal |
| :--- | :--- | :--- | :--- |
| **Oxford Blue** | `#112240` | Fundaluri principale, Header, Text pe light | **40%** (Dominant) |
| **Tomato** | `#F05E45` | Butoane (CTA), Link-uri active, Accente | **40-50%** |
| **Red CMYK** | `#E92628` | Badges, Alerte, Erori | **10-30%** |
| **Mindaro** | `#D4FB8C` | Hover effects, Accente subtile | **Max 10%** |
| **White** | `#FFFFFF` | Text pe fundal închis, Carduri | - |

**REGULI CRITICE:**
1. **NICIODATĂ** nu folosiți text de culoare `#E92628` (Red CMYK) pe fundal alb.
2. **NICIODATĂ** nu folosiți `#D4FB8C` (Mindaro) pentru text lung (doar iconițe sau titluri mici pe fundal închis).
3. Folosiți `#112240` (Oxford Blue) pentru textul principal pe fundal alb.

### 3.2. Contrast și Accesibilitate
- Text alb pe fundal Oxford Blue (`#112240`) oferă contrast excelent.
- Text Oxford Blue pe fundal alb este standardul pentru lizibilitate.

---

## 4. Layout și UX Patterns

### 4.1. Structura Paginilor
- **Desktop**: Grid cu 12 coloane.
- **Header**: Fundal **Oxford Blue** `#112240`, Text alb / Logo Alb.

### 4.2. Navigare
- Link-urile din meniu: **Poppins Medium**, Hover: **Mindaro** `#D4FB8C`.

### 4.3. Carduri de Știri (News Cards)
- **Umbre**: Textul pe poze (Hero) trebuie să aibă `text-shadow` (Drop Shadow) conform secțiunii 1.3 din Brandbook.
- **Gradient**: Imaginile pot avea un overlay gradient `Oxford Blue` -> `Tomato` (opacity redusă) pentru branding.

---

## 5. Experiența Articolului (Article Page UX)

- **Titlu Articol**: League Spartan Bold UPPERCASE.
- **Categorie Badge**: Fundal **Tomato** `#F05E45`, Text Alb.
- **Autor/Dată**: Poppins Regular, culoare gri neutru sau Oxford Blue deschis.

---

## 6. Logo & Branding

- **Dimensiune Minimă**: 20px înălțime.
- **Clear Space**: O lungime de logo distanță față de orice alt element.
- **Variante**:
  - *Logo Alb*: Pe fundaluri Oxford Blue, Tomato sau Poze.
  - *Logo Oxford Blue*: Exclusiv pe fundal alb curat.
  - *Logo Roșu*: Exclusiv pe fundal alb curat (utilizare rară).

## 7. Checklist Implementare (Tailwind)

- [ ] Verificați `tailwind.config.ts` pentru culorile `oxford-blue`, `tomato`, `mindaro`.
- [ ] Verificați importul fonturilor `League Spartan` și `Poppins`.
- [ ] Asigurați-vă că toate titlurile `H1-H6` au clasa `uppercase` aplicată global sau prin componente.
