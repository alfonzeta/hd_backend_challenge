<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

/**
 * Minimal method + path router. Maps incoming requests to controller callables
 * that return a {@see JsonResponse}.
 */
final class Router
{
    /** @var array<string, array<string, callable(): JsonResponse>> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function dispatch(string $method, string $path): JsonResponse
    {
        $normalizedPath = $this->normalizePath($path);

        if (!isset($this->routes[$method][$normalizedPath])) {
            return JsonResponse::fromError(new HttpError(404, 'Not found.'));
        }

        return ($this->routes[$method][$normalizedPath])();
    }

    private function add(string $method, string $path, callable $handler): void
    {
        $this->routes[$method][$this->normalizePath($path)] = $handler;
    }

    private function normalizePath(string $path): string
    {
        $trimmed = rtrim($path, '/');

        return $trimmed === '' ? '/' : $trimmed;
    }
}
