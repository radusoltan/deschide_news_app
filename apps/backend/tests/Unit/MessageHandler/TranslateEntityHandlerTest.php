<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Entity\Author;
use App\Entity\Category;
use App\Enum\TranslatableEntityType;
use App\Message\TranslateEntityMessage;
use App\MessageHandler\TranslateEntityHandler;
use App\Repository\AuthorRepository;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(TranslateEntityHandler::class)]
class TranslateEntityHandlerTest extends TestCase
{
    private CategoryRepository $categoryRepo;
    private AuthorRepository $authorRepo;
    private EntityManagerInterface $em;
    private TranslateEntityHandler $handler;

    protected function setUp(): void
    {
        $this->categoryRepo = $this->createMock(CategoryRepository::class);
        $this->authorRepo = $this->createMock(AuthorRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);

        $this->handler = new TranslateEntityHandler(
            $this->categoryRepo,
            $this->authorRepo,
            $this->em,
            new NullLogger(),
            '/usr/bin/gemini',
            '/tmp',
        );
    }

    #[Test]
    public function categoryNotFoundReturnsEarly(): void
    {
        $this->categoryRepo->method('find')->willReturn(null);
        $this->em->expects($this->never())->method('flush');

        ($this->handler)(new TranslateEntityMessage(
            entityType: TranslatableEntityType::CATEGORY,
            entityId: 999,
        ));
    }

    #[Test]
    public function authorNotFoundReturnsEarly(): void
    {
        $this->authorRepo->method('find')->willReturn(null);
        $this->em->expects($this->never())->method('flush');

        ($this->handler)(new TranslateEntityMessage(
            entityType: TranslatableEntityType::AUTHOR,
            entityId: 999,
        ));
    }

    #[Test]
    public function categoryWithEmptyTitleReturnsEarly(): void
    {
        $category = new Category();
        $category->setTitle('');

        $this->categoryRepo->method('find')->willReturn($category);
        $this->em->expects($this->never())->method('flush');

        ($this->handler)(new TranslateEntityMessage(
            entityType: TranslatableEntityType::CATEGORY,
            entityId: 1,
        ));
    }

    #[Test]
    public function authorWithEmptyBioReturnsEarly(): void
    {
        $author = new Author();
        $author->setFirstName('Ion');
        $author->setLastName('Popescu');
        $author->setBio(null);

        $this->authorRepo->method('find')->willReturn($author);
        $this->em->expects($this->never())->method('flush');

        ($this->handler)(new TranslateEntityMessage(
            entityType: TranslatableEntityType::AUTHOR,
            entityId: 1,
        ));
    }

    #[Test]
    public function categoryAlreadyTranslatedSkipsWithoutForce(): void
    {
        $category = new Category();
        $category->setTitle('Politică');
        $category->setTranslationStatus('completed');

        $this->categoryRepo->method('find')->willReturn($category);
        $this->em->expects($this->never())->method('flush');

        ($this->handler)(new TranslateEntityMessage(
            entityType: TranslatableEntityType::CATEGORY,
            entityId: 1,
            force: false,
        ));
    }

    #[Test]
    public function authorAlreadyTranslatedSkipsWithoutForce(): void
    {
        $author = new Author();
        $author->setFirstName('Ion');
        $author->setLastName('Popescu');
        $author->setBio('Jurnalist de investigație');
        $author->setTranslationStatus('completed');

        $this->authorRepo->method('find')->willReturn($author);
        $this->em->expects($this->never())->method('flush');

        ($this->handler)(new TranslateEntityMessage(
            entityType: TranslatableEntityType::AUTHOR,
            entityId: 1,
            force: false,
        ));
    }
}
