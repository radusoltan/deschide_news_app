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
    name: 'app:source:seed-google-alerts',
    description: 'Seed Google Alerts RSS feeds as diaspora/Moldova monitoring sources',
)]
class SeedGoogleAlertsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SourceRepository $sourceRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List sources without inserting')
            ->addOption('add', null, InputOption::VALUE_REQUIRED, 'Add a single alert: "Name|RSS_URL|lang"');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $addSingle = $input->getOption('add');

        $io->title('Seeding Google Alerts RSS Sources');

        if ($addSingle !== null) {
            return $this->addSingleAlert($addSingle, $dryRun, $io);
        }

        try {
        $alerts = $this->getAlertDefinitions();
        $created = 0;
        $updated = 0;

        foreach ($alerts as $def) {
            $existing = $this->sourceRepository->findByName($def['name']);

            if ($dryRun) {
                $status = $existing ? 'EXISTS' : 'NEW';
                $io->writeln(sprintf('  [%s] %s (%s)', $status, $def['name'], $def['lang']));
                continue;
            }

            if ($existing !== null) {
                $existing->setCredibilityWeight($def['credibilityWeight']);
                $existing->setSourceCategory(SourceCategory::ALERT);
                $existing->setFetchFrequencyMinutes(30);
                $updated++;
            } else {
                $source = new Source();
                $source->setName($def['name']);
                $source->setRssUrl($def['rssUrl']);
                $source->setCredibilityWeight($def['credibilityWeight']);
                $source->setCountry($def['country']);
                $source->setSourceCategory(SourceCategory::ALERT);
                $source->setFetchFrequencyMinutes(30);
                $source->setIsActive($def['isActive']);
                $source->setType('rss');
                $source->setDomainPattern('google.com');

                $this->em->persist($source);
                $created++;
            }
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        $io->success(sprintf(
            '%s: %d created, %d updated (total alerts: %d)',
            $dryRun ? 'DRY RUN' : 'Done',
            $created,
            $updated,
            \count($alerts),
        ));

        return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }

    private function addSingleAlert(string $spec, bool $dryRun, SymfonyStyle $io): int
    {
        $parts = explode('|', $spec);
        if (\count($parts) < 2) {
            $io->error('Format: "Alert Name|https://google.com/alerts/feeds/....|ro"');
            return Command::FAILURE;
        }

        $name = trim($parts[0]);
        $rssUrl = trim($parts[1]);
        $lang = $parts[2] ?? 'ro';

        if ($dryRun) {
            $io->writeln(sprintf('  [DRY RUN] Would add: %s (%s)', $name, $rssUrl));
            return Command::SUCCESS;
        }

        $existing = $this->sourceRepository->findByName($name);
        if ($existing !== null) {
            $existing->setRssUrl($rssUrl);
            $io->info('Updated existing alert: ' . $name);
        } else {
            $source = new Source();
            $source->setName($name);
            $source->setRssUrl($rssUrl);
            $source->setCredibilityWeight(0.60);
            $source->setCountry(strtoupper(substr($lang, 0, 2)));
            $source->setSourceCategory(SourceCategory::ALERT);
            $source->setFetchFrequencyMinutes(30);
            $source->setIsActive(true);
            $source->setType('rss');
            $source->setDomainPattern('google.com');
            $this->em->persist($source);
            $io->info('Created alert: ' . $name);
        }

        $this->em->flush();

        return Command::SUCCESS;
    }

    /**
     * Placeholder alert definitions. Use --add to add real Google Alerts RSS URLs.
     *
     * @return list<array{name: string, rssUrl: string, credibilityWeight: float, country: string, lang: string, isActive: bool}>
     */
    private function getAlertDefinitions(): array
    {
        return [
            // RO alerts
            ['name' => 'GA: Moldova', 'rssUrl' => '', 'credibilityWeight' => 0.60, 'country' => 'MD', 'lang' => 'ro', 'isActive' => false],
            ['name' => 'GA: Republica Moldova', 'rssUrl' => '', 'credibilityWeight' => 0.60, 'country' => 'MD', 'lang' => 'ro', 'isActive' => false],
            ['name' => 'GA: Chișinău', 'rssUrl' => '', 'credibilityWeight' => 0.60, 'country' => 'MD', 'lang' => 'ro', 'isActive' => false],
            ['name' => 'GA: Maia Sandu', 'rssUrl' => '', 'credibilityWeight' => 0.60, 'country' => 'MD', 'lang' => 'ro', 'isActive' => false],

            // EN alerts
            ['name' => 'GA: Moldova EN', 'rssUrl' => '', 'credibilityWeight' => 0.60, 'country' => 'MD', 'lang' => 'en', 'isActive' => false],
            ['name' => 'GA: Moldovan diaspora', 'rssUrl' => '', 'credibilityWeight' => 0.60, 'country' => 'MD', 'lang' => 'en', 'isActive' => false],

            // IT alerts
            ['name' => 'GA: Moldova Italia', 'rssUrl' => '', 'credibilityWeight' => 0.60, 'country' => 'IT', 'lang' => 'it', 'isActive' => false],
            ['name' => 'GA: moldavi Italia', 'rssUrl' => '', 'credibilityWeight' => 0.60, 'country' => 'IT', 'lang' => 'it', 'isActive' => false],

            // RU alerts
            ['name' => 'GA: Молдова', 'rssUrl' => '', 'credibilityWeight' => 0.60, 'country' => 'MD', 'lang' => 'ru', 'isActive' => false],
            ['name' => 'GA: Кишинёв', 'rssUrl' => '', 'credibilityWeight' => 0.60, 'country' => 'MD', 'lang' => 'ru', 'isActive' => false],

            // FR alerts
            ['name' => 'GA: Moldavie France', 'rssUrl' => '', 'credibilityWeight' => 0.60, 'country' => 'FR', 'lang' => 'fr', 'isActive' => false],

            // DE alerts
            ['name' => 'GA: Moldau Deutschland', 'rssUrl' => '', 'credibilityWeight' => 0.60, 'country' => 'DE', 'lang' => 'de', 'isActive' => false],

            // PT alerts
            ['name' => 'GA: Moldávia Portugal', 'rssUrl' => '', 'credibilityWeight' => 0.60, 'country' => 'PT', 'lang' => 'pt', 'isActive' => false],
        ];
    }
}
