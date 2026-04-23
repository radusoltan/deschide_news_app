<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Agent\AgentDispatcher;
use App\Agent\Exception\EmergencyHaltException;
use App\Dto\Agent\AgentRequest;
use App\Dto\Agent\AgentResponse;
use App\Entity\Article;
use App\Entity\Category;
use App\Enum\LlmModelTier;
use App\Message\TranslateArticleMessage;
use App\MessageHandler\TranslateArticleHandler;
use App\Repository\ArticleRepository;
use App\Service\Ai\Provider\GeminiCliException;
use App\Service\Ai\TierResolver;
use App\Service\NotificationFilterService;
use App\Service\NotificationService;
use App\Service\Translation\ArticleTranslationCompletenessChecker;
use App\Service\TranslationResultProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[CoversClass(TranslateArticleHandler::class)]
class TranslateArticleHandlerTest extends TestCase
{
    private ArticleRepository&MockObject $articleRepository;
    private TranslationResultProcessor $resultProcessor;
    private EntityManagerInterface&MockObject $em;
    private AgentDispatcher&MockObject $dispatcher;
    private TierResolver&MockObject $tierResolver;
    private TranslateArticleHandler $handler;

    protected function setUp(): void
    {
        $this->articleRepository = $this->createMock(ArticleRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);

        // TranslationResultProcessor is final — build a real one with mocked deps
        // NotificationService is also final — build a real instance
        $notifEm = $this->createMock(EntityManagerInterface::class);
        $httpClient = $this->createMock(HttpClientInterface::class);
        $serializer = $this->createMock(SerializerInterface::class);
        $filterService = $this->createMock(NotificationFilterService::class);
        $filterService->method('getRecipients')->willReturn([]);
        $notificationService = new NotificationService(
            $notifEm,
            $httpClient,
            $serializer,
            $filterService,
            new NullLogger(),
            'http://localhost:3000/.well-known/mercure',
            'fake-jwt-token',
        );

        $this->resultProcessor = new TranslationResultProcessor(
            $this->em,
            $notificationService,
            new ArticleTranslationCompletenessChecker($this->em),
            new NullLogger(),
        );

        $messageBus = $this->createStub(MessageBusInterface::class);
        $messageBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $this->dispatcher = $this->createMock(AgentDispatcher::class);
        $this->tierResolver = $this->createMock(TierResolver::class);

        $this->handler = new TranslateArticleHandler(
            $this->articleRepository,
            $this->resultProcessor,
            $this->em,
            $messageBus,
            $this->dispatcher,
            $this->tierResolver,
            new NullLogger(),
            '/tmp',
        );
    }

    public function testArticleNotFoundReturnsEarly(): void
    {
        $this->articleRepository->method('find')->willReturn(null);
        // No flush expected — handler returns before any work
        $this->em->expects($this->never())->method('flush');

        ($this->handler)(new TranslateArticleMessage(999));
    }

    public function testEmptyContentReturnsEarly(): void
    {
        $article = $this->createArticleStub(id: 1, title: '', content: '');
        $this->articleRepository->method('find')->willReturn($article);
        $this->em->expects($this->never())->method('flush');

        ($this->handler)(new TranslateArticleMessage(1));
    }

    public function testEmptyTitleReturnsEarly(): void
    {
        $article = $this->createArticleStub(id: 1, title: '', content: '<p>content</p>');
        $this->articleRepository->method('find')->willReturn($article);
        $this->em->expects($this->never())->method('flush');

        ($this->handler)(new TranslateArticleMessage(1));
    }

    // -------------------------------------------------------------------------
    // T57.P7.C2 — AgentDispatcher migration tests
    // -------------------------------------------------------------------------

    public function testSuccessPathDispatchesOneAgentRequestPerLocale(): void
    {
        $article = $this->createArticleStub(id: 42, title: 'Titlu', content: '<p>Conținut</p>');
        $this->articleRepository->method('find')->willReturn($article);
        $this->configureGedmoTranslationRepo();

        $this->tierResolver->expects($this->once())
            ->method('resolve')
            ->with(TranslateArticleHandler::AGENT_ID)
            ->willReturn(LlmModelTier::GEMINI_FLASH);

        // Dispatcher receives one AgentRequest per locale; agent_id is the
        // journalistic_translator constant; tier is GEMINI_FLASH; systemPrompt
        // is the agent-file payload (empty string in this test since the file
        // is not present on disk — handler falls back to '').
        $seenLocales = [];
        $this->dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (AgentRequest $request) use (&$seenLocales): AgentResponse {
                $this->assertSame(TranslateArticleHandler::AGENT_ID, $request->agentId);
                $this->assertSame(LlmModelTier::GEMINI_FLASH, $request->tier);
                $this->assertCount(1, $request->messages);
                $this->assertSame('user', $request->messages[0]['role']);

                // Pull locale out of the JSON payload — assert one-per-dispatch.
                /** @var array{locales: string[]} $payload */
                $payload = json_decode($request->messages[0]['content'], true, 512, \JSON_THROW_ON_ERROR);
                $this->assertIsArray($payload['locales']);
                $this->assertCount(1, $payload['locales']);
                $seenLocales[] = $payload['locales'][0];

                return new AgentResponse(
                    content: json_encode([
                        'translations' => [
                            $payload['locales'][0] => [
                                'title' => 'translated title',
                                'lead' => 'translated lead',
                                'content' => '<p>translated</p>',
                                'slug' => 'translated-slug',
                            ],
                        ],
                    ], \JSON_THROW_ON_ERROR),
                    agentId: TranslateArticleHandler::AGENT_ID,
                    tier: LlmModelTier::GEMINI_FLASH,
                    model: 'gemini-2.5-flash',
                    attempts: 1,
                    invocationId: '01JRU' . bin2hex(random_bytes(10)),
                );
            });

