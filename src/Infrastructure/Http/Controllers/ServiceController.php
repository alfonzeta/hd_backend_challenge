<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\ServiceMachine\ServiceMachineCommand;
use App\Application\ServiceMachine\ServiceMachineHandler;
use App\Domain\CoinInventory;
use App\Infrastructure\Http\ExceptionToHttpMapper;
use App\Infrastructure\Http\JsonResponse;
use App\Infrastructure\Http\RequestBody;
use InvalidArgumentException;
use Throwable;

final class ServiceController
{
    public function __construct(
        private ServiceMachineHandler $handler,
        private ExceptionToHttpMapper $exceptionMapper,
    ) {
    }

    public function __invoke(?string $rawBody = null): JsonResponse
    {
        try {
            $body = RequestBody::decode($rawBody);

            ($this->handler)(new ServiceMachineCommand(
                $this->parseProductStock($body),
                CoinInventory::fromCounts($this->parseChange($body)),
            ));

            return JsonResponse::ok(['status' => 'ok']);
        } catch (Throwable $throwable) {
            return JsonResponse::fromError($this->exceptionMapper->map($throwable));
        }
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, int>
     */
    private function parseProductStock(array $body): array
    {
        if (!isset($body['products']) || !is_array($body['products'])) {
            throw new InvalidArgumentException('Field "products" (object) is required.');
        }

        $stock = [];

        foreach ($body['products'] as $selector => $count) {
            if (!is_string($selector) || !is_int($count)) {
                throw new InvalidArgumentException(
                    'Each product entry must map a selector string to an integer stock count.'
                );
            }

            $stock[$selector] = $count;
        }

        return $stock;
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<int, int>
     */
    private function parseChange(array $body): array
    {
        if (!isset($body['change']) || !is_array($body['change'])) {
            throw new InvalidArgumentException('Field "change" (object) is required.');
        }

        $counts = [];

        foreach ($body['change'] as $denomination => $count) {
            if (!is_int($denomination) && !is_numeric($denomination)) {
                throw new InvalidArgumentException(
                    'Change keys must be coin denominations in cents.'
                );
            }

            if (!is_int($count)) {
                throw new InvalidArgumentException(
                    'Each change entry must map a denomination to an integer count.'
                );
            }

            $counts[(int) $denomination] = $count;
        }

        return $counts;
    }
}
