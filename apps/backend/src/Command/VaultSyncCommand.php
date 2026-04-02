<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Editorial\FrontmatterValidator;
use App\Service\Editorial\MarkdownParser;
use App\Service\Editorial\VaultSyncResult;
use App\Service\Editorial\VaultSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:vault:sync',
    description: 'Sincronizează vault-ul editorial cu baza de date',
)]
class VaultSyncCommand extends Command
{
    public function __construct(
        private readonly VaultSyncService $syncService,
        private readonly MarkdownParser $parser,
        private readonly FrontmatterValidator $frontmatterValidator,
        #[Autowire('%editorial.vault_path%')]
        private readonly string $vaultPath,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('path', null, InputOption::VALUE_OPTIONAL, 'Directorul de scanat (relativ la vault)', 'articles')
            ->addOption('file', null, InputOption::VALUE_OPTIONAL, 'Un singur fișier de sincronizat (relativ la vault)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Afișează ce ar face, fără a persista');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $singleFile = $input->getOption('file');

        if ($dryRun) {
            $io->note('Mod dry-run: nu se persistă nimic în baza de date');
        }

        // Collect files to process
        if ($singleFile !== null) {
            $files = [$singleFile];
        } else {
            $scanPath = $input->getOption('path');
            $fullPath = rtrim($this->vaultPath, '/') . '/' . ltrim($scanPath, '/');
            $files = $this->scanDirectory($fullPath);

            if ($files === []) {
                $io->warning("Nu s-au găsit fișiere .md în: $fullPath");
                return Command::SUCCESS;
            }
        }

        $io->info(sprintf('Se procesează %d fișier(e)...', count($files)));

        $results = [];
        $counts = ['created' => 0, 'updated' => 0, 'error' => 0];

        foreach ($files as $relativePath) {
            $fullFilePath = rtrim($this->vaultPath, '/') . '/' . ltrim($relativePath, '/');

            if ($dryRun) {
                // In dry-run mode, parse and validate only
                $result = $this->dryRunCheck($fullFilePath);
            } else {
                $result = $this->syncService->syncFromFile($fullFilePath);
            }

            $status = $result->status;
            $message = match ($status) {
                VaultSyncResult::STATUS_CREATED => 'Creat (ID: ' . $result->articleId . ')',
                VaultSyncResult::STATUS_UPDATED => 'Actualizat (ID: ' . $result->articleId . ')',
                VaultSyncResult::STATUS_VALIDATION_ERROR => implode('; ', $result->errors),
                VaultSyncResult::STATUS_PARSE_ERROR => implode('; ', $result->errors),
                default => 'Necunoscut',
            };

            if ($dryRun && in_array($status, [VaultSyncResult::STATUS_CREATED, VaultSyncResult::STATUS_UPDATED], true)) {
                $message = '[DRY-RUN] Ar fi procesat cu succes';
                $status = 'ok (dry-run)';
            }

            $results[] = [$relativePath, $status, $message];

            if (in_array($result->status, [VaultSyncResult::STATUS_CREATED, VaultSyncResult::STATUS_UPDATED], true)) {
                $counts[$result->status === VaultSyncResult::STATUS_CREATED ? 'created' : 'updated']++;
            } else {
                $counts['error']++;
            }
        }

        // Display results table
        $table = new Table($output);
        $table->setHeaders(['Fișier', 'Status', 'Mesaj']);
        $table->setRows($results);
        $table->render();

        $io->newLine();
        $io->success(sprintf(
            'Sumar: %d create, %d actualizate, %d erori (din %d total)',
            $counts['created'],
            $counts['updated'],
            $counts['error'],
            count($files),
        ));

        return $counts['error'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function dryRunCheck(string $filePath): VaultSyncResult
    {
        if (!file_exists($filePath)) {
            return new VaultSyncResult(VaultSyncResult::STATUS_PARSE_ERROR, errors: ['File not found: ' . $filePath]);
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return new VaultSyncResult(VaultSyncResult::STATUS_PARSE_ERROR, errors: ['Cannot read file']);
        }

        $parseResult = $this->parser->parse($content);
        if ($parseResult->frontmatter === []) {
            return new VaultSyncResult(VaultSyncResult::STATUS_PARSE_ERROR, errors: ['No frontmatter found']);
        }

        $validation = $this->frontmatterValidator->validate($parseResult->frontmatter);
        if (!$validation->isValid) {
            return new VaultSyncResult(VaultSyncResult::STATUS_VALIDATION_ERROR, errors: $validation->errors);
        }

        return new VaultSyncResult(VaultSyncResult::STATUS_CREATED);
    }

    /**
     * @return list<string>
     */
    private function scanDirectory(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'md') {
                $files[] = str_replace(rtrim($this->vaultPath, '/') . '/', '', $file->getPathname());
            }
        }

        sort($files);

        return $files;
    }
}
