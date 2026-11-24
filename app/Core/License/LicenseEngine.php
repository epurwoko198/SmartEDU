<?php
declare(strict_types=1);

namespace App\Core\License;

use DateTimeImmutable;
use DateTimeZone;

/**
 * License evaluation for trial, active, grace period, suspended and expired states.
 *
 * Expired licenses keep read access but disable writes (read-only policy).
 */
final class LicenseEngine
{
    public const MODULE_FEATURES = [
        'ppdb' => 'PPDB & Penerimaan',
        'master_data' => 'Master Data Sekolah',
        'academic' => 'Akademik & Pembelajaran',
        'finance' => 'Keuangan Sekolah',
        'letters' => 'Tata Usaha & Persuratan',
        'graduation' => 'Kelulusan & Sertifikat',
        'communication' => 'Komunikasi & Portal',
        'analytics' => 'Laporan & Analitik',
        'ai' => 'AI Assistant',
    ];

    private const LABELS = [
        'active' => 'Aktif',
        'trial' => 'Masa Uji Coba',
        'grace_period' => 'Masa Tenggang',
        'suspended' => 'Ditangguhkan',
        'expired' => 'Kedaluwarsa',
        'cancelled' => 'Dibatalkan',
        'unlicensed' => 'Belum Berlisensi',
    ];

    /**
     * @param array<string, mixed>|null $license Row from the licenses table.
     * @return array{
     *     status: string,
     *     label: string,
     *     read_only: bool,
     *     blocked: bool,
     *     days_left: int,
     *     features: list<string>,
     *     max_students: int,
     *     max_users: int,
     *     ai_quota: int,
     *     ends_at: ?string
     * }
     */
    public function evaluate(?array $license, ?int $now = null): array
    {
        $now ??= time();

        if ($license === null) {
            return $this->result('unlicensed', true, true, 0, [], 0, 0, 0, null);
        }

        $status = strtolower((string) ($license['status'] ?? 'expired'));
        $features = $this->decodeFeatures($license['features'] ?? null);
        $maxStudents = (int) ($license['max_students'] ?? 0);
        $maxUsers = (int) ($license['max_users'] ?? 0);
        $aiQuota = (int) ($license['ai_quota'] ?? 0);
        $endsAt = $this->timestamp($license['ends_at'] ?? null);

        if (in_array($status, ['suspended', 'cancelled'], true)) {
            return $this->result($status, true, true, 0, $features, $maxStudents, $maxUsers, $aiQuota, $license['ends_at'] ?? null);
        }

        if ($endsAt === null) {
            // No end date: treat as unlimited active license.
            return $this->result($status === 'trial' ? 'trial' : 'active', false, false, 0, $features, $maxStudents, $maxUsers, $aiQuota, null);
        }

        if ($now < $endsAt) {
            $daysLeft = (int) ceil(($endsAt - $now) / 86400);
            $effective = $status === 'trial' ? 'trial' : 'active';

            return $this->result($effective, false, false, $daysLeft, $features, $maxStudents, $maxUsers, $aiQuota, $license['ends_at'] ?? null);
        }

        $graceDays = max(0, (int) ($license['grace_days'] ?? 0));
        $graceEnds = $endsAt + ($graceDays * 86400);

        if ($now < $graceEnds) {
            $daysLeft = (int) ceil(($graceEnds - $now) / 86400);

            return $this->result('grace_period', false, false, $daysLeft, $features, $maxStudents, $maxUsers, $aiQuota, $license['ends_at'] ?? null);
        }

        return $this->result('expired', true, true, 0, $features, $maxStudents, $maxUsers, $aiQuota, $license['ends_at'] ?? null);
    }

    public function label(string $status): string
    {
        return self::LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    /**
     * @return list<string>
     */
    public function decodeFeatures(mixed $features): array
    {
        if (is_array($features)) {
            return array_values(array_map('strval', $features));
        }

        if (!is_string($features) || $features === '') {
            return [];
        }

        $decoded = json_decode($features, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_map('strval', $decoded));
    }

    /**
     * @param list<string> $features
     */
    public function hasFeature(array $evaluation, string $feature): bool
    {
        return in_array($feature, $evaluation['features'], true) || in_array('*', $evaluation['features'], true);
    }

    /**
     * @param list<string> $features
     * @return array{
     *     status: string, label: string, read_only: bool, blocked: bool, days_left: int,
     *     features: list<string>, max_students: int, max_users: int, ai_quota: int, ends_at: ?string
     * }
     */
    private function result(
        string $status,
        bool $readOnly,
        bool $blocked,
        int $daysLeft,
        array $features,
        int $maxStudents,
        int $maxUsers,
        int $aiQuota,
        ?string $endsAt
    ): array {
        return [
            'status' => $status,
            'label' => $this->label($status),
            'read_only' => $readOnly,
            'blocked' => $blocked,
            'days_left' => $daysLeft,
            'features' => $features,
            'max_students' => $maxStudents,
            'max_users' => $maxUsers,
            'ai_quota' => $aiQuota,
            'ends_at' => $endsAt,
        ];
    }

    private function timestamp(mixed $value): ?int
    {
        if (!is_string($value) || trim($value) === '' || str_starts_with($value, '0000')) {
            return null;
        }

        try {
            return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->getTimestamp();
        } catch (\Exception) {
            return null;
        }
    }
}
