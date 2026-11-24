<?php
declare(strict_types=1);

namespace App\Core\Auth;

use App\Core\Contracts\ConnectionInterface;
use App\Core\Session;

/**
 * Password based authentication against a users-like table.
 *
 * The table name is an internal constant (never request input) and is validated
 * to keep SQL identifiers safe.
 */
final class Auth
{
    /**
     * Constant bcrypt hash used to keep response timing similar for unknown accounts.
     */
    private const DUMMY_HASH = '$2y$12$C6UzMDM.H6dfI/f/IKcEe.7JqcKk8dWcC0hCr4xVxbYb3dR0dQw9a';

    public function __construct(
        private ConnectionInterface $db,
        private string $table,
        private string $sessionKey
    ) {
        if (!preg_match('/^[a-z0-9_]+$/', $table)) {
            throw new \InvalidArgumentException('Nama tabel autentikasi tidak valid.');
        }
    }

    /**
     * @return array<string, mixed>|null Authenticated user row, or null on failure.
     */
    public function attempt(string $email, string $password, string $ip = ''): ?array
    {
        $email = strtolower(trim($email));
        if ($email === '' || $password === '') {
            return null;
        }

        $user = $this->db->selectOne(
            'SELECT * FROM ' . $this->table . ' WHERE email = ? LIMIT 1',
            [$email]
        );

        if ($user === null || !(int) ($user['is_active'] ?? 0)) {
            password_verify($password, self::DUMMY_HASH);
            return null;
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            return null;
        }

        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            $this->db->execute(
                'UPDATE ' . $this->table . ' SET password_hash = ? WHERE id = ?',
                [password_hash($password, PASSWORD_DEFAULT), (int) $user['id']]
            );
        }

        $this->db->execute(
            'UPDATE ' . $this->table . ' SET last_login_at = ? WHERE id = ?',
            [date('Y-m-d H:i:s'), (int) $user['id']]
        );

        Session::regenerate();
        Session::put($this->sessionKey, (int) $user['id']);

        unset($user['password_hash']);

        return $user;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function user(): ?array
    {
        $id = Session::get($this->sessionKey);
        if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
            return null;
        }

        $user = $this->db->selectOne(
            'SELECT * FROM ' . $this->table . ' WHERE id = ? AND is_active = 1 LIMIT 1',
            [(int) $id]
        );

        if ($user === null) {
            Session::forget($this->sessionKey);
            return null;
        }

        unset($user['password_hash']);

        return $user;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function logout(): void
    {
        Session::forget($this->sessionKey);
        Session::regenerate();
    }
}
