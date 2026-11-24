<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Tiny lazy service container. Each binding is a factory resolved once per request.
 */
final class Container
{
    /** @var array<string, callable> */
    private array $bindings = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /**
     * @param array<string, callable> $bindings
     */
    public function __construct(array $bindings = [])
    {
        foreach ($bindings as $id => $factory) {
            $this->set($id, $factory);
        }
    }

    public function set(string $id, callable $factory): void
    {
        $this->bindings[$id] = $factory;
        unset($this->instances[$id]);
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]);
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (!isset($this->bindings[$id])) {
            throw new RuntimeException(sprintf('Service "%s" is not registered.', $id));
        }

        $instance = ($this->bindings[$id])($this);
        $this->instances[$id] = $instance;

        return $instance;
    }
}
