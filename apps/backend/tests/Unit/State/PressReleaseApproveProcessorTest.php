<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use App\Entity\Author;
use App\Repository\ArticleRepository;
use App\Repository\AuthorRepository;
use App\Repository\CategoryRepository;
use App\Service\Editorial\ArticleFactoryService;
use App\Service\RemoteImageDownloader;
use App\Service\SourceAuthorResolver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Unit tests for ArticleFactoryService (extracted from PressReleaseApproveProcessor).
 *
 * Tests extractDomain() and resolveAuthorFromSenderEmail() methods.
 */
class PressReleaseApproveProcessorTest extends TestCase
{
    private ArticleFactoryService $articleFactory;

    private EntityManagerInterface $em;

    private CategoryRepository $categoryRepository;

    private ArticleRepository $articleRepository;

    private AuthorRepository $authorRepository;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->categoryRepository = $this->createMock(CategoryRepository::class);
        $this->articleRepository = $this->createMock(ArticleRepository::class);
        $this->authorRepository = $this->createMock(AuthorRepository::class);

        $sourceAuthorResolver = $this->createMock(SourceAuthorResolver::class);
        $imageDownloader = $this->createMock(RemoteImageDownloader::class);

        $this->articleFactory = new ArticleFactoryService(
            $this->em,
            $this->categoryRepository,
            $this->articleRepository,
            $this->authorRepository,
            $sourceAuthorResolver,
            $imageDownloader,
            new NullLogger(),
            '/tmp/test',
        );
    }

    // ========================================
    // extractDomain() Tests
    // ========================================

    #[Test]
    #[DataProvider('extractDomainProvider')]
    public function extractDomainReturnsExpectedResult(string $email, ?string $expectedDomain): void
    {
        $result = $this->articleFactory->extractDomain($email);

        $this->assertSame($expectedDomain, $result);
    }

    public static function extractDomainProvider(): array
    {
        return [
            'plain email' => [
                'user@ipn.md',
                'ipn.md',
            ],
            'name angle bracket format' => [
                'Name <user@gov.md>',
                'gov.md',
            ],
            'full name angle bracket format' => [
                'John Doe <john@example.com>',
                'example.com',
            ],
            'email with subdomain' => [
                'press@news.gov.md',
                'news.gov.md',
            ],
            'email with plus addressing' => [
                'user+tag@ipn.md',
                'ipn.md',
            ],
            'uppercase domain gets lowered' => [
                'user@IPN.MD',
                'ipn.md',
            ],
            'mixed case in angle brackets' => [
                'Press Office <info@GOV.md>',
                'gov.md',
            ],
            'invalid no at sign' => [
                'invalid',
                null,
            ],
            'empty string' => [
                '',
                null,
            ],
            'angle brackets with invalid email' => [
                'Name <invalid>',
                null,
            ],
            'email with whitespace' => [
                '  user@ipn.md  ',
                'ipn.md',
            ],
            'only at sign' => [
                '@',
                null,
            ],
            'at sign at end' => [
                'user@',
                null,
            ],
        ];
    }

    // ============================================
    // resolveAuthorFromSenderEmail() Tests
    // ============================================

    #[Test]
    public function resolveAuthorReturnsAuthorWhenDomainMatches(): void
    {
        $author = new Author();
        $author->setFirstName('IPN');
        $author->setLastName('Info-Prim Neo');
        $author->setEmailDomain('ipn.md');

        $this->authorRepository
            ->expects($this->once())
            ->method('findByEmailDomain')
            ->with('ipn.md')
            ->willReturn($author);

        $result = $this->articleFactory->resolveAuthorFromSenderEmail('newsfeed@ipn.md');

        $this->assertSame($author, $result);
    }

    #[Test]
    public function resolveAuthorReturnsAuthorForAngleBracketFormat(): void
    {
        $author = new Author();
        $author->setFirstName('Government');
        $author->setLastName('Press Office');

        $this->authorRepository
            ->expects($this->once())
            ->method('findByEmailDomain')
            ->with('gov.md')
            ->willReturn($author);

        $result = $this->articleFactory->resolveAuthorFromSenderEmail('Press Office <press@gov.md>');

        $this->assertSame($author, $result);
    }

    #[Test]
    public function resolveAuthorReturnsNullForUnknownDomain(): void
    {
        $this->authorRepository
            ->expects($this->once())
            ->method('findByEmailDomain')
            ->with('unknown.com')
            ->willReturn(null);

        $result = $this->articleFactory->resolveAuthorFromSenderEmail('user@unknown.com');

        $this->assertNull($result);
    }

    #[Test]
    public function resolveAuthorReturnsNullForInvalidEmail(): void
    {
        $this->authorRepository
            ->expects($this->never())
            ->method('findByEmailDomain');

        $result = $this->articleFactory->resolveAuthorFromSenderEmail('invalid');

        $this->assertNull($result);
    }

    #[Test]
    public function resolveAuthorReturnsNullForEmptyEmail(): void
    {
        $this->authorRepository
            ->expects($this->never())
            ->method('findByEmailDomain');

        $result = $this->articleFactory->resolveAuthorFromSenderEmail('');

        $this->assertNull($result);
    }
}
