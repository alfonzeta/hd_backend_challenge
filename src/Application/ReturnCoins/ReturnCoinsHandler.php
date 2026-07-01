<?php

declare(strict_types=1);

namespace App\Application\ReturnCoins;

use App\Application\Port\VendingMachineRepository;
use App\Domain\Coin;

final readonly class ReturnCoinsHandler
{
    public function __construct(private VendingMachineRepository $repository)
    {
    }

    /**
     * @return list<Coin>
     */
    public function __invoke(ReturnCoinsCommand $command): array
    {
        $machine = $this->repository->get();
        $returned = $machine->returnInsertedCoins();
        $this->repository->save($machine);

        return $returned;
    }
}
