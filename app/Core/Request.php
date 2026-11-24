<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $input
     * @param array<string, mixed> $server
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly string $host,
        public readonly string $ip,
        public readonly array $query = [],
        public readonly array $input = [],
        public readonly array $server = []
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? rawurldecode($path) : '/';

        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        if (str_contains($host, ':')) {
            $host = substr($host, 0, (int) strrpos($host, ':'));
        }

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            self::normalizePath($path),
            $host,
            (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'),
            $_GET,
            $_POST,
            $_SERVER
        );
    }

    public static function normalizePath(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return $path === '' ? '/' : $path;
    }

    public function input(string $key, ?string $default = null): ?string
    {
        $value = $this->input[$key] ?? null;
        if (is_array($value)) {
            return $default;
        }

        return $value === null ? $default : trim((string) $value);
    }

    public function queryParam(string $key, ?string $default = null): ?string
    {
        $value = $this->query[$key] ?? null;
        if (is_array($value)) {
            return $default;
        }

        return $value === null ? $default : trim((string) $value);
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        return isset($this->server[$key]) ? (string) $this->server[$key] : null;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }
}
