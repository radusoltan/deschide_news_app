<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\PressRelease;
use App\Entity\Tag;
use App\Entity\Topic;
use App\Enum\ArticleStatus;
use App\Enum\PressReleaseStatus;
use App\Enum\SourceType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Gedmo\Translatable\Entity\Translation;

/**
 * Synthetic pipeline fixtures: 10 PressReleases + 3 draft Articles.
 * Exercises aggregator → editorial workflow pipeline.
 */
class LivePipelineSampleFixture extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    public static function getGroups(): array
    {
        return ['dev', 'live-pipeline'];
    }

    public function getDependencies(): array
    {
        return [
            CategoryFixtures::class,
            TopicFixture::class,
            TagFixture::class,
            UserFixtures::class,
            AuthorFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $translationRepo = $manager->getRepository(Translation::class);

        // === Press Releases ===
        $pressReleases = $this->createPressReleases($manager);
        echo "  Created " . \count($pressReleases) . " press releases\n";

        // === Draft Articles ===
        $this->createDraftArticles($manager, $translationRepo);
        echo "  Created 3 draft articles with topics + tags\n";
    }

    /**
     * @return array<string, PressRelease> keyed by identifier
     */
    private function createPressReleases(ObjectManager $manager): array
    {
        $prs = [];
        $now = new \DateTimeImmutable();

        $data = [
            // Cluster 1: EU Negotiations (4 PRs)
            ['id' => 'zdg-c1', 'title' => 'Cluster 1 al negocierilor cu UE deschis oficial', 'source' => 'Ziarul de Gardă', 'url' => 'https://www.zdg.md/stiri/politic/cluster-1-deschis/', 'cat' => 'politica', 'score' => 0.94, 'group' => 'c1'],
            ['id' => 'nm-c1', 'title' => 'Moldova începe oficial negocierile pe Cluster 1', 'source' => 'NewsMaker', 'url' => 'https://newsmaker.md/ro/moldova-cluster-1/', 'cat' => 'politica', 'score' => 0.90, 'group' => 'c1'],
            ['id' => 'tv8-c1', 'title' => 'Cluster 1 UE: primele sesiuni tehnice', 'source' => 'TV8', 'url' => 'https://tv8.md/2026/cluster-1-sesiuni/', 'cat' => 'politica', 'score' => 0.88, 'group' => 'c1'],
            ['id' => 'mp-c1', 'title' => 'Comunicat Moldpres: Cluster 1 de negocieri UE deschis', 'source' => 'Moldpres', 'url' => 'https://moldpres.md/news/cluster-1-eu', 'cat' => 'politica', 'score' => 0.95, 'group' => 'c1'],
            // Cluster 2: Transnistria Energy (3 PRs)
            ['id' => 'nm-c2', 'title' => 'Criza gazelor în stânga Nistrului se agravează', 'source' => 'NewsMaker', 'url' => 'https://newsmaker.md/ro/criza-gaze-transnistria/', 'cat' => 'politica', 'score' => 0.89, 'group' => 'c2'],
            ['id' => 'ipn-c2', 'title' => 'IPN: Transnistria fără gaz — situația la zi', 'source' => 'IPN', 'url' => 'https://ipn.md/ro/transnistria-gaz/', 'cat' => 'politica', 'score' => 0.91, 'group' => 'c2'],
            ['id' => 'pt-c2', 'title' => 'Point.md: Locuitorii din stânga Nistrului se confruntă cu frig', 'source' => 'Point.md', 'url' => 'https://point.md/ro/novosti/transnistria-frig/', 'cat' => 'politica', 'score' => 0.78, 'group' => 'c2'],
            // Cluster 3: Pension Reform (2 PRs)
            ['id' => 'mp-c3', 'title' => 'Reforma pensiilor: noile condiții de pensionare', 'source' => 'Moldpres', 'url' => 'https://moldpres.md/news/reforma-pensii-2026', 'cat' => 'economie', 'score' => 0.85, 'group' => 'c3'],
            ['id' => 'jn-c3', 'title' => 'Pensionarii vor primi compensații de la 1 aprilie', 'source' => 'Jurnal.md', 'url' => 'https://jurnal.md/ro/pensii-compensatii/', 'cat' => 'economie', 'score' => 0.82, 'group' => 'c3'],
            // Orphan (unclustered)
            ['id' => 'gov-orphan', 'title' => 'Comunicat Gov.md: Programul național de digitalizare', 'source' => 'Gov.md', 'url' => 'https://gov.md/comunicat-digitalizare', 'cat' => 'societate', 'score' => 1.00, 'group' => null],
        ];

        foreach ($data as $i => $d) {
            $pr = new PressRelease();
            $pr->setTitle($d['title']);
            $pr->setContent("Conținutul complet al comunicatului de presă de la {$d['source']}. Acesta include detalii despre subiectul abordat, citate oficiale și context suplimentar pentru redacția editorială. Textul are aproximativ 300 de cuvinte pentru a simula un comunicat real.");
            $pr->setSourceUrl($d['url']);
            $pr->setSourceName($d['source']);
            $pr->setCategorySlug($d['cat']);
            $pr->setSourceType(SourceType::SCRAPE);
            $pr->setContentHash(md5($d['id'] . '-fixture'));
            $pr->setRelevanceScore($d['score']);
            $pr->setStatus($d['group'] !== null ? PressReleaseStatus::APPROVED : PressReleaseStatus::PENDING);

            $receivedAt = $now->modify('-' . (10 - $i) . ' hours');
            $pr->setReceivedAt($receivedAt);

            $manager->persist($pr);
            $manager->flush();

            $prs[$d['id']] = $pr;
        }

        return $prs;
    }

    private function createDraftArticles(ObjectManager $manager, mixed $translationRepo): void
    {
        $catPolitica = $this->getReference('category-politica', Category::class);
        $catEditoriale = $this->getReference('category-editoriale', Category::class);
        $catOpinii = $this->getReference('category-opinii', Category::class);

        // Draft #1: AI-generated from Cluster 1 (pending review)
        $draft1 = new Article();
        $draft1->setTranslatableLocale('ro');
        $draft1->setTitle('Moldova deschide oficial Clusterul 1 al negocierilor cu UE');
        $draft1->setSlug('moldova-deschide-cluster-1-negocieri-ue-draft');
        $draft1->setLead('Republica Moldova a început oficial negocierile pe Clusterul 1 al procesului de aderare la Uniunea Europeană, un moment istoric care marchează o nouă etapă în relația dintre Chișinău și Bruxelles.');
        $draft1->setContent($this->getEuDraftContent());
        $draft1->setCategory($catPolitica);
        $draft1->setStatus(ArticleStatus::SUBMITTED);
        $draft1->setAiGenerated(true);
        $draft1->setAiConfidenceScore(0.94);

        $manager->persist($draft1);
        $manager->flush();

        // Override slug (Gedmo may regenerate from title)
        if ($draft1->getSlug() !== 'moldova-deschide-cluster-1-negocieri-ue-draft') {
            $draft1->setSlug('moldova-deschide-cluster-1-negocieri-ue-draft');
            $manager->flush();
        }

        $translationRepo->translate($draft1, 'title', 'en', 'Moldova officially opens Cluster 1 of EU negotiations');
        $translationRepo->translate($draft1, 'title', 'ru', 'Молдова официально открывает Кластер 1 переговоров с ЕС');
        $manager->flush();

        // Attach topics
        if ($this->hasReference('topic-moldova-eu-accession-clusters', Topic::class)) {
            $draft1->addTopic($this->getReference('topic-moldova-eu-accession-clusters', Topic::class));
        }
        if ($this->hasReference('topic-moldova-eu-growth-plan-financial-package', Topic::class)) {
            $draft1->addTopic($this->getReference('topic-moldova-eu-growth-plan-financial-package', Topic::class));
        }
        // Attach tags
        $this->attachTagsBySlug($manager, $draft1, ['ue', 'negocieri', 'cluster-1', 'aderare', 'bruxelles']);

        $manager->flush();

        // Draft #2: Editorial op-ed
        $draft2 = new Article();
        $draft2->setTranslatableLocale('ro');
        $draft2->setTitle('Editorial săptămânal: Alegerile din 2025 și drumul spre Europa');
        $draft2->setSlug('editorial-saptamanal-aprilie-2026-draft');
        $draft2->setLead('O analiză a contextului politic din Republica Moldova la un an după alegerile parlamentare.');
        $draft2->setContent($this->getEditorialContent());
        $draft2->setCategory($catEditoriale);
        $draft2->setStatus(ArticleStatus::NEW);
        $draft2->setAiGenerated(false);

        $manager->persist($draft2);
        $manager->flush();

        if ($draft2->getSlug() !== 'editorial-saptamanal-aprilie-2026-draft') {
            $draft2->setSlug('editorial-saptamanal-aprilie-2026-draft');
            $manager->flush();
        }

        if ($this->hasReference('topic-moldova-parliamentary-elections-2025', Topic::class)) {
            $draft2->addTopic($this->getReference('topic-moldova-parliamentary-elections-2025', Topic::class));
        }
        $this->attachTagsBySlug($manager, $draft2, ['saptamana-politica', 'analiza-editorial', 'alegeri']);
        $manager->flush();

        // Draft #3: Opinion on pension reform
        $draft3 = new Article();
        $draft3->setTranslatableLocale('ro');
        $draft3->setTitle('Opinie: Reforma pensiilor — cine câștigă și cine pierde?');
        $draft3->setSlug('opinie-reforma-pensii-draft');
        $draft3->setLead('Noile modificări ale sistemului de pensii ridică întrebări legitime despre echitatea socială.');
        $draft3->setContent($this->getOpinionContent());
        $draft3->setCategory($catOpinii);
        $draft3->setStatus(ArticleStatus::NEW);
        $draft3->setAiGenerated(false);

        $manager->persist($draft3);
        $manager->flush();

        if ($draft3->getSlug() !== 'opinie-reforma-pensii-draft') {
            $draft3->setSlug('opinie-reforma-pensii-draft');
            $manager->flush();
        }

        if ($this->hasReference('topic-moldova-pensions-cnas-social-protection', Topic::class)) {
            $draft3->addTopic($this->getReference('topic-moldova-pensions-cnas-social-protection', Topic::class));
        }
        $this->attachTagsBySlug($manager, $draft3, ['opinie', 'pensii', 'reforma-sociala']);
        $manager->flush();
    }

    /**
     * @param list<string> $slugs
     */
    private function attachTagsBySlug(ObjectManager $manager, Article $article, array $slugs): void
    {
        $tagRepo = $manager->getRepository(Tag::class);
        foreach ($slugs as $slug) {
            $tag = $tagRepo->findOneBy(['slug' => $slug]);
            if ($tag !== null) {
                $article->addTag($tag);
            }
        }
    }

    private function getEuDraftContent(): string
    {
        return <<<'HTML'
<p>Republica Moldova a deschis oficial Clusterul 1 al negocierilor de aderare la Uniunea Europeană, un moment istoric care marchează trecerea de la statutul de candidat la cel de stat în curs de negociere activă.</p>
<p>Clusterul 1, denumit „Fundamentale", cuprinde capitolele privind statul de drept, funcționarea instituțiilor democratice, reforma justiției, combaterea corupției și respectarea drepturilor fundamentale ale omului. Acesta este considerat cel mai complex și mai important cluster în procesul de aderare, fiind de obicei primul deschis și ultimul închis.</p>
<p>Ministrul Afacerilor Externe a declarat: „Deschiderea Clusterului 1 reprezintă rezultatul eforturilor consecvente ale Republicii Moldova în implementarea reformelor necesare. Ne angajăm să continuăm pe acest drum cu determinare și transparență."</p>
<p>Comisia Europeană a subliniat progresele înregistrate de Moldova în domeniul reformei justiției și al combaterii corupției, notând totodată că mai există provocări semnificative care trebuie abordate.</p>
<p>Analiștii politici consideră că deschiderea negocierilor pe Clusterul 1 va accelera procesul de transformare instituțională și va consolida angajamentul democratic al țării.</p>
HTML;
    }

    private function getEditorialContent(): string
    {
        return <<<'HTML'
<p>La un an de la alegerile parlamentare din 2025, peisajul politic din Republica Moldova continuă să fie marcat de tensiuni între forțele pro-europene și opoziția care contestă legitimitatea procesului electoral.</p>
<p>Guvernul actual, format în urma alegerilor, a reușit să mențină cursul european al țării, culminând cu deschiderea negocierilor de aderare la UE. Cu toate acestea, provocările interne rămân semnificative: inflația, criza energetică și presiunea externă continuă să testeze reziliența instituțiilor statului.</p>
<p>Opoziția parlamentară critică ritmul reformelor și acuză executivul de lipsa de transparență în negocierile cu partenerii europeni. Pe de altă parte, sondajele arată că majoritatea populației susține în continuare cursul european, deși cu rezerve tot mai mari privind impactul social al reformelor.</p>
<p>Anul 2026 se anunță a fi decisiv pentru parcursul european al Moldovei. Succesul negocierilor pe Clusterul 1 ar putea cataliza reformele necesare, iar eșecul ar risca să submineze încrederea publică în proiectul european.</p>
HTML;
    }

    private function getOpinionContent(): string
    {
        return <<<'HTML'
<p>Reforma sistemului de pensii, anunțată de guvern la începutul anului 2026, a generat reacții contradictorii în societate. În timp ce autoritățile prezintă noile condiții de pensionare ca pe o modernizare necesară, mulți cetățeni se întreabă dacă schimbările vor îmbunătăți sau vor agrava situația pensionarilor.</p>
<p>Printre modificările principale se numără majorarea treptată a vârstei de pensionare, introducerea unui sistem de puncte și revizuirea formulei de calcul a pensiei. Guvernul argumentează că aceste măsuri sunt necesare pentru a asigura sustenabilitatea bugetului de asigurări sociale pe termen lung.</p>
<p>Cu toate acestea, organizațiile de pensionari atrag atenția că în condițiile unei speranțe de viață relativ scăzute, majorarea vârstei de pensionare echivalează cu o reducere de facto a drepturilor sociale. De asemenea, cei cu stagii de cotizare incomplete — în special femeile din mediul rural — riscă să fie dezavantajați de noul sistem de puncte.</p>
<p>Reformele trebuie să echilibreze sustenabilitatea fiscală cu echitatea socială. Altfel, riscăm să creăm un sistem modern pe hârtie, dar inechitabil în practică.</p>
HTML;
    }
}
