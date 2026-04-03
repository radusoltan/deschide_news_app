<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use App\Entity\Author;
use App\Repository\ArticleRepository;
use App\Repository\AuthorRepository;
use App\Repository\CategoryRepository;
use App\Service\SourceAuthorResolver;
use App\State\PressReleaseApproveProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Envelope;

/**
 * Unit tests for PressReleaseApproveProcessor.
 *
 * Tests extractDomain() and resolveAuthorFromSenderEmail() private methods
 * via reflection.
 */
class PressReleaseApproveProcessorTest extends TestCase
{
    private PressReleaseApproveProcessor $processor;

    private EntityManagerInterface $em;

    private CategoryRepository $categoryRepository;

    private ArticleRepository $articleRepository;

    private AuthorRepository $authorRepository;

    private Security $security;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->categoryRepository = $this->createMock(CategoryRepository::class);
        $this->articleRepository = $this->createMock(ArticleRepository::class);
        $this->authorRepository = $this->createMock(AuthorRepository::class);
        $this->security = $this->createMock(Security::class);

        $sourceAuthorResolver = $this->createMock(SourceAuthorResolver::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->method('dispatch')->willReturnCallback(fn ($msg) => new Envelope($msg));

        $this->processor = new PressReleaseApproveProcessor(
            $this->em,
            $this->categoryRepository,
            $this->articleRepository,
            $this->authorRepository,
            $sourceAuthorResolver,
            $this->security,
            $messageBus,
            new NullLogger(),
            '/tmp/test',
        );
    }

    // ========================================
    // extractDomain() Tests (via reflection)
    // ========================================

    #[Test]
    #[DataProvider('extractDomainProvider')]
    public function extractDomainReturnsExpectedResult(string $email, ?string $expectedDomain): void
    {
        $result = $this->invokeExtractDomain($email);

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

        $result = $this->invokeResolveAuthorFromSenderEmail('newsfeed@ipn.md');

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

        $result = $this->invokeResolveAuthorFromSenderEmail('Press Office <press@gov.md>');

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

        $result = $this->invokeResolveAuthorFromSenderEmail('user@unknown.com');

        $this->assertNull($result);
    }

    #[Test]
    public function resolveAuthorReturnsNullForInvalidEmail(): void
    {
        $this->authorRepository
            ->expects($this->never())
            ->method('findByEmailDomain');

        $result = $this->invokeResolveAuthorFromSenderEmail('invalid');

        $this->assertNull($result);
    }

    #[Test]
    public function resolveAuthorReturnsNullForEmptyEmail(): void
    {
        $this->authorRepository
            ->expects($this->never())
            ->method('findByEmailDomain');

        $result = $this->invokeResolveAuthorFromSenderEmail('');

        $this->assertNull($result);
    }

    // ========================================
    // Helper methods
    // ========================================

    private function invokeExtractDomain(string $email): ?string
    {
        $reflection = new \ReflectionMethod(PressReleaseApproveProcessor::class, 'extractDomain');

        return $reflection->invoke($this->processor, $email);
    }

    private function invokeResolveAuthorFromSenderEmail(string $senderEmail): ?Author
    {
        $reflection = new \ReflectionMethod(PressReleaseApproveProcessor::class, 'resolveAuthorFromSenderEmail');

        return $reflection->invoke($this->processor, $senderEmail);
    }
}
