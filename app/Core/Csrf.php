<?php
declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        $token = Session::get(self::SESSION_KEY);
        if (!is_string($token) || strlen($token) < 32) {
            $token = bin2hex(random_bytes(32));
            Session::put(self::SESSION_KEY, $token);
        }

        return $token;
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars(self::token(), ENT_QUOTES) . '">';
    }

    public static function validate(?string $token): bool
    {
        $expected = Session::get(self::SESSION_KEY);
        if (!is_string($expected) || $expected === '' || !is_string($token) || $token === '') {
            return false;
        }

        return hash_equals($expected, $token);
    }

    /**
     * Verify the token of an incoming request, throwing on failure.
     */
    public static function verify(Request $request): void
    {
        if (!self::validate($request->input['_token'] ?? null)) {
            throw new Exceptions\ForbiddenHttpException('Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.');
        }
    }
}
