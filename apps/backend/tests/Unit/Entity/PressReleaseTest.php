<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\PressRelease;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PressReleaseTest extends TestCase
{
    #[Test]
    public function originalTitleDefaultsToNull(): void
    {
        $pr = new PressRelease();
        $this->assertNull($pr->getOriginalTitle());
    }

    #[Test]
    public function originalContentDefaultsToNull(): void
    {
        $pr = new PressRelease();
        $this->assertNull($pr->getOriginalContent());
    }

    #[Test]
    public function setAndGetOriginalTitle(): void
    {
        $pr = new PressRelease();
        $result = $pr->setOriginalTitle('EU announces new trade deal');

        $this->assertSame('EU announces new trade deal', $pr->getOriginalTitle());
        $this->assertSame($pr, $result, 'Setter should return $this for fluent API');
    }

    #[Test]
    public function setAndGetOriginalContent(): void
    {
        $pr = new PressRelease();
        $result = $pr->setOriginalContent('Full original article content in English.');

        $this->assertSame('Full original article content in English.', $pr->getOriginalContent());
        $this->assertSame($pr, $result, 'Setter should return $this for fluent API');
    }

    #[Test]
    public function originalTitleCanBeSetToNull(): void
    {
        $pr = new PressRelease();
        $pr->setOriginalTitle('Something');
        $pr->setOriginalTitle(null);

        $this->assertNull($pr->getOriginalTitle());
    }
}
