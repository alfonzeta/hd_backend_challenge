<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Exception\CannotDispenseChangeException;

/**
 * Strategy for deciding which coins to return as change for a given amount,
 * constrained by the coins the machine actually holds.
 *
 * Extracting this behind an interface keeps the change algorithm a swappable
 * implementation detail: the rest of the domain depends on this port, so the
 * default greedy strategy can be replaced (e.g. by an exact dynamic-programming
 * strategy) without touching any caller. See {@see GreedyChangeStrategy} for the
 * current default and its documented trade-off.
 */
interface ChangeStrategy
{
    /**
     * @return list<Coin> coins to dispense as change, in no particular order
     *                    (an empty list when no change is due)
     *
     * @throws CannotDispenseChangeException when exact change cannot be made
     */
    public function calculate(Money $amount, CoinInventory $available): array;
}
