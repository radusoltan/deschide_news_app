<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Article;
use App\Service\Cache\CacheService;
use App\State\CachedArticleProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class CachedArticleProviderTest extends TestCase
{
    private CachedArticleProvider $provider;
    private ProviderInterface $decorated;
    private CacheService $performance;
    private RequestStack $requestStack;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->decorated = $this->createMock(ProviderInterface::class);
        $this->performance = $this->createStub(CacheService::class);
        $this->requestStack = $this->createStub(RequestStack::class);
        $this->logger = $this->createStub(LoggerInterface::class);

        $this->provider = new CachedArticleProvider(
            $this->decorated,
            $this->performance,
            $this->requestStack,
            $this->logger
        );
    }

    #[Test]
    public function itDelegatesToDecoratedProviderForSingleItem(): void
    {
        $article = new Article();

        $this->decorated->expects($this->once())
            ->method('provide')
            ->willReturn($article);

        $operation = new Get();
        $result = $this->provider->provide($operation, ['id' => 1]);

        $this->assertInstanceOf(Article::class, $result);
    }

    #[Test]
    public function itDelegatesToDecoratedProviderForCollection(): void
    {
        $articles = [new Article(), new Article()];

        $this->decorated->expects($this->once())
            ->method('provide')
            ->willReturn($articles);

        $operation = new GetCollection();
        $result = $this->provider->provide($operation);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    #[Test]
    public function itReturnsNullWhenDecoratedProviderReturnsNull(): void
    {
        $this->decorated->expects($this->once())
            ->method('provide')
            ->willReturn(null);

        $operation = new Get();
        $result = $this->provider->provide($operation, ['id' => 999]);

        $this->assertNull($result);
    }

    #[Test]
    public function itPassesAllArgumentsToDecoratedProvider(): void
    {
        $operation = new Get();
        $uriVariables = ['id' => 5];
        $context = ['filters' => ['status' => 'published']];

        $this->decorated->expects($this->once())
            ->method('provide')
            ->with($operation, $uriVariables, $context)
            ->willReturn(new Article());

        $this->provider->provide($operation, $uriVariables, $context);
    }
}
