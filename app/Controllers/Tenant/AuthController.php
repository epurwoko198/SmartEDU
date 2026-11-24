<?php
declare(strict_types=1);

namespace App\Controllers\Tenant;

use App\Controllers\Controller;
use App\Core\Audit\AuditLog;
use App\Core\Auth\TenantAuth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use Throwable;

final class AuthController extends Controller
{
    /**
     * @param array<string, mixed> $tenant
     */
    public function __construct(
        \App\Core\Container $container,
        private array $tenant
    ) {
        parent::__construct($container);
    }

    public function showLogin(): Response
    {
        $databaseOffline = false;

        try {
            $db = $this->container->get('tenant.connector')->connect($this->tenant);
            $auth = new TenantAuth($db, $this->tenant);

            if ($auth->check()) {
                return Response::redirect($this->dashboardUrl());
            }
        } catch (Throwable) {
            // A database outage must not turn the school login page into a 500 page.
            $databaseOffline = true;
        }

        return $this->render('tenant/login', [
            'title' => 'Masuk ' . $this->tenant['name'],
            'tenant' => $this->tenant,
            'database_offline' => $databaseOffline,
        ], 'layouts/auth');
    }

    public function login(Request $request): Response
    {
        $this->csrf($request);

        $email = (string) $request->input('email', '');
        $password = (string) ($request->input['password'] ?? '');

        if ($email === '' || $password === '') {
            Session::flash('danger', 'Email dan kata sandi wajib diisi.');
            Session::flashOld(['email' => $email]);

            return Response::redirect($this->loginUrl());
        }

        try {
            $db = $this->container->get('tenant.connector')->connect($this->tenant);
            $auth = new TenantAuth($db, $this->tenant);
            $user = $auth->attempt($email, $password, $request->ip);
        } catch (Throwable) {
            Session::flash('danger', 'Database sekolah sedang tidak tersedia. Login belum dapat diproses.');
            Session::flashOld(['email' => $email]);

            return Response::redirect($this->loginUrl());
        }

        if ($user === null) {
            $this->audit($db, $request, [
                'actor_type' => 'tenant_user',
                'action' => 'auth.login.failed',
                'metadata' => ['email' => $email, 'tenant_slug' => $this->tenant['slug']],
            ]);
            Session::flash('danger', 'Email atau kata sandi tidak cocok, atau percobaan login terlalu sering.');
            Session::flashOld(['email' => $email]);

            return Response::redirect($this->loginUrl());
        }

        $this->audit($db, $request, [
            'actor_type' => 'tenant_user',
            'actor_id' => (int) $user['id'],
            'action' => 'auth.login',
            'metadata' => ['tenant_slug' => $this->tenant['slug']],
        ]);

        Session::flash('success', 'Selamat datang, ' . $user['name'] . '.');

        return Response::redirect($this->dashboardUrl());
    }

    public function logout(Request $request): Response
    {
        $this->csrf($request);

        $db = $this->container->get('tenant.connector')->connect($this->tenant);
        $auth = new TenantAuth($db, $this->tenant);
        $user = $auth->user();

        if ($user !== null) {
            $this->audit($db, $request, [
                'actor_type' => 'tenant_user',
                'actor_id' => (int) $user['id'],
                'action' => 'auth.logout',
            ]);
        }

        $auth->logout();

        return Response::redirect($this->loginUrl());
    }

    private function loginUrl(): string
    {
        return app_url('/t/' . rawurlencode((string) $this->tenant['slug']) . '/login');
    }

    private function dashboardUrl(): string
    {
        return app_url('/t/' . rawurlencode((string) $this->tenant['slug']) . '/dashboard');
    }

    /**
     * @param array<string, mixed> $event
     */
    private function audit(\App\Core\Contracts\ConnectionInterface $db, Request $request, array $event): void
    {
        try {
            (new AuditLog($db, false))->record($event + [
                'ip' => $request->ip,
                'user_agent' => $request->header('User-Agent'),
            ]);
        } catch (Throwable) {
            // Audit failures never block authentication.
        }
    }
}
