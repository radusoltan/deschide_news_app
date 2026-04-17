<?php

declare(strict_types=1);

namespace App\Command\Topic;

use App\Entity\Topic;
use App\Repository\TopicRepository;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Translation;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Idempotent seeder for the Sport taxonomy branch.
 *
 * Amended T51c.4 (ADR-018 D2): the Sprint 49 v2 taxonomy has 10 macro-topic
 * roots and none of them cover sport. This command adds an 11th root (`sport`)
 * and 5 leaves underneath so the 76 orphan sport articles pick up usable
 * topics during the Sprint 51c backfill.
 *
 * Uses EntityManager + Gedmo Translatable so the Nested Set columns
 * (lft/rgt/lvl/root_id) get computed by the tree listener — never via raw
 * SQL.
 */
#[AsCommand(
    name: 'app:topic:seed-sport',
    description: 'Create the Sport root and its 5 leaf topics with RO/EN/RU translations (idempotent).',
)]
class SeedSportTaxonomyCommand extends Command
{
    private const ROOT_SLUG = 'sport';

    /**
     * Leaves attached to the Sport root. Keep Romanian diacritics with
     * comma-below (ș U+0219, ț U+021B) — never cedilla.
     *
     * @var list<array{slug: string, ro: string, en: string, ru: string}>
     */
    private const LEAVES = [
        [
            'slug' => 'fotbal-moldova',
            'ro' => 'Fotbal moldovenesc',
            'en' => 'Moldovan Football',
            'ru' => 'Молдавский футбол',
        ],
        [
            'slug' => 'olympic-games-moldova',
            'ro' => 'Jocurile Olimpice (Moldova)',
            'en' => 'Olympic Games (Moldova)',
            'ru' => 'Олимпийские игры (Молдова)',
        ],
        [
            'slug' => 'sport-international',
            'ro' => 'Sport internațional',
            'en' => 'International Sports',
            'ru' => 'Международный спорт',
        ],
        [
            'slug' => 'lupte-box-moldova',
            'ro' => 'Lupte și box (Moldova)',
            'en' => 'Wrestling and Boxing (Moldova)',
            'ru' => 'Борьба и бокс (Молдова)',
        ],
        [
            'slug' => 'sport-amator-regional',
            'ro' => 'Sport amator regional',
            'en' => 'Regional Amateur Sports',
            'ru' => 'Региональный любительский спорт',
        ],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TopicRepository $topicRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print plan without persisting anything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $io->title('Sprint 51c — Sport taxonomy seeder (idempotent)');

        $existingRoot = $this->topicRepository->findOneBy(['slug' => self::ROOT_SLUG]);
        $io->text($existingRoot === null
            ? 'Sport root: MISSING — will create'
            : sprintf('Sport root: EXISTS (id=%d) — will reuse', $existingRoot->getId()),
        );

        $plannedLeaves = [];
        foreach (self::LEAVES as $leaf) {
            $existing = $this->topicRepository->findOneBy(['slug' => $leaf['slug']]);
            $plannedLeaves[] = [
                'slug' => $leaf['slug'],
                'exists' => $existing !== null,
                'id' => $existing?->getId(),
            ];
        }

        $io->table(
            ['Leaf slug', 'Exists', 'ID'],
            array_map(
                static fn (array $row) => [$row['slug'], $row['exists'] ? 'yes' : 'no', (string) ($row['id'] ?? '')],
                $plannedLeaves,
            ),
        );

        if ($dryRun) {
            $io->warning('DRY RUN — no database changes.');

            return Command::SUCCESS;
        }

        $root = $existingRoot ?? $this->createTopic(self::ROOT_SLUG, 'Sport', null);

        // Always refresh translations — safe with Gedmo Translation repository
        // (upserts on the unique (locale, object_class, foreign_key, field) key).
        $this->upsertTranslations($root, [
            'ro' => 'Sport',
            'en' => 'Sports',
            'ru' => 'Спорт',
        ]);

        $createdLeaves = 0;
        foreach (self::LEAVES as $leaf) {
            $topic = $this->topicRepository->findOneBy(['slug' => $leaf['slug']]);
            if ($topic === null) {
                $topic = $this->createTopic($leaf['slug'], $leaf['ro'], $root);
                $createdLeaves++;
            }
            $this->upsertTranslations($topic, [
                'ro' => $leaf['ro'],
                'en' => $leaf['en'],
                'ru' => $leaf['ru'],
            ]);
        }

        $this->em->flush();

        $io->success(sprintf(
            'Sport root ready (id=%d). Leaves created: %d. Translations upserted.',
            $root->getId(),
            $createdLeaves,
        ));

        return Command::SUCCESS;
    }

    private function createTopic(string $slug, string $roTitle, ?Topic $parent): Topic
    {
        $topic = new Topic();
        $topic->setTranslatableLocale('ro');
        $topic->setTitle($roTitle);
        $topic->setSlug($slug);
        $topic->setIsActive(true);
        $topic->setIsSensitive(false);
        if ($parent !== null) {
            $topic->setParent($parent);
        }

        $this->em->persist($topic);
        $this->em->flush();

        // Gedmo Sluggable may rewrite the slug from the title — re-assert.
        if ($topic->getSlug() !== $slug) {
            $topic->setSlug($slug);
            $this->em->flush();
        }

        return $topic;
    }

    /**
     * @param array<string, string> $translations locale => title
     */
    private function upsertTranslations(Topic $topic, array $translations): void
    {
        $repo = $this->em->getRepository(Translation::class);

        foreach ($translations as $locale => $title) {
            // Skip `ro` — that's the default locale wired on the entity row itself.
            if ($locale === 'ro') {
                continue;
            }
            $repo->translate($topic, 'title', $locale, $title);
        }

        $this->em->flush();
    }
}
