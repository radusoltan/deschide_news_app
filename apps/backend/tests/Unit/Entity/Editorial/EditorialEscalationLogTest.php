<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity\Editorial;

use App\Entity\Editorial\EditorialEscalationLog;
use App\Entity\User;
use App\Enum\Editorial\EscalationCategory;
use App\Enum\EscalationDecision;
use PHPUnit\Framework\TestCase;

/**
 * T53.3 — EditorialEscalationLog schema-only tests.
 *
 * Schema shape is validated by Doctrine migrations + schema:validate; these
 * tests lock the minimal PHP contract that Sprint 55 business logic will
 * depend on.
 */
class EditorialEscalationLogTest extends TestCase
{
    public function testConstructionPreservesSnapshotsAndCategoryCode(): void
    {
        $articleSnapshot = [
            'id' => 42,
            'title' => 'Test article',
            'status' => 'pending_review',
            'locale' => 'ro',
        ];
        $originGraph = [
            'press_releases' => [11, 12, 13],
            'signals' => [101, 102],
            'verified_sources' => ['reuters', 'zdg'],
        ];

        $before = new \DateTimeImmutable();
        $log = new EditorialEscalationLog(
            articleSnapshot: $articleSnapshot,
            category: EscalationCategory::CATEGORY_3_NBC_ATTACK,
            originGraphSnapshot: $originGraph,
        );
        $after = new \DateTimeImmutable();

        self::assertNull($log->getId());
        self::assertSame($articleSnapshot, $log->getArticleSnapshot());
        self::assertSame(EscalationCategory::CATEGORY_3_NBC_ATTACK, $log->getCategory());
        self::assertSame('categ_3', $log->getCategoryCode(), 'Legacy string shim keeps S53 API contract');
        self::assertSame($originGraph, $log->getOriginGraphSnapshot());
        self::assertNull($log->getExpiresAt(), 'expiresAt starts null — set by EscalationLogWriter on insert');
        self::assertNull($log->getDecision(), 'Decision starts null — filled in on human adjudication');
        self::assertNull($log->getDecidedBy());
        self::assertNull($log->getDecidedAt());
        self::assertGreaterThanOrEqual($before, $log->getCreatedAt());
        self::assertLessThanOrEqual($after, $log->getCreatedAt());
    }

    public function testFamilyTaxonomyMappedToEnum(): void
    {
        $log = new EditorialEscalationLog(
            articleSnapshot: ['id' => 1],
            category: EscalationCategory::FAMILY_B_EU_NATO_RUSSIA,
            originGraphSnapshot: [],
        );

        self::assertSame(EscalationCategory::FAMILY_B_EU_NATO_RUSSIA, $log->getCategory());
        self::assertSame('family_b', $log->getCategoryCode());
    }

    public function testExpiresAtSetterGetterRoundTrip(): void
    {
        $log = new EditorialEscalationLog(
            articleSnapshot: ['id' => 1],
            category: EscalationCategory::CATEGORY_7_PRE_CEC_ELECTORAL,
            originGraphSnapshot: [],
        );

        self::assertNull($log->getExpiresAt());

        $expires = new \DateTimeImmutable('+10 minutes');
        $log->setExpiresAt($expires);
        self::assertEquals($expires, $log->getExpiresAt());

        $log->setExpiresAt(null);
        self::assertNull($log->getExpiresAt());
    }

    public function testDecisionWorkflowMutators(): void
    {
        $log = new EditorialEscalationLog(
            articleSnapshot: ['id' => 7],
            category: EscalationCategory::CATEGORY_1_NUCLEAR_WAR,
            originGraphSnapshot: ['pr' => [1]],
        );

        $user = new User();
        $decidedAt = new \DateTimeImmutable();

        $log->setDecision(EscalationDecision::APPROVED);
        $log->setDecidedBy($user);
        $log->setDecidedAt($decidedAt);

        self::assertSame(EscalationDecision::APPROVED, $log->getDecision());
        self::assertSame($user, $log->getDecidedBy());
        self::assertEquals($decidedAt, $log->getDecidedAt());
    }

    public function testDecisionCanBeReset(): void
    {
        $log = new EditorialEscalationLog(
            articleSnapshot: ['id' => 7],
            category: EscalationCategory::CATEGORY_1_NUCLEAR_WAR,
            originGraphSnapshot: [],
        );

        $log->setDecision(EscalationDecision::REJECTED);
        $log->setDecision(null);

        self::assertNull($log->getDecision());
    }
}
