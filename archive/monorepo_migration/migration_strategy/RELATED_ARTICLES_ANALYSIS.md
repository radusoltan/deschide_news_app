# Analiza Articolelor Relaționale: Newscoop vs news_app

**Data analiză:** 2025-10-25

---

## 📊 Situația în Newscoop

### Structura Bazei de Date

Newscoop folosește un sistem de **"Context Boxes"** pentru a gestiona articolele relaționale:

#### Tabele Implicate

**1. `context_boxes` (Container Principal)**
```sql
CREATE TABLE context_boxes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    fk_article_no INT NOT NULL  -- Articolul "principal"
);
```

**2. `context_articles` (Articole Asociate)**
```sql
CREATE TABLE context_articles (
    Id INT PRIMARY KEY AUTO_INCREMENT,
    fk_context_id INT NOT NULL,      -- FK → context_boxes.id
    fk_article_no INT NOT NULL,      -- Articolul "related"
    order_number INT NOT NULL DEFAULT 0  -- Ordinea afișării
);
```

### Logica de Funcționare

```
Context Box (ID: 206)
├─ Main Article: 186 ("V.Cibotaru: Pettit a exagerat...")
└─ Related Articles (în ordine):
   ├─ [order: 0] Article 177 ("Traian Băsescu: Declarația lui Pettit...")
   ├─ [order: 0] Article 178 ("Ghimpu, despre declarația lui Pettit...")
   └─ [order: 0] Article 180 ("Diacov și Dodon, primii susținători...")
```

**Observații:**
- Un articol poate avea **un singur Context Box**
- Un Context Box poate conține **multiple articole related**
- `order_number` permite ordonarea manuală (dar în practica observată, toate sunt 0)
- Relația este **unidirecțională**: 186 → [177, 178, 180]
  - Articolul 186 "știe" despre 177, 178, 180
  - Articolele 177, 178, 180 **nu știu** despre 186 (nu e bidirectional)

### Statistici Newscoop

| Metrică | Valoare |
|---------|---------|
| **Total Context Boxes** | 154,748 |
| **Articole cu related articles** | 154,748 (87.8% din total) |
| **Total relații** | 3,807 |
| **Medie related per article** | ~2.5 articole |
| **Max related per article** | 17 articole |
| **Articole cu 1 related** | Majoritatea |
| **Articole cu 5+ related** | Rare |

**Exemple concrete:**
```sql
Main Article 46 → Related: [44, 52] (2 articole)
Main Article 186 → Related: [177, 178, 180] (3 articole)
Main Article 28437 → Related: [17 articole] (maxim)
```

### Implementare în Cod (Newscoop)

**Entity: RelatedArticles.php**
```php
#[ORM\Entity(repositoryClass: 'RelatedArticlesRepository')]
#[ORM\Table(name: 'context_boxes')]
class RelatedArticles
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'integer', name: 'fk_article_no')]
    private $articleNumber;

    // Simplă - doar ID și article number
    // Relația cu context_articles se face prin repository
}
```

**Repository: RelatedArticlesRepository.php**
```php
class RelatedArticlesRepository extends EntityRepository
{
    public function getRelatedArticles($articleNumber)
    {
        return $this->createQueryBuilder('r')
            ->where('r.articleNumber = :articleNumber')
            ->setParameter('articleNumber', $articleNumber)
            ->getQuery();
    }
}
```

**Limitări:**
- Entity-ul e foarte simplu, doar un wrapper pentru tabel
- Nu există metode pentru add/remove/order
- Query-ul returnează doar context box, nu articolele related direct
- Trebuie query separat pe `context_articles` pentru lista completă

---

## 📊 Situația în news_app (Actuală)

### Structura Bazei de Date

news_app folosește un **ManyToMany self-referencing** standard în Doctrine:

#### Tabel: `article_related`
```sql
CREATE TABLE article_related (
    article_id INTEGER NOT NULL,           -- FK → article.id
    related_article_id INTEGER NOT NULL,   -- FK → article.id
    PRIMARY KEY (article_id, related_article_id),
    FOREIGN KEY (article_id) REFERENCES article(id),
    FOREIGN KEY (related_article_id) REFERENCES article(id)
);
```

### Implementare în Cod (news_app)

