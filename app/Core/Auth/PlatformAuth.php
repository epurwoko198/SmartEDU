<?php
declare(strict_types=1);

namespace App\Core\Auth;

use App\Core\Contracts\ConnectionInterface;
use App\Core\Exceptions\ForbiddenHttpException;
use App\Core\Session;

/**
 * Platform (SaaS) authentication for super admin and platform staff.
 */
final class PlatformAuth
{
    private const ROLE_PERMISSIONS = [
        'super_admin' => ['*'],
        'support' => ['tenants.view', 'tenants.impersonate', 'audit.view'],
        'finance' => ['tenants.view', 'billing.view', 'billing.manage'],
    ];

    private Auth $auth;

    public function __construct(private ConnectionInterface $core)
    {
        $this->auth = new Auth($core, 'platform_users', 'saas_user_id');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function attempt(string $email, string $password, string $ip = ''): ?array
    {
        $email = strtolower(trim($email));
        $throttle = new LoginThrottle($this->core);

        if ($throttle->tooManyAttempts($email, $ip)) {
            return null;
        }

        $user = $this->auth->attempt($email, $password, $ip);
        $throttle->record($email, $ip, $user !== null);

        if ($user !== null) {
            $throttle->clear($email, $ip);
        }

        return $user;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function user(): ?array
    {
        return $this->auth->user();
    }

    public function check(): bool
    {
        return $this->auth->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function requireUser(): array
    {
        $user = $this->user();
        if ($user === null) {
            throw new ForbiddenHttpException('Silakan masuk terlebih dahulu.');
        }

        return $user;
    }

    public function logout(): void
    {
        $this->auth->logout();
        Session::forget('saas_user_id');
    }

    /**
     * @param array<string, mixed> $user
     */
    public function can(array $user, string $permission): bool
    {
        $permissions = self::ROLE_PERMISSIONS[(string) ($user['role'] ?? '')] ?? [];

        return \App\Core\Auth\Rbac::allows($permissions, $permission);
    }
}
