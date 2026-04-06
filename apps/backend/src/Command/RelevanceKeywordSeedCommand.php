<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\RelevanceKeyword;
use App\Enum\KeywordAddedBy;
use App\Repository\RelevanceKeywordRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:relevance:seed',
    description: 'Seed relevance keywords from YAML config into database',
)]
final class RelevanceKeywordSeedCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RelevanceKeywordRepository $repository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Actually insert into database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');

        $io->title('Relevance Keyword Seeder');

        $keywords = $this->getAllKeywords();
        $existingCount = $this->repository->count();

        $io->info(sprintf('Found %d keywords to seed (%d already in DB)', \count($keywords), $existingCount));

        if (!$force) {
            $this->displayTable($io, $keywords);
            $io->note('Dry run — use --force to insert into database.');

            return Command::SUCCESS;
        }

        // Truncate existing keywords if re-seeding
        if ($existingCount > 0) {
            $this->em->getConnection()->executeStatement('DELETE FROM relevance_keywords WHERE added_by = \'manual\'');
            $io->warning('Cleared existing MANUAL keywords.');
        }

        $inserted = 0;
        foreach ($keywords as $kw) {
            $entity = new RelevanceKeyword();
            $entity->setKeyword($kw['keyword']);
            $entity->setTier($kw['tier']);
            $entity->setLanguage($kw['language']);
            $entity->setAddedBy(KeywordAddedBy::MANUAL);

            $this->em->persist($entity);
            $inserted++;
        }

        $this->em->flush();

        $this->displayTable($io, $keywords);
        $io->success(sprintf('Inserted %d keywords into relevance_keywords table.', $inserted));

        // Summary by tier
        foreach ([1, 2, 3, 4] as $tier) {
            $count = \count(array_filter($keywords, fn ($k) => $k['tier'] === $tier));
            $io->writeln(sprintf('  Tier %d: %d keywords', $tier, $count));
        }

        return Command::SUCCESS;
    }

    /**
     * @return list<array{keyword: string, tier: int, language: string}>
     */
    private function getAllKeywords(): array
    {
        $keywords = [];

        // Tier 1 — Direct Moldova mentions (from existing YAML)
        $tier1 = [
            'Moldova', 'Moldovan', 'Chișinău', 'Chisinau', 'Kishinev',
            'Кишинёв', 'Молдова', 'Transnistria', 'Transdniestria',
            'Приднестровье', 'Gagauzia', 'Гагаузия', 'Sandu', 'Recean', 'Moldpres',
        ];
        foreach ($tier1 as $kw) {
            $keywords[] = ['keyword' => $kw, 'tier' => 1, 'language' => 'multi'];
        }

        // Tier 2 — Regional context
        $tier2 = [
            'Eastern Europe', 'Black Sea', 'EU enlargement', 'EU accession',
            'Eastern Partnership', 'Russia sanctions', 'energy security Europe',
            'NATO east', 'Ukraine border', 'Romania Moldova', 'OSCE',
            'IMF Eastern Europe', 'World Bank Moldova',
        ];
        foreach ($tier2 as $kw) {
            $keywords[] = ['keyword' => $kw, 'tier' => 2, 'language' => 'multi'];
        }

        // Tier 3 — Key entities (existing)
        $tier3 = [
            'Maia Sandu', 'Dorin Recean', 'Igor Dodon', 'Ilan Shor',
            'Krasnoselsky', 'Красносельский', 'Moldovagaz', 'Energocom',
        ];
        // Tier 3 — New actors
        $tier3New = [
            'Lavrov', 'Borrell', 'von der Leyen', 'Zelensky',
            'Зеленський', 'Лавров',
        ];
        foreach ([...$tier3, ...$tier3New] as $kw) {
            $keywords[] = ['keyword' => $kw, 'tier' => 3, 'language' => 'multi'];
        }

        // Tier 4 — Declarations/Statements (requires Tier 1 or Tier 3 co-occurrence)
        $tier4 = [
            // EN
            ['declared', 'en'], ['statement', 'en'], ['announced', 'en'],
            ['condemned', 'en'], ['urged', 'en'], ['warned', 'en'],
            ['agreement signed', 'en'], ['sanctions imposed', 'en'], ['memorandum', 'en'],
            // RO
            ['a declarat', 'ro'], ['declarație', 'ro'], ['a anunțat', 'ro'],
            ['a condamnat', 'ro'], ['acord semnat', 'ro'], ['sancțiuni', 'ro'],
            // RU
            ['заявил', 'ru'], ['заявление', 'ru'], ['объявил', 'ru'],
            ['осудил', 'ru'], ['санкции', 'ru'], ['соглашение', 'ru'],
            // FR
            ['a déclaré', 'fr'], ['déclaration', 'fr'], ['a annoncé', 'fr'],
            // DE
            ['erklärte', 'de'], ['Erklärung', 'de'], ['angekündigt', 'de'],
        ];
        foreach ($tier4 as [$kw, $lang]) {
            $keywords[] = ['keyword' => $kw, 'tier' => 4, 'language' => $lang];
        }

        return $keywords;
    }

    /**
     * @param list<array{keyword: string, tier: int, language: string}> $keywords
     */
    private function displayTable(SymfonyStyle $io, array $keywords): void
    {
        $io->table(
            ['Tier', 'Keyword', 'Language'],
            array_map(fn ($k) => [$k['tier'], $k['keyword'], $k['language']], $keywords),
        );
    }
}
