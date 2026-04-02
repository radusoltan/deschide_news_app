<?php

declare(strict_types=1);

namespace App\Tests\Unit\Factory;

use App\Entity\Author;
use App\Factory\AuthorFactory;
use PHPUnit\Framework\TestCase;

class AuthorFactoryTest extends TestCase
{
    public function testClassReturnsAuthorClass(): void
    {
        $this->assertSame(Author::class, AuthorFactory::class());
    }

    public function testFactoryCanBeInstantiated(): void
    {
        $factory = new AuthorFactory();
        $this->assertInstanceOf(AuthorFactory::class, $factory);
    }
}
