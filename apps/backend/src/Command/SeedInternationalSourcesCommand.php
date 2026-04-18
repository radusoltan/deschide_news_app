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
    name: 'app:source:seed-international',
    description: 'Seed international RSS sources with credibility weights',
)]
class SeedInternationalSourcesCommand extends Command
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

        $io->title('Seeding International RSS Sources');

        try {
        $sources = $this->getSourceDefinitions();
        $created = 0;
        $updated = 0;
        $skipped = 0;

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
                // Update credibility weight, country, category on existing sources
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
            '%s: %d created, %d updated, %d skipped',
            $dryRun ? 'DRY RUN' : 'Done',
            $created,
            $updated,
            $skipped,
        ));

        return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * @return list<array{name: string, rssUrl: string, credibilityWeight: float, country: string, sourceCategory: SourceCategory, fetchFrequencyMinutes: int, domainPattern: ?string}>
     */
    private function getSourceDefinitions(): array
    {
        return [
            // === INTERNATIONAL WIRE AGENCIES ===
            [
                'name' => 'Reuters',
                'rssUrl' => 'https://www.reutersagency.com/feed/',
                'credibilityWeight' => 1.00,
                'country' => 'GB',
                'sourceCategory' => SourceCategory::AGENCY,
                'fetchFrequencyMinutes' => 60,
                'domainPattern' => 'reuters.com',
            ],
            [
                'name' => 'Associated Press',
                'rssUrl' => 'https://rsshub.app/apnews/topics/apf-topnews',
                'credibilityWeight' => 1.00,
                'country' => 'US',
                'sourceCategory' => SourceCategory::AGENCY,
                'fetchFrequencyMinutes' => 60,
                'domainPattern' => 'apnews.com',
            ],
            [
                'name' => 'AFP (via France24)',
                'rssUrl' => 'https://www.france24.com/en/rss',
                'credibilityWeight' => 1.00,
                'country' => 'FR',
                'sourceCategory' => SourceCategory::AGENCY,
                'fetchFrequencyMinutes' => 60,
                'domainPattern' => 'france24.com',
            ],

            // === GENERALIST ===
            [
                'name' => 'BBC News World',
                'rssUrl' => 'http://feeds.bbci.co.uk/news/world/rss.xml',
                'credibilityWeight' => 0.95,
                'country' => 'GB',
                'sourceCategory' => SourceCategory::GENERALIST,
                'fetchFrequencyMinutes' => 60,
                'domainPattern' => 'bbc.co.uk',
            ],
            [
                'name' => 'The Guardian World',
                'rssUrl' => 'https://www.theguardian.com/world/rss',
                'credibilityWeight' => 0.90,
                'country' => 'GB',
                'sourceCategory' => SourceCategory::GENERALIST,
                'fetchFrequencyMinutes' => 60,
                'domainPattern' => 'theguardian.com',
            ],
            [
                'name' => 'NYT World',
                'rssUrl' => 'https://rss.nytimes.com/services/xml/rss/nyt/World.xml',
                'credibilityWeight' => 0.95,
                'country' => 'US',
                'sourceCategory' => SourceCategory::GENERALIST,
                'fetchFrequencyMinutes' => 60,
                'domainPattern' => 'nytimes.com',
            ],
            [
                'name' => 'Washington Post World',
                'rssUrl' => 'https://feeds.washingtonpost.com/rss/world',
                'credibilityWeight' => 0.90,
                'country' => 'US',
                'sourceCategory' => SourceCategory::GENERALIST,
                'fetchFrequencyMinutes' => 60,
                'domainPattern' => 'washingtonpost.com',
            ],
            [
                'name' => 'CNN World',
                'rssUrl' => 'http://rss.cnn.com/rss/edition_world.rss',
                'credibilityWeight' => 0.85,
                'country' => 'US',
                'sourceCategory' => SourceCategory::GENERALIST,
                'fetchFrequencyMinutes' => 60,
                'domainPattern' => 'cnn.com',
            ],
            [
                'name' => 'Al Jazeera',
                'rssUrl' => 'https://www.aljazeera.com/xml/rss/all.xml',
                'credibilityWeight' => 0.85,
                'country' => 'QA',
                'sourceCategory' => SourceCategory::GENERALIST,
                'fetchFrequencyMinutes' => 60,
                'domainPattern' => 'aljazeera.com',
            ],
            [
                'name' => 'Deutsche Welle World',
                'rssUrl' => 'https://rss.dw.com/xml/rss-en-world',
                'credibilityWeight' => 0.90,
                'country' => 'DE',
                'sourceCategory' => SourceCategory::GENERALIST,
                'fetchFrequencyMinutes' => 60,
                'domainPattern' => 'dw.com',
            ],
            [
                'name' => 'France 24 EN',
                'rssUrl' => 'https://www.france24.com/en/rss',
                'credibilityWeight' => 0.85,
                'country' => 'FR',
                'sourceCategory' => SourceCategory::GENERALIST,
                'fetchFrequencyMinutes' => 60,
                'domainPattern' => 'france24.com',
            ],

            // === BUSINESS ===
            [
                'name' => 'Financial Times',
                'rssUrl' => 'https://www.ft.com/rss/home',
                'credibilityWeight' => 0.95,
                'country' => 'GB',
                'sourceCategory' => SourceCategory::BUSINESS,
                'fetchFrequencyMinutes' => 60,
                'domainPattern' => 'ft.com',
            ],
            [
                'name' => 'WSJ World',
                'rssUrl' => 'https://feeds.a.dj.com/rss/RSSWorldNews.xml',
                'credibilityWeight' => 0.95,
                'country' => 'US',
                'sourceCategory' => SourceCategory::BUSINESS,
                'fetchFrequencyMinutes' => 60,
                'domainPattern' => 'wsj.com',
            ],
            [
                'name' => 'Bloomberg Markets',
                'rssUrl' => 'https://feeds.bloomberg.com/markets/news.rss',
                'credibilityWeight' => 0.95,
                'country' => 'US',
                'sourceCategory' => SourceCategory::BUSINESS,
                'fetchFrequencyMinutes' => 60,
                'domainPattern' => 'bloomberg.com',
            ],
            [
                'name' => 'Nikkei Asia',
                'rssUrl' => 'https://asia.nikkei.com/rss',
                'credibilityWeight' => 0.85,
                'country' => 'JP',
                'sourceCategory' => SourceCategory::BUSINESS,
                'fetchFrequencyMinutes' => 60,
                'domainPattern' => 'nikkei.com',
            ],
            [
                'name' => 'CNBC World',
                'rssUrl' => 'https://www.cnbc.com/id/100727362/device/rss/rss.html',
                'credibilityWeight' => 0.85,
                'country' => 'US',
                'sourceCategory' => SourceCategory::BUSINESS,
                'fetchFrequencyMinutes' => 60,
                'domainPattern' => 'cnbc.com',
            ],

            // === GEOPOLITICS ===
            [
                'name' => 'POLITICO EU',
                'rssUrl' => 'https://www.politico.eu/feed/',
                'credibilityWeight' => 0.90,
                'country' => 'US',
                'sourceCategory' => SourceCategory::GEOPOLITICS,
                'fetchFrequencyMinutes' => 60,
                'domainPattern' => 'politico.eu',
            ],
            [
                'name' => 'The Economist',
                'rssUrl' => 'https://www.economist.com/international/rss.xml',
                'credibilityWeight' => 0.95,
                'country' => 'GB',
                'sourceCategory' => SourceCategory::GEOPOLITICS,
                'fetchFrequencyMinutes' => 60,
                'domainPattern' => 'economist.com',
            ],

            // === REGIONAL ===
            [
                'name' => 'SCMP',
                'rssUrl' => 'https://www.scmp.com/rss/91/feed',
                'credibilityWeight' => 0.85,
                'country' => 'HK',
                'sourceCategory' => SourceCategory::REGIONAL,
                'fetchFrequencyMinutes' => 60,
                'domainPattern' => 'scmp.com',
            ],
            [
                'name' => 'Times of India',
                'rssUrl' => 'https://timesofindia.indiatimes.com/rssfeedstopstories.cms',
                'credibilityWeight' => 0.80,
                'country' => 'IN',
                'sourceCategory' => SourceCategory::REGIONAL,
                'fetchFrequencyMinutes' => 60,
                'domainPattern' => 'timesofindia.indiatimes.com',
            ],

            // === LOCAL SOURCES (MD/RO/UA/EU) ===
            [
                'name' => 'Moldpres',
                'rssUrl' => 'https://www.moldpres.md/ro/rss',
                'credibilityWeight' => 0.85,
                'country' => 'MD',
                'sourceCategory' => SourceCategory::LOCAL,
                'fetchFrequencyMinutes' => 30,
                'domainPattern' => 'moldpres.md',
            ],
            [
                'name' => 'IPN Press',
                'rssUrl' => 'https://www.ipn.md/ro/rss',
                'credibilityWeight' => 0.85,
                'country' => 'MD',
                'sourceCategory' => SourceCategory::LOCAL,
                'fetchFrequencyMinutes' => 30,
                'domainPattern' => 'ipn.md',
            ],
            [
                'name' => 'Gov.md',
                'rssUrl' => 'https://gov.md/ro/rss.xml',
                'credibilityWeight' => 0.80,
                'country' => 'MD',
                'sourceCategory' => SourceCategory::INSTITUTIONAL,
                'fetchFrequencyMinutes' => 30,
                'domainPattern' => 'gov.md',
            ],
            [
                'name' => 'Agerpres',
                'rssUrl' => 'https://www.agerpres.ro/rss/externe.xml',
                'credibilityWeight' => 0.90,
                'country' => 'RO',
                'sourceCategory' => SourceCategory::AGENCY,
                'fetchFrequencyMinutes' => 60,
                'domainPattern' => 'agerpres.ro',
            ],
            [
                'name' => 'Comisia Europeană',
                'rssUrl' => 'https://ec.europa.eu/commission/presscorner/api/rss',
                'credibilityWeight' => 0.90,
                'country' => 'EU',
                'sourceCategory' => SourceCategory::INSTITUTIONAL,
                'fetchFrequencyMinutes' => 120,
                'domainPattern' => 'ec.europa.eu',
            ],
            [
                'name' => 'UNIAN',
                'rssUrl' => 'https://www.unian.net/rss/index.xml',
                'credibilityWeight' => 0.80,
                'country' => 'UA',
                'sourceCategory' => SourceCategory::REGIONAL,
                'fetchFrequencyMinutes' => 60,
                'domainPattern' => 'unian.net',
            ],
            [
                'name' => 'Ukrinform',
                'rssUrl' => 'https://www.ukrinform.net/rss/block-lastnews',
                'credibilityWeight' => 0.80,
                'country' => 'UA',
                'sourceCategory' => SourceCategory::AGENCY,
                'fetchFrequencyMinutes' => 60,
                'domainPattern' => 'ukrinform.net',
            ],
            [
                'name' => 'Consiliul European',
                'rssUrl' => 'https://www.consilium.europa.eu/en/press/press-releases/rss/',
                'credibilityWeight' => 0.90,
                'country' => 'EU',
                'sourceCategory' => SourceCategory::INSTITUTIONAL,
                'fetchFrequencyMinutes' => 120,
                'domainPattern' => 'consilium.europa.eu',
            ],
            [
                'name' => 'Parlamentul European',
                'rssUrl' => 'https://www.europarl.europa.eu/rss/doc/top-stories/en.xml',
                'credibilityWeight' => 0.85,
                'country' => 'EU',
                'sourceCategory' => SourceCategory::INSTITUTIONAL,
                'fetchFrequencyMinutes' => 120,
                'domainPattern' => 'europarl.europa.eu',
            ],
        ];
    }
}
