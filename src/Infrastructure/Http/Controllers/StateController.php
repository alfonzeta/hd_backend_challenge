<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Port\VendingMachineRepository;
use App\Domain\Coin;
use App\Domain\ProductSelector;
use App\Domain\VendingMachine;
use App\Infrastructure\Http\ExceptionToHttpMapper;
use App\Infrastructure\Http\JsonResponse;
use Throwable;

final class StateController
{
    public function __construct(
        private VendingMachineRepository $repository,
        private ExceptionToHttpMapper $exceptionMapper,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        try {
            $machine = $this->repository->get();

            return JsonResponse::ok([
                'balance' => $machine->insertedBalance()->cents,
                'products' => $this->serializeProducts($machine),
                'change' => $this->serializeChange($machine),
            ]);
        } catch (Throwable $throwable) {
            return JsonResponse::fromError($this->exceptionMapper->map($throwable));
        }
    }

    /**
     * @return array<string, array{price: int, stock: int}>
     */
    private function serializeProducts(VendingMachine $machine): array
    {
        $products = [];

        foreach (ProductSelector::cases() as $selector) {
            $product = $machine->product($selector);
            $products[$selector->value] = [
                'price' => $product->price->cents,
                'stock' => $product->stock(),
            ];
        }

        return $products;
    }

    /**
     * @return array<int, int>
     */
    private function serializeChange(VendingMachine $machine): array
    {
        $change = [];

        foreach (Coin::cases() as $coin) {
            $change[$coin->value] = $machine->changeBank()->count($coin);
        }

        return $change;
    }
}
