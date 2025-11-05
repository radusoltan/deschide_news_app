<?php

declare(strict_types=1);

namespace App\Command\Import;

use App\Entity\Author;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:import:authors-direct',
    description: 'Import authors from Newscoop (direct to DB)'
)]
class ImportAuthorsDirectCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        #[Autowire(service: 'doctrine.dbal.newscoop_connection')]
        private Connection $newscoopConnection,
        private Connection $defaultConnection,
        private LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Run without persisting data')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Limit number of authors to import')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $limit = $input->getOption('limit');

        $io->title('Import Authors from Newscoop (Direct)');

        if ($dryRun) {
            $io->warning('DRY RUN MODE - No data will be persisted');
        }

        $io->section('Step 1: Fetching Authors from Newscoop');

        // Fetch all authors
        $sql = '
            SELECT
                id,
                first_name,
                last_name,
                email,
                biography
            FROM Authors
            ORDER BY id
        ';

        if ($limit) {
            $sql .= \sprintf(' LIMIT %d', (int) $limit);
        }

        $authors = $this->newscoopConnection->fetchAllAssociative($sql);
        $io->success(\sprintf('Found %d authors in Newscoop', \count($authors)));

        $io->section('Step 2: Creating Authors');

        $importedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        $duplicateEmailCount = 0;

        $io->progressStart(\count($authors));

        foreach ($authors as $row) {
            try {
                $newscoopId = $row['id'];

                // Check if already imported
                if (!$dryRun) {
                    $existing = $this->defaultConnection->fetchOne(
                        'SELECT news_app_id FROM newscoop_id_mapping WHERE entity_type = ? AND newscoop_id = ?',
                        ['author', $newscoopId]
                    );

                    if ($existing) {
                        ++$skippedCount;
                        $io->progressAdvance();
                        continue;
                    }
                }

                // Determine email - generate dummy if null
                $email = $row['email'] ? trim($row['email']) : null;
                if (!$email) {
                    // Generate dummy email: author{id}@imported.deschide.md
                    $email = \sprintf('author%d@imported.deschide.md', $newscoopId);
                }

                // Check for duplicate email
                if (!$dryRun) {
                    $existingByEmail = $this->entityManager->getRepository(Author::class)
                        ->findOneBy(['email' => $email]);

                    if ($existingByEmail) {
                        $io->text(\sprintf(
                            '  ⊙ Duplicate email: %s (Author #%d)',
                            $email,
                            $newscoopId
                        ), OutputInterface::VERBOSITY_VERBOSE);
                        ++$duplicateEmailCount;
                        ++$skippedCount;
                        $io->progressAdvance();
                        continue;
                    }
                }

                // Create author
                $author = new Author();
                $author->setFirstName($row['first_name'] ?: 'Unknown');
                $author->setLastName($row['last_name'] ?: 'Author');
                $author->setEmail($email);

                // Biography (if exists)
                if (!empty($row['biography'])) {
                    $bio = $this->decodeBlobContent($row['biography']);
                    if ($bio) {
                        $author->setBio($bio);
                    }
                }

                if (!$dryRun) {
                    $this->entityManager->persist($author);
                    $this->entityManager->flush();

                    // Save mapping
                    $this->defaultConnection->insert('newscoop_id_mapping', [
                        'entity_type' => 'author',
                        'newscoop_id' => $newscoopId,
                        'news_app_id' => $author->getId(),
                    ]);

                    $io->text(\sprintf(
                        '  ✓ Created: %s %s (ID: %d)',
                        $author->getFirstName(),
                        $author->getLastName(),
                        $author->getId()
                    ), OutputInterface::VERBOSITY_VERY_VERBOSE);

                    // Clear entity manager every 50 authors to avoid memory issues
                    if ($importedCount % 50 === 0) {
                        $this->entityManager->clear();
                    }
                }

                ++$importedCount;
                $io->progressAdvance();

            } catch (Exception $e) {
                ++$errorCount;
                $io->text(\sprintf(
                    '  ✗ Failed: Author #%d: %s',
                    $row['id'],
                    $e->getMessage()
                ), OutputInterface::VERBOSITY_VERBOSE);

                $this->logger->error('Author import failed', [
                    'author_id' => $row['id'],
                    'error' => $e->getMessage(),
                ]);

                // If EntityManager is closed, reset it
                if (!$this->entityManager->isOpen()) {
                    $this->entityManager = $this->entityManager->create(
                        $this->entityManager->getConnection(),
                        $this->entityManager->getConfiguration()
                    );
                }

                $io->progressAdvance();
            }
        }

        $io->progressFinish();

        // Summary
        $io->section('Import Summary');
        $io->table(
            ['Metric', 'Count'],
            [
                ['Total authors in Newscoop', \count($authors)],
                ['Authors imported', $importedCount],
                ['Skipped (already exist)', $skippedCount],
                ['Skipped (duplicate email)', $duplicateEmailCount],
                ['Errors', $errorCount],
            ]
        );

        if ($dryRun) {
            $io->warning('DRY RUN: No data was persisted');
        } else {
            $io->success(\sprintf('Successfully imported %d authors!', $importedCount));
        }

        return Command::SUCCESS;
    }

    private function decodeBlobContent(?string $content): ?string
    {
        if ($content === null) {
            return null;
        }

        if (\is_resource($content)) {
            $content = stream_get_contents($content);
        }

        return trim($content) ?: null;
    }
}
