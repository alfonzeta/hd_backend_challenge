<?php

declare(strict_types=1);

namespace App\Application\PurchaseProduct;

use App\Application\Port\VendingMachineRepository;
use App\Domain\PurchaseResult;

final readonly class PurchaseProductHandler
{
    public function __construct(private VendingMachineRepository $repository)
    {
    }

    public function __invoke(PurchaseProductCommand $command): PurchaseResult
    {
        $machine = $this->repository->get();
        $result = $machine->purchase($command->selector);
        $this->repository->save($machine);

        return $result;
    }
}
