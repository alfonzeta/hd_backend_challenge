<?php

declare(strict_types=1);

namespace Tests\Application;

use App\Domain\Coin;
use App\Domain\CoinInventory;
use App\Domain\Money;
use App\Domain\Product;
use App\Domain\ProductSelector;
use App\Domain\VendingMachine;
use App\Infrastructure\Persistence\InMemoryVendingMachineRepository;
use PHPUnit\Framework\TestCase;

final class InMemoryVendingMachineRepositoryTest extends TestCase
{
    public function testSaveAndGetPreservesState(): void
    {
        $machine = VendingMachine::create(
            [new Product(ProductSelector::Water, Money::fromCents(65), 5)],
            CoinInventory::empty(),
        );
        $repository = new InMemoryVendingMachineRepository($machine);

        $machine->insert(Coin::TwentyFiveCents);
        $repository->save($machine);

        self::assertTrue(
            $repository->get()->insertedBalance()->equals(Money::fromCents(25)),
        );
    }

    public function testGetReturnsTheInjectedMachine(): void
    {
        $machine = VendingMachine::create(
            [new Product(ProductSelector::Soda, Money::fromCents(150), 1)],
            CoinInventory::empty(),
        );
        $repository = new InMemoryVendingMachineRepository($machine);

        self::assertSame($machine, $repository->get());
    }

    public function testDefaultInitialStateBootsUsableMachine(): void
    {
        $repository = new InMemoryVendingMachineRepository();

        $machine = $repository->get();
        $machine->insert(Coin::OneEuro);
        $result = $machine->purchase(ProductSelector::Water);

        self::assertSame(ProductSelector::Water, $result->product);
    }
}
