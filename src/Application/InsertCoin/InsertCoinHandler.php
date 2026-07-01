<?php

declare(strict_types=1);

namespace App\Application\InsertCoin;

use App\Application\Port\VendingMachineRepository;

final readonly class InsertCoinHandler
{
    public function __construct(private VendingMachineRepository $repository)
    {
    }

    public function __invoke(InsertCoinCommand $command): void
    {
        $machine = $this->repository->get();
        $machine->insert($command->coin);
        $this->repository->save($machine);
    }
}
