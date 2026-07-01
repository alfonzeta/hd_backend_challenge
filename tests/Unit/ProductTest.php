<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Exception\ProductOutOfStockException;
use App\Domain\Money;
use App\Domain\Product;
use App\Domain\ProductSelector;
use PHPUnit\Framework\TestCase;

final class ProductTest extends TestCase
{
    public function testIsAvailableWhenStockPositive(): void
    {
        $product = new Product(ProductSelector::Water, Money::fromCents(65), 1);

        self::assertTrue($product->isAvailable());
    }

    public function testIsNotAvailableWhenStockZero(): void
    {
        $product = new Product(ProductSelector::Water, Money::fromCents(65), 0);

        self::assertFalse($product->isAvailable());
    }

    public function testDispenseOneDecrementsStock(): void
    {
        $product = new Product(ProductSelector::Juice, Money::fromCents(100), 2);

        $product->dispenseOne();

        self::assertSame(1, $product->stock());
    }

    public function testDispenseOneWhenOutOfStockThrows(): void
    {
        $product = new Product(ProductSelector::Soda, Money::fromCents(150), 0);

        $this->expectException(ProductOutOfStockException::class);

        $product->dispenseOne();
    }

    public function testRestockAddsUnits(): void
    {
        $product = new Product(ProductSelector::Water, Money::fromCents(65), 0);

        $product->restock(3);

        self::assertSame(3, $product->stock());
    }
}
