<?php
declare(strict_types=1);

namespace App\Core\Auth;

use App\Core\Contracts\ConnectionInterface;

/**
 * Tenant scoped login for school users.
 */
final class TenantAuth
{
    private Auth $auth;
    private LoginThrottle $throttle;

    /**
     * @param array<string, mixed> $tenant
     */
    public function __construct(
        private ConnectionInterface $db,
        private array $tenant
    ) {
        $this->auth = new Auth($db, 'users', 'tenant_user_id');
        $this->throttle = new LoginThrottle($db);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function attempt(string $email, string $password, string $ip = ''): ?array
    {
        $email = strtolower(trim($email));

        if ($this->throttle->tooManyAttempts($email, $ip)) {
            return null;
        }

        $user = $this->auth->attempt($email, $password, $ip);
        $this->throttle->record($email, $ip, $user !== null);

        if ($user === null) {
            return null;
        }

        if ($user !== null) {
            $this->throttle->clear($email, $ip);
            \App\Core\Session::put('tenant_id', (int) $this->tenant['id']);
            \App\Core\Session::put('tenant_slug', (string) $this->tenant['slug']);
        }

        return $user;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function user(): ?array
    {
        if ((int) \App\Core\Session::get('tenant_id', 0) !== (int) $this->tenant['id']) {
            return null;
        }

        return $this->auth->user();
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function logout(): void
    {
        $this->auth->logout();
        \App\Core\Session::forget('tenant_id');
        \App\Core\Session::forget('tenant_slug');
    }

    public function throttle(): LoginThrottle
    {
        return $this->throttle;
    }
}
