<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Domain\Coin;

final class CoinSerializer
{
    /**
     * @param list<Coin> $coins
     *
     * @return list<int>
     */
    public static function toCents(array $coins): array
    {
        return array_map(static fn (Coin $coin): int => $coin->value, $coins);
    }
}
