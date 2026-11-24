<?php
declare(strict_types=1);

/**
 * Dependency-free smoke tests (no Composer/PHPUnit/MySQL required):
 *   php scripts/selftest.php
 *
 * Covers routing, tenant slug/database naming, RBAC, license evaluation,
 * CSRF and migration file integrity.
 */

use App\Core\Auth\Rbac;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Exceptions\NotFoundHttpException;
use App\Core\License\LicenseEngine;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\TenantResolver;
use App\Services\MigrationRunner;
use Tests\Fixtures\InMemoryConnection;

require __DIR__ . '/../app/bootstrap.php';

$failures = 0;
$checks = 0;

function check(string $name, callable $test): void
{
    global $failures, $checks;
    $checks++;

    try {
        $result = $test();
        if ($result === false) {
            throw new RuntimeException('assertion returned false');
        }
        echo "  \033[32mPASS\033[0m {$name}\n";
    } catch (Throwable $throwable) {
        $failures++;
        echo "  \033[31mFAIL\033[0m {$name} — {$throwable->getMessage()}\n";
    }
}

function assertSame(mixed $expected, mixed $actual, string $context = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf(
            '%sexpected %s, got %s',
            $context === '' ? '' : $context . ': ',
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
}

function assertTrue(mixed $value, string $context = ''): void
{
    assertSame(true, (bool) $value, $context);
}

function assertFalse(mixed $value, string $context = ''): void
{
    assertSame(false, (bool) $value, $context);
}

echo "EDUSMART ERP selftest\n\n";

echo "Router\n";
$router = new Router();
$router->get('/health', static fn (): Response => Response::json(['ok' => true]));
$router->get('/t/{slug}/dashboard', static fn (array $params): Response => Response::html('tenant:' . $params['slug']));
$router->post('/t/{slug}/login', static fn (array $params): Response => Response::html('login:' . $params['slug']));

check('static route resolves', function () use ($router): void {
    $response = $router->dispatch('GET', '/health');
    assertSame(200, $response->status);
    assertSame('application/json; charset=UTF-8', $response->header('Content-Type'));
});

check('path parameter is captured', function () use ($router): void {
    $response = $router->dispatch('GET', '/t/smpn-1/dashboard');
    assertSame('tenant:smpn-1', $response->body);
});

check('trailing slash is normalized', function () use ($router): void {
    $response = $router->dispatch('GET', '/t/smk-demo/dashboard/');
    assertSame('tenant:smk-demo', $response->body);
});

check('method mismatch yields 404', function () use ($router): void {
    try {
        $router->dispatch('GET', '/t/demo/login');
    } catch (NotFoundHttpException) {
        return;
    }
    throw new RuntimeException('expected NotFoundHttpException');
});

check('unknown route yields 404', function () use ($router): void {
    try {
        $router->dispatch('GET', '/tidak-ada');
    } catch (NotFoundHttpException) {
        return;
    }
    throw new RuntimeException('expected NotFoundHttpException');
});

echo "\nTenantResolver\n";
$core = new InMemoryConnection('edusmart_core');
$resolver = new TenantResolver($core, 'edusmart_t_');

check('valid slugs accepted', function () use ($resolver): void {
    foreach (['demo', 'smpn-1', 'a1', 'sekolah-abc-123'] as $slug) {
        assertTrue($resolver->isValidSlug($slug), $slug);
    }
});

check('invalid and reserved slugs rejected', function () use ($resolver, $core): void {
    foreach (['', 'Demo', 'a', '-demo', 'demo-', 'demo_x', 'demo x', 'www', 'saas', 't'] as $slug) {
        assertFalse($resolver->isValidSlug($slug), var_export($slug, true));
        assertSame(null, $resolver->findBySlug($slug));
    }
    assertSame([], $core->log, 'invalid slug must not query the database');
});

check('database name derives from slug, not input', function () use ($resolver): void {
    assertSame('edusmart_t_smpn_1', $resolver->databaseNameFor('smpn-1'));

    try {
        $resolver->databaseNameFor('evil; DROP DATABASE x');
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException('expected InvalidArgumentException');
});

check('findBySlug binds the slug as a parameter', function () use ($resolver, $core): void {
    $core->selectRows = [['id' => 1, 'slug' => 'demo', 'status' => 'active']];
    $tenant = $resolver->findBySlug('demo');
    assertSame('demo', $tenant['slug'] ?? null);
    assertTrue(str_contains((string) $core->lastSql(), 'WHERE slug = ?'));
    assertSame(['demo'], $core->log[count($core->log) - 1]['params']);
});

echo "\nRBAC\n";
check('exact permission allowed', function (): void {
    assertTrue(Rbac::allows(['students.view', 'finance.view'], 'students.view'));
});

check('unknown permission denied', function (): void {
    assertFalse(Rbac::allows(['students.view'], 'finance.manage'));
});

check('resource wildcard allowed', function (): void {
    assertTrue(Rbac::allows(['students.*'], 'students.create'));
});

check('super admin wildcard allowed', function (): void {
    assertTrue(Rbac::allows(['*'], 'finance.payment.post'));
});

check('allowsAny checks alternatives', function (): void {
    assertTrue(Rbac::allowsAny(['letters.approve'], ['finance.view', 'letters.approve']));
    assertFalse(Rbac::allowsAny(['letters.view'], ['finance.view', 'letters.approve']));
});

check('permissions resolve from role joins', function (): void {
    $db = new InMemoryConnection();
    $db->selectRows = [['code' => 'students.view'], ['code' => 'finance.payment.post']];
    $rbac = new Rbac($db);
    $permissions = $rbac->permissionsForUser(7);
    assertSame(['students.view', 'finance.payment.post'], $permissions);
    assertSame($permissions, $rbac->permissionsForUser(7), 'cached');
    assertSame(1, count($db->log), 'second call must use cache');
    assertSame([7], $db->log[0]['params']);
});

echo "\nLicense engine\n";
$engine = new LicenseEngine();
$now = strtotime('2026-11-23 10:00:00');

check('trial license is writable with days left', function () use ($engine, $now): void {
    $evaluation = $engine->evaluate([
        'status' => 'trial',
        'starts_at' => '2026-11-01 00:00:00',
        'ends_at' => '2026-12-01 00:00:00',
        'grace_days' => 14,
        'features' => json_encode(['ppdb', 'academic']),
        'max_students' => 500,
    ], $now);

    assertSame('trial', $evaluation['status']);
    assertFalse($evaluation['read_only']);
    assertTrue($evaluation['days_left'] > 0);
    assertSame(['ppdb', 'academic'], $evaluation['features']);
});

check('active license reports active', function () use ($engine, $now): void {
    $evaluation = $engine->evaluate([
        'status' => 'active',
        'ends_at' => '2027-11-23 00:00:00',
        'grace_days' => 14,
        'features' => '["*"]',
    ], $now);
    assertSame('active', $evaluation['status']);
    assertTrue($engine->hasFeature($evaluation, 'finance'));
});

check('expired license inside grace period stays writable', function () use ($engine, $now): void {
    $evaluation = $engine->evaluate([
        'status' => 'active',
        'ends_at' => '2026-11-20 00:00:00',
        'grace_days' => 14,
    ], $now);
    assertSame('grace_period', $evaluation['status']);
    assertFalse($evaluation['read_only']);
    assertFalse($evaluation['blocked']);
});

check('license beyond grace period is read-only and blocked', function () use ($engine, $now): void {
    $evaluation = $engine->evaluate([
        'status' => 'active',
        'ends_at' => '2026-10-01 00:00:00',
        'grace_days' => 14,
    ], $now);
    assertSame('expired', $evaluation['status']);
    assertTrue($evaluation['read_only']);
    assertTrue($evaluation['blocked']);
});

check('suspended license is blocked', function () use ($engine, $now): void {
    $evaluation = $engine->evaluate([
        'status' => 'suspended',
        'ends_at' => '2027-01-01 00:00:00',
        'grace_days' => 14,
    ], $now);
    assertSame('suspended', $evaluation['status']);
    assertTrue($evaluation['blocked']);
});

check('missing license is unlicensed', function () use ($engine): void {
    $evaluation = $engine->evaluate(null);
    assertSame('unlicensed', $evaluation['status']);
    assertTrue($evaluation['blocked']);
});

check('license without end date never expires', function () use ($engine, $now): void {
    $evaluation = $engine->evaluate(['status' => 'active', 'ends_at' => null], $now);
    assertSame('active', $evaluation['status']);
    assertFalse($evaluation['blocked']);
});

echo "\nCSRF\n";
check('token validates and rejects tampering', function (): void {
    Session::put('_csrf_token', null);
    $token = Csrf::token();
    assertTrue(strlen($token) >= 32);
    assertSame($token, Csrf::token(), 'token is stable inside a session');
    assertTrue(Csrf::validate($token));
    assertFalse(Csrf::validate($token . 'x'));
    assertFalse(Csrf::validate(null));
    assertTrue(str_contains(Csrf::field(), 'name="_token"'));
});

echo "\nMigrations\n";
check('core migration files return SQL statement lists', function (): void {
    $runner = new MigrationRunner(__DIR__ . '/../database/migrations/core');
    $files = $runner->migrationFiles();
    assertTrue(count($files) >= 1);

    foreach ($files as $name => $path) {
        $statements = require $path;
        assertTrue(is_array($statements), $name);
        assertTrue(count($statements) > 0, $name);
        foreach ($statements as $statement) {
            assertTrue(is_string($statement) && trim($statement) !== '', $name);
        }
    }
});

check('tenant migration files return SQL statement lists', function (): void {
    $runner = new MigrationRunner(__DIR__ . '/../database/migrations/tenant');
    $files = $runner->migrationFiles();
    assertTrue(count($files) >= 1);

    foreach ($files as $name => $path) {
        $statements = require $path;
        assertTrue(is_array($statements), $name);
        foreach ($statements as $statement) {
            assertTrue(is_string($statement) && trim($statement) !== '', $name);
        }
    }
});

check('runner skips already applied migrations', function (): void {
    $db = new InMemoryConnection();
    $db->selectRows = [['migration' => '2026_11_14_000001_create_platform_schema']];
    $runner = new MigrationRunner(__DIR__ . '/../database/migrations/core');

    $applied = $runner->run($db);
    assertSame([], $applied);
});

echo "\nRoles seed\n";
check('seed catalog has roles and permissions', function (): void {
    $catalog = require __DIR__ . '/../database/seeds/roles_permissions.php';
    assertTrue(count($catalog['permissions']) >= 30);
    assertTrue(isset($catalog['roles']['school_super_admin']));
    assertSame(['*'], $catalog['roles']['school_super_admin']['permissions']);
    assertTrue(isset($catalog['roles']['teacher']['permissions']));
});

echo "\nConfig\n";
check('env values parse with defaults', function (): void {
    Config::set('EDUSMART_TEST_VALUE', 'abc');
    assertSame('abc', Config::get('EDUSMART_TEST_VALUE'));
    assertSame('fallback', Config::get('EDUSMART_MISSING_VALUE', 'fallback'));
    assertSame(5, Config::int('EDUSMART_MISSING_INT', 5));
    assertTrue(Config::bool('EDUSMART_MISSING_BOOL', true));
    Config::set('EDUSMART_TEST_VALUE', null);
});

echo "\n";
if ($failures > 0) {
    echo "\033[31m{$failures} dari {$checks} pemeriksaan gagal.\033[0m\n";
    exit(1);
}

echo "\033[32mSemua {$checks} pemeriksaan lulus.\033[0m\n";
exit(0);
