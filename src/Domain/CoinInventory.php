<?php

declare(strict_types=1);

namespace App\Domain;

use InvalidArgumentException;

/**
 * Immutable count of how many coins of each denomination the machine holds.
 *
 * Coins are stored keyed by their value in cents (enums cannot be array keys).
 * Mutating operations ({@see add()}, {@see remove()}) return a new instance, so
 * a change calculation can "spend" coins on a working copy without corrupting
 * the real machine state until the operation is known to succeed.
 */
final readonly class CoinInventory
{
    /**
     * @param array<int,int> $countsByValue coin value in cents => positive count
     */
    private function __construct(private array $countsByValue)
    {
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @param array<int,int> $counts coin value in cents => count (>= 0)
     */
    public static function fromCounts(array $counts): self
    {
        $normalized = [];
        foreach ($counts as $value => $count) {
            if ($count < 0) {
                throw new InvalidArgumentException(
                    sprintf('Coin count cannot be negative, got %d.', $count)
                );
            }

            // Coin::from() rejects any value that is not an accepted denomination.
            $coin = Coin::from($value);

            if ($count > 0) {
                $normalized[$coin->value] = $count;
            }
        }

        return new self($normalized);
    }

    public function count(Coin $coin): int
    {
        return $this->countsByValue[$coin->value] ?? 0;
    }

    public function has(Coin $coin): bool
    {
        return $this->count($coin) > 0;
    }

    public function add(Coin $coin): self
    {
        $counts = $this->countsByValue;
        $counts[$coin->value] = ($counts[$coin->value] ?? 0) + 1;

        return new self($counts);
    }

    /**
     * @throws InvalidArgumentException when no coin of this denomination is held.
     */
    public function remove(Coin $coin): self
    {
        if (!$this->has($coin)) {
            throw new InvalidArgumentException(
                sprintf('Cannot remove a %d-cent coin: none available.', $coin->value)
            );
        }

        $counts = $this->countsByValue;
        $counts[$coin->value]--;

        if ($counts[$coin->value] === 0) {
            unset($counts[$coin->value]);
        }

        return new self($counts);
    }

    public function totalValue(): Money
    {
        $cents = 0;
        foreach ($this->countsByValue as $value => $count) {
            $cents += $value * $count;
        }

        return Money::fromCents($cents);
    }
}
