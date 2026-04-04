<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Topic;
use App\Repository\TopicRepository;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use Gedmo\Translatable\Entity\Translation;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed:topics',
    description: 'Seed topics from docs/topics-seed-data.json with RO/EN/RU translations',
)]
class SeedTopicsCommand extends Command
{
    private int $created = 0;
    private int $skipped = 0;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TopicRepository $topicRepository,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // JSON is in the monorepo root docs/ (two levels up from backend)
        $jsonPath = \dirname($this->projectDir, 2) . '/docs/topics-seed-data.json';
        if (!is_file($jsonPath)) {
            $io->error("Seed file not found: {$jsonPath}");

            return Command::FAILURE;
        }

        $data = json_decode(file_get_contents($jsonPath), true);
        if (!\is_array($data)) {
            $io->error('Invalid JSON in seed file');

            return Command::FAILURE;
        }

        $io->title('Seeding topics from docs/topics-seed-data.json');

        $this->processNodes($data, null, $io);
        $this->em->flush();

        $io->success(\sprintf('Done: %d created, %d skipped (already existed)', $this->created, $this->skipped));

        return Command::SUCCESS;
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     */
    private function processNodes(array $nodes, ?Topic $parent, SymfonyStyle $io): void
    {
        $position = 0;
        foreach ($nodes as $node) {
            $titleRo = $node['title_ro'] ?? '';
            if ($titleRo === '') {
                continue;
            }

            // Idempotent: match on title_ro + parent
            $existing = $this->findExistingTopic($titleRo, $parent);

            if ($existing) {
                $io->text(\sprintf('  [skip] %s', $titleRo));
                $this->skipped++;

                // Still process children for idempotency
                if (!empty($node['children'])) {
                    $this->processNodes($node['children'], $existing, $io);
                }

                $position++;
                continue;
            }

            $topic = new Topic();
            $topic->setTitle($titleRo);
            $topic->setDescription($node['description_ro'] ?? null);
            $topic->setParent($parent);
            $topic->setPosition($position);
            $topic->setIsActive(true);

            $this->em->persist($topic);
            $this->em->flush(); // Flush to get ID for translations

            // Add EN/RU translations via Gedmo Translatable
            /** @var TranslationRepository $translationRepo */
            $translationRepo = $this->em->getRepository(Translation::class);

            if (!empty($node['title_en'])) {
                $translationRepo->translate($topic, 'title', 'en', $node['title_en']);
            }
            if (!empty($node['title_ru'])) {
                $translationRepo->translate($topic, 'title', 'ru', $node['title_ru']);
            }

            $this->em->flush();

            $io->text(\sprintf('  [new]  %s (id:%d)', $titleRo, $topic->getId()));
            $this->created++;

            // Process children recursively
            if (!empty($node['children'])) {
                $this->processNodes($node['children'], $topic, $io);
            }

            $position++;
        }
    }

    private function findExistingTopic(string $titleRo, ?Topic $parent): ?Topic
    {
        $qb = $this->topicRepository->createQueryBuilder('t')
            ->where('t.title = :title')
            ->setParameter('title', $titleRo);

        if ($parent === null) {
            $qb->andWhere('t.parent IS NULL');
        } else {
            $qb->andWhere('t.parent = :parent')
                ->setParameter('parent', $parent);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }
}
