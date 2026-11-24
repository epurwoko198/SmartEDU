<?php
declare(strict_types=1);

namespace App\Core\Tenant;

use App\Core\Config;
use App\Core\Contracts\ConnectionInterface;
use App\Core\Database\MySqlConnection;
use RuntimeException;

/**
 * Opens read/write connections to tenant databases using the runtime account.
 * The database name always comes from the tenant record, never from request input.
 */
final class TenantConnector
{
    public function connect(array $tenant): ConnectionInterface
    {
        return $this->open($tenant, 'TENANT_DB');
    }

    /**
     * Opens a tenant database with the provisioning account, which is the only
     * account allowed to run schema migrations (DDL).
     */
    public function connectForProvisioning(array $tenant): ConnectionInterface
    {
        return $this->open($tenant, 'PROVISION_DB');
    }

    private function open(array $tenant, string $prefix): ConnectionInterface
    {
        $database = (string) ($tenant['db_name'] ?? '');
        if (!preg_match('/^[a-z0-9_]+$/', $database)) {
            throw new RuntimeException('Nama database tenant tidak valid.');
        }

        return MySqlConnection::connect(
            (string) Config::get($prefix . '_HOST', (string) Config::get('CORE_DB_HOST', '127.0.0.1')),
            Config::int($prefix . '_PORT', Config::int('CORE_DB_PORT', 3306)),
            $database,
            (string) Config::get($prefix . '_USER', ''),
            (string) Config::get($prefix . '_PASSWORD', '')
        );
    }

    public function isConfigured(): bool
    {
        return (string) Config::get('TENANT_DB_USER', '') !== '';
    }
}
