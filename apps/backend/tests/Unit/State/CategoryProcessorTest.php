<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Entity\Article;
use App\Entity\Category;
use App\Enum\CategoryStatus;
use App\Service\PerformanceService;
use App\State\CategoryProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\String\UnicodeString;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class CategoryProcessorTest extends TestCase
{
    private CategoryProcessor $processor;
    private EntityManagerInterface $entityManager;
    private RequestStack $requestStack;
    private PerformanceService $performanceService;
    private SluggerInterface $slugger;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->requestStack = $this->createStub(RequestStack::class);
        $this->performanceService = $this->createMock(PerformanceService::class);
        $this->slugger = $this->createMock(SluggerInterface::class);
        $this->slugger->method('slug')->willReturnCallback(
            fn (string $string) => new UnicodeString(strtolower(str_replace(' ', '-', $string)))
        );

        $this->processor = new CategoryProcessor(
            $this->entityManager,
            $this->requestStack,
            $this->performanceService,
            $this->slugger,
        );
    }

    // ========================
    // DELETE Operation Tests
    // ========================

    #[Test]
    public function itDeletesCategoryWithNoArticles(): void
    {
        $this->setupRequest('ro');

        $managedCategory = $this->createStub(Category::class);
        $managedCategory->method('getId')->willReturn(5);
        $managedCategory->method('getArticles')->willReturn(new ArrayCollection());

        $category = $this->createStub(Category::class);
        $category->method('getId')->willReturn(5);

        $categoryRepo = $this->createStub(EntityRepository::class);
        $categoryRepo->method('find')->with(5)->willReturn($managedCategory);

        $this->entityManager->method('getRepository')->willReturn($categoryRepo);

        $this->entityManager->expects($this->once())->method('remove')->with($managedCategory);
        $this->entityManager->expects($this->once())->method('flush');
        $this->performanceService->expects($this->once())->method('invalidateCategory')->with(5);

        $operation = new Delete();
        $result = $this->processor->process($category, $operation);

        $this->assertNull($result);
    }

    #[Test]
    public function itThrowsExceptionWhenDeletingCategoryWithArticles(): void
    {
        $this->setupRequest('ro');

        $managedCategory = $this->createStub(Category::class);
        $managedCategory->method('getId')->willReturn(5);
        $managedCategory->method('getArticles')->willReturn(new ArrayCollection([new Article()]));

        $category = $this->createStub(Category::class);
        $category->method('getId')->willReturn(5);

        $categoryRepo = $this->createStub(EntityRepository::class);
        $categoryRepo->method('find')->with(5)->willReturn($managedCategory);

        $this->entityManager->method('getRepository')->willReturn($categoryRepo);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot delete category with existing articles');

        $operation = new Delete();
        $this->processor->process($category, $operation);
    }

    #[Test]
    public function itThrowsExceptionWhenDeletingNonExistentCategory(): void
    {
        $this->setupRequest('ro');

        $category = $this->createStub(Category::class);
        $category->method('getId')->willReturn(999);

        $categoryRepo = $this->createStub(EntityRepository::class);
        $categoryRepo->method('find')->with(999)->willReturn(null);

        $this->entityManager->method('getRepository')->willReturn($categoryRepo);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Category not found');

        $operation = new Delete();
        $this->processor->process($category, $operation);
    }

    // ========================
    // CREATE Operation Tests
    // ========================

    #[Test]
    public function itCreatesNewCategoryInDefaultLocale(): void
    {
        $this->setupRequest('ro');

        $category = new Category();
        $category->setTitle('Test Category');
        $category->setStatus(CategoryStatus::ACTIVE);

        $this->entityManager->expects($this->once())->method('persist')->with($category);
        $this->entityManager->expects($this->once())->method('flush')
            ->willReturnCallback(function () use ($category): void {
                // Simulate DB assigning an ID
                $ref = new \ReflectionProperty(Category::class, 'id');
                $ref->setValue($category, 1);
            });

        $operation = new Post();
        $result = $this->processor->process($category, $operation);

        $this->assertInstanceOf(Category::class, $result);
        $this->assertEquals('Test Category', $result->getTitle());
    }

    #[Test]
    public function itCreatesNewCategoryWithTranslationForNonDefaultLocale(): void
    {
        $this->setupRequest('en');

        $category = new Category();
        $category->setTitle('English Category');
        $category->setStatus(CategoryStatus::ACTIVE);

        $translationRepo = $this->createMock(TranslationRepository::class);
        $translationRepo->expects($this->exactly(2))
            ->method('translate');

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($translationRepo) {
                if ($class === 'Gedmo\Translatable\Entity\Translation') {
                    return $translationRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        // Simulate DB assigning an ID on first flush
        $category_ref = $category;
        $this->entityManager->method('flush')
            ->willReturnCallback(function () use ($category_ref): void {
                $ref = new \ReflectionProperty(Category::class, 'id');
                if (!$ref->getValue($category_ref)) {
                    $ref->setValue($category_ref, 1);
                }
            });

        $operation = new Post();
        $result = $this->processor->process($category, $operation);

        $this->assertInstanceOf(Category::class, $result);
    }

    #[Test]
    public function itCreatesNewCategoryWithParent(): void
    {
        $this->setupRequest('ro');

        $parent = $this->createStub(Category::class);
        $parent->method('getId')->willReturn(3);

        $managedParent = $this->createStub(Category::class);

        $categoryRepo = $this->createStub(EntityRepository::class);
        $categoryRepo->method('find')->with(3)->willReturn($managedParent);

        $this->entityManager->method('getRepository')->willReturn($categoryRepo);

        $category = new Category();
        $category->setTitle('Child Category');
        $category->setStatus(CategoryStatus::ACTIVE);
        $category->setParent($parent);

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->method('flush')
            ->willReturnCallback(function () use ($category): void {
                $ref = new \ReflectionProperty(Category::class, 'id');
                if (!$ref->getValue($category)) {
                    $ref->setValue($category, 1);
                }
            });

        $operation = new Post();
        $result = $this->processor->process($category, $operation);

        $this->assertInstanceOf(Category::class, $result);
    }

    // ========================
    // UPDATE Operation Tests
    // ========================

    #[Test]
    public function itUpdatesExistingCategory(): void
    {
        $this->setupRequestWithContent('ro', json_encode(['title' => 'Updated Title']));

        $existingCategory = $this->createMock(Category::class);
        $existingCategory->method('getId')->willReturn(5);
        $existingCategory->expects($this->once())->method('setTitle')->with('Updated Title');
        $existingCategory->expects($this->atLeastOnce())->method('setTranslatableLocale')->with('ro');

        $categoryRepo = $this->createStub(EntityRepository::class);
        $categoryRepo->method('find')->with(5)->willReturn($existingCategory);

        $this->entityManager->method('getRepository')->willReturn($categoryRepo);
        $this->entityManager->expects($this->once())->method('flush');

        $data = $this->createStub(Category::class);
        $data->method('getTitle')->willReturn('Updated Title');
        $data->method('getStatus')->willReturn(CategoryStatus::ACTIVE);
        $data->method('isOnFrontPage')->willReturn(false);
        $data->method('getParent')->willReturn(null);

        $this->performanceService->expects($this->once())->method('invalidateCategory')->with(5);

        $operation = new Put();
        $result = $this->processor->process($data, $operation, ['id' => 5]);

        $this->assertInstanceOf(Category::class, $result);
    }

    #[Test]
    public function itThrowsExceptionWhenUpdatingNonExistentCategory(): void
    {
        $this->setupRequest('ro');

        $categoryRepo = $this->createStub(EntityRepository::class);
        $categoryRepo->method('find')->with(999)->willReturn(null);

        $this->entityManager->method('getRepository')->willReturn($categoryRepo);

        $data = $this->createStub(Category::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Category not found');

        $operation = new Put();
        $this->processor->process($data, $operation, ['id' => 999]);
    }

    #[Test]
    public function itUpdatesTranslationForNonDefaultLocale(): void
    {
        $this->setupRequest('en');

        $existingCategory = $this->createMock(Category::class);
        $existingCategory->method('getId')->willReturn(5);
        $existingCategory->method('getTitle')->willReturn('English Title');

        $categoryRepo = $this->createStub(EntityRepository::class);
        $categoryRepo->method('find')->with(5)->willReturn($existingCategory);

        $translationRepo = $this->createMock(TranslationRepository::class);
        $translationRepo->expects($this->exactly(2))
            ->method('translate');

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($categoryRepo, $translationRepo) {
                if ($class === Category::class) {
                    return $categoryRepo;
                }
                if ($class === 'Gedmo\Translatable\Entity\Translation') {
                    return $translationRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $data = $this->createStub(Category::class);
        $data->method('getTitle')->willReturn('English Title');
        $data->method('getSlug')->willReturn(null);
        $data->method('getStatus')->willReturn(CategoryStatus::ACTIVE);
        $data->method('isOnFrontPage')->willReturn(false);
        $data->method('getParent')->willReturn(null);

        $operation = new Put();
        $this->processor->process($data, $operation, ['id' => 5]);
    }

    // ========================
    // Non-Category Data Tests
    // ========================

    #[Test]
    public function itReturnsNullForNonCategoryData(): void
    {
        $this->setupRequest('ro');

        $operation = new Post();
        $result = $this->processor->process('not-a-category', $operation);

        $this->assertNull($result);
    }

    // ========================
    // Locale Handling Tests
    // ========================

    #[Test]
    public function itExtractsLocaleFromComplexHeader(): void
    {
        $this->setupRequest('en-US,en;q=0.9');

        $category = new Category();
        $category->setTitle('Test');
        $category->setStatus(CategoryStatus::ACTIVE);

        $translationRepo = $this->createMock(TranslationRepository::class);
        $translationRepo->expects($this->exactly(2))
            ->method('translate');

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($translationRepo) {
                if ($class === 'Gedmo\Translatable\Entity\Translation') {
                    return $translationRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $this->entityManager->method('flush')
            ->willReturnCallback(function () use ($category): void {
                $ref = new \ReflectionProperty(Category::class, 'id');
                if (!$ref->getValue($category)) {
                    $ref->setValue($category, 1);
                }
            });

        $operation = new Post();
        $this->processor->process($category, $operation);
    }

    #[Test]
    public function itUsesDefaultLocaleWhenNoRequest(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $category = new Category();
        $category->setTitle('Default');
        $category->setStatus(CategoryStatus::ACTIVE);

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush')
            ->willReturnCallback(function () use ($category): void {
                $ref = new \ReflectionProperty(Category::class, 'id');
                if (!$ref->getValue($category)) {
                    $ref->setValue($category, 1);
                }
            });

        $operation = new Post();
        $this->processor->process($category, $operation);
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

    private function setupRequestWithContent(string $locale, string $content): void
    {
        $request = new Request([], [], [], [], [], [], $content);
        $request->headers = new HeaderBag(['Accept-Language' => $locale]);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);
    }
}
