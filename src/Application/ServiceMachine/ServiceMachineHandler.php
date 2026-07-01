<?php

declare(strict_types=1);

namespace App\Application\ServiceMachine;

use App\Application\Port\VendingMachineRepository;

final readonly class ServiceMachineHandler
{
    public function __construct(private VendingMachineRepository $repository)
    {
    }

    public function __invoke(ServiceMachineCommand $command): void
    {
        $machine = $this->repository->get();
        $machine->service($command->productStock, $command->changeBank);
        $this->repository->save($machine);
    }
}
