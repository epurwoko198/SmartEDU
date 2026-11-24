<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\NotFoundHttpException;
use InvalidArgumentException;

final class Router
{
    /** @var list<array{method: string, pattern: string, regex: string, handler: callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function add(string $method, string $pattern, callable $handler): void
    {
        $method = strtoupper($method);
        if (!in_array($method, ['GET', 'POST'], true)) {
            throw new InvalidArgumentException('Only GET and POST routes are supported.');
        }

        $pattern = Request::normalizePath($pattern);
        $escaped = preg_quote($pattern, '#');
        $regex = preg_replace(
            '#\\\\\{([a-zA-Z_][a-zA-Z0-9_]*)\\\\\}#',
            '(?P<$1>[^/]+)',
            $escaped
        );

        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'regex' => '#^' . ($regex ?? $escaped) . '$#',
            'handler' => $handler,
        ];
    }

    /**
     * @return list<array{method: string, pattern: string}>
     */
    public function routes(): array
    {
        return array_map(
            static fn (array $route): array => ['method' => $route['method'], 'pattern' => $route['pattern']],
            $this->routes
        );
    }

    /**
     * @param array<string, mixed> $server
     */
    public function dispatch(string $method, string $path, array $server = []): Response
    {
        $method = strtoupper($method);
        $path = Request::normalizePath($path);

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }

            $response = ($route['handler'])($params, $server);
            if (!$response instanceof Response) {
                throw new InvalidArgumentException('Route handlers must return a Response instance.');
            }

            return $response;
        }

        throw new NotFoundHttpException('Route not found.');
    }
}
