<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Contracts\ConnectionInterface;
use App\Core\Database\MySqlConnection;
use App\Core\TenantResolver;
use App\Core\Exceptions\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Provisions a new school (tenant): creates the isolated database, runs tenant
 * migrations, seeds roles/permissions, creates the first admin, then registers
 * the tenant, license and subscription in the core database.
 */
final class TenantProvisioner
{
    public function __construct(
        private ConnectionInterface $core,
        private MigrationRunner $migrations,
        private TenantSeeder $seeder,
        private TenantResolver $resolver
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{tenant: array<string, mixed>, admin_email: string, database: string, migrated: list<string>}
     */
    public function provision(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $slug = strtolower(trim((string) ($input['slug'] ?? '')));
        $adminName = trim((string) ($input['admin_name'] ?? ''));
        $adminEmail = strtolower(trim((string) ($input['admin_email'] ?? '')));
        $adminPassword = (string) ($input['admin_password'] ?? '');
        $level = trim((string) ($input['level'] ?? 'SMP'));
        $planCode = trim((string) ($input['plan_code'] ?? 'starter'));
        $trialDays = (int) ($input['trial_days'] ?? 30);

        $errors = $this->validate($name, $slug, $adminName, $adminEmail, $adminPassword);
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        if ($this->resolver->findBySlug($slug) !== null) {
            throw new ValidationException(['slug' => 'Slug sekolah sudah digunakan. Pilih slug lain.']);
        }

        $plan = $this->core->selectOne('SELECT * FROM plans WHERE code = ? LIMIT 1', [$planCode]);
        if ($plan === null) {
            throw new ValidationException(['plan_code' => 'Paket langganan tidak ditemukan.']);
        }

        $database = $this->resolver->databaseNameFor($slug);
        $provisionConnection = $this->provisionConnection();

        $exists = $provisionConnection->selectOne(
            'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ? LIMIT 1',
            [$database]
        );
        if ($exists !== null) {
            throw new ValidationException(['slug' => 'Database untuk slug tersebut sudah ada.']);
        }

        $provisionConnection->execute(
            'CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );

        try {
            $tenantDb = MySqlConnection::connect(
                (string) Config::get('PROVISION_DB_HOST', (string) Config::get('CORE_DB_HOST', '127.0.0.1')),
                Config::int('PROVISION_DB_PORT', Config::int('CORE_DB_PORT', 3306)),
                $database,
                (string) Config::get('PROVISION_DB_USER', ''),
                (string) Config::get('PROVISION_DB_PASSWORD', '')
            );

            $migrated = $this->migrations->run($tenantDb);
            $this->seeder->seed($tenantDb, $name, $adminName, $adminEmail, $adminPassword);
        } catch (Throwable $throwable) {
            $provisionConnection->execute('DROP DATABASE IF EXISTS `' . $database . '`');
            throw new RuntimeException('Provisioning tenant gagal: ' . $throwable->getMessage(), 0, $throwable);
        }

        $now = time();
        $tenantId = $this->core->insert(
            'INSERT INTO tenants (name, slug, db_name, level, status, domain, email, timezone, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $name,
                $slug,
                $database,
                $level,
                'active',
                null,
                $adminEmail,
                'Asia/Jakarta',
                date('Y-m-d H:i:s'),
                date('Y-m-d H:i:s'),
            ]
        );

        $features = $this->resolvePlanFeatures($plan);
        $this->core->insert(
            'INSERT INTO licenses (tenant_id, plan_id, license_key_hash, status, starts_at, ends_at, grace_days,
                features, max_students, max_users, ai_quota, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $tenantId,
                (int) $plan['id'],
                null,
                $trialDays > 0 ? 'trial' : 'active',
                date('Y-m-d H:i:s', $now),
                date('Y-m-d H:i:s', $now + ($trialDays * 86400)),
                Config::int('LICENSE_GRACE_DAYS', 14),
                json_encode($features),
                (int) $plan['max_students'],
                (int) $plan['max_users'],
                (int) $plan['ai_quota'],
                date('Y-m-d H:i:s'),
                date('Y-m-d H:i:s'),
            ]
        );

        $this->core->insert(
            'INSERT INTO subscriptions (tenant_id, plan_id, status, billing_cycle, started_at, current_period_end, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $tenantId,
                (int) $plan['id'],
                $trialDays > 0 ? 'trial' : 'active',
                'monthly',
                date('Y-m-d H:i:s', $now),
                date('Y-m-d H:i:s', $now + ($trialDays * 86400)),
                date('Y-m-d H:i:s'),
            ]
        );

        $tenant = $this->core->selectOne('SELECT * FROM tenants WHERE id = ? LIMIT 1', [$tenantId]);
        if ($tenant === null) {
            throw new RuntimeException('Tenant gagal dibaca setelah provisioning.');
        }

        return [
            'tenant' => $tenant,
            'admin_email' => $adminEmail,
            'database' => $database,
            'migrated' => $migrated,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function validate(string $name, string $slug, string $adminName, string $adminEmail, string $adminPassword): array
    {
        $errors = [];

        if (mb_strlen($name) < 3) {
            $errors['name'] = 'Nama sekolah minimal 3 karakter.';
        }

        if (!$this->resolver->isValidSlug($slug)) {
            $errors['slug'] = 'Slug hanya boleh huruf kecil, angka, dan tanda hubung (2-63 karakter).';
        }

        if ($adminName === '') {
            $errors['admin_name'] = 'Nama admin wajib diisi.';
        }

        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $errors['admin_email'] = 'Alamat email admin tidak valid.';
        }

        if (strlen($adminPassword) < 12) {
            $errors['admin_password'] = 'Kata sandi minimal 12 karakter.';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $plan
     * @return list<string>
     */
    private function resolvePlanFeatures(array $plan): array
    {
        $features = $plan['features'] ?? null;
        if (is_string($features)) {
            $decoded = json_decode($features, true);
            $features = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($features) || $features === []) {
            return ['*'];
        }

        return array_values(array_map('strval', $features));
    }

    private function provisionConnection(): ConnectionInterface
    {
        return MySqlConnection::connect(
            (string) Config::get('PROVISION_DB_HOST', (string) Config::get('CORE_DB_HOST', '127.0.0.1')),
            Config::int('PROVISION_DB_PORT', Config::int('CORE_DB_PORT', 3306)),
            (string) Config::get('CORE_DB_NAME', 'edusmart_core'),
            (string) Config::get('PROVISION_DB_USER', ''),
            (string) Config::get('PROVISION_DB_PASSWORD', '')
        );
    }
}
