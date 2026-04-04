<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Article;
use App\Entity\Topic;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TopicTest extends TestCase
{
    #[Test]
    public function itInitializesWithDefaults(): void
    {
        $topic = new Topic();

        $this->assertNull($topic->getId());
        $this->assertNull($topic->getTitle());
        $this->assertNull($topic->getSlug());
        $this->assertNull($topic->getDescription());
        $this->assertNull($topic->getParent());
        $this->assertTrue($topic->isActive());
        $this->assertSame(0, $topic->getPosition());
        $this->assertSame(0, $topic->getLvl());
        $this->assertSame(0, $topic->getLft());
        $this->assertSame(0, $topic->getRgt());
        $this->assertNull($topic->getRoot());
        $this->assertInstanceOf(ArrayCollection::class, $topic->getChildren());
        $this->assertInstanceOf(ArrayCollection::class, $topic->getArticles());
        $this->assertCount(0, $topic->getChildren());
        $this->assertCount(0, $topic->getArticles());
    }

    #[Test]
    public function itSetsAndGetsTitle(): void
    {
        $topic = new Topic();
        $result = $topic->setTitle('Alegeri');

        $this->assertSame('Alegeri', $topic->getTitle());
        $this->assertSame($topic, $result); // fluent interface
    }

    #[Test]
    public function itSetsAndGetsSlug(): void
    {
        $topic = new Topic();
        $topic->setSlug('alegeri-parlamentare');

        $this->assertSame('alegeri-parlamentare', $topic->getSlug());
    }

    #[Test]
    public function itSetsAndGetsDescription(): void
    {
        $topic = new Topic();
        $topic->setDescription('Procese electorale');

        $this->assertSame('Procese electorale', $topic->getDescription());

        $topic->setDescription(null);
        $this->assertNull($topic->getDescription());
    }

    #[Test]
    public function itSetsAndGetsParent(): void
    {
        $parent = new Topic();
        $parent->setTitle('Politica');

        $child = new Topic();
        $child->setTitle('Alegeri');
        $child->setParent($parent);

        $this->assertSame($parent, $child->getParent());

        $child->setParent(null);
        $this->assertNull($child->getParent());
    }

    #[Test]
    public function itAddsAndRemovesChildren(): void
    {
        $parent = new Topic();
        $child1 = new Topic();
        $child1->setTitle('Child 1');
        $child2 = new Topic();
        $child2->setTitle('Child 2');

        $parent->addChild($child1);
        $parent->addChild($child2);

        $this->assertCount(2, $parent->getChildren());
        $this->assertSame($parent, $child1->getParent());

        // Adding same child again should not duplicate
        $parent->addChild($child1);
        $this->assertCount(2, $parent->getChildren());

        $parent->removeChild($child1);
        $this->assertCount(1, $parent->getChildren());
        $this->assertNull($child1->getParent());
    }

    #[Test]
    public function itSetsAndGetsPosition(): void
    {
        $topic = new Topic();
        $topic->setPosition(5);

        $this->assertSame(5, $topic->getPosition());
    }

    #[Test]
    public function itSetsAndGetsIsActive(): void
    {
        $topic = new Topic();
        $this->assertTrue($topic->isActive());
        $this->assertTrue($topic->getIsActive());

        $topic->setIsActive(false);
        $this->assertFalse($topic->isActive());
    }

    #[Test]
    public function itAddsAndRemovesArticles(): void
    {
        $topic = new Topic();
        $article = $this->createMock(Article::class);

        $topic->addArticle($article);
        $this->assertCount(1, $topic->getArticles());

        // Adding same article should not duplicate
        $topic->addArticle($article);
        $this->assertCount(1, $topic->getArticles());

        $topic->removeArticle($article);
        $this->assertCount(0, $topic->getArticles());
    }

    #[Test]
    public function itSetsAndGetsLocale(): void
    {
        $topic = new Topic();
        $topic->setTranslatableLocale('en');

        $this->assertSame('en', $topic->getLocale());
    }

    #[Test]
    public function itSetsAndGetsTranslatedSlugs(): void
    {
        $topic = new Topic();
        $slugs = ['ro' => 'politica', 'en' => 'politics', 'ru' => 'politika'];
        $topic->setTranslatedSlugs($slugs);

        $this->assertSame($slugs, $topic->getTranslatedSlugs());

        $topic->setTranslatedSlugs(null);
        $this->assertNull($topic->getTranslatedSlugs());
    }
}
