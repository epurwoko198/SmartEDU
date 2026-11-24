<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Minimal environment/configuration loader.
 * Values are read from the process environment first, then from .env.
 */
final class Config
{
    /** @var array<string, string> */
    private static array $values = [];

    public static function load(string $file): void
    {
        if (!is_file($file) || !is_readable($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if ($key === '') {
                continue;
            }

            $length = strlen($value);
            if ($length >= 2) {
                $first = $value[0];
                $last = $value[$length - 1];
                if (($first === '"' || $first === "'") && $first === $last) {
                    $value = substr($value, 1, -1);
                }
            }

            self::$values[$key] = $value;
        }
    }

    public static function set(string $key, ?string $value): void
    {
        if ($value === null) {
            unset(self::$values[$key]);
            return;
        }

        self::$values[$key] = $value;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $fromEnv = getenv($key);
        if ($fromEnv !== false && $fromEnv !== '') {
            return $fromEnv;
        }

        return self::$values[$key] ?? $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);
        if ($value === null || $value === '' || !is_numeric($value)) {
            return $default;
        }

        return (int) $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null || $value === '') {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }
}
