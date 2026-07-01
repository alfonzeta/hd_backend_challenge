<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Accepted coin denominations, expressed in cents.
 *
 * Modelling coins as a backed enum makes invalid denominations unrepresentable:
 * the type system forbids inserting a coin the machine does not accept.
 *
 * All four denominations are accepted on insert, but change is only ever
 * dispensed in 5/10/25 cent coins. This mirrors the spec (Example 3: insert
 * 1.00, buy Water 0.65, receive 0.25 + 0.10 back); the 1.00 coin is never
 * returned as change.
 */
enum Coin: int
{
    case FiveCents = 5;
    case TenCents = 10;
    case TwentyFiveCents = 25;
    case OneEuro = 100;

    public function toMoney(): Money
    {
        return Money::fromCents($this->value);
    }

    /**
     * Denominations valid as change, in descending order.
     *
     * Excludes OneEuro by design (never returned as change) and the descending
     * order is what the greedy change algorithm relies on.
     *
     * @return list<self>
     */
    public static function changeDenominations(): array
    {
        return [self::TwentyFiveCents, self::TenCents, self::FiveCents];
    }
}
