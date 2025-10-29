<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Article;
use App\Entity\ImportantArticlesList;
use App\Enum\ArticleStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ImportantArticlesListFixtures extends Fixture implements DependentFixtureInterface
{
    private const IMPORTANT_ARTICLES_COUNT = 5;
    private const ARTICLE_COUNT = 80;

    public function getDependencies(): array
    {
        return [
            ArticleFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        // Get published articles to select from
        $publishedArticles = [];
        for ($i = 1; $i <= self::ARTICLE_COUNT; $i++) {
            /** @var Article $article */
            $article = $this->getReference('article_' . $i, Article::class);
            if ($article->getStatus() === ArticleStatus::PUBLISHED) {
                $publishedArticles[] = $article;
            }
        }

        // Shuffle and select first 5 published articles
        shuffle($publishedArticles);
        $selectedArticles = array_slice($publishedArticles, 0, self::IMPORTANT_ARTICLES_COUNT);

        // Create important articles list entries with positions 1-5
        foreach ($selectedArticles as $position => $article) {
            $importantArticle = new ImportantArticlesList();
            $importantArticle->setArticle($article);
            $importantArticle->setPosition($position + 1); // Position starts at 1

            $manager->persist($importantArticle);
        }

        $manager->flush();

        echo "✅ Created " . self::IMPORTANT_ARTICLES_COUNT . " important articles (pinned to homepage)\n";
    }
}