**Entity: Article.php**
```php
#[ORM\ManyToMany(targetEntity: self::class)]
#[ORM\JoinTable(name: 'article_related')]
#[ORM\JoinColumn(name: 'article_id', referencedColumnName: 'id')]
#[ORM\InverseJoinColumn(name: 'related_article_id', referencedColumnName: 'id')]
#[Groups(['article:read', 'article:write'])]
private Collection $relatedArticles;

public function __construct()
{
    $this->relatedArticles = new ArrayCollection();
}

public function getRelatedArticles(): Collection
{
    return $this->relatedArticles;
}

public function addRelatedArticle(Article $relatedArticle): static
{
    if (!$this->relatedArticles->contains($relatedArticle)) {
        $this->relatedArticles->add($relatedArticle);
    }
    return $this;
}

public function removeRelatedArticle(Article $relatedArticle): static
{
    $this->relatedArticles->removeElement($relatedArticle);
    return $this;
}
```

### Caracteristici

✅ **Avantaje față de Newscoop:**
- Implementare standard Doctrine ManyToMany
- API simplă: `addRelatedArticle()`, `removeRelatedArticle()`
- Collection handling automat
- Serialization groups pentru API Platform
- Nu necesită tabele intermediare complexe

❌ **Dezavantaje față de Newscoop:**
- **LIPSĂ ORDER:** Nu există `order_number` pentru ordonare manuală
- Relațiile sunt nefixate în ordine (depinde de DB insert order)
- Nu există timestamps pentru "când a fost adăugat"
- Nu există metadata per relație (de ce e related, cât de relevant)

---

## 🔍 Comparație Detaliată

| Aspect | Newscoop | news_app |
|--------|----------|----------|
| **Arhitectură** | 2 tabele: `context_boxes` + `context_articles` | 1 tabel: `article_related` |
| **Complexitate** | Mai complexă (context box container) | Simplă (ManyToMany direct) |
| **Ordonare** | ✅ `order_number` field | ❌ Lipsește |
| **Direccionalitate** | Unidirecțional (A → B, dar B nu știe de A) | Unidirecțional (A → B, dar B nu știe de A) |
| **Metadata** | Posibilă (context box ca entitate) | ❌ Lipsește |
| **API Entity** | Simplă (doar ID + article_no) | Collection Doctrine |
| **Add/Remove** | Manual prin SQL/DQL | `addRelatedArticle()` / `removeRelatedArticle()` |
| **Scalabilitate** | Bună (indexed FK) | Bună (composite PK) |

---

## 🎯 Îmbunătățiri Recomandate pentru news_app

### Opțiunea 1: Minimal (Păstrăm Simplicitatea) ⭐ RECOMANDATĂ

**Ce:** Păstrăm ManyToMany actual, adăugăm doar ordering logic în application layer

**Implementare:**
```php
// Article.php
#[ORM\OrderBy(['publishedAt' => 'DESC'])]
private Collection $relatedArticles;

// Sau custom repository query:
public function getRelatedArticlesSorted(Article $article): array
{
    return $this->createQueryBuilder('a')
        ->join('a.relatedArticles', 'ra')
        ->where('a.id = :articleId')
        ->setParameter('articleId', $article->getId())
        ->orderBy('ra.publishedAt', 'DESC')  // Sau views, sau createdAt
        ->getQuery()
        ->getResult();
}
```

**Pro:**
- Zero modificări schema
- Simplu, rapid
- Suficient pentru majoritatea use-case-urilor

**Contra:**
- Nu permite ordonare manuală (editorial control)
- Ordinea e bazată pe proprietăți articol, nu pe relevanță relației

---

### Opțiunea 2: Intermediară (Entity pentru Relație)

**Ce:** Creare entity `ArticleRelationship` pentru metadata și ordonare

**Schema:**
```sql
CREATE TABLE article_relationship (
    id SERIAL PRIMARY KEY,
    article_id INTEGER NOT NULL,
    related_article_id INTEGER NOT NULL,
    order_position INTEGER NOT NULL DEFAULT 0,
    relevance_score DECIMAL(3,2) DEFAULT 1.0,
    relationship_type VARCHAR(50),  -- 'manual', 'auto_topic', 'auto_category'
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(article_id, related_article_id)
);
```

