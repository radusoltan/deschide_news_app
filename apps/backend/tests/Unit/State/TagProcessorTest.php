<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Entity\Tag;
use App\State\TagProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class TagProcessorTest extends TestCase
{
    private TagProcessor $processor;
    private EntityManagerInterface $entityManager;
    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->requestStack = $this->createStub(RequestStack::class);

        $this->processor = new TagProcessor(
            $this->entityManager,
            $this->requestStack
        );
    }

    // ========================
    // DELETE Operation Tests
    // ========================

    #[Test]
    public function itDeletesTagWithZeroUsageCount(): void
    {
        $this->setupRequest('ro');

        $tag = $this->createStub(Tag::class);
        $tag->method('getUsageCount')->willReturn(0);

        $this->entityManager->expects($this->once())->method('remove')->with($tag);
        $this->entityManager->expects($this->once())->method('flush');

        $operation = new Delete();
        $result = $this->processor->process($tag, $operation);

        $this->assertNull($result);
    }

    #[Test]
    public function itThrowsExceptionWhenDeletingTagInUse(): void
    {
        $this->setupRequest('ro');

        $tag = $this->createStub(Tag::class);
        $tag->method('getUsageCount')->willReturn(5);
        $tag->method('getName')->willReturn('technology');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot delete tag "technology" because it is currently used by 5 article(s)');

        $operation = new Delete();
        $this->processor->process($tag, $operation);
    }

    // ========================
    // CREATE Operation Tests
    // ========================

    #[Test]
    public function itCreatesNewTagInDefaultLocale(): void
    {
        $this->setupRequest('ro');

        $tag = new Tag();
        $tag->setName('test-tag');

        $this->entityManager->expects($this->once())->method('persist')->with($tag);
        $this->entityManager->expects($this->once())->method('flush');

        $operation = new Post();
        $result = $this->processor->process($tag, $operation);

        $this->assertInstanceOf(Tag::class, $result);
    }

    #[Test]
    public function itCreatesTagWithTranslationForNonDefaultLocale(): void
    {
        $this->setupRequest('en');

        $tag = new Tag();
        $tag->setName('english-tag');

        $translationRepo = $this->createMock(TranslationRepository::class);
        $translationRepo->expects($this->atLeastOnce())->method('translate');

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($translationRepo) {
                if ($class === 'Gedmo\Translatable\Entity\Translation') {
                    return $translationRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $operation = new Post();
        $result = $this->processor->process($tag, $operation);

        $this->assertInstanceOf(Tag::class, $result);
    }

    // ========================
    // UPDATE Operation Tests
    // ========================

    #[Test]
    public function itUpdatesExistingTag(): void
    {
        $this->setupRequest('ro');

        $existingTag = $this->createMock(Tag::class);
        $existingTag->method('getId')->willReturn(10);
        $existingTag->expects($this->once())->method('setName')->with('Updated Name');

        $tagRepo = $this->createStub(EntityRepository::class);
        $tagRepo->method('find')->with(10)->willReturn($existingTag);

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($tagRepo) {
                if ($class === Tag::class) {
                    return $tagRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $data = $this->createStub(Tag::class);
        $data->method('getName')->willReturn('Updated Name');
        $data->method('getDescription')->willReturn(null);

        $operation = new Put();
        $result = $this->processor->process($data, $operation, ['id' => 10]);

        $this->assertInstanceOf(Tag::class, $result);
    }

    #[Test]
    public function itThrowsExceptionWhenUpdatingNonExistentTag(): void
    {
        $this->setupRequest('ro');

        $tagRepo = $this->createStub(EntityRepository::class);
        $tagRepo->method('find')->with(999)->willReturn(null);

        $this->entityManager->method('getRepository')->willReturn($tagRepo);

        $data = $this->createStub(Tag::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tag not found');

        $operation = new Put();
        $this->processor->process($data, $operation, ['id' => 999]);
    }

    // ========================
    // Non-Tag Data Tests
    // ========================

    #[Test]
    public function itReturnsNullForNonTagData(): void
    {
        $this->setupRequest('ro');

        $operation = new Post();
        $result = $this->processor->process('not-a-tag', $operation);

        $this->assertNull($result);
    }

    // ========================
    // Helper Methods
    // ========================

    private function setupRequest(string $locale): void
    {
        $request = new Request();
        $request->headers = new HeaderBag(['Accept-Language' => $locale]);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);
    }
}
