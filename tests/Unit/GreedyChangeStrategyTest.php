<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Coin;
use App\Domain\CoinInventory;
use App\Domain\Exception\CannotDispenseChangeException;
use App\Domain\GreedyChangeStrategy;
use App\Domain\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GreedyChangeStrategyTest extends TestCase
{
    /**
     * @param array<int,int> $stock      coin value => count
     * @param list<int>      $expected   expected change as coin values, in order
     */
    #[DataProvider('changeCases')]
    public function testCalculateReturnsExpectedChange(int $amount, array $stock, array $expected): void
    {
        $strategy = new GreedyChangeStrategy();

        $change = $strategy->calculate(Money::fromCents($amount), CoinInventory::fromCounts($stock));

        self::assertSame($expected, array_map(static fn (Coin $coin): int => $coin->value, $change));
    }

    /**
     * @return iterable<string, array{int, array<int,int>, list<int>}>
     */
    public static function changeCases(): iterable
    {
        yield 'spec example 3: 35 with abundant stock' => [
            35,
            [Coin::TwentyFiveCents->value => 5, Coin::TenCents->value => 5, Coin::FiveCents->value => 5],
            [25, 10],
        ];

        yield 'zero change owed' => [
            0,
            [Coin::TwentyFiveCents->value => 1],
            [],
        ];

        yield 'falls back to smaller coins when large one is missing' => [
            30,
            [Coin::TwentyFiveCents->value => 0, Coin::TenCents->value => 3],
            [10, 10, 10],
        ];

        yield 'mixes denominations' => [
            40,
            [Coin::TwentyFiveCents->value => 1, Coin::TenCents->value => 1, Coin::FiveCents->value => 1],
            [25, 10, 5],
        ];
    }

    public function testThrowsWhenStockCannotCoverAmount(): void
    {
        $strategy = new GreedyChangeStrategy();

        $this->expectException(CannotDispenseChangeException::class);

        $strategy->calculate(Money::fromCents(30), CoinInventory::empty());
    }

    /**
     * Documents the known greedy limitation: owing 30 with only {25x1, 10x3},
     * greedy commits to the 25 and gets stuck on the remaining 5, even though
     * 10+10+10 is a valid solution. Locking this in makes the trade-off visible
     * and would flip the day we swap in an exact strategy.
     */
    public function testGreedyFailsEvenThoughAnExactSolutionExists(): void
    {
        $strategy = new GreedyChangeStrategy();
        $stock = CoinInventory::fromCounts([
            Coin::TwentyFiveCents->value => 1,
            Coin::TenCents->value => 3,
        ]);

        $this->expectException(CannotDispenseChangeException::class);

        $strategy->calculate(Money::fromCents(30), $stock);
    }

    public function testInventoryPassedInIsNotMutated(): void
    {
        $strategy = new GreedyChangeStrategy();
        $stock = CoinInventory::fromCounts([
            Coin::TwentyFiveCents->value => 1,
            Coin::TenCents->value => 1,
        ]);

        $strategy->calculate(Money::fromCents(35), $stock);

        self::assertSame(1, $stock->count(Coin::TwentyFiveCents));
        self::assertSame(1, $stock->count(Coin::TenCents));
    }
}
