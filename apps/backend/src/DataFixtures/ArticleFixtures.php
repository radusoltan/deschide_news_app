<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Article;
use App\Enum\ArticleBadge;
use App\Enum\ArticleStatus;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class ArticleFixtures extends Fixture implements DependentFixtureInterface
{
    private const ARTICLE_COUNT = 80;

    private const CATEGORY_COUNT = 8;

    private const AUTHOR_COUNT = 12;

    public function getDependencies(): array
    {
        return [
            CategoryFixtures::class,
            AuthorFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $fakerRo = Factory::create('ro_RO');
        $fakerEn = Factory::create('en_US');
        $fakerRu = Factory::create('ru_RU');

        $publishedArticles = [];

        for ($i = 1; $i <= self::ARTICLE_COUNT; ++$i) {
            $article = new Article();

            // Assign category (uniform distribution)
            $categoryIndex = ($i - 1) % self::CATEGORY_COUNT;
            $category = $this->getReference('category_' . $categoryIndex, \App\Entity\Category::class);
            $article->setCategory($category);

            // Assign 1-3 authors
            $authorCount = rand(1, 3);
            for ($j = 0; $j < $authorCount; ++$j) {
                $authorIndex = rand(0, self::AUTHOR_COUNT - 1);
                $author = $this->getReference('author_' . $authorIndex, \App\Entity\Author::class);
                $article->addAuthor($author);
            }

            // Status distribution: 80% PUBLISHED, 15% SUBMITTED, 5% NEW
            $rand = rand(1, 100);
            if ($rand <= 80) {
                $status = ArticleStatus::PUBLISHED;
            } elseif ($rand <= 95) {
                $status = ArticleStatus::SUBMITTED;
            } else {
                $status = ArticleStatus::NEW;
            }
            $article->setStatus($status);

            // Badge distribution: 70% null, 10% BREAKING, 10% ALERT, 10% FLASH
            $badgeRand = rand(1, 100);
            if ($badgeRand <= 70) {
                $article->setBadge(null);
            } elseif ($badgeRand <= 80) {
                $article->setBadge(ArticleBadge::BREAKING);
            } elseif ($badgeRand <= 90) {
                $article->setBadge(ArticleBadge::ALERT);
            } else {
                $article->setBadge(ArticleBadge::FLASH);
            }

            // Featured: 10-15% (8-12 articles)
            $article->setIsFeatured($i <= 12);

            // View count: weighted towards lower numbers
            $viewCount = rand(1, 100) <= 70 ? rand(50, 500) : rand(500, 10000);
            $article->setViewCount($viewCount);

            // Published date and publishedLocales for PUBLISHED articles (last 30 days)
            if ($status === ArticleStatus::PUBLISHED) {
                $daysAgo = $this->getRandomDaysAgo();
                $publishedAt = new DateTimeImmutable("-$daysAgo days");
                $article->setPublishedAt($publishedAt);
                $publishedArticles[] = $i;

                // Per-locale publishing: 60% RO only, 25% RO+EN+RU, 15% RO+EN
                $localeRand = rand(1, 100);
                if ($localeRand <= 60) {
                    $article->setPublishedLocales(['ro']);
                } elseif ($localeRand <= 85) {
                    $article->setPublishedLocales(['ro', 'en', 'ru']);
                } else {
                    $article->setPublishedLocales(['ro', 'en']);
                }
            }

            // Publish at for SUBMITTED/NEW (30% have future dates)
            if (($status === ArticleStatus::SUBMITTED || $status === ArticleStatus::NEW) && rand(1, 100) <= 30) {
                $daysAhead = rand(1, 7);
                $publishAt = new DateTimeImmutable("+$daysAhead days");
                $article->setPublishAt($publishAt);
            }

            // Romanian content (default locale)
            $article->setTitle($fakerRo->sentence(rand(6, 12)));
            $article->setLead($fakerRo->paragraph(2));
            $article->setContent($this->generateContent($fakerRo));
            $article->setTranslatableLocale('ro');
            $manager->persist($article);
            $manager->flush();

            // English translation
            $article->setTitle($fakerEn->sentence(rand(6, 12)));
            $article->setLead($fakerEn->paragraph(2));
            $article->setContent($this->generateContent($fakerEn));
            $article->setTranslatableLocale('en');
            $manager->persist($article);
            $manager->flush();

            // Russian translation
            $article->setTitle($fakerRu->sentence(rand(6, 12)));
            $article->setLead($fakerRu->paragraph(2));
            $article->setContent($this->generateContent($fakerRu));
            $article->setTranslatableLocale('ru');
            $manager->persist($article);
            $manager->flush();

            // Reset to default locale
            $manager->refresh($article);
            $article->setTranslatableLocale('ro');

            // Add reference for ArticleImageFixtures and related articles
            $this->addReference('article_' . $i, $article);

            if ($i % 20 === 0) {
                echo "  Created $i/" . self::ARTICLE_COUNT . " articles...\n";
            }
        }

        // Add related articles to 40% of PUBLISHED articles
        $this->addRelatedArticles($manager, $publishedArticles);

        echo '✅ Created ' . self::ARTICLE_COUNT . ' articles (' . \count($publishedArticles) . " published)\n";
    }

    private function generateContent($faker): string
    {
        $paragraphCount = rand(3, 8);
        $paragraphs = [];

        for ($i = 0; $i < $paragraphCount; ++$i) {
            $paragraphs[] = $faker->paragraph(rand(4, 8));
        }

        return implode("\n\n", $paragraphs);
    }

    private function getRandomDaysAgo(): int
    {
        $rand = rand(1, 100);

        // 40% in last 7 days
        if ($rand <= 40) {
            return rand(0, 7);
        }

        // 30% between 7-14 days
        if ($rand <= 70) {
            return rand(7, 14);
        }

        // 20% between 14-21 days
        if ($rand <= 90) {
            return rand(14, 21);
        }

        // 10% between 21-30 days
        return rand(21, 30);
    }

    private function addRelatedArticles(ObjectManager $manager, array $publishedArticles): void
    {
        // 40% of published articles get related articles
        $articlesWithRelated = \array_slice($publishedArticles, 0, (int) (\count($publishedArticles) * 0.4));

        foreach ($articlesWithRelated as $articleIndex) {
            $article = $this->getReference('article_' . $articleIndex, Article::class);

            // Get number of related articles (1-5)
            $relatedCount = $this->getRandomRelatedCount();

            // Pick random published articles (excluding self)
            $availableArticles = array_filter($publishedArticles, fn ($idx) => $idx !== $articleIndex);
            shuffle($availableArticles);
            $selectedArticles = \array_slice($availableArticles, 0, $relatedCount);

            foreach ($selectedArticles as $relatedIndex) {
                $relatedArticle = $this->getReference('article_' . $relatedIndex, Article::class);
                $article->addRelatedArticle($relatedArticle);
            }

            $manager->persist($article);
        }

        $manager->flush();

        echo '  Added related articles to ' . \count($articlesWithRelated) . " articles\n";
    }

    private function getRandomRelatedCount(): int
    {
        $rand = rand(1, 100);

        if ($rand <= 30) {
            return 1;
        }  // 30%
        if ($rand <= 60) {
            return 2;
        }  // 30%
        if ($rand <= 80) {
            return 3;
        }  // 20%
        if ($rand <= 95) {
            return 4;
        }  // 15%

        return 5;                    // 5%
    }
}
