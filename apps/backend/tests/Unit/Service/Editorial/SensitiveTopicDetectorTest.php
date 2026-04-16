<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\Topic;
use App\Repository\AppSettingRepository;
use App\Service\Editorial\SensitiveTopicDetector;
use PHPUnit\Framework\TestCase;

class SensitiveTopicDetectorTest extends TestCase
{
    private SensitiveTopicDetector $detector;
    private AppSettingRepository $settings;

    protected function setUp(): void
    {
        $this->settings = $this->createMock(AppSettingRepository::class);
        $this->settings->method('getJson')
            ->willReturn(['politica', 'alegeri', 'transnistria', 'gagauzia', 'externe', 'opinii', 'editoriale']);

        $this->detector = new SensitiveTopicDetector($this->settings);
    }

    public function testSportTopicNotSensitive(): void
    {
        $article = $this->createArticleWithTopic('sport', 'Noi prețuri la combustibil');

        $this->assertFalse($this->detector->isSensitive($article));
    }

    public function testPoliticaTopicIsSensitive(): void
    {
        $article = $this->createArticleWithTopic('politica', 'Noi legi adoptate', isSensitive: true);

        $this->assertTrue($this->detector->isSensitive($article));
        $this->assertContains('politica', $this->detector->getSensitiveTopics($article));
    }

    public function testPersonMentionInTitleIsSensitive(): void
    {
        $article = $this->createArticleWithTopic('economie', 'Igor Dodon acuzat de corupție');

        $this->assertTrue($this->detector->isSensitive($article));
        $this->assertContains('person_mention', $this->detector->getSensitiveTopics($article));
    }

    public function testNoPersonMentionInTitle(): void
    {
        $article = $this->createArticleWithTopic('economie', 'Noi prețuri la combustibil în Moldova');

        $this->assertFalse($this->detector->isSensitive($article));
    }

    public function testExcludesKnownNonPersonPatterns(): void
    {
        $article = $this->createArticleWithTopic('economie', 'Republica Moldova semnează acord cu Uniunea Europeană');

        $this->assertFalse($this->detector->isSensitive($article));
    }

    public function testCategorySensitivity(): void
    {
        $article = new Article();
        $article->setTitle('Test article');
        $article->setContent('Some content with many words for the article to be long enough');

        $category = new Category();
        $category->setTitle('Politica');
        // We need to set the slug via reflection since there's no setter
        $ref = new \ReflectionClass($category);
        if ($ref->hasProperty('slug')) {
            $prop = $ref->getProperty('slug');
            $prop->setValue($category, 'politica');
        }
        $article->setCategory($category);

        $result = $this->detector->getSensitiveTopics($article);

        $this->assertContains('category:politica', $result);
    }

    public function testMaiaSanduDetectedAsPersonMention(): void
    {
        $article = $this->createArticleWithTopic('economie', 'Maia Sandu a anunțat noi reforme');

        $this->assertTrue($this->detector->isSensitive($article));
    }

    private function createArticleWithTopic(string $topicSlug, string $title, bool $isSensitive = false): Article
    {
        $article = new Article();
        $article->setTitle($title);
        $article->setContent('Some content about the topic at hand');

        $topic = new Topic();
        $topic->setIsSensitive($isSensitive);
        $ref = new \ReflectionClass($topic);
        if ($ref->hasProperty('slug')) {
            $prop = $ref->getProperty('slug');
            $prop->setValue($topic, $topicSlug);
        }

        // Add topic via reflection (addTopic or the collection)
        $topicsRef = $ref->getProperty('articles') ?? null;
        // Use Article::addTopic if available
        if (method_exists($article, 'addTopic')) {
            $article->addTopic($topic);
        } else {
            // Direct collection manipulation via reflection
            $articleRef = new \ReflectionClass($article);
            $topicsProp = $articleRef->getProperty('topics');
            $topicsProp->getValue($article)->add($topic);
        }

        return $article;
    }
}
