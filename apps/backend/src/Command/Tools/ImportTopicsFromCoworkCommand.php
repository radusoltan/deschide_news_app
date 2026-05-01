<?php

declare(strict_types=1);

namespace App\Command\Tools;

use App\Entity\Topic;
use App\Enum\TopicStatus;
use App\Repository\TopicRepository;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use Gedmo\Translatable\Entity\Translation;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:tools:import-topics-from-cowork',
    description: 'Import locally-proposed topics from cowork extensions JSON into Deschide taxonomy.',
)]
final class ImportTopicsFromCoworkCommand extends Command
{
    private const DEFAULT_SOURCE = '/mnt/c/Users/Radu/cowork/config/topics_local_extensions.json';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TopicRepository $topicRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Parse + validate + report, no writes')
            ->addOption('source', null, InputOption::VALUE_REQUIRED, 'Override source JSON path', self::DEFAULT_SOURCE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $source = (string) $input->getOption('source');

        $io->title(\sprintf(
            'Import topics from cowork-local extensions%s',
            $dryRun ? ' (DRY-RUN)' : '',
        ));
        $io->writeln(\sprintf('Source: <info>%s</info>', $source));

        if (!is_file($source)) {
            $io->error("Source file not found: {$source}");

            return Command::FAILURE;
        }

        $raw = file_get_contents($source);
        if ($raw === false) {
            $io->error("Cannot read source file: {$source}");

            return Command::FAILURE;
        }

        $data = json_decode($raw, true);
        if (!\is_array($data) || !isset($data['topics']) || !\is_array($data['topics'])) {
            $io->error('Invalid JSON: expected object with "topics" array');

            return Command::FAILURE;
        }

        $localTopics = array_values(array_filter(
            $data['topics'],
            static fn (mixed $t): bool => \is_array($t) && (($t['origin'] ?? null) === 'local'),
        ));

        if ($localTopics === []) {
            $io->note('No topics with origin="local" found. Nothing to do.');

            return Command::SUCCESS;
        }

        $io->writeln(\sprintf('Found <info>%d</info> local topics to process.', \count($localTopics)));
        $io->newLine();

        $imported = 0;
        $skipped = 0;
        $errors = 0;
        $reportLines = [];

        $this->em->beginTransaction();
        try {
            foreach ($localTopics as $entry) {
                $slug = (string) ($entry['slug'] ?? '');
                $parentSlug = (string) ($entry['parent_slug'] ?? '');
                $level = (int) ($entry['level'] ?? 0);

                if ($slug === '') {
                    $reportLines[] = \sprintf('ERROR   slug=(missing)  (empty slug in JSON entry)');
                    $errors++;
                    continue;
                }

                // Check if slug already exists
                $existing = $this->topicRepository->findOneBy(['slug' => $slug]);
                if ($existing !== null) {
                    $reportLines[] = \sprintf('SKIP    slug=%s  (already exists)', $slug);
                    $skipped++;
                    continue;
                }

                // Look up parent
                if ($parentSlug === '') {
                    $reportLines[] = \sprintf('ERROR   slug=%s  (missing parent_slug)', $slug);
                    $errors++;
                    continue;
                }

                $parent = $this->topicRepository->findOneBy(['slug' => $parentSlug]);
                if ($parent === null) {
                    $reportLines[] = \sprintf("ERROR   slug=%s  (parent '%s' not found)", $slug, $parentSlug);
                    $errors++;
                    continue;
                }

                // Validate level
                $expectedLevel = $parent->getLvl() + 1;
                if ($level !== $expectedLevel) {
                    $reportLines[] = \sprintf(
                        'ERROR   slug=%s  (level mismatch: JSON=%d, parent.lvl+1=%d)',
                        $slug,
                        $level,
                        $expectedLevel,
                    );
                    $errors++;
                    continue;
                }

                // Validate required title
                $titleRo = (string) ($entry['title_ro'] ?? '');
                if ($titleRo === '') {
                    $reportLines[] = \sprintf('ERROR   slug=%s  (missing title_ro)', $slug);
                    $errors++;
                    continue;
                }

                $isSensitive = (bool) ($entry['is_sensitive'] ?? false);
                $reportLines[] = \sprintf(
                    'IMPORT  slug=%s  parent=%s  L%d  sensitive=%s',
                    $slug,
                    $parentSlug,
                    $level,
                    $isSensitive ? 'true' : 'false',
                );

                if ($dryRun) {
                    $imported++;
                    continue;
                }

                // Create the topic
                $topic = new Topic();
                $topic->setTitle($titleRo);
                $topic->setSlug($slug);
                $topic->setIsActive(true);
                $topic->setIsSensitive($isSensitive);
                $topic->setIsStoryLeaf(false);
                $topic->setStatus(TopicStatus::ACTIVE);
                $topic->setWeight(0.5);

                // Keywords for level 2 only
                if ($level === 2) {
                    $keywords = $entry['keywords'] ?? null;
                    if (\is_array($keywords) && $keywords !== []) {
                        $topic->setKeywords(array_values(array_map('strval', $keywords)));
                    }
                }

                // Persist via Gedmo Nested Tree to maintain lft/rgt
                $this->topicRepository->persistAsLastChildOf($topic, $parent);

                // Flush to obtain ID, then attach translations
                $this->em->flush();

                /** @var TranslationRepository $translationRepo */
                $translationRepo = $this->em->getRepository(Translation::class);

                $titleEn = (string) ($entry['title_en'] ?? '');
                $titleRu = (string) ($entry['title_ru'] ?? '');

                if ($titleEn !== '') {
                    $translationRepo->translate($topic, 'title', 'en', $titleEn);
                }
                if ($titleRu !== '') {
                    $translationRepo->translate($topic, 'title', 'ru', $titleRu);
                }

                // Flush translations
                $this->em->flush();

                $imported++;
            }

            // Print report
            foreach ($reportLines as $line) {
                $io->writeln('  ' . $line);
            }

            if ($errors > 0) {
                throw new \RuntimeException(\sprintf('%d error(s) encountered, rolling back transaction.', $errors));
            }

            if ($dryRun) {
                $this->em->rollback();
                $io->newLine();
                $io->success(\sprintf(
                    'DRY-RUN: %d would be imported, %d would be skipped, %d errors',
                    $imported,
                    $skipped,
                    $errors,
                ));

                return Command::SUCCESS;
            }

            $this->em->commit();
        } catch (\Throwable $e) {
            if ($this->em->getConnection()->isTransactionActive()) {
                $this->em->rollback();
            }

            // Still print whatever was collected so the operator can see progress
            foreach ($reportLines as $line) {
                $io->writeln('  ' . $line);
            }

            $io->newLine();
            $io->error($e->getMessage());
            $io->warning('Transaction rolled back — no changes persisted.');

            return Command::FAILURE;
        }

        $io->newLine();
        $io->success(\sprintf(
            '%d imported, %d skipped, %d errors',
            $imported,
            $skipped,
            $errors,
        ));

        // Clear caches so changes are visible immediately
        $io->section('Clearing caches');
        $this->runSubCommand($output, 'cache:pool:clear', [
            'pools' => ['cache.app', 'doctrine.system_cache_pool'],
        ]);

        return Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function runSubCommand(OutputInterface $output, string $commandName, array $arguments = []): int
    {
        $command = $this->getApplication()?->find($commandName);
        if ($command === null) {
            throw new \RuntimeException("Command not found: {$commandName}");
        }

        $input = new ArrayInput($arguments);
        $input->setInteractive(false);

        return $command->run($input, $output);
    }
}
