<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Coin;
use App\Domain\CoinInventory;
use App\Domain\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CoinInventoryTest extends TestCase
{
    public function testEmptyHasNoCoins(): void
    {
        $inventory = CoinInventory::empty();

        self::assertSame(0, $inventory->count(Coin::FiveCents));
        self::assertFalse($inventory->has(Coin::FiveCents));
        self::assertTrue($inventory->totalValue()->equals(Money::fromCents(0)));
    }

    public function testFromCountsExposesCountsAndTotal(): void
    {
        $inventory = CoinInventory::fromCounts([
            Coin::TwentyFiveCents->value => 2,
            Coin::FiveCents->value => 3,
        ]);

        self::assertSame(2, $inventory->count(Coin::TwentyFiveCents));
        self::assertSame(3, $inventory->count(Coin::FiveCents));
        self::assertSame(0, $inventory->count(Coin::TenCents));
        self::assertTrue($inventory->totalValue()->equals(Money::fromCents(65)));
    }

    public function testFromCountsRejectsUnknownDenomination(): void
    {
        $this->expectException(\ValueError::class);

        CoinInventory::fromCounts([7 => 1]);
    }

    public function testFromCountsRejectsNegativeCount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CoinInventory::fromCounts([Coin::FiveCents->value => -1]);
    }

    public function testAddReturnsNewInstanceAndLeavesOriginalUntouched(): void
    {
        $original = CoinInventory::empty();

        $updated = $original->add(Coin::TenCents);

        self::assertSame(0, $original->count(Coin::TenCents));
        self::assertSame(1, $updated->count(Coin::TenCents));
    }

    public function testRemoveReturnsNewInstanceAndLeavesOriginalUntouched(): void
    {
        $original = CoinInventory::fromCounts([Coin::TenCents->value => 2]);

        $updated = $original->remove(Coin::TenCents);

        self::assertSame(2, $original->count(Coin::TenCents));
        self::assertSame(1, $updated->count(Coin::TenCents));
    }

    public function testRemoveWhenNoneAvailableThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CoinInventory::empty()->remove(Coin::FiveCents);
    }
}
