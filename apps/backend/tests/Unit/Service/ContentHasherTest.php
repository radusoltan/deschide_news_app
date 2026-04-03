<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\ContentHasher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ContentHasherTest extends TestCase
{
    private ContentHasher $hasher;

    protected function setUp(): void
    {
        $this->hasher = new ContentHasher();
    }

    #[Test]
    public function identicalTextWithDifferentWhitespaceProducesSameHash(): void
    {
        $a = $this->hasher->hash('Hello   world   test');
        $b = $this->hasher->hash("Hello\n\tworld\n\ntest");

        $this->assertSame($a, $b);
    }

    #[Test]
    public function htmlTagsDifferentButTextIdenticalProducesSameHash(): void
    {
        $a = $this->hasher->hash('<p>Hello world</p>');
        $b = $this->hasher->hash('<div><strong>Hello</strong> world</div>');

        $this->assertSame($a, $b);
    }

    #[Test]
    public function differentTextProducesDifferentHash(): void
    {
        $a = $this->hasher->hash('First article about politics');
        $b = $this->hasher->hash('Second article about economics');

        $this->assertNotSame($a, $b);
    }

    #[Test]
    public function hashIsSha256(): void
    {
        $hash = $this->hasher->hash('Test content');

        $this->assertSame(64, strlen($hash));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
    }

    #[Test]
    public function caseInsensitive(): void
    {
        $a = $this->hasher->hash('Hello World');
        $b = $this->hasher->hash('hello world');

        $this->assertSame($a, $b);
    }
}