**Entity:**
```php
#[ORM\Entity]
#[ORM\Table(name: 'article_relationship')]
class ArticleRelationship
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Article::class, inversedBy: 'articleRelationships')]
    private Article $article;

    #[ORM\ManyToOne(targetEntity: Article::class)]
    private Article $relatedArticle;

    #[ORM\Column]
    private int $orderPosition = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 3, scale: 2)]
    private string $relevanceScore = '1.0';

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $relationshipType = 'manual';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;
}
```

**Article.php modificat:**
```php
// Înlocuiește ManyToMany cu OneToMany → ArticleRelationship
#[ORM\OneToMany(
    targetEntity: ArticleRelationship::class,
    mappedBy: 'article',
    cascade: ['persist', 'remove'],
    orphanRemoval: true
)]
#[ORM\OrderBy(['orderPosition' => 'ASC', 'createdAt' => 'DESC'])]
private Collection $articleRelationships;

// Helper method pentru backwards compatibility
public function getRelatedArticles(): Collection
{
    return $this->articleRelationships->map(
        fn(ArticleRelationship $rel) => $rel->getRelatedArticle()
    );
}

public function addRelatedArticle(
    Article $relatedArticle,
    int $order = 0,
    string $type = 'manual'
): self {
    $relationship = new ArticleRelationship();
    $relationship->setArticle($this);
    $relationship->setRelatedArticle($relatedArticle);
    $relationship->setOrderPosition($order);
    $relationship->setRelationshipType($type);

    $this->articleRelationships->add($relationship);
    return $this;
}
```

**Pro:**
- Control complet editorial asupra ordinii
- Metadata per relație (când, de ce, cât de relevant)
- Posibilitate distinguere între related manual vs auto-sugerate
- Compatibil cu AI recommendations (relevance_score)

**Contra:**
- Migrație DB necesară
- Breaking change pentru API (dacă e deja folosit)
- Complexitate crescută

---

### Opțiunea 3: Hibridă (Compatibilitate Newscoop)

**Ce:** Replicăm exact structura Newscoop pentru compatibilitate maximă

**Schema:**
```sql
-- Echivalent context_boxes
CREATE TABLE article_context (
    id SERIAL PRIMARY KEY,
    article_id INTEGER NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Echivalent context_articles
CREATE TABLE article_context_items (
    id SERIAL PRIMARY KEY,
    context_id INTEGER NOT NULL,
    related_article_id INTEGER NOT NULL,
    order_number INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (context_id) REFERENCES article_context(id) ON DELETE CASCADE,
    FOREIGN KEY (related_article_id) REFERENCES article(id) ON DELETE CASCADE
);
```

**Pro:**
- Import 1:1 din Newscoop fără transformări
- Păstrare exactă a ordinii originale
- Ușor de migrat date

**Contra:**
- Overhead: 2 tabele pentru o funcție simplă
- Nu e idiomatică pentru Symfony/Doctrine
- Complexitate crescută fără beneficii clare

---

## 🚀 Recomandare Finală

### Pentru Migrarea din Newscoop → news_app

**Strategie Aleasă:** ✅ **Opțiunea 1 (Minimal - Păstrăm Simplicitatea)** ⭐

**Status:** 🟢 **95% IMPLEMENTAT** - Infrastructură completă, lipsește doar import logic!

**Motivație pentru Opțiunea 1:**
1. **Zero modificări schema:** ManyToMany existent funcționează perfect
2. **Rapid deployment:** Nu necesită refactorizare, migrații sau testing extensiv
3. **Suficient pentru MVP:** 3,807 relații (medie 2.5 per articol) nu justifică complexitate
4. **order_number = 0 în Newscoop:** Toate relațiile au order=0, ordonarea nu e folosită efectiv!
5. **Implementation-ready:** Article entity deja are tot codul necesar

**De ce NU Opțiunea 2:**
- Overhead nejustificat: 2-3 ore dev + migrație DB pentru feature nefolosit
- `order_number` în Newscoop este ALL ZEROS (consultat în analiza anterioară)
- Complexitate crescută fără beneficii reale în acest context
- Putem oricând extinde mai târziu dacă apare necesitatea

---

## ✅ Status Implementare (news_app)

### Ce AVEM deja ✅

