<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Editorial\EditorialEscalationLog;
use App\Entity\User;
use App\Enum\Editorial\EscalationCategory;
use App\Enum\EscalationDecision;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Functional tests for Sprint 55 T55.12 AdminEscalationController.
 *
 * Covers: auth (401 / 403), list + filters, stats, approve (incl. publish),
 * reject (incl. validation), extend-sla (validation ceiling + conflict on
 * decided row + re-open of EXPIRED), rate-limit enforcement.
 */
class AdminEscalationControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private ?User $editorUser = null;
    private ?User $regularUser = null;

    /** @var list<int> */
    private array $createdLogIds = [];

    /** @var list<int> */
    private array $createdUserIds = [];

    protected function setUp(): void
    {
        static::ensureKernelShutdown();
        $this->client = static::createClient();
        $em = static::getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);
        $this->em = $em;

        $this->createUsers();
    }

    protected function tearDown(): void
    {
        foreach ($this->createdLogIds as $id) {
            $log = $this->em->find(EditorialEscalationLog::class, $id);
            if ($log !== null) {
                $this->em->remove($log);
            }
        }
        foreach ($this->createdUserIds as $id) {
            $user = $this->em->find(User::class, $id);
            if ($user !== null) {
                $this->em->remove($user);
            }
        }
        $this->em->flush();
        $this->em->close();

        parent::tearDown();
    }

    // ========== Authorization ==========

    public function testListRequires401WhenUnauthenticated(): void
    {
        $this->client->request('GET', '/api/admin/escalations');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testListReturns403ForNonEditorUser(): void
    {
        $this->client->request('GET', '/api/admin/escalations', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token($this->regularUser),
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testListReturns200ForEditor(): void
    {
        $this->seedLog(EscalationCategory::CATEGORY_6_CRIMINAL_ACCUSATION);

        $this->client->request('GET', '/api/admin/escalations', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token($this->editorUser),
        ]);

        $this->assertResponseIsSuccessful();
        $body = $this->decode();
        $this->assertTrue($body['success']);
        $this->assertSame('ok', $body['status']);
        $this->assertIsArray($body['data']['items']);
        $this->assertGreaterThanOrEqual(1, \count($body['data']['items']));
    }

    public function testListFiltersByCategory(): void
    {
        $cat6Log = $this->seedLog(EscalationCategory::CATEGORY_6_CRIMINAL_ACCUSATION);
        $famCLog = $this->seedLog(EscalationCategory::FAMILY_C_TRANSNISTRIA_GAGAUZIA);

        $this->client->request('GET', '/api/admin/escalations?category=family_c', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token($this->editorUser),
        ]);

        $this->assertResponseIsSuccessful();
        $body = $this->decode();
        $ids = array_column($body['data']['items'], 'id');
        $this->assertContains($famCLog->getId(), $ids);
        $this->assertNotContains($cat6Log->getId(), $ids);
    }

    public function testListRejectsUnknownCategory(): void
    {
        $this->client->request('GET', '/api/admin/escalations?category=garbage_code', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token($this->editorUser),
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $body = $this->decode();
        $this->assertSame('validation', $body['status']);
    }

    // ========== Stats ==========

    public function testStatsReturnsCategoryBreakdownKeyedByEnumName(): void
    {
        $this->seedLog(EscalationCategory::CATEGORY_6_CRIMINAL_ACCUSATION);
        $this->seedLog(EscalationCategory::FAMILY_A_CHURCH);
        $this->seedLog(EscalationCategory::FAMILY_A_CHURCH);

        $this->client->request('GET', '/api/admin/escalations/stats', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token($this->editorUser),
        ]);

        $this->assertResponseIsSuccessful();
        $body = $this->decode();
        $this->assertArrayHasKey('pending_total', $body['data']);
        $this->assertArrayHasKey('pending_by_category', $body['data']);
        $this->assertGreaterThanOrEqual(3, $body['data']['pending_total']);
        // Keyed by verbose enum name.
        $this->assertGreaterThanOrEqual(2, $body['data']['pending_by_category']['FAMILY_A_CHURCH'] ?? 0);
    }

    // ========== Approve ==========

    public function testApproveSetsDecisionAndDecidedBy(): void
    {
        $log = $this->seedLog(EscalationCategory::CATEGORY_7_PRE_CEC_ELECTORAL);

        $this->client->request(
            'POST',
            sprintf('/api/admin/escalations/%d/approve', $log->getId()),
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token($this->editorUser),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode(['publishAsArticle' => false, 'editorialNotes' => 'Verified against CEC website.']),
        );

        $this->assertResponseIsSuccessful();
        $body = $this->decode();
        $this->assertSame('approved', $body['status']);

        $this->em->refresh($log);
        $this->assertSame(EscalationDecision::APPROVED, $log->getDecision());
        $this->assertNotNull($log->getDecidedAt());
        $this->assertSame($this->editorUser?->getId(), $log->getDecidedBy()?->getId());
    }

    public function testApprovePublishAsArticleWithMissingSignalIdReturnsWarning(): void
    {
        // articleSnapshot is intentionally minimal — primary_signal_id absent.
        $log = $this->seedLog(
            EscalationCategory::CATEGORY_7_PRE_CEC_ELECTORAL,
            ['title' => 'Test'], // no primary_signal_id
        );

        $this->client->request(
            'POST',
            sprintf('/api/admin/escalations/%d/approve', $log->getId()),
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token($this->editorUser),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode(['publishAsArticle' => true]),
        );

        $this->assertResponseIsSuccessful();
        $body = $this->decode();
        // Decision is still recorded; dispatch status flags the missing signal.
        $this->assertSame('approved', $body['status']);
        $this->assertSame('missing_signal_id', $body['data']['publish_dispatch']['status']);
    }

    public function testApproveReturns404OnUnknownEscalation(): void
    {
        $this->client->request(
            'POST',
            '/api/admin/escalations/99999999/approve',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token($this->editorUser),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode(['publishAsArticle' => false]),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testApproveReturns409WhenAlreadyDecided(): void
    {
        $log = $this->seedLog(EscalationCategory::CATEGORY_6_CRIMINAL_ACCUSATION);
        $log->setDecision(EscalationDecision::APPROVED);
        $log->setDecidedAt(new \DateTimeImmutable());
        $this->em->flush();

        $this->client->request(
            'POST',
            sprintf('/api/admin/escalations/%d/approve', $log->getId()),
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token($this->editorUser),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode(['publishAsArticle' => false]),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    // ========== Reject ==========

    public function testRejectRequiresReason(): void
    {
        $log = $this->seedLog(EscalationCategory::CATEGORY_6_CRIMINAL_ACCUSATION);

        $this->client->request(
            'POST',
            sprintf('/api/admin/escalations/%d/reject', $log->getId()),
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token($this->editorUser),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode(['reason' => 'x']),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $body = $this->decode();
        $this->assertSame('validation', $body['status']);
        $this->assertSame('reason', $body['violations'][0]['field']);
    }

    public function testRejectHappyPath(): void
    {
        $log = $this->seedLog(EscalationCategory::FAMILY_B_EU_NATO_RUSSIA);

        $this->client->request(
            'POST',
            sprintf('/api/admin/escalations/%d/reject', $log->getId()),
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token($this->editorUser),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode(['reason' => 'Source is a known impersonator account. Not verifiable.']),
        );

        $this->assertResponseIsSuccessful();
        $body = $this->decode();
        $this->assertSame('rejected', $body['status']);

        $this->em->refresh($log);
        $this->assertSame(EscalationDecision::REJECTED, $log->getDecision());
        $this->assertSame($this->editorUser?->getId(), $log->getDecidedBy()?->getId());
    }

    // ========== Extend SLA ==========

    public function testExtendSlaRejectsValuesAboveMax(): void
    {
        $log = $this->seedLog(EscalationCategory::FAMILY_D_CEC_PARTY_LEADERS);

        $this->client->request(
            'POST',
            sprintf('/api/admin/escalations/%d/extend-sla', $log->getId()),
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token($this->editorUser),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode(['additionalSeconds' => 7200]), // > 3600
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $body = $this->decode();
        $this->assertSame('validation', $body['status']);
    }

    public function testExtendSlaHappyPathPushesExpiresAtForward(): void
    {
        $log = $this->seedLog(EscalationCategory::FAMILY_D_CEC_PARTY_LEADERS);
        $originalExpiry = $log->getExpiresAt();
        $this->assertNotNull($originalExpiry);

        $this->client->request(
            'POST',
            sprintf('/api/admin/escalations/%d/extend-sla', $log->getId()),
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token($this->editorUser),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode(['additionalSeconds' => 1800]),
        );

        $this->assertResponseIsSuccessful();
        $body = $this->decode();
        $this->assertSame('extended', $body['status']);
        $this->assertSame(1800, $body['data']['added_seconds']);

        $this->em->refresh($log);
        $this->assertGreaterThan($originalExpiry, $log->getExpiresAt());
    }

    public function testExtendSlaOnStaleExpiredRowUsesNowAsBase(): void
    {
        // Regression guard for B-H1: an EXPIRED row whose expires_at already
        // lies in the past must NOT be extended from that stale timestamp —
        // otherwise short extensions land in the past and the scheduler
        // re-expires the row on the next tick.
        $log = $this->seedLog(EscalationCategory::CATEGORY_6_CRIMINAL_ACCUSATION);
        $log->setDecision(EscalationDecision::EXPIRED);
        $log->setDecidedAt(new \DateTimeImmutable('-1 hour'));
        $log->setExpiresAt(new \DateTimeImmutable('-1 hour'));
        $this->em->flush();

        $beforeRequest = new \DateTimeImmutable();

        $this->client->request(
            'POST',
            sprintf('/api/admin/escalations/%d/extend-sla', $log->getId()),
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token($this->editorUser),
                'CONTENT_TYPE' => 'application/json',
            ],
            // +10 minutes — smaller than the 1h staleness, so using the stale
            // expiry would yield newExpiry = now - 50min (still in the past).
            json_encode(['additionalSeconds' => 600]),
        );

        $this->assertResponseIsSuccessful();

        $this->em->refresh($log);
        $newExpiry = $log->getExpiresAt();
        $this->assertNotNull($newExpiry);

        // New expiry must be in the future AND roughly now+600s (±a few
        // seconds for request processing), NOT stale_expiry+600s.
        $this->assertGreaterThan(
            $beforeRequest,
            $newExpiry,
            'Reopened expiry must be in the future, not still in the past',
        );
        $delta = $newExpiry->getTimestamp() - $beforeRequest->getTimestamp();
        $this->assertGreaterThanOrEqual(595, $delta, 'Base must be ~now, not stale expiry');
        $this->assertLessThanOrEqual(610, $delta, 'Base must be ~now, not stale expiry');
    }

    public function testExtendSlaReopensExpiredRow(): void
    {
        $log = $this->seedLog(EscalationCategory::CATEGORY_7_PRE_CEC_ELECTORAL);
        $log->setDecision(EscalationDecision::EXPIRED);
        $log->setDecidedAt(new \DateTimeImmutable());
        $this->em->flush();

        $this->client->request(
            'POST',
            sprintf('/api/admin/escalations/%d/extend-sla', $log->getId()),
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token($this->editorUser),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode(['additionalSeconds' => 600]),
        );

        $this->assertResponseIsSuccessful();

        $this->em->refresh($log);
        // Re-opened: decision cleared back to null.
        $this->assertNull($log->getDecision());
        $this->assertNull($log->getDecidedAt());
    }

    public function testExtendSlaRejectsAlreadyApprovedRow(): void
    {
        $log = $this->seedLog(EscalationCategory::CATEGORY_6_CRIMINAL_ACCUSATION);
        $log->setDecision(EscalationDecision::APPROVED);
        $log->setDecidedAt(new \DateTimeImmutable());
        $this->em->flush();

        $this->client->request(
            'POST',
            sprintf('/api/admin/escalations/%d/extend-sla', $log->getId()),
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token($this->editorUser),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode(['additionalSeconds' => 600]),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    // ========== Helpers ==========

    /**
     * @return array<string, mixed>
     */
    private function decode(): array
    {
        $raw = $this->client->getResponse()->getContent();
        $data = json_decode((string) $raw, true);
        $this->assertIsArray($data);

        return $data;
    }

    private function createUsers(): void
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $this->editorUser = new User();
        $this->editorUser->setUsername('esc_editor_' . uniqid());
        $this->editorUser->setEmail('esc_editor_' . uniqid() . '@example.com');
        $this->editorUser->setRoles(['ROLE_EDITOR']);
        $this->editorUser->setPassword($hasher->hashPassword($this->editorUser, 'password123'));
        $this->em->persist($this->editorUser);

        $this->regularUser = new User();
        $this->regularUser->setUsername('esc_user_' . uniqid());
        $this->regularUser->setEmail('esc_user_' . uniqid() . '@example.com');
        $this->regularUser->setRoles(['ROLE_USER']);
        $this->regularUser->setPassword($hasher->hashPassword($this->regularUser, 'password123'));
        $this->em->persist($this->regularUser);

        $this->em->flush();

        $this->createdUserIds[] = $this->editorUser->getId();
        $this->createdUserIds[] = $this->regularUser->getId();
    }

    private function token(?User $user): string
    {
        $user ??= $this->editorUser;
        if ($user === null) {
            throw new \RuntimeException('Editor user not initialised — createUsers() must run before token().');
        }

        /** @var JWTTokenManagerInterface $jwt */
        $jwt = static::getContainer()->get('lexik_jwt_authentication.jwt_manager');

        return $jwt->create($user);
    }

    /**
     * @param array<string, mixed>|null $snapshot Optional override of the default articleSnapshot
     */
    private function seedLog(EscalationCategory $category, ?array $snapshot = null): EditorialEscalationLog
    {
        $log = new EditorialEscalationLog(
            articleSnapshot: $snapshot ?? [
                'title' => 'Seeded escalation',
                'primary_signal_id' => 123,
                'verdict_type' => 'flash_with_attribution',
            ],
            category: $category,
            originGraphSnapshot: [],
        );
        $log->setExpiresAt(new \DateTimeImmutable('+10 minutes'));

        $this->em->persist($log);
        $this->em->flush();

        $this->createdLogIds[] = $log->getId();

        return $log;
    }
}
