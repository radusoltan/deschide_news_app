<?php

declare(strict_types=1);

namespace App\Tests\Unit\Message;

use App\Message\OptimizeSeoMessage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OptimizeSeoMessageTest extends TestCase
{
    #[Test]
    public function itStoresAllProperties(): void
    {
        $message = new OptimizeSeoMessage(
            articleId: 42,
            generateMeta: true,
            suggestTags: false,
            force: true,
        );

        $this->assertSame(42, $message->articleId);
        $this->assertTrue($message->generateMeta);
        $this->assertFalse($message->suggestTags);
        $this->assertTrue($message->force);
    }

    #[Test]
    public function itHasCorrectDefaults(): void
    {
        $message = new OptimizeSeoMessage(articleId: 1);

        $this->assertSame(1, $message->articleId);
        $this->assertTrue($message->generateMeta);
        $this->assertTrue($message->suggestTags);
        $this->assertFalse($message->force);
    }
}
