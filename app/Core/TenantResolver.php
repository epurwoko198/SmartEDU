<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Contracts\ConnectionInterface;

/**
 * Tenant resolution and database naming.
 *
 * The tenant database name is never taken from user input: it is derived from
 * the validated slug stored in the core database, or by the provisioner.
 */
final class TenantResolver
{
    public const SLUG_PATTERN = '/^[a-z0-9][a-z0-9-]{0,61}[a-z0-9]$/';
    public const RESERVED_SLUGS = ['www', 'saas', 'admin', 'api', 'app', 'static', 'assets', 't', 'mail'];

    public function __construct(
        private ConnectionInterface $core,
        private string $tablePrefix = 'edusmart_t_'
    ) {
    }

    public function isValidSlug(string $slug): bool
    {
        if (!preg_match(self::SLUG_PATTERN, $slug)) {
            return false;
        }

        return !in_array($slug, self::RESERVED_SLUGS, true);
    }

    public function databaseNameFor(string $slug): string
    {
        if (!$this->isValidSlug($slug)) {
            throw new \InvalidArgumentException('Slug tenant tidak valid.');
        }

        $prefix = preg_match('/^[a-z0-9_]+$/', $this->tablePrefix) ? $this->tablePrefix : 'edusmart_t_';

        return $prefix . str_replace('-', '_', $slug);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        if (!$this->isValidSlug($slug)) {
            return null;
        }

        return $this->core->selectOne('SELECT * FROM tenants WHERE slug = ? LIMIT 1', [$slug]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByDomain(string $host): ?array
    {
        $host = strtolower(trim($host));
        if ($host === '' || !str_contains($host, '.')) {
            return null;
        }

        return $this->core->selectOne('SELECT * FROM tenants WHERE domain = ? LIMIT 1', [$host]);
    }

    /**
     * Resolve the tenant of an incoming request from the /t/{slug} path or a mapped domain.
     *
     * @return array<string, mixed>|null
     */
    public function resolve(Request $request): ?array
    {
        if (preg_match('#^/t/([^/]+)#', $request->path, $matches)) {
            return $this->findBySlug(strtolower($matches[1]));
        }

        return $this->findByDomain($request->host);
    }

    public function isActive(array $tenant): bool
    {
        return ($tenant['status'] ?? '') === 'active';
    }

    public function displayUrl(array $tenant): string
    {
        $appUrl = rtrim((string) Config::get('APP_URL', ''), '/');

        return $appUrl . '/t/' . rawurlencode((string) ($tenant['slug'] ?? ''));
    }
}
