<?php
declare(strict_types=1);

namespace App\Core\Contracts;

interface ConnectionInterface
{
    /**
     * @param array<int|string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function select(string $sql, array $params = []): array;

    /**
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function selectOne(string $sql, array $params = []): ?array;

    /**
     * Run a statement and return the number of affected rows.
     *
     * @param array<int|string, mixed> $params
     */
    public function execute(string $sql, array $params = []): int;

    /**
     * Run an INSERT and return the generated identifier.
     *
     * @param array<int|string, mixed> $params
     */
    public function insert(string $sql, array $params = []): int;

    /**
     * @template T
     * @param callable(self): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed;

    public function name(): string;
}