        ($this->handler)(new TranslateArticleMessage(42, locales: ['ru', 'en']));

        $this->assertSame(['ru', 'en'], $seenLocales);
        $this->assertSame('completed', $article->getTranslationStatus());
    }

    public function testAllLocalesHaltedMarksFailedAndThrowsRuntimeException(): void
    {
        $article = $this->createArticleStub(id: 7, title: 'T', content: '<p>C</p>');
        $this->articleRepository->method('find')->willReturn($article);

        $this->tierResolver->method('resolve')->willReturn(LlmModelTier::GEMINI_FLASH);

        // Both locales halt — dispatcher throws EmergencyHaltException for each.
        $this->dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willThrowException(new EmergencyHaltException(TranslateArticleHandler::AGENT_ID));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/All translations halted for article 7.*emergency_halt active.*ru.*en/');

        try {
            ($this->handler)(new TranslateArticleMessage(7, locales: ['ru', 'en']));
        } finally {
            // Finalize is invoked before rethrow — article status reflects failure.
            $this->assertSame('failed', $article->getTranslationStatus());
        }
    }

    public function testPartialHaltMarksNeedsReviewAndDoesNotThrow(): void
    {
        $article = $this->createArticleStub(id: 11, title: 'T', content: '<p>C</p>');
        $this->articleRepository->method('find')->willReturn($article);
        $this->configureGedmoTranslationRepo();

        $this->tierResolver->method('resolve')->willReturn(LlmModelTier::GEMINI_FLASH);

        // First locale halts, second succeeds. Handler must continue past the
        // halt (keeper #13 non-fatal), persist the second locale, and finalize
        // as needs_review (mixed halt+success → manual editor review signalled).
        $call = 0;
        $this->dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (AgentRequest $request) use (&$call): AgentResponse {
                ++$call;

                if ($call === 1) {
                    throw new EmergencyHaltException(TranslateArticleHandler::AGENT_ID);
                }

                return new AgentResponse(
                    content: json_encode([
                        'translations' => [
                            'en' => [
                                'title' => 'translated',
                                'lead' => 'translated',
                                'content' => '<p>translated</p>',
                                'slug' => 'translated',
                            ],
                        ],
                    ], \JSON_THROW_ON_ERROR),
                    agentId: TranslateArticleHandler::AGENT_ID,
                    tier: LlmModelTier::GEMINI_FLASH,
                    model: 'gemini-2.5-flash',
                    attempts: 1,
                    invocationId: '01JRUPARTIALHALT000000001',
                );
            });

        ($this->handler)(new TranslateArticleMessage(11, locales: ['ru', 'en']));

        $this->assertSame('needs_review', $article->getTranslationStatus());
    }

    public function testAllLocalesFailedWithoutHaltMarksFailedAndDoesNotThrow(): void
    {
        // T4 rule: RuntimeException is reserved for the clean all-halt case.
        // All-genuine-fail (zero halts) marks the article failed but does NOT
        // rethrow — avoiding dead-letter churn for transient Gemini errors
        // that would otherwise loop forever on the same payload.
        $article = $this->createArticleStub(id: 23, title: 'T', content: '<p>C</p>');
        $this->articleRepository->method('find')->willReturn($article);

        $this->tierResolver->method('resolve')->willReturn(LlmModelTier::GEMINI_FLASH);

        $this->dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willThrowException(new GeminiCliException('CLI exited non-zero'));

        // No RuntimeException expected here — contract asserted by absence of
        // expectException() + successful return from the handler call.
        ($this->handler)(new TranslateArticleMessage(23, locales: ['ru', 'en']));

        $this->assertSame('failed', $article->getTranslationStatus());
    }

    private function createArticleStub(int $id, string $title, string $content): Article
    {
        $category = $this->createStub(Category::class);
        $category->method('getTitle')->willReturn('Politica');

        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn($id);
        $article->method('getTitle')->willReturn($title);
        $article->method('getContent')->willReturn($content);
        $article->method('getLead')->willReturn('Lead text');
        $article->method('getCategory')->willReturn($category);
        $article->method('getAuthors')->willReturn(new ArrayCollection());

        // Translation status is set by the handler; let the stub accept and
        // report it back so status-assertion tests can observe transitions.
        // `&$status` must be captured by reference (classic closure), NOT by
        // an arrow function — arrow functions capture by value so late-bound
        // reads would always see the initial `null`.
        $status = null;
        $article->method('setTranslationStatus')->willReturnCallback(function (?string $s) use ($article, &$status): Article {
            $status = $s;

            return $article;
        });
        $article->method('getTranslationStatus')->willReturnCallback(function () use (&$status): ?string {
            return $status;
        });

        // Gedmo-backed fields referenced by buildPrompt.
        $article->method('getSlug')->willReturn('slug');
        $article->method('getMetaTitle')->willReturn(null);
        $article->method('getMetaDescription')->willReturn(null);

        return $article;
    }

    /**
     * Stubs the Gedmo TranslationRepository lookup so TranslationResultProcessor
     * can call `->translate(...)` without blowing up on null-deref. Fire-and-
     * forget — the mock records but does not verify each call.
     */
    private function configureGedmoTranslationRepo(): void
    {
        $gedmoRepo = $this->createMock(TranslationRepository::class);
        $this->em->method('getRepository')
            ->with('Gedmo\Translatable\Entity\Translation')
            ->willReturn($gedmoRepo);
    }
}
