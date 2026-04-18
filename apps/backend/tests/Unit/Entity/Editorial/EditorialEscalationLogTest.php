<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity\Editorial;

use App\Entity\Editorial\EditorialEscalationLog;
use App\Entity\User;
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
            categoryCode: 'categ_3',
            originGraphSnapshot: $originGraph,
        );
        $after = new \DateTimeImmutable();

        self::assertNull($log->getId());
        self::assertSame($articleSnapshot, $log->getArticleSnapshot());
        self::assertSame('categ_3', $log->getCategoryCode());
        self::assertSame($originGraph, $log->getOriginGraphSnapshot());
        self::assertNull($log->getDecision(), 'Decision starts null — filled in on human adjudication');
        self::assertNull($log->getDecidedBy());
        self::assertNull($log->getDecidedAt());
        self::assertGreaterThanOrEqual($before, $log->getCreatedAt());
        self::assertLessThanOrEqual($after, $log->getCreatedAt());
    }

    public function testFamilyTaxonomyCategoryCodeAcceptedVerbatim(): void
    {
        $log = new EditorialEscalationLog(
            articleSnapshot: ['id' => 1],
            categoryCode: 'family_b',
            originGraphSnapshot: [],
        );

        self::assertSame('family_b', $log->getCategoryCode());
    }

    public function testDecisionWorkflowMutators(): void
    {
        $log = new EditorialEscalationLog(
            articleSnapshot: ['id' => 7],
            categoryCode: 'categ_1',
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
            categoryCode: 'categ_1',
            originGraphSnapshot: [],
        );

        $log->setDecision(EscalationDecision::REJECTED);
        $log->setDecision(null);

        self::assertNull($log->getDecision());
    }
}
