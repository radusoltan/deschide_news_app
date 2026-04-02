<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Category;
use App\Enum\CategoryStatus;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for CategoryRepository.
 */
class CategoryRepositoryTest extends KernelTestCase
{
    private CategoryRepository $repository;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = static::getContainer()->get(CategoryRepository::class);
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    // =====================================================================
    // findFrontPageCategories
    // =====================================================================

    public function testFindFrontPageCategoriesReturnsArray(): void
    {
        $results = $this->repository->findFrontPageCategories();

        $this->assertIsArray($results);
    }

    public function testFindFrontPageCategoriesOnlyReturnsOnFrontPageCategories(): void
    {
        $uniqueSuffix = uniqid('', true);

        $frontPageCat = new Category();
        $frontPageCat->setTitle('FPCat' . $uniqueSuffix);
        $frontPageCat->setStatus(CategoryStatus::ACTIVE);
        $frontPageCat->setOnFrontPage(true);
        $this->em->persist($frontPageCat);

        $notFrontPageCat = new Category();
        $notFrontPageCat->setTitle('NormCat' . $uniqueSuffix);
        $notFrontPageCat->setStatus(CategoryStatus::ACTIVE);
        $notFrontPageCat->setOnFrontPage(false);
        $this->em->persist($notFrontPageCat);

        $this->em->flush();

        // After flush, Gedmo generates the slugs from titles
        $frontPageSlug = $frontPageCat->getSlug();
        $notFrontPageSlug = $notFrontPageCat->getSlug();

        $results = $this->repository->findFrontPageCategories();

        $ids = array_map(fn ($c) => $c->getId(), $results);
        $this->assertContains($frontPageCat->getId(), $ids);
        $this->assertNotContains($notFrontPageCat->getId(), $ids);

        // Verify the non-front-page category was not included
        $resultIds = array_map(fn ($c) => $c->getId(), $results);
        $this->assertNotContains($notFrontPageCat->getId(), $resultIds);
    }

    // =====================================================================
    // findActiveCategories
    // =====================================================================

    public function testFindActiveCategoriesReturnsArray(): void
    {
        $results = $this->repository->findActiveCategories();

        $this->assertIsArray($results);
    }

    public function testFindActiveCategoriesOnlyReturnsActiveOnes(): void
    {
        $uniqueSuffix = uniqid('active-cat-', true);

        $activeCat = new Category();
        $activeCat->setTitle('Active Cat ' . $uniqueSuffix);
        $activeCat->setSlug('active-cat-' . $uniqueSuffix);
        $activeCat->setStatus(CategoryStatus::ACTIVE);
        $this->em->persist($activeCat);

        $inactiveCat = new Category();
        $inactiveCat->setTitle('Inactive Cat ' . $uniqueSuffix);
        $inactiveCat->setSlug('inactive-cat-' . $uniqueSuffix);
        $inactiveCat->setStatus(CategoryStatus::INACTIVE);
        $this->em->persist($inactiveCat);

        $this->em->flush();

        $results = $this->repository->findActiveCategories();

        foreach ($results as $category) {
            $this->assertInstanceOf(Category::class, $category);
            $this->assertEquals(CategoryStatus::ACTIVE, $category->getStatus());
        }
    }

    // =====================================================================
    // findCategoriesWithArticleCount
    // =====================================================================

    public function testFindCategoriesWithArticleCountReturnsArray(): void
    {
        $results = $this->repository->findCategoriesWithArticleCount();

        $this->assertIsArray($results);
    }
}
