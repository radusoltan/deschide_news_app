<?php

declare(strict_types=1);

namespace App\Command\Tools;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:tools:export-topics',
    description: 'Export topic taxonomy as flat JSON (for external consumers).',
)]
final class ExportTopicsCommand extends Command
{
    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('pretty', null, InputOption::VALUE_NONE, 'Pretty-print JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT
                t.id,
                t.slug,
                t.title              AS title_ro,
                t.description        AS description_ro,
                t.parent_id,
                parent.slug          AS parent_slug,
                t.root_id,
                root.slug            AS root_slug,
                t.lvl,
                t.position,
                t.is_active,
                t.is_sensitive,
                t.is_story_leaf,
                t.status,
                t.weight,
                t.keywords,
                t_en.content         AS title_en,
                t_ru.content         AS title_ru,
                d_en.content         AS description_en,
                d_ru.content         AS description_ru
            FROM topics t
            LEFT JOIN topics parent ON parent.id = t.parent_id
            LEFT JOIN topics root   ON root.id   = t.root_id
            LEFT JOIN ext_translations t_en
                ON t_en.object_class = 'App\Entity\Topic'
               AND t_en.foreign_key  = t.id::text
               AND t_en.field        = 'title'
               AND t_en.locale       = 'en'
            LEFT JOIN ext_translations t_ru
                ON t_ru.object_class = 'App\Entity\Topic'
               AND t_ru.foreign_key  = t.id::text
               AND t_ru.field        = 'title'
               AND t_ru.locale       = 'ru'
            LEFT JOIN ext_translations d_en
                ON d_en.object_class = 'App\Entity\Topic'
               AND d_en.foreign_key  = t.id::text
               AND d_en.field        = 'description'
               AND d_en.locale       = 'en'
            LEFT JOIN ext_translations d_ru
                ON d_ru.object_class = 'App\Entity\Topic'
               AND d_ru.foreign_key  = t.id::text
               AND d_ru.field        = 'description'
               AND d_ru.locale       = 'ru'
            ORDER BY t.lft ASC
        SQL);

        $topics = array_map(
            static function (array $r): array {
                $keywords = $r['keywords'];
                if (is_string($keywords) && $keywords !== '') {
                    $decoded = json_decode($keywords, true);
                    $keywords = is_array($decoded) ? $decoded : [];
                } elseif ($keywords === null) {
                    $keywords = [];
                }

                return [
                    'id'              => (int) $r['id'],
                    'slug'            => $r['slug'],
                    'title_ro'        => $r['title_ro'],
                    'title_en'        => $r['title_en'],
                    'title_ru'        => $r['title_ru'],
                    'description_ro'  => $r['description_ro'],
                    'description_en'  => $r['description_en'],
                    'description_ru'  => $r['description_ru'],
                    'parent_id'       => $r['parent_id'] !== null ? (int) $r['parent_id'] : null,
                    'parent_slug'     => $r['parent_slug'],
                    'root_id'         => $r['root_id'] !== null ? (int) $r['root_id'] : null,
                    'root_slug'       => $r['root_slug'],
                    'level'           => (int) $r['lvl'],
                    'position'        => (int) $r['position'],
                    'is_active'       => (bool) $r['is_active'],
                    'is_sensitive'    => (bool) $r['is_sensitive'],
                    'is_story_leaf'   => (bool) $r['is_story_leaf'],
                    'status'          => $r['status'],
                    'weight'          => (float) $r['weight'],
                    'keywords'        => $keywords,
                ];
            },
            $rows,
        );

        $payload = [
            'exported_at'    => gmdate('c'),
            'source'         => 'deschide-news-backend',
            'default_locale' => 'ro',
            'locales'        => ['ro', 'en', 'ru'],
            'total'          => count($topics),
            'topics'         => $topics,
        ];

        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($input->getOption('pretty')) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $output->writeln(json_encode($payload, $flags));

        return Command::SUCCESS;
    }
}
