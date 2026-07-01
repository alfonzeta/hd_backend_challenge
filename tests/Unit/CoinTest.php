<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Coin;
use App\Domain\Money;
use PHPUnit\Framework\TestCase;

final class CoinTest extends TestCase
{
    public function testEachCoinMapsToCorrectMoney(): void
    {
        self::assertTrue(Coin::FiveCents->toMoney()->equals(Money::fromCents(5)));
        self::assertTrue(Coin::TenCents->toMoney()->equals(Money::fromCents(10)));
        self::assertTrue(Coin::TwentyFiveCents->toMoney()->equals(Money::fromCents(25)));
        self::assertTrue(Coin::OneEuro->toMoney()->equals(Money::fromCents(100)));
    }

    public function testChangeDenominationsExcludesOneEuro(): void
    {
        self::assertNotContains(Coin::OneEuro, Coin::changeDenominations());
    }

    public function testChangeDenominationsOrder(): void
    {
        self::assertSame(
            [Coin::TwentyFiveCents, Coin::TenCents, Coin::FiveCents],
            Coin::changeDenominations()
        );
    }
}
