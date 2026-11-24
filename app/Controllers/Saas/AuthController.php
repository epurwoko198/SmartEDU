<?php
declare(strict_types=1);

namespace App\Controllers\Saas;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use Throwable;

final class AuthController extends Controller
{
    public function showLogin(): Response
    {
        $databaseOffline = false;

        try {
            if ($this->container->get('platform.auth')->check()) {
                return $this->redirect('/saas');
            }
        } catch (Throwable) {
            // The database being down must not hide the login form.
            $databaseOffline = true;
        }

        return $this->render('saas/login', [
            'title' => 'Masuk Super Admin',
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

            return $this->redirect('/saas/login');
        }

        try {
            $auth = $this->container->get('platform.auth');
            $user = $auth->attempt($email, $password, $request->ip);
        } catch (Throwable) {
            Session::flash('danger', 'Database sedang tidak tersedia. Login belum dapat diproses.');
            Session::flashOld(['email' => $email]);

            return $this->redirect('/saas/login');
        }

        if ($user === null) {
            $this->audit($request, [
                'action' => 'auth.login.failed',
                'metadata' => ['email' => $email, 'area' => 'saas'],
            ]);
            Session::flash('danger', 'Email atau kata sandi tidak cocok, atau akun terkunci sementara.');
            Session::flashOld(['email' => $email]);

            return $this->redirect('/saas/login');
        }

        $this->audit($request, [
            'actor_type' => 'platform',
            'actor_id' => (int) $user['id'],
            'action' => 'auth.login',
            'metadata' => ['area' => 'saas'],
        ]);

        Session::flash('success', 'Selamat datang, ' . $user['name'] . '.');

        return $this->redirect('/saas');
    }

    public function logout(Request $request): Response
    {
        $this->csrf($request);

        try {
            $user = $this->container->get('platform.auth')->user();
            if ($user !== null) {
                $this->audit($request, [
                    'actor_type' => 'platform',
                    'actor_id' => (int) $user['id'],
                    'action' => 'auth.logout',
                ]);
            }

            $this->container->get('platform.auth')->logout();
        } catch (Throwable) {
            // Always end the local session, even when the database is unreachable.
            Session::forget('saas_user_id');
            Session::regenerate();
        }

        return $this->redirect('/saas/login');
    }

    /**
     * @param array<string, mixed> $event
     */
    private function audit(Request $request, array $event): void
    {
        try {
            $this->container->get('audit')->record($event + [
                'ip' => $request->ip,
                'user_agent' => $request->header('User-Agent'),
            ]);
        } catch (Throwable) {
            // Audit failures must never break authentication.
        }
    }
}
