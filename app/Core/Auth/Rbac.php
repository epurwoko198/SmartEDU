<?php
declare(strict_types=1);

namespace App\Core\Auth;

use App\Core\Contracts\ConnectionInterface;

/**
 * Role based access control for tenant users.
 * Roles are collections of permissions; checks always run server side.
 */
final class Rbac
{
    /** @var array<int, list<string>> */
    private array $cache = [];

    public function __construct(private ConnectionInterface $db)
    {
    }

    /**
     * @return list<string>
     */
    public function permissionsForUser(int $userId): array
    {
        if (isset($this->cache[$userId])) {
            return $this->cache[$userId];
        }

        $rows = $this->db->select(
            'SELECT DISTINCT p.code
             FROM permissions p
             INNER JOIN role_permissions rp ON rp.permission_id = p.id
             INNER JOIN user_roles ur ON ur.role_id = rp.role_id
             WHERE ur.user_id = ?',
            [$userId]
        );

        $permissions = array_values(array_map(
            static fn (array $row): string => (string) $row['code'],
            $rows
        ));

        $this->cache[$userId] = $permissions;

        return $permissions;
    }

    /**
     * @param list<string> $permissions
     */
    public static function allows(array $permissions, string $required): bool
    {
        if ($required === '') {
            return true;
        }

        if (in_array('*', $permissions, true) || in_array($required, $permissions, true)) {
            return true;
        }

        $resource = explode('.', $required)[0];

        return in_array($resource . '.*', $permissions, true);
    }

    /**
     * @param list<string> $permissions
     */
    public static function allowsAny(array $permissions, array $required): bool
    {
        foreach ($required as $permission) {
            if (self::allows($permissions, $permission)) {
                return true;
            }
        }

        return false;
    }
}
