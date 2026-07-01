<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use App\Application\InsertCoin\InsertCoinCommand;
use App\Application\InsertCoin\InsertCoinHandler;
use App\Application\PurchaseProduct\PurchaseProductCommand;
use App\Application\PurchaseProduct\PurchaseProductHandler;
use App\Application\ReturnCoins\ReturnCoinsCommand;
use App\Application\ReturnCoins\ReturnCoinsHandler;
use App\Application\ServiceMachine\ServiceMachineCommand;
use App\Application\ServiceMachine\ServiceMachineHandler;
use App\Domain\Coin;
use App\Domain\CoinInventory;
use App\Domain\Money;
use App\Domain\Product;
use App\Domain\ProductSelector;
use App\Domain\VendingMachine;
use App\Infrastructure\Persistence\InMemoryVendingMachineRepository;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end acceptance scenarios from the challenge specification.
 *
 * Exercises the full application layer by chaining real use case handlers
 * through the in-memory repository adapter — no mocked domain.
 */
final class VendingMachineScenariosTest extends TestCase
{
    public function testExample1BuySodaWithExactChange(): void
    {
        $handlers = $this->handlers();

        ($handlers['serviceMachine'])(new ServiceMachineCommand(
            [
                ProductSelector::Water->value => 5,
                ProductSelector::Juice->value => 5,
                ProductSelector::Soda->value => 5,
            ],
            CoinInventory::fromCounts([
                Coin::TwentyFiveCents->value => 10,
                Coin::TenCents->value => 10,
                Coin::FiveCents->value => 10,
            ]),
        ));

        ($handlers['insertCoin'])(new InsertCoinCommand(Coin::OneEuro));
        ($handlers['insertCoin'])(new InsertCoinCommand(Coin::TwentyFiveCents));
        ($handlers['insertCoin'])(new InsertCoinCommand(Coin::TwentyFiveCents));

        $result = ($handlers['purchaseProduct'])(
            new PurchaseProductCommand(ProductSelector::Soda),
        );

        self::assertSame(ProductSelector::Soda, $result->product);
        self::assertSame([], $result->changeCoins);
        self::assertTrue(
            $handlers['repository']->get()->insertedBalance()->equals(Money::fromCents(0)),
        );
        self::assertSame(4, $handlers['repository']->get()->product(ProductSelector::Soda)->stock());
    }

    public function testExample2ReturnCoin(): void
    {
        $handlers = $this->handlers();

        ($handlers['insertCoin'])(new InsertCoinCommand(Coin::TenCents));
        ($handlers['insertCoin'])(new InsertCoinCommand(Coin::TenCents));

        $returned = ($handlers['returnCoins'])(new ReturnCoinsCommand());

        self::assertSame([Coin::TenCents, Coin::TenCents], $returned);
        self::assertTrue(
            $handlers['repository']->get()->insertedBalance()->equals(Money::fromCents(0)),
        );
    }

    public function testExample3BuyWaterWithChange(): void
    {
        $handlers = $this->handlers();

        ($handlers['serviceMachine'])(new ServiceMachineCommand(
            [
                ProductSelector::Water->value => 1,
                ProductSelector::Juice->value => 5,
                ProductSelector::Soda->value => 5,
            ],
            CoinInventory::fromCounts([
                Coin::TwentyFiveCents->value => 1,
                Coin::TenCents->value => 1,
            ]),
        ));

        ($handlers['insertCoin'])(new InsertCoinCommand(Coin::OneEuro));

        $result = ($handlers['purchaseProduct'])(
            new PurchaseProductCommand(ProductSelector::Water),
        );

        self::assertSame(ProductSelector::Water, $result->product);
        self::assertEqualsCanonicalizing(
            [Coin::TwentyFiveCents, Coin::TenCents],
            $result->changeCoins,
        );
        self::assertSame(0, $handlers['repository']->get()->product(ProductSelector::Water)->stock());
        self::assertTrue(
            $handlers['repository']->get()->insertedBalance()->equals(Money::fromCents(0)),
        );
    }

    /**
     * @return array{
     *     repository: InMemoryVendingMachineRepository,
     *     insertCoin: InsertCoinHandler,
     *     returnCoins: ReturnCoinsHandler,
     *     purchaseProduct: PurchaseProductHandler,
     *     serviceMachine: ServiceMachineHandler,
     * }
     */
    private function handlers(): array
    {
        $repository = new InMemoryVendingMachineRepository(
            VendingMachine::create(
                [
                    new Product(ProductSelector::Water, Money::fromCents(65), 0),
                    new Product(ProductSelector::Juice, Money::fromCents(100), 0),
                    new Product(ProductSelector::Soda, Money::fromCents(150), 0),
                ],
                CoinInventory::empty(),
            ),
        );

        return [
            'repository' => $repository,
            'insertCoin' => new InsertCoinHandler($repository),
            'returnCoins' => new ReturnCoinsHandler($repository),
            'purchaseProduct' => new PurchaseProductHandler($repository),
            'serviceMachine' => new ServiceMachineHandler($repository),
        ];
    }
}