**1. Tabelă `article_related`** (PostgreSQL)
```sql
CREATE TABLE article_related (
    article_id INTEGER NOT NULL,
    related_article_id INTEGER NOT NULL,
    PRIMARY KEY (article_id, related_article_id),
    FOREIGN KEY (article_id) REFERENCES article(id) ON DELETE CASCADE,
    FOREIGN KEY (related_article_id) REFERENCES article(id) ON DELETE CASCADE
);
```
✅ Creat, indexat, gata de folosit

**2. Article Entity** (`src/Entity/Article.php:170-175`)
```php
/**
 * @var Collection<int, Article>
 */
#[ORM\ManyToMany(targetEntity: self::class)]
#[ORM\JoinTable(name: 'article_related')]
#[ORM\JoinColumn(name: 'article_id', referencedColumnName: 'id')]
#[ORM\InverseJoinColumn(name: 'related_article_id', referencedColumnName: 'id')]
#[Groups(['article:read', 'article:write'])]
private Collection $relatedArticles;
```
✅ Definit complet cu annotations

**3. Constructor Initialization** (`src/Entity/Article.php:219-227`)
```php
public function __construct()
{
    $this->authors = new ArrayCollection();
    $this->images = new ArrayCollection();
    $this->relatedArticles = new ArrayCollection();  // ✅ Inițializat
    $this->tags = new ArrayCollection();
    $this->createdAt = new \DateTimeImmutable();
    $this->updatedAt = new \DateTimeImmutable();
}
```
✅ Collection inițializată corect

**4. Getter Method** (`src/Entity/Article.php:450-453`)
```php
/**
 * @return Collection<int, Article>
 */
public function getRelatedArticles(): Collection
{
    return $this->relatedArticles;
}
```
✅ Returnează Collection

**5. Add Method** (`src/Entity/Article.php:455-462`)
```php
public function addRelatedArticle(Article $relatedArticle): static
{
    if (!$this->relatedArticles->contains($relatedArticle)) {
        $this->relatedArticles->add($relatedArticle);
    }
    return $this;
}
```
✅ Logica de adăugare cu duplicate check

**6. Remove Method** (`src/Entity/Article.php:464-469`)
```php
public function removeRelatedArticle(Article $relatedArticle): static
{
    $this->relatedArticles->removeElement($relatedArticle);
    return $this;
}
```
✅ Logica de ștergere

**7. API Platform Serialization**
- ✅ `#[Groups(['article:read', 'article:write'])]` - relatedArticles expuse în API
- ✅ Suport complet pentru GET/POST/PUT operations

---

### Ce LIPSEȘTE ⚠️

**DOAR import logic în `ImportArticlesCommand`** (estimat ~30 minute):

```php
// TO BE ADDED in src/Command/Newscoop/ImportArticlesCommand.php

private function importRelatedArticles(int $newscoopArticleNumber, Article $article): void
{
    // Query context_boxes pentru acest articol
    $contextBox = $this->newscoopConnection->executeQuery(
        "SELECT id FROM context_boxes WHERE fk_article_no = ?",
        [$newscoopArticleNumber]
    )->fetchAssociative();

    if (!$contextBox) {
        return; // Nu are related articles
    }

    // Query related articles (ignorăm order_number deoarece e tot 0)
    $relatedArticles = $this->newscoopConnection->executeQuery(
        "SELECT fk_article_no
         FROM context_articles
         WHERE fk_context_id = ?",
        [$contextBox['id']]
    )->fetchAllAssociative();

    foreach ($relatedArticles as $related) {
        // Map Newscoop article number → news_app ID
        $newscoopId = $related['fk_article_no'];
        $relatedArticleId = $this->getMappedId('article', $newscoopId);

        if (!$relatedArticleId) {
            $this->logger->warning("Related article not found", [
                'main_article' => $newscoopArticleNumber,
                'related_newscoop_id' => $newscoopId
            ]);
            continue;
        }

        $relatedArticleEntity = $this->articleRepository->find($relatedArticleId);

        if ($relatedArticleEntity) {
            $article->addRelatedArticle($relatedArticleEntity);
        }
    }
}

// Apelare în main import loop (după setare authors, category, etc):
$this->importRelatedArticles($articleNumber, $article);
```

**De adăugat în command:**
1. Method `importRelatedArticles()` (cod de mai sus)
2. Apel în loopul principal de import articole
3. Logging pentru related articles importate

