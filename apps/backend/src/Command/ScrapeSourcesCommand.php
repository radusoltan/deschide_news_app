<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\Editorial\ScrapeSourceMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:scrape:sources',
    description: 'Scrape surse editoriale moldovenești',
)]
final class ScrapeSourcesCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        #[Autowire(param: 'scraping.sources')]
        private readonly array $sources,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('source', 's', InputOption::VALUE_REQUIRED, 'Source key (moldpres, ipn, gov_md)')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Scrape all configured sources')
            ->addOption('language', 'l', InputOption::VALUE_REQUIRED, 'Specific language (ro, en, ru)')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Max articles per source', '30')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be scraped without executing')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $sourceKey = $input->getOption('source');
        $all = $input->getOption('all');
        $language = $input->getOption('language');
        $limit = (int) $input->getOption('limit');
        $dryRun = $input->getOption('dry-run');

        if (!$all && $sourceKey === null) {
            $io->error('Specify --source=<key> or --all');

            return Command::INVALID;
        }

        $sourcesToScrape = [];

        if ($all) {
            $sourcesToScrape = array_keys($this->sources);
        } elseif (isset($this->sources[$sourceKey])) {
            $sourcesToScrape = [$sourceKey];
        } else {
            $io->error(sprintf(
                'Unknown source "%s". Available: %s',
                $sourceKey,
                implode(', ', array_keys($this->sources)),
            ));

            return Command::INVALID;
        }

        if ($dryRun) {
            $io->title('DRY RUN — surse care ar fi scraped:');

            foreach ($sourcesToScrape as $key) {
                $source = $this->sources[$key];
                $feeds = $source['feed_urls'] ?? [];

                $io->section($source['name'] . " ({$key})");

                foreach ($feeds as $lang => $url) {
                    if ($language !== null && $language !== $lang) {
                        continue;
                    }

                    $io->writeln("  [{$lang}] {$url} (limit: {$limit})");
                }
            }

            $io->success('Dry run complete. No messages dispatched.');

            return Command::SUCCESS;
        }

        $dispatched = 0;

        foreach ($sourcesToScrape as $key) {
            $io->writeln(sprintf('Dispatching scrape for <info>%s</info>...', $this->sources[$key]['name']));

            $this->messageBus->dispatch(new ScrapeSourceMessage(
                sourceKey: $key,
                language: $language,
                limit: $limit,
            ));

            $dispatched++;
        }

        $io->success("Dispatched {$dispatched} scraping message(s). Run messenger:consume scraping editorial to process.");

        return Command::SUCCESS;
    }
}
