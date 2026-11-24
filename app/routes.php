<?php
declare(strict_types=1);

use App\Controllers\HomeController;
use App\Controllers\Saas\AuthController as SaasAuthController;
use App\Controllers\Saas\DashboardController as SaasDashboardController;
use App\Controllers\Tenant\AuthController as TenantAuthController;
use App\Controllers\Tenant\DashboardController as TenantDashboardController;
use App\Core\Container;
use App\Core\Exceptions\NotFoundHttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;

/**
 * Register all application routes.
 */
function registerRoutes(Router $router, Container $container): void
{
    $router->get('/', static fn (): Response => (new HomeController($container))->index());
    $router->get('/health', static fn (): Response => (new HomeController($container))->health());

    // SaaS platform (super admin).
    $router->get('/saas/login', static fn (): Response => (new SaasAuthController($container))->showLogin());
    $router->post('/saas/login', static fn (): Response => (new SaasAuthController($container))->login(Request::fromGlobals()));
    $router->post('/saas/logout', static fn (): Response => (new SaasAuthController($container))->logout(Request::fromGlobals()));

    $router->get('/saas', static fn (): Response => (new SaasDashboardController($container))->index());
    $router->get('/saas/tenants', static fn (): Response => (new SaasDashboardController($container))->tenants(Request::fromGlobals()));
    $router->get('/saas/tenants/create', static fn (): Response => (new SaasDashboardController($container))->create());
    $router->post('/saas/tenants', static fn (): Response => (new SaasDashboardController($container))->store(Request::fromGlobals()));
    $router->get('/saas/tenants/{id}', static fn (array $params): Response => (new SaasDashboardController($container))->show(Request::fromGlobals(), (string) $params['id']));
    $router->post('/saas/tenants/{id}/status', static fn (array $params): Response => (new SaasDashboardController($container))->setStatus(Request::fromGlobals(), (string) $params['id']));

    // Tenant (school) area, resolved from /t/{slug}.
    $withTenant = static function (callable $handler) use ($container): callable {
        return static function (array $params) use ($container, $handler): Response {
            $slug = strtolower((string) ($params['slug'] ?? ''));
            $tenant = $container->get('tenants')->findBySlug($slug);

            if ($tenant === null) {
                throw new NotFoundHttpException('Sekolah tidak ditemukan.');
            }

            return $handler($tenant);
        };
    };

    $router->get('/t/{slug}', $withTenant(static fn (array $tenant): Response => (new TenantDashboardController($container, $tenant))->entry()));
    $router->get('/t/{slug}/dashboard', $withTenant(static fn (array $tenant): Response => (new TenantDashboardController($container, $tenant))->index(Request::fromGlobals())));
    $router->get('/t/{slug}/license', $withTenant(static fn (array $tenant): Response => (new TenantDashboardController($container, $tenant))->license()));
    // The school login page stays available even when the databases are unreachable.
    $router->get('/t/{slug}/login', static function (array $params) use ($container): Response {
        $slug = strtolower((string) ($params['slug'] ?? ''));

        try {
            $tenant = $container->get('tenants')->findBySlug($slug);
        } catch (Throwable) {
            $tenant = ['slug' => $slug, 'name' => 'Portal Sekolah', 'level' => 'Sekolah'];
        }

        if ($tenant === null) {
            throw new NotFoundHttpException('Sekolah tidak ditemukan.');
        }

        return (new TenantAuthController($container, $tenant))->showLogin();
    });

    $router->post('/t/{slug}/login', static function (array $params) use ($container): Response {
        $slug = strtolower((string) ($params['slug'] ?? ''));

        try {
            $tenant = $container->get('tenants')->findBySlug($slug);
        } catch (Throwable) {
            Session::flash('danger', 'Database sekolah sedang tidak tersedia. Coba lagi beberapa saat lagi.');

            return Response::redirect(app_url('/t/' . rawurlencode($slug) . '/login'));
        }

        if ($tenant === null) {
            throw new NotFoundHttpException('Sekolah tidak ditemukan.');
        }

        return (new TenantAuthController($container, $tenant))->login(Request::fromGlobals());
    });
    $router->post('/t/{slug}/logout', $withTenant(static fn (array $tenant): Response => (new TenantAuthController($container, $tenant))->logout(Request::fromGlobals())));
}
