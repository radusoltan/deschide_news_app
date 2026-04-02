<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Search\ElasticsearchIndexManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:search:quality-test',
    description: 'Testează calitatea căutării Elasticsearch (fuzzy, sinonime, trilingv)',
)]
final class SearchQualityTestCommand extends Command
{
    public function __construct(
        private readonly ElasticsearchIndexManager $indexManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->indexManager->isEnabled()) {
            $io->error('Elasticsearch is not configured or disabled.');

            return Command::FAILURE;
        }

        $io->title('Test calitate căutare Elasticsearch');

        $testQueries = [
            ['query' => 'reforma energetică', 'lang' => 'ro', 'min_results' => 1, 'type' => 'exact'],
            ['query' => 'energy reform', 'lang' => 'en', 'min_results' => 1, 'type' => 'exact'],
            ['query' => 'энергетическая реформа', 'lang' => 'ru', 'min_results' => 1, 'type' => 'exact'],
            ['query' => 'Maia Sandu', 'lang' => 'ro', 'min_results' => 1, 'type' => 'exact'],
            ['query' => 'reforma', 'lang' => 'ro', 'min_results' => 1, 'type' => 'fuzzy (fără diacritice)'],
            ['query' => 'BNM rata', 'lang' => 'ro', 'min_results' => 0, 'type' => 'sinonim'],
            ['query' => 'integrare europeana', 'lang' => 'ro', 'min_results' => 1, 'type' => 'fuzzy (fără diacritice)'],
            ['query' => 'Moldova politica', 'lang' => 'ro', 'min_results' => 1, 'type' => 'fuzzy + sinonim'],
        ];

        $rows = [];
        $passed = 0;
        $failed = 0;

        foreach ($testQueries as $test) {
            $start = microtime(true);
            $results = $this->search($test['query'], $test['lang']);
            $latency = round((microtime(true) - $start) * 1000);

            $count = $results['hits']['total']['value'] ?? 0;
            $pass = $count >= $test['min_results'];

            if ($pass) {
                $passed++;
                $status = '<fg=green>PASS</>';
            } else {
                $failed++;
                $status = '<fg=red>FAIL</>';
            }

            $rows[] = [
                $test['query'],
                strtoupper($test['lang']),
                $test['type'],
                $count,
                $test['min_results'],
                "{$latency}ms",
                $status,
            ];
        }

        $io->table(
            ['Query', 'Limba', 'Tip', 'Rezultate', 'Min așteptat', 'Latență', 'Status'],
            $rows,
        );

        $total = $passed + $failed;
        $io->text(\sprintf('Rezultat: <info>%d/%d</info> teste trecute', $passed, $total));

        // Index stats
        $this->showIndexStats($io);

        if ($failed > 0) {
            $io->warning("{$failed} teste nu au trecut. Verificați indexul și conținutul.");

            return Command::FAILURE;
        }

        $io->success('Toate testele au trecut.');

        return Command::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function search(string $query, string $lang): array
    {
        $client = $this->indexManager->getClient();
        $indexName = $this->indexManager->getIndexName();

        // Multi-match across exact + fuzzy fields for the specified language
        $fields = [
            "title_{$lang}^3",
            "title_{$lang}.fuzzy^2",
            "body_{$lang}",
            "body_{$lang}.fuzzy",
            "description_{$lang}^2",
            "description_{$lang}.fuzzy",
        ];

        $params = [
            'index' => $indexName,
            'body' => [
                'size' => 10,
                'query' => [
                    'multi_match' => [
                        'query' => $query,
                        'fields' => $fields,
                        'type' => 'best_fields',
                        'fuzziness' => 'AUTO',
                    ],
                ],
            ],
        ];

        try {
            $response = $client->search($params);

            return $response->asArray();
        } catch (\Throwable $e) {
            return ['hits' => ['total' => ['value' => 0]], 'error' => $e->getMessage()];
        }
    }

    private function showIndexStats(SymfonyStyle $io): void
    {
        try {
            $client = $this->indexManager->getClient();
            $indexName = $this->indexManager->getIndexName();

            $stats = $client->indices()->stats(['index' => $indexName])->asArray();
            $total = $stats['_all']['primaries']['docs']['count'] ?? 0;
            $sizeBytes = $stats['_all']['primaries']['store']['size_in_bytes'] ?? 0;
            $sizeMb = round($sizeBytes / 1024 / 1024, 1);

            $io->newLine();
            $io->text(\sprintf('Index: <info>%s</info> | Documente: <info>%s</info> | Mărime: <info>%s MB</info>',
                $indexName, number_format($total), $sizeMb));
        } catch (\Throwable) {
            // Non-critical, skip
        }
    }
}
