<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Exception\CannotDispenseChangeException;

/**
 * Change strategy that always takes the largest coin that still fits and is in
 * stock, working down the denominations.
 *
 * For the canonical {5, 10, 25} system greedy returns the minimum number of
 * coins when stock is unlimited. Known trade-off: with limited stock it can
 * fail to find change even when a valid combination exists (e.g. owing 30 with
 * only {25x1, 10x3} — greedy takes the 25 and gets stuck on 5, although 10+10+10
 * would work). For this machine that means a sale is occasionally rejected and
 * the inserted money refunded, which is safe but not optimal.
 *
 * The decision of whether to replace this with an exact (dynamic-programming)
 * strategy is intentionally deferred; thanks to {@see ChangeStrategy} that swap
 * is a one-line change in the composition root.
 */
final class GreedyChangeStrategy implements ChangeStrategy
{
    public function calculate(Money $amount, CoinInventory $available): array
    {
        $remaining = $amount->cents;
        $change = [];

        foreach (Coin::changeDenominations() as $coin) {
            while ($remaining >= $coin->value && $available->has($coin)) {
                $remaining -= $coin->value;
                $available = $available->remove($coin);
                $change[] = $coin;
            }
        }

        if ($remaining > 0) {
            throw new CannotDispenseChangeException($amount);
        }

        return $change;
    }
}
