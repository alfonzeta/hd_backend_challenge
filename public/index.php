<?php

declare(strict_types=1);

use App\Application\InsertCoin\InsertCoinHandler;
use App\Application\PurchaseProduct\PurchaseProductHandler;
use App\Application\ReturnCoins\ReturnCoinsHandler;
use App\Application\ServiceMachine\ServiceMachineHandler;
use App\Infrastructure\Http\Controllers\CoinController;
use App\Infrastructure\Http\Controllers\PurchaseController;
use App\Infrastructure\Http\Controllers\ReturnController;
use App\Infrastructure\Http\Controllers\ServiceController;
use App\Infrastructure\Http\Controllers\StateController;
use App\Infrastructure\Http\ExceptionToHttpMapper;
use App\Infrastructure\Http\Router;
use App\Infrastructure\Persistence\InMemoryVendingMachineRepository;

require dirname(__DIR__) . '/vendor/autoload.php';

$repository = new InMemoryVendingMachineRepository();
$exceptionMapper = new ExceptionToHttpMapper();

$coinController = new CoinController(
    new InsertCoinHandler($repository),
    $repository,
    $exceptionMapper,
);
$returnController = new ReturnController(
    new ReturnCoinsHandler($repository),
    $repository,
    $exceptionMapper,
);
$purchaseController = new PurchaseController(
    new PurchaseProductHandler($repository),
    $repository,
    $exceptionMapper,
);
$serviceController = new ServiceController(
    new ServiceMachineHandler($repository),
    $exceptionMapper,
);
$stateController = new StateController($repository, $exceptionMapper);

$router = new Router();
$router->post('/coins', $coinController(...));
$router->post('/return', $returnController(...));
$router->post('/purchase', $purchaseController(...));
$router->post('/service', $serviceController(...));
$router->get('/state', $stateController(...));

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$router->dispatch($method, $path)->emit();
