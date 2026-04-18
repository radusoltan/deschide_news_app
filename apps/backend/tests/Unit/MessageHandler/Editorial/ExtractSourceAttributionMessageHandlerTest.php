<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler\Editorial;

use App\Dto\Editorial\SourceAttributionResult;
use App\Entity\Editorial\SourceSignal;
use App\Entity\Editorial\VerifiedSource;
use App\Entity\Source;
use App\Enum\EditorialAlignment;
use App\Message\Editorial\ExtractSourceAttributionMessage;
use App\MessageHandler\Editorial\ExtractSourceAttributionMessageHandler;
use App\Repository\Editorial\SourceSignalRepository;
use App\Service\Ai\TierResolver;
use App\Service\Editorial\Verification\SignalStabilizationBuffer;
use App\Service\Editorial\Verification\SourceAttributionExtractor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ExtractSourceAttributionMessageHandlerTest extends TestCase
{
    private SourceSignalRepository&MockObject $repository;
    private SourceAttributionExtractor&MockObject $extractor;
    private TierResolver&MockObject $tierResolver;
    private SignalStabilizationBuffer&MockObject $buffer;
    private EntityManagerInterface&MockObject $em;
    private ExtractSourceAttributionMessageHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(SourceSignalRepository::class);
        $this->extractor = $this->createMock(SourceAttributionExtractor::class);
        $this->tierResolver = $this->createMock(TierResolver::class);
        $this->buffer = $this->createMock(SignalStabilizationBuffer::class);
        $this->em = $this->createMock(EntityManagerInterface::class);

        $this->handler = new ExtractSourceAttributionMessageHandler(
            $this->repository,
            $this->extractor,
            $this->tierResolver,
            $this->buffer,
            $this->em,
            new NullLogger(),
        );
    }

    public function testMissingSignalIsLoggedAndHandlerReturns(): void
    {
        $this->repository->method('find')->with(99)->willReturn(null);
        $this->extractor->expects($this->never())->method('extract');
        $this->buffer->expects($this->never())->method('add');

        $this->handler->__invoke(new ExtractSourceAttributionMessage(99));
    }

    public function testAgentEnabledHappyPathPersistsAndBuffers(): void
    {
        $signal = $this->seedSignalWithId(42);

        $this->repository->method('find')->willReturn($signal);
        $this->tierResolver->method('isEnabled')
            ->with(SourceAttributionExtractor::AGENT_ID)
            ->willReturn(true);

        $this->extractor->expects($this->once())
            ->method('extract')
            ->with($signal)
            ->willReturn(new SourceAttributionResult('potrivit Reuters', ['https://reuters.com/x']));

        $this->em->expects($this->once())->method('flush');

        $this->buffer->expects($this->once())
            ->method('add')
            ->with(
                $this->callback(fn ($hash) => \is_string($hash) && \strlen($hash) === 32),
                42,
                $signal->getCapturedAt(),
            );

        $this->handler->__invoke(new ExtractSourceAttributionMessage(42));

        $this->assertSame('potrivit Reuters', $signal->getSourceAttribution());
        $this->assertSame(['https://reuters.com/x'], $signal->getSourceLinksOut());
    }

    public function testEmptyExtractionResultSkipsFlushButStillBuffers(): void
    {
        $signal = $this->seedSignalWithId(43);

        $this->repository->method('find')->willReturn($signal);
        $this->tierResolver->method('isEnabled')->willReturn(true);

        $this->extractor->method('extract')
            ->willReturn(SourceAttributionResult::empty());

        $this->em->expects($this->never())->method('flush');
        $this->buffer->expects($this->once())->method('add');

        $this->handler->__invoke(new ExtractSourceAttributionMessage(43));

        $this->assertNull($signal->getSourceAttribution());
    }

    public function testAgentDisabledBypassesExtractorButStillBuffers(): void
    {
        $signal = $this->seedSignalWithId(44);

        $this->repository->method('find')->willReturn($signal);
        $this->tierResolver->method('isEnabled')->willReturn(false);

        $this->extractor->expects($this->never())->method('extract');
        $this->em->expects($this->never())->method('flush');
        $this->buffer->expects($this->once())->method('add');

        $this->handler->__invoke(new ExtractSourceAttributionMessage(44));
    }

    public function testExtractorExceptionIsSwallowedAndSignalStillBuffered(): void
    {
        $signal = $this->seedSignalWithId(45);

        $this->repository->method('find')->willReturn($signal);
        $this->tierResolver->method('isEnabled')->willReturn(true);

        $this->extractor->method('extract')
            ->willThrowException(new \RuntimeException('simulated transport failure'));

        $this->em->expects($this->never())->method('flush');
        // Buffer is called even though extractor threw — fail-open semantics.
        $this->buffer->expects($this->once())->method('add');

        $this->handler->__invoke(new ExtractSourceAttributionMessage(45));
    }

    /**
     * Create a SourceSignal with an id set via reflection (avoids needing a
     * real EM round-trip).
     */
    private function seedSignalWithId(int $id): SourceSignal
    {
        $source = new Source();
        $source->setName('Stub');

        $vs = new VerifiedSource(
            slug: 'stub-' . uniqid(),
            tier: 1,
            editorialAlignment: EditorialAlignment::WIRE_NEUTRAL,
            trustScoreBaseline: '0.80',
            source: $source,
        );

        $signal = new SourceSignal(
            verifiedSource: $vs,
            sourceUrl: 'https://example.com/' . $id,
            title: 'Titlu test pentru semnal #' . $id,
            rawContentHash: str_repeat((string) ($id % 10), 64),
        );

        $ref = new \ReflectionClass($signal);
        $idProp = $ref->getProperty('id');
        $idProp->setAccessible(true);
        $idProp->setValue($signal, $id);

        return $signal;
    }
}
