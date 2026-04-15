<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Scraping\PythonScraperService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test:python-scraper',
    description: 'Test the Python CLI scraper integration',
)]
class TestPythonScraperCommand extends Command
{
    public function __construct(
        private readonly PythonScraperService $scraperService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('url', null, InputOption::VALUE_REQUIRED, 'Source URL to scrape')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Extractor type: ' . implode(', ', PythonScraperService::getValidTypes()))
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Max items to return', '10')
            ->addOption('raw', null, InputOption::VALUE_NONE, 'Output raw JSON instead of formatted table')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $url = $input->getOption('url');
        $type = $input->getOption('type');
        $limit = (int) $input->getOption('limit');
        $raw = $input->getOption('raw');

        if (!$url || !$type) {
            $io->error('Both --url and --type are required.');
            $io->text('Example: symfony console app:test:python-scraper --url=https://gov.md/ro/rss.xml --type=gov-rss');

            return Command::FAILURE;
        }

        try {
            $result = $this->scraperService->fetch($url, $type, $limit);
        } catch (\Throwable $e) {
            if ($raw) {
                $output->writeln(json_encode(['error' => $e->getMessage()], \JSON_UNESCAPED_UNICODE));

                return Command::FAILURE;
            }
            $io->error(sprintf('Scraper failed: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        if ($raw) {
            $output->writeln(json_encode($result, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));

            return Command::SUCCESS;
        }

        $io->title('Python Scraper Test');
        $io->text(sprintf('URL: %s', $url));
        $io->text(sprintf('Type: %s', $type));
        $io->text(sprintf('Limit: %d', $limit));
        $io->newLine();

        // Display formatted results
        $items = $result['items'];
        $errors = $result['errors'];

        $io->success(sprintf(
            'Scraped %d items (type: %s, at: %s)',
            \count($items),
            $result['source_type'],
            $result['scraped_at'],
        ));

        if ($errors) {
            $io->warning('Scraper reported errors:');
            $io->listing($errors);
        }

        if (!$items) {
            $io->note('No items returned.');

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($items as $i => $item) {
            $rows[] = [
                $i + 1,
                mb_substr($item['title'], 0, 60),
                $item['source_name'],
                $item['language'],
                mb_substr($item['source_url'], 0, 50),
                mb_strlen($item['content']),
                \count($item['attachments']),
            ];
        }

        $io->table(
            ['#', 'Title', 'Source', 'Lang', 'URL', 'Content Len', 'Attachments'],
            $rows,
        );

        return Command::SUCCESS;
    }
}
