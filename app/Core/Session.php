<?php
declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        session_name((string) Config::get('SESSION_NAME', 'edusmart_session'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => Config::bool('SESSION_SECURE_COOKIE', false),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = self::get($key, $default);
        self::forget($key);

        return $value;
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'] ?? '/',
                'domain' => $params['domain'] ?? '',
                'secure' => (bool) ($params['secure'] ?? false),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_destroy();
        }
    }

    public static function flash(string $type, string $message): void
    {
        $messages = self::get('_flash', []);
        if (!is_array($messages)) {
            $messages = [];
        }

        $messages[] = ['type' => $type, 'message' => $message];
        self::put('_flash', $messages);
    }

    /**
     * @return list<array{type: string, message: string}>
     */
    public static function flashBag(): array
    {
        $messages = self::pull('_flash', []);
        if (!is_array($messages)) {
            return [];
        }

        return array_values(array_filter($messages, static fn ($item): bool => is_array($item) && isset($item['message'])));
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function flashOld(array $input): void
    {
        unset($input['_token'], $input['password'], $input['password_confirmation']);
        self::put('_old', $input);
    }

    public static function old(string $key, string $default = ''): string
    {
        $old = self::get('_old', []);

        return is_array($old) && isset($old[$key]) ? (string) $old[$key] : $default;
    }
}
