<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Outcome of a successful purchase: the product that was vended and the coins
 * returned as change (empty when the customer paid the exact price).
 *
 * Lives in the domain because it is a meaningful business concept (the result
 * of vending), not a transport/serialization detail. Application and HTTP
 * layers translate it to their own representations.
 */
final readonly class PurchaseResult
{
    /**
     * @param list<Coin> $changeCoins coins returned as change (empty when none is due)
     */
    public function __construct(
        public ProductSelector $product,
        public array $changeCoins,
    ) {
    }
}
