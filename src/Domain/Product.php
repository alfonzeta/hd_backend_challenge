<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Exception\ProductOutOfStockException;
use InvalidArgumentException;

/**
 * A product the machine can vend: its selector, price and remaining stock.
 *
 * The selector and price are immutable; stock is mutable but can only change
 * through {@see dispenseOne()} and {@see restock()}, which protect the
 * invariant that stock never becomes negative.
 */
final class Product
{
    public function __construct(
        public readonly ProductSelector $selector,
        public readonly Money $price,
        private int $stock,
    ) {
        if ($stock < 0) {
            throw new InvalidArgumentException(
                sprintf('Stock cannot be negative, got %d.', $stock)
            );
        }
    }

    public function isAvailable(): bool
    {
        return $this->stock > 0;
    }

    /**
     * Removes a single unit from stock.
     *
     * @throws ProductOutOfStockException when no units remain.
     */
    public function dispenseOne(): void
    {
        if (!$this->isAvailable()) {
            throw new ProductOutOfStockException($this->selector);
        }

        $this->stock--;
    }

    public function stock(): int
    {
        return $this->stock;
    }

    /**
     * Adds units to stock (used by the SERVICE operation).
     */
    public function restock(int $count): void
    {
        if ($count < 0) {
            throw new InvalidArgumentException(
                sprintf('Restock count cannot be negative, got %d.', $count)
            );
        }

        $this->stock += $count;
    }
}
