<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\ImportantArticlesList;
use App\Entity\Image;
use App\EventListener\ImportantArticlesListDeleteListener;
use App\Repository\ImportantArticlesListRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class ImportantArticlesListDeleteListenerTest extends TestCase
{
    private ImportantArticlesListRepository $repository;
    private ImportantArticlesListDeleteListener $listener;

    protected function setUp(): void
    {
        $this->repository = $this->createStub(ImportantArticlesListRepository::class);
        $this->listener = new ImportantArticlesListDeleteListener($this->repository);
    }

    public function testPreRemoveAllowsDeletionWhenAboveMinimum(): void
    {
        $entity = $this->createStub(ImportantArticlesList::class);
        $em = $this->createStub(EntityManagerInterface::class);
        $args = new PreRemoveEventArgs($entity, $em);

        // 6 articles in list, deleting one leaves 5 which is the minimum
        $this->repository->method('count')->willReturn(6);

        // Should not throw
        $this->listener->preRemove($args);
        $this->assertTrue(true);
    }

    public function testPreRemoveThrowsWhenAtMinimum(): void
    {
        $entity = $this->createStub(ImportantArticlesList::class);
        $em = $this->createStub(EntityManagerInterface::class);
        $args = new PreRemoveEventArgs($entity, $em);

        // Exactly 5 articles, can't delete
        $this->repository->method('count')->willReturn(5);

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('at least 5 articles');

        $this->listener->preRemove($args);
    }

    public function testPreRemoveThrowsWhenBelowMinimum(): void
    {
        $entity = $this->createStub(ImportantArticlesList::class);
        $em = $this->createStub(EntityManagerInterface::class);
        $args = new PreRemoveEventArgs($entity, $em);

        $this->repository->method('count')->willReturn(3);

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->listener->preRemove($args);
    }

    public function testPreRemoveIgnoresNonImportantArticlesListEntities(): void
    {
        $image = $this->createStub(Image::class);
        $em = $this->createStub(EntityManagerInterface::class);
        $args = new PreRemoveEventArgs($image, $em);

        // Should not throw - entity is not ImportantArticlesList
        $this->listener->preRemove($args);
        $this->assertTrue(true);
    }
}
