<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Contracts\ConnectionInterface;

/**
 * Seeds role/permission catalog and initial tenant settings.
 */
final class TenantSeeder
{
    /**
     * @return array{permissions: int, roles: int, admin_id: int}
     */
    public function seed(
        ConnectionInterface $db,
        string $schoolName,
        string $adminName,
        string $adminEmail,
        string $adminPassword
    ): array {
        $catalog = require dirname(__DIR__, 2) . '/database/seeds/roles_permissions.php';
        $permissionIds = [];

        foreach ($catalog['permissions'] as $code => $label) {
            $db->execute(
                'INSERT INTO permissions (code, name) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE name = VALUES(name)',
                [$code, $label]
            );
            $row = $db->selectOne('SELECT id FROM permissions WHERE code = ? LIMIT 1', [$code]);
            if ($row !== null) {
                $permissionIds[$code] = (int) $row['id'];
            }
        }

        $roleIds = [];
        foreach ($catalog['roles'] as $code => $role) {
            $db->execute(
                'INSERT INTO roles (code, name, description) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE name = VALUES(name)',
                [$code, $role['name'], $role['description'] ?? null]
            );
            $row = $db->selectOne('SELECT id FROM roles WHERE code = ? LIMIT 1', [$code]);
            if ($row === null) {
                continue;
            }

            $roleId = (int) $row['id'];
            $roleIds[$code] = $roleId;

            foreach ($role['permissions'] as $permissionCode) {
                if ($permissionCode === '*') {
                    foreach ($permissionIds as $permissionId) {
                        $this->attachPermission($db, $roleId, $permissionId);
                    }
                    continue;
                }

                if (isset($permissionIds[$permissionCode])) {
                    $this->attachPermission($db, $roleId, $permissionIds[$permissionCode]);
                }
            }
        }

        $db->execute(
            'INSERT INTO school_profile (name, timezone, currency, created_at)
             SELECT ?, ?, ?, ? FROM DUAL
             WHERE NOT EXISTS (SELECT 1 FROM school_profile)',
            [$schoolName, 'Asia/Jakarta', 'IDR', date('Y-m-d H:i:s')]
        );

        $db->execute(
            'INSERT INTO academic_years (name, starts_on, ends_on, semester, is_active, created_at)
             SELECT ?, ?, ?, ?, 1, ? FROM DUAL
             WHERE NOT EXISTS (SELECT 1 FROM academic_years)',
            [
                'Tahun Ajaran ' . date('Y') . '/' . (date('Y') + 1),
                date('Y') . '-07-01',
                (date('Y') + 1) . '-06-30',
                'Ganjil',
                date('Y-m-d H:i:s'),
            ]
        );

        $adminId = $this->createAdmin($db, $adminName, $adminEmail, $adminPassword);

        if (isset($roleIds['school_super_admin'])) {
            $this->attachRole($db, $adminId, $roleIds['school_super_admin']);
        }

        return [
            'permissions' => count($permissionIds),
            'roles' => count($roleIds),
            'admin_id' => $adminId,
        ];
    }

    public function createAdmin(
        ConnectionInterface $db,
        string $name,
        string $email,
        string $password
    ): int {
        $email = strtolower(trim($email));
        $existing = $db->selectOne('SELECT id FROM users WHERE email = ? LIMIT 1', [$email]);

        if ($existing !== null) {
            return (int) $existing['id'];
        }

        return $db->insert(
            'INSERT INTO users (name, email, password_hash, is_active, created_at) VALUES (?, ?, ?, 1, ?)',
            [$name, $email, password_hash($password, PASSWORD_DEFAULT), date('Y-m-d H:i:s')]
        );
    }

    private function attachPermission(ConnectionInterface $db, int $roleId, int $permissionId): void
    {
        $db->execute(
            'INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)',
            [$roleId, $permissionId]
        );
    }

    private function attachRole(ConnectionInterface $db, int $userId, int $roleId): void
    {
        $db->execute(
            'INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)',
            [$userId, $roleId]
        );
    }
}
