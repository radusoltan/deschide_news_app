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
    description: 'Seed diaspora media RSS sources (IT/FR/DE/UK/IL/PT/RO) for clustering',
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
        ];
    }
}
