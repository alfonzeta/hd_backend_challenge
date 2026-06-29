<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testAdd(): void
    {
        $result = Money::fromCents(65)->add(Money::fromCents(35));

        self::assertSame(100, $result->cents);
    }

    public function testSubtract(): void
    {
        $result = Money::fromCents(100)->subtract(Money::fromCents(35));

        self::assertSame(65, $result->cents);
    }

    public function testIsGreaterThanOrEqual(): void
    {
        self::assertTrue(Money::fromCents(100)->isGreaterThanOrEqual(Money::fromCents(65)));
        self::assertTrue(Money::fromCents(65)->isGreaterThanOrEqual(Money::fromCents(65)));
        self::assertFalse(Money::fromCents(65)->isGreaterThanOrEqual(Money::fromCents(100)));
    }

    public function testEquals(): void
    {
        self::assertTrue(Money::fromCents(150)->equals(Money::fromCents(150)));
        self::assertFalse(Money::fromCents(150)->equals(Money::fromCents(65)));
    }

    public function testNegativeCentsThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromCents(-1);
    }

    public function testSubtractBelowZeroThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromCents(10)->subtract(Money::fromCents(20));
    }

    public function testArithmeticReturnsNewInstances(): void
    {
        $base = Money::fromCents(50);

        $base->add(Money::fromCents(10));

        self::assertSame(50, $base->cents);
    }
}
