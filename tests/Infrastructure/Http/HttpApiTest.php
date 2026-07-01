<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Http;

use App\Application\InsertCoin\InsertCoinHandler;
use App\Application\PurchaseProduct\PurchaseProductHandler;
use App\Application\ReturnCoins\ReturnCoinsHandler;
use App\Application\ServiceMachine\ServiceMachineHandler;
use App\Domain\Coin;
use App\Infrastructure\Http\Controllers\CoinController;
use App\Infrastructure\Http\Controllers\PurchaseController;
use App\Infrastructure\Http\Controllers\ReturnController;
use App\Infrastructure\Http\Controllers\ServiceController;
use App\Infrastructure\Http\Controllers\StateController;
use App\Infrastructure\Http\ExceptionToHttpMapper;
use App\Infrastructure\Http\Router;
use App\Infrastructure\Persistence\InMemoryVendingMachineRepository;
use PHPUnit\Framework\TestCase;

final class HttpApiTest extends TestCase
{
    public function testRouterReturns404ForUnknownRoute(): void
    {
        $router = new Router();

        $response = $router->dispatch('GET', '/missing');

        self::assertSame(404, $response->status);
        self::assertSame('Not found.', $response->body['error']);
    }

    public function testInsertCoinPurchaseAndReturnFlow(): void
    {
        $repository = new InMemoryVendingMachineRepository();
        $exceptionMapper = new ExceptionToHttpMapper();

        $coinController = new CoinController(
            new InsertCoinHandler($repository),
            $repository,
            $exceptionMapper,
        );
        $purchaseController = new PurchaseController(
            new PurchaseProductHandler($repository),
            $repository,
            $exceptionMapper,
        );
        $returnController = new ReturnController(
            new ReturnCoinsHandler($repository),
            $repository,
            $exceptionMapper,
        );
        $stateController = new StateController($repository, $exceptionMapper);

        $insertResponse = $coinController('{"coin":100}');
        self::assertSame(200, $insertResponse->status);
        self::assertSame(100, $insertResponse->body['balance']);

        $purchaseResponse = $purchaseController('{"product":"WATER"}');
        self::assertSame(200, $purchaseResponse->status);
        self::assertSame('WATER', $purchaseResponse->body['product']);
        self::assertEqualsCanonicalizing([25, 10], $purchaseResponse->body['change']);
        self::assertSame(0, $purchaseResponse->body['balance']);

        $stateResponse = $stateController();
        self::assertSame(200, $stateResponse->status);
        /** @var array<string, array{price: int, stock: int}> $products */
        $products = $stateResponse->body['products'];
        self::assertSame(4, $products['WATER']['stock']);

        $returnResponse = $returnController();
        self::assertSame(200, $returnResponse->status);
        self::assertSame([], $returnResponse->body['coins']);
    }

    public function testInsufficientFundsReturns400(): void
    {
        $repository = new InMemoryVendingMachineRepository();
        $exceptionMapper = new ExceptionToHttpMapper();
        $purchaseController = new PurchaseController(
            new PurchaseProductHandler($repository),
            $repository,
            $exceptionMapper,
        );

        $response = $purchaseController('{"product":"SODA"}');

        self::assertSame(400, $response->status);
        self::assertArrayHasKey('error', $response->body);
    }

    public function testServiceEndpointRestocksMachine(): void
    {
        $repository = new InMemoryVendingMachineRepository();
        $exceptionMapper = new ExceptionToHttpMapper();

        $serviceController = new ServiceController(
            new ServiceMachineHandler($repository),
            $exceptionMapper,
        );
        $stateController = new StateController($repository, $exceptionMapper);

        $response = $serviceController(
            '{"products":{"WATER":12},"change":{"25":3,"10":1,"5":0}}'
        );

        self::assertSame(200, $response->status);

        $state = $stateController();
        /** @var array<string, array{price: int, stock: int}> $products */
        $products = $state->body['products'];
        /** @var array<int, int> $change */
        $change = $state->body['change'];
        self::assertSame(12, $products['WATER']['stock']);
        self::assertSame(3, $change[Coin::TwentyFiveCents->value]);
        self::assertSame(1, $change[Coin::TenCents->value]);
    }
}
