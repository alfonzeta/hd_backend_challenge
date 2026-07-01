<?php

declare(strict_types=1);

namespace App\Application\ServiceMachine;

use App\Domain\CoinInventory;
use App\Domain\ProductSelector;

final readonly class ServiceMachineCommand
{
    /**
     * @param array<string, int> $productStock selector value (e.g. WATER) => stock count
     */
    public function __construct(
        public array $productStock,
        public CoinInventory $changeBank,
    ) {
    }
}
