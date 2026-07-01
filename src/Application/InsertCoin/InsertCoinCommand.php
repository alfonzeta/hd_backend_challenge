<?php

declare(strict_types=1);

namespace App\Application\InsertCoin;

use App\Domain\Coin;

final readonly class InsertCoinCommand
{
    public function __construct(public Coin $coin)
    {
    }
}
