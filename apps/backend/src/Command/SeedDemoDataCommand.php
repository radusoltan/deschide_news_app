<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Article;
use App\Entity\Author;
use App\Entity\Category;
use App\Entity\LiveText;
use App\Entity\LiveTextCollaborator;
use App\Entity\LiveTextMatchEvent;
use App\Entity\LiveTextPost;
use App\Entity\LiveTextSportMatch;
use App\Entity\Tag;
use App\Entity\User;
use App\Enum\ArticleBadge;
use App\Enum\ArticleStatus;
use App\Enum\LiveTextStatus;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Faker\Factory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-demo-data',
    description: 'Seeds complete demo data for testing (archive articles, LiveText sport matches, tags)'
)]
class SeedDemoDataCommand extends Command
{
    private const ARCHIVE_YEARS = [2022, 2023, 2024];
    private const ARTICLES_PER_MONTH = 15;
    private const SPORT_MATCHES_COUNT = 5;
    private const TAGS_COUNT = 20;

    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('archive', null, InputOption::VALUE_NONE, 'Seed archive articles (2022-2024)')
            ->addOption('sport', null, InputOption::VALUE_NONE, 'Seed LiveText sport matches')
            ->addOption('tags', null, InputOption::VALUE_NONE, 'Seed tags')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Seed all demo data')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Seeding Demo Data for Testing');

        $seedAll = $input->getOption('all');
        $seedArchive = $input->getOption('archive') || $seedAll;
        $seedSport = $input->getOption('sport') || $seedAll;
        $seedTags = $input->getOption('tags') || $seedAll;

        if (!$seedArchive && !$seedSport && !$seedTags) {
            $io->warning('No option specified. Use --all to seed all data or specify individual options.');
            $io->listing([
                '--archive  : Seed archive articles (2022-2024)',
                '--sport    : Seed LiveText sport matches with events',
                '--tags     : Seed tags',
                '--all      : Seed all demo data',
            ]);

            return Command::SUCCESS;
        }

        // Get required entities
        $users = $this->entityManager->getRepository(User::class)->findAll();
        if (empty($users)) {
            $io->error('No users found. Please run fixtures first.');

            return Command::FAILURE;
        }

        $categories = $this->entityManager->getRepository(Category::class)->findAll();
        if (empty($categories)) {
            $io->error('No categories found. Please run fixtures first.');

            return Command::FAILURE;
        }

        $authors = $this->entityManager->getRepository(Author::class)->findAll();
        if (empty($authors)) {
            $io->error('No authors found. Please run fixtures first.');

            return Command::FAILURE;
        }

        if ($seedTags) {
            $this->seedTags($io);
        }

        if ($seedArchive) {
            $this->seedArchiveArticles($io, $categories, $authors);
        }

        if ($seedSport) {
            // Reload entities after potential clear() in archive seeding
            $users = $this->entityManager->getRepository(User::class)->findAll();
            $categories = $this->entityManager->getRepository(Category::class)->findAll();
            $this->seedSportMatches($io, $users, $categories);
        }

        $io->success('Demo data seeding completed!');

        return Command::SUCCESS;
    }

    private function seedTags(SymfonyStyle $io): void
    {
        $io->section('Seeding Tags');

        $tagsRo = [
            'Politică' => 'politics',
            'Economie' => 'economy',
            'Sport' => 'sport',
            'Cultură' => 'culture',
            'Tehnologie' => 'technology',
            'Sănătate' => 'health',
            'Educație' => 'education',
            'Mediu' => 'environment',
            'Justiție' => 'justice',
            'Infrastructură' => 'infrastructure',
            'Moldova' => 'moldova',
            'UE' => 'eu',
            'România' => 'romania',
            'Rusia' => 'russia',
            'Chișinău' => 'chisinau',
            'COVID-19' => 'covid-19',
            'Alegeri' => 'elections',
            'Corupție' => 'corruption',
            'Agricultură' => 'agriculture',
            'Energie' => 'energy',
        ];

        $tagsEn = [
            'politics' => 'Politics',
            'economy' => 'Economy',
            'sport' => 'Sport',
            'culture' => 'Culture',
            'technology' => 'Technology',
            'health' => 'Health',
            'education' => 'Education',
            'environment' => 'Environment',
            'justice' => 'Justice',
            'infrastructure' => 'Infrastructure',
            'moldova' => 'Moldova',
            'eu' => 'EU',
            'romania' => 'Romania',
            'russia' => 'Russia',
            'chisinau' => 'Chisinau',
            'covid-19' => 'COVID-19',
            'elections' => 'Elections',
            'corruption' => 'Corruption',
            'agriculture' => 'Agriculture',
            'energy' => 'Energy',
        ];

        $tagsRu = [
            'politics' => 'Политика',
            'economy' => 'Экономика',
            'sport' => 'Спорт',
            'culture' => 'Культура',
            'technology' => 'Технологии',
            'health' => 'Здоровье',
            'education' => 'Образование',
            'environment' => 'Экология',
            'justice' => 'Правосудие',
            'infrastructure' => 'Инфраструктура',
            'moldova' => 'Молдова',
            'eu' => 'ЕС',
            'romania' => 'Румыния',
            'russia' => 'Россия',
            'chisinau' => 'Кишинёв',
            'covid-19' => 'COVID-19',
            'elections' => 'Выборы',
            'corruption' => 'Коррупция',
            'agriculture' => 'Сельское хозяйство',
            'energy' => 'Энергетика',
        ];

        $count = 0;
        foreach ($tagsRo as $nameRo => $slug) {
            // Check if tag already exists
            $existing = $this->entityManager->getRepository(Tag::class)->findOneBy(['slug' => $slug]);
            if ($existing) {
                continue;
            }

            $tag = new Tag();
            $tag->setName($nameRo);
            $tag->setSlug($slug);
            $tag->setTranslatableLocale('ro');

            $this->entityManager->persist($tag);
            $this->entityManager->flush();

            // English translation
            $tag->setName($tagsEn[$slug]);
            $tag->setTranslatableLocale('en');
            $this->entityManager->persist($tag);
            $this->entityManager->flush();

            // Russian translation
            $tag->setName($tagsRu[$slug]);
            $tag->setTranslatableLocale('ru');
            $this->entityManager->persist($tag);
            $this->entityManager->flush();

            ++$count;
        }

        $io->success("Created $count tags with translations");
    }

    private function seedArchiveArticles(SymfonyStyle $io, array $categories, array $authors): void
    {
        $io->section('Seeding Archive Articles (2022-2024)');

        $fakerRo = Factory::create('ro_RO');
        $fakerEn = Factory::create('en_US');
        $fakerRu = Factory::create('ru_RU');

        $tags = $this->entityManager->getRepository(Tag::class)->findAll();
        $totalArticles = 0;

        foreach (self::ARCHIVE_YEARS as $year) {
            $io->text("Creating articles for year $year...");

            for ($month = 1; $month <= 12; ++$month) {
                for ($i = 0; $i < self::ARTICLES_PER_MONTH; ++$i) {
                    $article = new Article();

                    // Random category
                    $category = $categories[array_rand($categories)];
                    $article->setCategory($category);

                    // Random 1-2 authors
                    $authorCount = rand(1, 2);
                    $shuffledAuthors = $authors;
                    shuffle($shuffledAuthors);
                    for ($a = 0; $a < $authorCount; ++$a) {
                        $article->addAuthor($shuffledAuthors[$a]);
                    }

                    // All archive articles are published
                    $article->setStatus(ArticleStatus::PUBLISHED);

                    // Random day in the month
                    $day = rand(1, 28);
                    $hour = rand(8, 20);
                    $minute = rand(0, 59);
                    $publishedAt = new DateTimeImmutable("$year-$month-$day $hour:$minute:00");
                    $article->setPublishedAt($publishedAt);

                    // Random featured (5%)
                    $article->setIsFeatured(rand(1, 100) <= 5);

                    // Random badge (5%)
                    if (rand(1, 100) <= 5) {
                        $badges = [ArticleBadge::BREAKING, ArticleBadge::ALERT, ArticleBadge::FLASH];
                        $article->setBadge($badges[array_rand($badges)]);
                    }

                    // View count (older articles have more views)
                    $ageMultiplier = 2025 - $year;
                    $article->setViewCount(rand(100 * $ageMultiplier, 5000 * $ageMultiplier));

                    // Add 1-3 random tags
                    if (!empty($tags)) {
                        $tagCount = rand(1, 3);
                        $shuffledTags = $tags;
                        shuffle($shuffledTags);
                        for ($t = 0; $t < min($tagCount, \count($shuffledTags)); ++$t) {
                            $article->addTag($shuffledTags[$t]);
                        }
                    }

                    // Romanian content
                    $article->setTitle($fakerRo->sentence(rand(6, 12)));
                    $article->setLead($fakerRo->paragraph(2));
                    $article->setContent($this->generateContent($fakerRo));
                    $article->setTranslatableLocale('ro');
                    $this->entityManager->persist($article);
                    $this->entityManager->flush();

                    // English translation
                    $article->setTitle($fakerEn->sentence(rand(6, 12)));
                    $article->setLead($fakerEn->paragraph(2));
                    $article->setContent($this->generateContent($fakerEn));
                    $article->setTranslatableLocale('en');
                    $this->entityManager->persist($article);
                    $this->entityManager->flush();

                    // Russian translation
                    $article->setTitle($fakerRu->sentence(rand(6, 12)));
                    $article->setLead($fakerRu->paragraph(2));
                    $article->setContent($this->generateContent($fakerRu));
                    $article->setTranslatableLocale('ru');
                    $this->entityManager->persist($article);
                    $this->entityManager->flush();

                    ++$totalArticles;
                }

                // Clear entity manager periodically to prevent memory issues
                if ($month % 3 === 0) {
                    $this->entityManager->clear();
                    // Reload categories and authors
                    $categories = $this->entityManager->getRepository(Category::class)->findAll();
                    $authors = $this->entityManager->getRepository(Author::class)->findAll();
                    $tags = $this->entityManager->getRepository(Tag::class)->findAll();
                }
            }

            $io->success("Created articles for year $year");
        }

        $io->success("Total archive articles created: $totalArticles");
    }

    private function seedSportMatches(SymfonyStyle $io, array $users, array $categories): void
    {
        $io->section('Seeding LiveText Sport Matches');

        $fakerRo = Factory::create('ro_RO');
        $author = $users[0];

        // Get Sport category if exists, otherwise use first category
        $sportCategory = null;
        foreach ($categories as $cat) {
            if (str_contains(strtolower($cat->getTitle()), 'sport')) {
                $sportCategory = $cat;
                break;
            }
        }
        if (!$sportCategory) {
            $sportCategory = $categories[0];
        }

        $sportTypes = ['football', 'basketball', 'tennis', 'handball', 'volleyball'];
        $competitions = [
            'football' => ['Liga Națională', 'Cupa Moldovei', 'Champions League', 'Europa League'],
            'basketball' => ['Liga Națională Baschet', 'FIBA EuroBasket'],
            'tennis' => ['ATP Tour', 'WTA Tour', 'Grand Slam'],
            'handball' => ['Liga Națională Handbal', 'EHF Champions League'],
            'volleyball' => ['Liga Națională Volei', 'CEV Champions League'],
        ];

        $teams = [
            'football' => [
                ['Sheriff Tiraspol', 'Zimbru Chișinău'],
                ['Petrocub Hîncești', 'Milsami Orhei'],
                ['Real Madrid', 'Barcelona'],
                ['Bayern München', 'Borussia Dortmund'],
            ],
            'basketball' => [
                ['CSU Sibiu', 'U-BT Cluj'],
                ['Steaua București', 'Dinamo București'],
            ],
            'tennis' => [
                ['Player A', 'Player B'],
            ],
            'handball' => [
                ['CSM București', 'Corona Brașov'],
            ],
            'volleyball' => [
                ['Dinamo București', 'Arcada Galați'],
            ],
        ];

        $matchStatuses = ['not_started', 'live', 'half_time', 'finished'];

        for ($i = 1; $i <= self::SPORT_MATCHES_COUNT; ++$i) {
            // Create LiveText for the match
            $liveText = new LiveText();
            $liveText->setAuthor($author);
            $liveText->setCategory($sportCategory);

            // Random sport type
            $sportType = $sportTypes[array_rand($sportTypes)];
            $competition = $competitions[$sportType][array_rand($competitions[$sportType])];
            $teamPair = $teams[$sportType][array_rand($teams[$sportType])];

            // Status: one LIVE, others varied
            if ($i === 1) {
                $ltStatus = LiveTextStatus::LIVE;
                $matchStatus = 'live';
            } elseif ($i === 2) {
                $ltStatus = LiveTextStatus::LIVE;
                $matchStatus = 'half_time';
            } else {
                $ltStatus = $i <= 3 ? LiveTextStatus::ENDED : LiveTextStatus::DRAFT;
                $matchStatus = $i <= 3 ? 'finished' : 'not_started';
            }

            $liveText->setStatus($ltStatus);

            $startTime = new DateTime('-' . rand(1, 72) . ' hours');
            $liveText->setStartTime($startTime);

            if ($ltStatus === LiveTextStatus::ENDED) {
                $liveText->setEndTime((clone $startTime)->modify('+2 hours'));
            }

            // Set title
            $matchTitle = "{$teamPair[0]} vs {$teamPair[1]} - $competition";
            $liveText->setLocale('ro');
            $liveText->setTitle("LIVE: $matchTitle");
            $liveText->setDescription("Urmărește în direct meciul $matchTitle.");

            $this->entityManager->persist($liveText);
            $this->entityManager->flush();

            // English translation
            $liveText->setLocale('en');
            $liveText->setTitle("LIVE: $matchTitle");
            $liveText->setDescription("Follow the live match $matchTitle.");
            $this->entityManager->persist($liveText);
            $this->entityManager->flush();

            // Russian translation
            $liveText->setLocale('ru');
            $liveText->setTitle("LIVE: $matchTitle");
            $liveText->setDescription("Следите за матчем $matchTitle в прямом эфире.");
            $this->entityManager->persist($liveText);
            $this->entityManager->flush();

            // Reset locale
            $liveText->setLocale('ro');

            // Create SportMatch
            $sportMatch = new LiveTextSportMatch();
            $sportMatch->setLiveText($liveText);
            $sportMatch->setSportType($sportType);
            $sportMatch->setHomeTeam($teamPair[0]);
            $sportMatch->setAwayTeam($teamPair[1]);
            $sportMatch->setCompetition($competition);
            $sportMatch->setVenue($fakerRo->city() . ' Arena');
            $sportMatch->setStatus($matchStatus);

            // Set scores based on status
            if (\in_array($matchStatus, ['live', 'half_time', 'finished'])) {
                $sportMatch->setHomeScore(rand(0, 4));
                $sportMatch->setAwayScore(rand(0, 4));

                if ($sportType === 'football') {
                    $sportMatch->setCurrentMinute($matchStatus === 'half_time' ? 45 : rand(1, 90));
                    $sportMatch->setCurrentPeriod($matchStatus === 'half_time' ? 'Half Time' : ($sportMatch->getCurrentMinute() > 45 ? '2nd Half' : '1st Half'));
                }

                $sportMatch->setActualStartTime($startTime);
            }

            $sportMatch->setScheduledStartTime($startTime);

            if ($matchStatus === 'finished') {
                $sportMatch->setEndTime((clone $startTime)->modify('+2 hours'));
            }

            // Add statistics
            if ($sportType === 'football' && $matchStatus !== 'not_started') {
                $sportMatch->setStatistics([
                    'possession' => [rand(40, 60), 100 - rand(40, 60)],
                    'shots' => [rand(5, 15), rand(5, 15)],
                    'shots_on_target' => [rand(2, 8), rand(2, 8)],
                    'corners' => [rand(2, 10), rand(2, 10)],
                    'fouls' => [rand(5, 15), rand(5, 15)],
                ]);
            }

            $this->entityManager->persist($sportMatch);
            $this->entityManager->flush();

            // Add match events (goals, cards, etc.)
            $this->addMatchEvents($sportMatch, $sportType);

            // Add LiveText posts for match updates
            $this->addMatchPosts($liveText, $sportMatch, $author, $startTime);

            $io->success("Created sport match #$i: $matchTitle ({$sportMatch->getStatus()})");
        }

        $this->entityManager->flush();
        $io->success('Sport matches with events created successfully');
    }

    private function addMatchEvents(LiveTextSportMatch $sportMatch, string $sportType): void
    {
        if ($sportMatch->getStatus() === 'not_started') {
            return;
        }

        $eventTypes = match ($sportType) {
            'football' => ['goal', 'yellow_card', 'red_card', 'substitution', 'penalty', 'own_goal'],
            'basketball' => ['basket', 'three_pointer', 'free_throw', 'timeout', 'foul'],
            'tennis' => ['ace', 'double_fault', 'break_point', 'game_won', 'set_won'],
            'handball' => ['goal', 'penalty', 'timeout', 'red_card'],
            'volleyball' => ['point', 'ace', 'block', 'timeout'],
            default => ['score', 'foul', 'timeout'],
        };

        $homeTeam = $sportMatch->getHomeTeam();
        $awayTeam = $sportMatch->getAwayTeam();

        $eventCount = rand(3, 8);
        for ($e = 0; $e < $eventCount; ++$e) {
            $event = new LiveTextMatchEvent();
            $event->setSportMatch($sportMatch);
            $event->setEventType($eventTypes[array_rand($eventTypes)]);

            // Team must be 'home' or 'away'
            $isHome = rand(0, 1) === 0;
            $event->setTeam($isHome ? 'home' : 'away');
            $teamName = $isHome ? $homeTeam : $awayTeam;

            $event->setPlayerName('Player ' . chr(65 + rand(0, 25)));

            // Always set event minute (required field)
            $event->setEventMinute(rand(1, 90));

            $event->setDescription($this->getEventDescription($event->getEventType(), $event->getPlayerName(), $teamName));

            $this->entityManager->persist($event);
        }
    }

    private function getEventDescription(string $eventType, string $player, string $team): string
    {
        return match ($eventType) {
            'goal' => "GOL! $player marchează pentru $team!",
            'yellow_card' => "Cartonaș galben pentru $player ($team)",
            'red_card' => "Cartonaș roșu! $player ($team) este eliminat!",
            'substitution' => "Substituție pentru $team: $player intră pe teren",
            'penalty' => "Penalty acordat pentru $team!",
            'own_goal' => "Autogol! $player înscrie în propria poartă",
            default => "$eventType: $player ($team)",
        };
    }

    private function addMatchPosts(LiveText $liveText, LiveTextSportMatch $sportMatch, User $author, DateTime $startTime): void
    {
        $posts = [];

        // Opening post
        $posts[] = [
            'content' => "Bine ați venit la transmisiunea live a meciului {$sportMatch->getHomeTeam()} vs {$sportMatch->getAwayTeam()}!",
            'isKeyPoint' => true,
            'minutesAfterStart' => 0,
        ];

        if ($sportMatch->getStatus() !== 'not_started') {
            // Match start post
            $posts[] = [
                'content' => '⚽ Meciul a început! Primul șut aparține echipei ' . $sportMatch->getHomeTeam(),
                'isKeyPoint' => true,
                'minutesAfterStart' => 1,
            ];

            // Add random updates
            $updateCount = rand(5, 10);
            for ($u = 0; $u < $updateCount; ++$u) {
                $posts[] = [
                    'content' => 'Acțiune pe teren. ' . ['Posesie prelungită', 'Atac periculos', 'Corner acordat', 'Fault în zona de mijloc', 'Ocazie mare de gol!'][rand(0, 4)],
                    'isKeyPoint' => rand(1, 100) <= 20,
                    'minutesAfterStart' => ($u + 2) * rand(3, 8),
                ];
            }

            if (\in_array($sportMatch->getStatus(), ['half_time', 'finished'])) {
                $posts[] = [
                    'content' => "⏱️ Pauză! Scorul este {$sportMatch->getHomeTeam()} {$sportMatch->getHomeScore()} - {$sportMatch->getAwayScore()} {$sportMatch->getAwayTeam()}",
                    'isKeyPoint' => true,
                    'minutesAfterStart' => 47,
                ];
            }

            if ($sportMatch->getStatus() === 'finished') {
                $posts[] = [
                    'content' => "🏁 Final de meci! Scorul final: {$sportMatch->getHomeTeam()} {$sportMatch->getHomeScore()} - {$sportMatch->getAwayScore()} {$sportMatch->getAwayTeam()}",
                    'isKeyPoint' => true,
                    'minutesAfterStart' => 95,
                ];
            }
        }

        foreach ($posts as $index => $postData) {
            $post = new LiveTextPost();
            $post->setLiveText($liveText);
            $post->setAuthor($author);
            $post->setContent($postData['content']);
            $post->setContentHtml('<p>' . $postData['content'] . '</p>');
            $post->setIsKeyPoint($postData['isKeyPoint']);
            $post->setPosition($index);

            $publishedAt = (clone $startTime)->modify('+' . $postData['minutesAfterStart'] . ' minutes');
            $post->setPublishedAt($publishedAt);

            $this->entityManager->persist($post);
        }
    }

    private function generateContent($faker): string
    {
        $paragraphCount = rand(3, 6);
        $paragraphs = [];

        for ($i = 0; $i < $paragraphCount; ++$i) {
            $paragraphs[] = $faker->paragraph(rand(3, 6));
        }

        return implode("\n\n", $paragraphs);
    }
}