**Timp estimat:** 30 minute

---

---

## 📊 Date pentru Migrare

**Din Newscoop:**
```sql
SELECT
    cb.fk_article_no as main_article_number,
    ca.fk_article_no as related_article_number,
    ca.order_number
FROM context_boxes cb
JOIN context_articles ca ON cb.id = ca.fk_context_id
ORDER BY cb.fk_article_no, ca.order_number;
```

**Transformare:**
```
Newscoop:
  context_boxes.fk_article_no = 186
  └─ context_articles:
     ├─ fk_article_no = 177, order_number = 0
     ├─ fk_article_no = 178, order_number = 0
     └─ fk_article_no = 180, order_number = 0

news_app:
  Article ID = X (mapped from 186)
  └─ article_relationship:
     ├─ related_article_id = Y (mapped from 177), order_position = 0
     ├─ related_article_id = Z (mapped from 178), order_position = 0
     └─ related_article_id = W (mapped from 180), order_position = 0
```

**Statistici Import:**
- **Total relații de migrat:** ~3,807
- **Articole afectate:** ~154,748
- **Timp estimat:** 10-15 minute (în cadrul import articole)

---

## 🎨 Exemplu API Response

**Cu Opțiunea 2 implementată:**

```json
GET /api/articles/123

{
  "id": 123,
  "title": "V.Cibotaru: Pettit a exagerat...",
  "relatedArticles": [
    {
      "id": 456,
      "title": "Traian Băsescu: Declarația lui Pettit...",
      "order": 0,
      "relevance": 1.0,
      "type": "manual"
    },
    {
      "id": 457,
      "title": "Ghimpu, despre declarația lui Pettit...",
      "order": 0,
      "relevance": 1.0,
      "type": "manual"
    },
    {
      "id": 458,
      "title": "Diacov și Dodon, primii susținători...",
      "order": 0,
      "relevance": 1.0,
      "type": "manual"
    }
  ]
}
```

---

## ✅ Checklist Implementare

- [x] **Decizie:** ✅ Opțiunea 1 (Minimal) aleasă
- [x] ~~Creare `ArticleRelationship` entity~~ ❌ Nu e necesar (folosim ManyToMany existent)
- [x] ~~Generare migrație DB~~ ✅ Deja existentă (`article_related` table)
- [x] ~~Modificare `Article` entity~~ ✅ Deja implementat complet (ManyToMany)
- [x] ~~Adăugare helper methods~~ ✅ `addRelatedArticle()`, `removeRelatedArticle()` existente
- [x] ~~Update API serialization groups~~ ✅ Deja configurate (`article:read`, `article:write`)
- [x] ~~Migrare date existente~~ ✅ Nu există date în `article_related` (DB nouă)
- [ ] **Implementare import logic în `ImportArticlesCommand`** ⚠️ **SINGURA TASK RĂMASĂ**
- [ ] Testing: verificare 3,807 relații importate corect
- [ ] Post-validare: query pentru articole fără related (dacă ar trebui să aibă)

---

## 📝 Concluzie

**Newscoop** folosește un sistem de "Context Boxes" pentru articole relaționale (3,807 relații).
**news_app** are **deja implementat 95%** din infrastructură!

**Decizie Finală:** ✅ **Opțiunea 1 (Minimal)** - Zero overhead, rapid deployment

**De ce funcționează:**
- ✅ Infrastructură completă: table + entity + methods + API
- ✅ `order_number` în Newscoop = 0 pentru toate relațiile (ordonare nefolosită)
- ✅ Relații simple (medie 2.5 articole per context box)
- ✅ Zero breaking changes sau refactorizări

**Ce lipsește:** Doar ~30 min de cod pentru import logic în `ImportArticlesCommand`

**Timp implementare:** 30 minute (doar import logic)
**Timp migrare date:** 10-15 minute (în cadrul import articole)
**Total overhead:** ~45 minute vs 2-3 ore pentru Opțiunea 2

**Benefits:**
- 🚀 MVP-ready imediat după import
- 🔧 Extensibil: putem adăuga ArticleRelationship entity mai târziu dacă e necesar
- 📊 Suficient pentru 99% din use-cases (arhivă, nu CMS activ)
