<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto\Clustering;

use App\Dto\Clustering\VerificationResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class VerificationResultTest extends TestCase
{
    #[Test]
    public function passCreatesAcceptedResult(): void
    {
        $result = VerificationResult::pass('All good');

        $this->assertTrue($result->sameStory);
        $this->assertSame(1.0, $result->confidence);
        $this->assertSame('All good', $result->reason);
        $this->assertFalse($result->failedOpen);
    }

    #[Test]
    public function rejectCreatesRejectedResult(): void
    {
        $result = VerificationResult::reject(0.2, 'Different topics');

        $this->assertFalse($result->sameStory);
        $this->assertSame(0.2, $result->confidence);
        $this->assertSame('Different topics', $result->reason);
        $this->assertFalse($result->failedOpen);
    }

    #[Test]
    public function failOpenCreatesAcceptedWithFlag(): void
    {
        $result = VerificationResult::failOpen('Gemini timeout');

        $this->assertTrue($result->sameStory);
        $this->assertSame(0.5, $result->confidence);
        $this->assertSame('Gemini timeout', $result->reason);
        $this->assertTrue($result->failedOpen);
    }

    #[Test]
    public function constructorSetsAllProperties(): void
    {
        $result = new VerificationResult(
            sameStory: false,
            confidence: 0.75,
            reason: 'Custom reason',
            failedOpen: true,
        );

        $this->assertFalse($result->sameStory);
        $this->assertSame(0.75, $result->confidence);
        $this->assertSame('Custom reason', $result->reason);
        $this->assertTrue($result->failedOpen);
    }
}
