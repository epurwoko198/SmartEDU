<?php
declare(strict_types=1);

namespace App\Core\Audit;

use App\Core\Contracts\ConnectionInterface;

/**
 * Append-only audit trail. Sensitive values are never stored in metadata.
 */
final class AuditLog
{
    /**
     * @param bool $hasTenantColumn Core database audits carry tenant_id; a tenant
     *                              database is already isolated and its audit table
     *                              has no such column.
     */
    public function __construct(
        private ConnectionInterface $db,
        private bool $hasTenantColumn = true
    ) {
    }

    /**
     * @param array<string, mixed> $event
     */
    public function record(array $event): void
    {
        $metadata = $event['metadata'] ?? [];
        if (!is_array($metadata)) {
            $metadata = ['value' => $metadata];
        }

        // Drop potentially sensitive keys before persisting.
        foreach (['password', 'password_hash', 'token', 'secret', 'api_key'] as $forbidden) {
            unset($metadata[$forbidden]);
        }

        $columns = 'actor_type, actor_id, ' . ($this->hasTenantColumn ? 'tenant_id, ' : '')
            . 'action, entity, entity_id, ip, user_agent, metadata, created_at';
        $placeholders = '?, ?, ' . ($this->hasTenantColumn ? '?, ' : '') . '?, ?, ?, ?, ?, ?, ?';

        $values = [
            (string) ($event['actor_type'] ?? 'system'),
            isset($event['actor_id']) ? (int) $event['actor_id'] : null,
        ];

        if ($this->hasTenantColumn) {
            $values[] = isset($event['tenant_id']) ? (int) $event['tenant_id'] : null;
        }

        $values = array_merge($values, [
            (string) ($event['action'] ?? 'unknown'),
            $event['entity'] ?? null,
            isset($event['entity_id']) ? (int) $event['entity_id'] : null,
            $event['ip'] ?? null,
            $event['user_agent'] ?? null,
            $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            date('Y-m-d H:i:s'),
        ]);

        $this->db->insert(
            'INSERT INTO audit_events (' . $columns . ') VALUES (' . $placeholders . ')',
            $values
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recent(int $limit = 20, ?int $tenantId = null): array
    {
        $limit = max(1, min(100, $limit));

        if ($tenantId !== null && $this->hasTenantColumn) {
            return $this->db->select(
                'SELECT * FROM audit_events WHERE tenant_id = ? ORDER BY id DESC LIMIT ' . $limit,
                [$tenantId]
            );
        }

        return $this->db->select('SELECT * FROM audit_events ORDER BY id DESC LIMIT ' . $limit);
    }
}
