<?php
declare(strict_types=1);

use App\Core\Audit\AuditLog;
use App\Core\Auth\PlatformAuth;
use App\Core\Config;
use App\Core\Container;
use App\Core\Contracts\ConnectionInterface;
use App\Core\Database\MySqlConnection;
use App\Core\License\LicenseEngine;
use App\Core\Session;
use App\Core\Tenant\TenantConnector;
use App\Core\TenantResolver;
use App\Core\View;
use App\Services\MigrationRunner;
use App\Services\TenantProvisioner;
use App\Services\TenantSeeder;

if (!defined('EDUSMART_ROOT')) {
    define('EDUSMART_ROOT', dirname(__DIR__));
}

require EDUSMART_ROOT . '/app/Core/helpers.php';

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'App\\' => EDUSMART_ROOT . '/app/',
        'Tests\\' => EDUSMART_ROOT . '/tests/',
    ];

    foreach ($prefixes as $prefix => $basePath) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $relative = substr($class, strlen($prefix));
        $path = $basePath . str_replace('\\', '/', $relative) . '.php';

        if (is_file($path)) {
            require $path;

            return;
        }
    }
});

Config::load(EDUSMART_ROOT . '/.env');
date_default_timezone_set((string) Config::get('APP_TIMEZONE', 'Asia/Jakarta'));

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    Session::start();
}

/**
 * Build the application service container.
 */
$container = new Container([
    'core.db' => static fn (): ConnectionInterface => MySqlConnection::fromConfig(
        'CORE_DB',
        (string) Config::get('CORE_DB_NAME', 'edusmart_core')
    ),
    'provision.db' => static fn (): ConnectionInterface => MySqlConnection::fromConfig(
        'PROVISION_DB',
        (string) Config::get('CORE_DB_NAME', 'edusmart_core')
    ),
    'tenants' => static fn (Container $c): TenantResolver => new TenantResolver(
        $c->get('core.db'),
        (string) Config::get('TENANT_DB_PREFIX', 'edusmart_t_')
    ),
    'licenses' => static fn (): LicenseEngine => new LicenseEngine(),
    'audit' => static fn (Container $c): AuditLog => new AuditLog($c->get('core.db')),
    'platform.auth' => static fn (Container $c): PlatformAuth => new PlatformAuth($c->get('core.db')),
    'tenant.connector' => static fn (): TenantConnector => new TenantConnector(),
    'migrations.core' => static fn (): MigrationRunner => new MigrationRunner(EDUSMART_ROOT . '/database/migrations/core'),
    'migrations.tenant' => static fn (): MigrationRunner => new MigrationRunner(EDUSMART_ROOT . '/database/migrations/tenant'),
    'seeder.tenant' => static fn (): TenantSeeder => new TenantSeeder(),
    'provisioner' => static fn (Container $c): TenantProvisioner => new TenantProvisioner(
        $c->get('core.db'),
        $c->get('migrations.tenant'),
        $c->get('seeder.tenant'),
        $c->get('tenants')
    ),
    'view' => static fn (): View => new View(EDUSMART_ROOT . '/resources/views'),
]);

return $container;
