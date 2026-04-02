<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Entity\Author;
use App\Enum\AuthorStatus;
use App\State\AuthorProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class AuthorProcessorTest extends TestCase
{
    private AuthorProcessor $processor;
    private EntityManagerInterface $entityManager;
    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->requestStack = $this->createStub(RequestStack::class);

        $this->processor = new AuthorProcessor(
            $this->entityManager,
            $this->requestStack
        );
    }

    // ========================
    // DELETE Operation Tests
    // ========================

    #[Test]
    public function itDeletesAuthor(): void
    {
        $this->setupRequest('ro');

        $author = $this->createStub(Author::class);

        $this->entityManager->expects($this->once())->method('remove')->with($author);
        $this->entityManager->expects($this->once())->method('flush');

        $operation = new Delete();
        $result = $this->processor->process($author, $operation);

        $this->assertNull($result);
    }

    #[Test]
    public function itReturnsNullForDeleteOfNonAuthorData(): void
    {
        $this->setupRequest('ro');

        $operation = new Delete();
        $result = $this->processor->process('not-an-author', $operation);

        $this->assertNull($result);
    }

    // ========================
    // CREATE Operation Tests
    // ========================

    #[Test]
    public function itCreatesNewAuthorInDefaultLocale(): void
    {
        $this->setupRequest('ro');

        $author = new Author();
        $author->setFirstName('Ion');
        $author->setLastName('Popescu');
        $author->setEmail('ion@test.com');
        $author->setStatus(AuthorStatus::ACTIVE);

        $this->entityManager->expects($this->once())->method('persist')->with($author);
        $this->entityManager->expects($this->once())->method('flush');

        $operation = new Post();
        $result = $this->processor->process($author, $operation);

        $this->assertInstanceOf(Author::class, $result);
        $this->assertEquals('Ion', $result->getFirstName());
    }

    #[Test]
    public function itCreatesAuthorWithTranslationForNonDefaultLocale(): void
    {
        $this->setupRequest('en');

        $author = new Author();
        $author->setFirstName('John');
        $author->setLastName('Doe');
        $author->setEmail('john@test.com');
        $author->setStatus(AuthorStatus::ACTIVE);
        $author->setBio('English bio');

        $translationRepo = $this->createMock(TranslationRepository::class);
        $translationRepo->expects($this->once())
            ->method('translate')
            ->with($author, 'bio', 'en', 'English bio');

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($translationRepo) {
                if ($class === 'Gedmo\Translatable\Entity\Translation') {
                    return $translationRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $operation = new Post();
        $result = $this->processor->process($author, $operation);

        $this->assertInstanceOf(Author::class, $result);
    }

    // ========================
    // UPDATE Operation Tests
    // ========================

    #[Test]
    public function itUpdatesExistingAuthor(): void
    {
        $this->setupRequest('ro');

        $existingAuthor = $this->createMock(Author::class);
        $existingAuthor->method('getId')->willReturn(10);
        $existingAuthor->method('getEmail')->willReturn('old@test.com');
        $existingAuthor->expects($this->once())->method('setFirstName')->with('Updated');

        $authorRepo = $this->createStub(EntityRepository::class);
        $authorRepo->method('find')->with(10)->willReturn($existingAuthor);

        $this->entityManager->method('getRepository')->willReturn($authorRepo);
        $this->entityManager->expects($this->once())->method('flush');

        $data = $this->createStub(Author::class);
        $data->method('getFirstName')->willReturn('Updated');
        $data->method('getLastName')->willReturn('Name');
        $data->method('getEmail')->willReturn('new@test.com');
        $data->method('getBio')->willReturn(null);
        $data->method('getStatus')->willReturn(AuthorStatus::ACTIVE);
        $data->method('isActive')->willReturn(true);
        $data->method('getTwitter')->willReturn(null);
        $data->method('getFacebook')->willReturn(null);
        $data->method('getLinkedin')->willReturn(null);
        $data->method('getWebsite')->willReturn(null);

        $operation = new Put();
        $result = $this->processor->process($data, $operation, ['id' => 10]);

        $this->assertInstanceOf(Author::class, $result);
    }

    #[Test]
    public function itThrowsExceptionWhenUpdatingNonExistentAuthor(): void
    {
        $this->setupRequest('ro');

        $authorRepo = $this->createStub(EntityRepository::class);
        $authorRepo->method('find')->with(999)->willReturn(null);

        $this->entityManager->method('getRepository')->willReturn($authorRepo);

        $data = $this->createStub(Author::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Author not found');

        $operation = new Put();
        $this->processor->process($data, $operation, ['id' => 999]);
    }

    #[Test]
    public function itSkipsEmailUpdateWhenSameEmail(): void
    {
        $this->setupRequest('ro');

        $existingAuthor = $this->createMock(Author::class);
        $existingAuthor->method('getId')->willReturn(10);
        $existingAuthor->method('getEmail')->willReturn('same@test.com');
        // setEmail should NOT be called when email is the same
        $existingAuthor->expects($this->never())->method('setEmail');

        $authorRepo = $this->createStub(EntityRepository::class);
        $authorRepo->method('find')->with(10)->willReturn($existingAuthor);

        $this->entityManager->method('getRepository')->willReturn($authorRepo);

        $data = $this->createStub(Author::class);
        $data->method('getFirstName')->willReturn('Name');
        $data->method('getLastName')->willReturn('Last');
        $data->method('getEmail')->willReturn('same@test.com');
        $data->method('getBio')->willReturn(null);
        $data->method('getStatus')->willReturn(AuthorStatus::ACTIVE);
        $data->method('isActive')->willReturn(true);
        $data->method('getTwitter')->willReturn(null);
        $data->method('getFacebook')->willReturn(null);
        $data->method('getLinkedin')->willReturn(null);
        $data->method('getWebsite')->willReturn(null);

        $operation = new Put();
        $this->processor->process($data, $operation, ['id' => 10]);
    }

    // ========================
    // Non-Author Data Tests
    // ========================

    #[Test]
    public function itReturnsNullForNonAuthorData(): void
    {
        $this->setupRequest('ro');

        $operation = new Post();
        $result = $this->processor->process('not-an-author', $operation);

        $this->assertNull($result);
    }

    // ========================
    // Locale Tests
    // ========================

    #[Test]
    public function itHandlesRussianLocale(): void
    {
        $this->setupRequest('ru');

        $author = new Author();
        $author->setFirstName('Иван');
        $author->setLastName('Петров');
        $author->setEmail('ivan@test.com');
        $author->setStatus(AuthorStatus::ACTIVE);
        $author->setBio('Русское био');

        $translationRepo = $this->createMock(TranslationRepository::class);
        $translationRepo->expects($this->once())
            ->method('translate')
            ->with($author, 'bio', 'ru', 'Русское био');

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($translationRepo) {
                if ($class === 'Gedmo\Translatable\Entity\Translation') {
                    return $translationRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $operation = new Post();
        $this->processor->process($author, $operation);
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
