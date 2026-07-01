<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\ReturnCoins\ReturnCoinsCommand;
use App\Application\ReturnCoins\ReturnCoinsHandler;
use App\Application\Port\VendingMachineRepository;
use App\Infrastructure\Http\CoinSerializer;
use App\Infrastructure\Http\ExceptionToHttpMapper;
use App\Infrastructure\Http\JsonResponse;
use Throwable;

final class ReturnController
{
    public function __construct(
        private ReturnCoinsHandler $handler,
        private VendingMachineRepository $repository,
        private ExceptionToHttpMapper $exceptionMapper,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        try {
            $returned = ($this->handler)(new ReturnCoinsCommand());

            return JsonResponse::ok([
                'coins' => CoinSerializer::toCents($returned),
                'balance' => $this->repository->get()->insertedBalance()->cents,
            ]);
        } catch (Throwable $throwable) {
            return JsonResponse::fromError($this->exceptionMapper->map($throwable));
        }
    }
}
