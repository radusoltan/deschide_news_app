<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Source;
use App\Enum\SourceCategory;
use App\Repository\SourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:source:seed-diaspora',
    description: 'Seed diaspora media RSS sources (IT/FR/DE/UK/IL/PT/ES/IE/CA/RU/TR/GR/CY/RO) for clustering',
)]
class SeedDiasporaSourcesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SourceRepository $sourceRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'List sources without inserting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        $io->title('Seeding Diaspora Media Sources');

        $sources = $this->getSourceDefinitions();
        $created = 0;
        $updated = 0;

        foreach ($sources as $def) {
            $existing = $this->sourceRepository->findByName($def['name']);

            if ($dryRun) {
                $status = $existing ? 'EXISTS' : 'NEW';
                $io->writeln(sprintf(
                    '  [%s] %s (%.2f, %s, %s)',
                    $status,
                    $def['name'],
                    $def['credibilityWeight'],
                    $def['country'],
                    $def['sourceCategory']->value,
                ));
                continue;
            }

            if ($existing !== null) {
                $existing->setCredibilityWeight($def['credibilityWeight']);
                $existing->setCountry($def['country']);
                $existing->setSourceCategory($def['sourceCategory']);
                if ($def['domainPattern'] !== null) {
                    $existing->setDomainPattern($def['domainPattern']);
                }
                $updated++;
            } else {
                $source = new Source();
                $source->setName($def['name']);
                $source->setRssUrl($def['rssUrl']);
                $source->setCredibilityWeight($def['credibilityWeight']);
                $source->setCountry($def['country']);
                $source->setSourceCategory($def['sourceCategory']);
                $source->setFetchFrequencyMinutes($def['fetchFrequencyMinutes']);
                $source->setIsActive(true);
                $source->setType('rss');
                $source->setDomainPattern($def['domainPattern']);

                $this->em->persist($source);
                $created++;
            }
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        $io->success(sprintf(
            '%s: %d created, %d updated (total diaspora: %d)',
            $dryRun ? 'DRY RUN' : 'Done',
            $created,
            $updated,
            \count($sources),
        ));

        return Command::SUCCESS;
    }

    /**
     * @return list<array{name: string, rssUrl: string, credibilityWeight: float, country: string, sourceCategory: SourceCategory, fetchFrequencyMinutes: int, domainPattern: ?string}>
     */
    private function getSourceDefinitions(): array
    {
        return [
            // === ITALIA (large Moldovan diaspora) ===
            [
                'name' => 'Stranieri in Italia',
                'rssUrl' => 'https://stranieriinitalia.it/feed/',
                'credibilityWeight' => 0.75,
                'country' => 'IT',
                'sourceCategory' => SourceCategory::DIASPORA,
                'fetchFrequencyMinutes' => 120,
                'domainPattern' => 'stranieriinitalia.it',
            ],
            [
                'name' => 'Il Gazzettino Veneto',
                'rssUrl' => 'https://www.ilgazzettino.it/rss/nordest.xml',
                'credibilityWeight' => 0.80,
                'country' => 'IT',
                'sourceCategory' => SourceCategory::REGIONAL,
                'fetchFrequencyMinutes' => 120,
                'domainPattern' => 'ilgazzettino.it',
            ],
            [
                'name' => 'Il Resto del Carlino',
                'rssUrl' => 'https://www.ilrestodelcarlino.it/rss',
                'credibilityWeight' => 0.80,
                'country' => 'IT',
                'sourceCategory' => SourceCategory::REGIONAL,
                'fetchFrequencyMinutes' => 120,
                'domainPattern' => 'ilrestodelcarlino.it',
            ],
            [
                'name' => 'Adnkronos',
                'rssUrl' => 'https://rss.adnkronos.com/RSS_PrimaPagina.xml',
                'credibilityWeight' => 0.85,
                'country' => 'IT',
                'sourceCategory' => SourceCategory::AGENCY,
                'fetchFrequencyMinutes' => 60,
                'domainPattern' => 'adnkronos.com',
            ],
            [
                'name' => 'Roma Today',
                'rssUrl' => 'https://www.romatoday.it/rss/',
                'credibilityWeight' => 0.70,
                'country' => 'IT',
                'sourceCategory' => SourceCategory::REGIONAL,
                'fetchFrequencyMinutes' => 120,
                'domainPattern' => 'romatoday.it',
            ],

            // === FRANȚA ===
            [
                'name' => 'Ouest-France',
                'rssUrl' => 'https://www.ouest-france.fr/rss-en-continu.xml',
                'credibilityWeight' => 0.80,
                'country' => 'FR',
                'sourceCategory' => SourceCategory::REGIONAL,
                'fetchFrequencyMinutes' => 120,
                'domainPattern' => 'ouest-france.fr',
            ],
            [
                'name' => 'Franceinfo',
                'rssUrl' => 'https://www.francetvinfo.fr/titres.rss',
                'credibilityWeight' => 0.85,
                'country' => 'FR',
                'sourceCategory' => SourceCategory::GENERALIST,
                'fetchFrequencyMinutes' => 60,
                'domainPattern' => 'francetvinfo.fr',
            ],
            [
                'name' => 'Le Parisien',
                'rssUrl' => 'https://www.leparisien.fr/arc/outboundfeeds/rss/',
                'credibilityWeight' => 0.80,
                'country' => 'FR',
                'sourceCategory' => SourceCategory::GENERALIST,
                'fetchFrequencyMinutes' => 120,
                'domainPattern' => 'leparisien.fr',
            ],

            // === GERMANIA ===
            [
                'name' => 'tagesschau.de',
                'rssUrl' => 'https://www.tagesschau.de/xml/rss2',
                'credibilityWeight' => 0.90,
                'country' => 'DE',
                'sourceCategory' => SourceCategory::GENERALIST,
                'fetchFrequencyMinutes' => 60,
                'domainPattern' => 'tagesschau.de',
            ],
            [
                'name' => 'ZEIT ONLINE',
                'rssUrl' => 'https://newsfeed.zeit.de/index',
                'credibilityWeight' => 0.85,
                'country' => 'DE',
                'sourceCategory' => SourceCategory::GENERALIST,
                'fetchFrequencyMinutes' => 120,
                'domainPattern' => 'zeit.de',
            ],

            // === UK ===
            [
                'name' => 'London Evening Standard',
                'rssUrl' => 'https://www.standard.co.uk/rss',
                'credibilityWeight' => 0.80,
                'country' => 'GB',
                'sourceCategory' => SourceCategory::REGIONAL,
                'fetchFrequencyMinutes' => 120,
                'domainPattern' => 'standard.co.uk',
            ],
            [
                'name' => 'Manchester Evening News',
                'rssUrl' => 'https://www.manchestereveningnews.co.uk/news/?service=rss',
                'credibilityWeight' => 0.75,
                'country' => 'GB',
                'sourceCategory' => SourceCategory::REGIONAL,
                'fetchFrequencyMinutes' => 120,
                'domainPattern' => 'manchestereveningnews.co.uk',
            ],

            // === ISRAEL ===
            [
                'name' => 'Times of Israel',
                'rssUrl' => 'https://www.timesofisrael.com/feed/',
                'credibilityWeight' => 0.75,
                'country' => 'IL',
                'sourceCategory' => SourceCategory::GENERALIST,
                'fetchFrequencyMinutes' => 120,
                'domainPattern' => 'timesofisrael.com',
            ],
            [
                'name' => 'Jerusalem Post',
                'rssUrl' => 'https://www.jpost.com/rss/rssfeedsfrontpage.aspx',
                'credibilityWeight' => 0.75,
                'country' => 'IL',
                'sourceCategory' => SourceCategory::GENERALIST,
                'fetchFrequencyMinutes' => 120,
                'domainPattern' => 'jpost.com',
            ],

            // === PORTUGALIA ===
            [
                'name' => 'Público',
                'rssUrl' => 'https://feeds.feedburner.com/PublicoRSS',
                'credibilityWeight' => 0.80,
                'country' => 'PT',
                'sourceCategory' => SourceCategory::GENERALIST,
                'fetchFrequencyMinutes' => 120,
                'domainPattern' => 'publico.pt',
            ],

            // === ROMÂNIA (supplementary, MD interest) ===
            [
                'name' => 'G4Media',
                'rssUrl' => 'https://www.g4media.ro/feed',
                'credibilityWeight' => 0.85,
                'country' => 'RO',
                'sourceCategory' => SourceCategory::GENERALIST,
                'fetchFrequencyMinutes' => 60,
                'domainPattern' => 'g4media.ro',
            ],
            [
                'name' => 'Ziarul de Iași',
                'rssUrl' => 'https://www.ziaruldeiasi.ro/rss/',
                'credibilityWeight' => 0.75,
                'country' => 'RO',
                'sourceCategory' => SourceCategory::REGIONAL,
                'fetchFrequencyMinutes' => 120,
                'domainPattern' => 'ziaruldeiasi.ro',
            ],

            // === META / DIASPORA FOCUS ===
            [
                'name' => 'InfoMigrants',
                'rssUrl' => 'https://www.infomigrants.net/en/rss',
                'credibilityWeight' => 0.75,
                'country' => 'DE',
                'sourceCategory' => SourceCategory::DIASPORA,
                'fetchFrequencyMinutes' => 120,
                'domainPattern' => 'infomigrants.net',
            ],
            [
                'name' => 'Moldovan Diaspora Network',
                'rssUrl' => 'https://bfrm.md/rss/news-dn.xml',
                'credibilityWeight' => 0.65,
                'country' => 'MD',
                'sourceCategory' => SourceCategory::DIASPORA,
                'fetchFrequencyMinutes' => 120,
                'domainPattern' => 'bfrm.md',
            ],

            // ============================================================
            // WAVE 2 — Sprint 33 additions (~31 sources)
            // ============================================================

            // === ITALIA (Wave 2) ===
            [
                'name' => 'ANSA',
                'rssUrl' => 'https://www.ansa.it/sito/notizie/topnews/topnews_rss.xml',
                'credibilityWeight' => 0.90,
                'country' => 'IT',
                'sourceCategory' => SourceCategory::AGENCY,
                'fetchFrequencyMinutes' => 60,
                'domainPattern' => 'ansa.it',
            ],
            [
                'name' => 'La Repubblica',
                'rssUrl' => 'https://www.repubblica.it/rss/homepage/rss2.0.xml',
                'credibilityWeight' => 0.85,
                'country' => 'IT',
                'sourceCategory' => SourceCategory::GENERALIST,
                'fetchFrequencyMinutes' => 60,
                'domainPattern' => 'repubblica.it',
            ],
            [
                'name' => 'Corriere della Sera',
                'rssUrl' => 'https://xml2.corriereobjects.it/rss/homepage.xml',
                'credibilityWeight' => 0.85,
                'country' => 'IT',
                'sourceCategory' => SourceCategory::GENERALIST,
                'fetchFrequencyMinutes' => 60,
                'domainPattern' => 'corriere.it',
            ],
            [
                'name' => 'Il Sole 24 Ore',
                'rssUrl' => 'https://www.ilsole24ore.com/rss/mondo.xml',
                'credibilityWeight' => 0.85,
                'country' => 'IT',
                'sourceCategory' => SourceCategory::BUSINESS,
                'fetchFrequencyMinutes' => 120,
                'domainPattern' => 'ilsole24ore.com',
            ],
            [
                'name' => 'Il Messaggero',
                'rssUrl' => 'https://www.ilmessaggero.it/rss/home.xml',
                'credibilityWeight' => 0.75,
                'country' => 'IT',
                'sourceCategory' => SourceCategory::REGIONAL,
                'fetchFrequencyMinutes' => 120,
                'domainPattern' => 'ilmessaggero.it',
            ],

            // === FRANȚA (Wave 2) ===
            [
                'name' => 'Le Monde',
                'rssUrl' => 'https://www.lemonde.fr/rss/une.xml',
                'credibilityWeight' => 0.90,
                'country' => 'FR',
                'sourceCategory' => SourceCategory::GENERALIST,
                'fetchFrequencyMinutes' => 60,
                'domainPattern' => 'lemonde.fr',
            ],
            [
                'name' => 'Le Figaro',
                'rssUrl' => 'https://www.lefigaro.fr/rss/figaro_flash-actu.xml',
                'credibilityWeight' => 0.85,
                'country' => 'FR',
                'sourceCategory' => SourceCategory::GENERALIST,
                'fetchFrequencyMinutes' => 60,
                'domainPattern' => 'lefigaro.fr',
            ],
            [
                'name' => '20 Minutes',
                'rssUrl' => 'https://www.20minutes.fr/feeds/rss-une.xml',
                'credibilityWeight' => 0.75,
                'country' => 'FR',
                'sourceCategory' => SourceCategory::GENERALIST,
                'fetchFrequencyMinutes' => 120,
                'domainPattern' => '20minutes.fr',
            ],

            // === RUSIA (Wave 2 — Moldovan diaspora context) ===
            [
                'name' => 'Interfax',
                'rssUrl' => 'https://www.interfax.ru/rss.asp',
                'credibilityWeight' => 0.70,
                'country' => 'RU',
                'sourceCategory' => SourceCategory::AGENCY,
                'fetchFrequencyMinutes' => 60,
                'domainPattern' => 'interfax.ru',
            ],
            [
                'name' => 'Kommersant',
                'rssUrl' => 'https://www.kommersant.ru/RSS/news.xml',
                'credibilityWeight' => 0.70,
                'country' => 'RU',
                'sourceCategory' => SourceCategory::GENERALIST,
                'fetchFrequencyMinutes' => 120,
                'domainPattern' => 'kommersant.ru',
            ],
            [
                'name' => 'Meduza',
                'rssUrl' => 'https://meduza.io/rss/all',
                'credibilityWeight' => 0.80,
                'country' => 'LV',
                'sourceCategory' => SourceCategory::GENERALIST,
                'fetchFrequencyMinutes' => 60,
                'domainPattern' => 'meduza.io',
            ],
            [
                'name' => 'Novaya Gazeta Europe',
                'rssUrl' => 'https://novayagazeta.eu/rss',
                'credibilityWeight' => 0.80,
                'country' => 'LV',
                'sourceCategory' => SourceCategory::GENERALIST,
                'fetchFrequencyMinutes' => 120,
                'domainPattern' => 'novayagazeta.eu',
            ],

            // === UK (Wave 2) ===
            [
                'name' => 'The Independent',
                'rssUrl' => 'https://www.independent.co.uk/news/world/europe/rss',
                'credibilityWeight' => 0.80,
                'country' => 'GB',
                'sourceCategory' => SourceCategory::GENERALIST,
                'fetchFrequencyMinutes' => 120,
                'domainPattern' => 'independent.co.uk',
            ],
            [
                'name' => 'The Telegraph',
                'rssUrl' => 'https://www.telegraph.co.uk/rss.xml',
                'credibilityWeight' => 0.80,
                'country' => 'GB',
                'sourceCategory' => SourceCategory::GENERALIST,
                'fetchFrequencyMinutes' => 120,
                'domainPattern' => 'telegraph.co.uk',
            ],

            // === PORTUGALIA (Wave 2) ===
            [
                'name' => 'Jornal de Notícias',
                'rssUrl' => 'https://www.jn.pt/rss/',
                'credibilityWeight' => 0.75,
                'country' => 'PT',
                'sourceCategory' => SourceCategory::GENERALIST,
                'fetchFrequencyMinutes' => 120,
                'domainPattern' => 'jn.pt',
            ],
            [
                'name' => 'Observador',
                'rssUrl' => 'https://observador.pt/feed/',
                'credibilityWeight' => 0.75,
                'country' => 'PT',
                'sourceCategory' => SourceCategory::GENERALIST,
                'fetchFrequencyMinutes' => 120,
                'domainPattern' => 'observador.pt',
            ],
            [
                'name' => 'Luso Jornal',
                'rssUrl' => 'https://lusojornal.com/feed/',
                'credibilityWeight' => 0.65,
                'country' => 'PT',
                'sourceCategory' => SourceCategory::DIASPORA,
                'fetchFrequencyMinutes' => 120,
                'domainPattern' => 'lusojornal.com',
            ],

            // === SPANIA (Wave 2) ===
            [
                'name' => 'El País',
                'rssUrl' => 'https://feeds.elpais.com/mrss-s/pages/ep/site/elpais.com/portada',
                'credibilityWeight' => 0.85,
                'country' => 'ES',
                'sourceCategory' => SourceCategory::GENERALIST,
                'fetchFrequencyMinutes' => 60,
                'domainPattern' => 'elpais.com',
            ],
            [
                'name' => 'El Mundo',
                'rssUrl' => 'https://e00-elmundo.uecdn.es/elmundo/rss/portada.xml',
                'credibilityWeight' => 0.80,
                'country' => 'ES',
                'sourceCategory' => SourceCategory::GENERALIST,
                'fetchFrequencyMinutes' => 120,
                'domainPattern' => 'elmundo.es',
            ],

            // === IRLANDA (Wave 2) ===
            [
                'name' => 'The Irish Times',
                'rssUrl' => 'https://www.irishtimes.com/cmlink/the-irish-times-news-1.1837',
                'credibilityWeight' => 0.80,
                'country' => 'IE',
                'sourceCategory' => SourceCategory::GENERALIST,
                'fetchFrequencyMinutes' => 120,
                'domainPattern' => 'irishtimes.com',
            ],
            [
                'name' => 'RTE News',
                'rssUrl' => 'https://www.rte.ie/feeds/rss/?index=/news/',
                'credibilityWeight' => 0.80,
                'country' => 'IE',
                'sourceCategory' => SourceCategory::GENERALIST,
                'fetchFrequencyMinutes' => 120,
                'domainPattern' => 'rte.ie',
            ],

            // === CANADA (Wave 2) ===
            [
                'name' => 'CBC News',
                'rssUrl' => 'https://www.cbc.ca/webfeed/rss/rss-world',
                'credibilityWeight' => 0.85,
                'country' => 'CA',
                'sourceCategory' => SourceCategory::GENERALIST,
                'fetchFrequencyMinutes' => 60,
                'domainPattern' => 'cbc.ca',
            ],
            [
                'name' => 'Globe and Mail',
                'rssUrl' => 'https://www.theglobeandmail.com/arc/outboundfeeds/rss/category/world/',
                'credibilityWeight' => 0.80,
                'country' => 'CA',
                'sourceCategory' => SourceCategory::GENERALIST,
                'fetchFrequencyMinutes' => 120,
                'domainPattern' => 'theglobeandmail.com',
            ],

            // === TURCIA (Wave 2) ===
            [
                'name' => 'Hürriyet Daily News',
                'rssUrl' => 'https://www.hurriyetdailynews.com/rss/homepage',
                'credibilityWeight' => 0.70,
                'country' => 'TR',
                'sourceCategory' => SourceCategory::GENERALIST,
                'fetchFrequencyMinutes' => 120,
                'domainPattern' => 'hurriyetdailynews.com',
            ],

            // === GRECIA (Wave 2) ===
            [
                'name' => 'Kathimerini English',
                'rssUrl' => 'https://www.ekathimerini.com/rss',
                'credibilityWeight' => 0.75,
                'country' => 'GR',
                'sourceCategory' => SourceCategory::GENERALIST,
                'fetchFrequencyMinutes' => 120,
                'domainPattern' => 'ekathimerini.com',
            ],

            // === CIPRU (Wave 2) ===
            [
                'name' => 'Cyprus Mail',
                'rssUrl' => 'https://cyprus-mail.com/feed/',
                'credibilityWeight' => 0.70,
                'country' => 'CY',
                'sourceCategory' => SourceCategory::GENERALIST,
                'fetchFrequencyMinutes' => 120,
                'domainPattern' => 'cyprus-mail.com',
            ],

            // === ROMÂNIA (Wave 2) ===
            [
                'name' => 'Digi24',
                'rssUrl' => 'https://www.digi24.ro/rss',
                'credibilityWeight' => 0.80,
                'country' => 'RO',
                'sourceCategory' => SourceCategory::GENERALIST,
                'fetchFrequencyMinutes' => 60,
                'domainPattern' => 'digi24.ro',
            ],
            [
                'name' => 'HotNews',
                'rssUrl' => 'https://www.hotnews.ro/rss',
                'credibilityWeight' => 0.80,
                'country' => 'RO',
                'sourceCategory' => SourceCategory::GENERALIST,
                'fetchFrequencyMinutes' => 60,
                'domainPattern' => 'hotnews.ro',
            ],

            // === META / DIASPORA FOCUS (Wave 2) ===
            [
                'name' => 'Euractiv',
                'rssUrl' => 'https://www.euractiv.com/feed/',
                'credibilityWeight' => 0.80,
                'country' => 'BE',
                'sourceCategory' => SourceCategory::GEOPOLITICS,
                'fetchFrequencyMinutes' => 60,
                'domainPattern' => 'euractiv.com',
            ],
        ];
    }
}
