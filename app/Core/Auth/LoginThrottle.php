<?php
declare(strict_types=1);

namespace App\Core\Auth;

use App\Core\Contracts\ConnectionInterface;

/**
 * Brute-force protection: counts failed logins per email/IP inside a time window.
 */
final class LoginThrottle
{
    public function __construct(
        private ConnectionInterface $db,
        private int $maxAttempts = 5,
        private int $windowSeconds = 900,
        private string $table = 'login_attempts'
    ) {
        if (!preg_match('/^[a-z0-9_]+$/', $table)) {
            throw new \InvalidArgumentException('Nama tabel throttle tidak valid.');
        }
    }

    public function tooManyAttempts(string $email, string $ip, ?int $now = null): bool
    {
        $now ??= time();
        $rows = $this->db->select(
            'SELECT COUNT(*) AS total FROM ' . $this->table . '
             WHERE email = ? AND ip = ? AND success = 0 AND created_at >= ?',
            [strtolower(trim($email)), $ip, date('Y-m-d H:i:s', $now - $this->windowSeconds)]
        );

        return (int) ($rows[0]['total'] ?? 0) >= $this->maxAttempts;
    }

    public function record(string $email, string $ip, bool $success, ?int $now = null): void
    {
        $now ??= time();
        $this->db->insert(
            'INSERT INTO ' . $this->table . ' (email, ip, success, created_at) VALUES (?, ?, ?, ?)',
            [strtolower(trim($email)), $ip, $success ? 1 : 0, date('Y-m-d H:i:s', $now)]
        );
    }

    public function clear(string $email, string $ip): void
    {
        $this->db->execute(
            'DELETE FROM ' . $this->table . ' WHERE email = ? AND ip = ?',
            [strtolower(trim($email)), $ip]
        );
    }

    public function secondsRemaining(string $email, string $ip, ?int $now = null): int
    {
        $now ??= time();
        $row = $this->db->selectOne(
            'SELECT created_at FROM ' . $this->table . '
             WHERE email = ? AND ip = ? AND success = 0 ORDER BY created_at DESC LIMIT 1',
            [strtolower(trim($email)), $ip]
        );

        if ($row === null) {
            return 0;
        }

        $last = strtotime((string) $row['created_at']);
        if ($last === false) {
            return 0;
        }

        return max(0, ($last + $this->windowSeconds) - $now);
    }
}
