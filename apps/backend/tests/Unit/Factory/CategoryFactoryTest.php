<?php

declare(strict_types=1);

namespace App\Tests\Unit\Factory;

use App\Entity\Category;
use App\Factory\CategoryFactory;
use PHPUnit\Framework\TestCase;

class CategoryFactoryTest extends TestCase
{
    public function testClassReturnsCategoryClass(): void
    {
        $this->assertSame(Category::class, CategoryFactory::class());
    }

    public function testFactoryCanBeInstantiated(): void
    {
        $factory = new CategoryFactory();
        $this->assertInstanceOf(CategoryFactory::class, $factory);
    }
}
