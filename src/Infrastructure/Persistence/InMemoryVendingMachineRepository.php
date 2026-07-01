<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Port\VendingMachineRepository;
use App\Domain\Coin;
use App\Domain\CoinInventory;
use App\Domain\Money;
use App\Domain\Product;
use App\Domain\ProductSelector;
use App\Domain\VendingMachine;

/**
 * In-memory adapter for {@see VendingMachineRepository}.
 *
 * Holds a single {@see VendingMachine} instance in a process-local field: enough
 * for the challenge, the tests and a live demo. Migrating to a durable store
 * (MongoDB, Redis, SQL) means adding another adapter that implements the same
 * port; neither the domain nor the use cases change.
 *
 * The constructor accepts an optional initial machine so the composition root can
 * inject a configured one. When omitted it boots the spec's standard machine, a
 * convenience for tests and the demo rather than a business decision baked into
 * infrastructure.
 */
final class InMemoryVendingMachineRepository implements VendingMachineRepository
{
    private VendingMachine $machine;

    public function __construct(?VendingMachine $initial = null)
    {
        $this->machine = $initial ?? self::standardMachine();
    }

    public function get(): VendingMachine
    {
        return $this->machine;
    }

    public function save(VendingMachine $machine): void
    {
        $this->machine = $machine;
    }

    /**
     * Builds a machine matching the spec catalogue (Water 0.65, Juice 1.00,
     * Soda 1.50) with a starting stock and change bank, ready to operate.
     */
    private static function standardMachine(): VendingMachine
    {
        return VendingMachine::create(
            [
                new Product(ProductSelector::Water, Money::fromCents(65), 5),
                new Product(ProductSelector::Juice, Money::fromCents(100), 5),
                new Product(ProductSelector::Soda, Money::fromCents(150), 5),
            ],
            CoinInventory::fromCounts([
                Coin::TwentyFiveCents->value => 10,
                Coin::TenCents->value => 10,
                Coin::FiveCents->value => 10,
            ]),
        );
    }
}
