<?php
/** @var array<string, mixed> $tenant */
/** @var array<string, mixed>|null $license */
/** @var array<string, mixed> $evaluation */
/** @var array<string, mixed>|null $subscription */
/** @var array<string, mixed> $usage */
/** @var list<array<string, mixed>> $auditEvents */
$isActive = ($tenant['status'] ?? '') === 'active';
$maxStudents = (int) $evaluation['max_students'];
$maxUsers = (int) $evaluation['max_users'];
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <a class="small text-muted-soft text-decoration-none" href="<?= e(app_url('/saas/tenants')) ?>">
            <i class="bi bi-arrow-left me-1"></i>Kembali ke daftar tenant
        </a>
        <h2 class="h4 fw-bold mb-1 mt-2"><?= e((string) $tenant['name']) ?></h2>
        <div class="small text-muted-soft">
            <span class="mono">/t/<?= e((string) $tenant['slug']) ?></span>
            · <?= e((string) $tenant['level']) ?>
            · database <span class="mono"><?= e((string) $tenant['db_name']) ?></span>
        </div>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="badge text-bg-<?= $isActive ? 'success' : 'secondary' ?>">
            <?= $isActive ? 'Aktif' : 'Ditangguhkan' ?>
        </span>
        <span class="badge text-bg-<?= e(status_badge_class((string) $evaluation['status'])) ?>">
            Lisensi: <?= e((string) $evaluation['label']) ?>
        </span>
        <form method="post" action="<?= e(app_url('/saas/tenants/' . (int) $tenant['id'] . '/status')) ?>">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="status" value="<?= $isActive ? 'suspended' : 'active' ?>">
            <button class="btn btn-sm <?= $isActive ? 'btn-outline-danger' : 'btn-outline-success' ?>" type="submit">
                <i class="bi <?= $isActive ? 'bi-pause-circle' : 'bi-play-circle' ?> me-1"></i>
                <?= $isActive ? 'Tangguhkan' : 'Aktifkan' ?>
            </button>
        </form>
        <a class="btn btn-sm btn-primary" href="<?= e(app_url('/t/' . rawurlencode((string) $tenant['slug']) . '/login')) ?>" target="_blank" rel="noopener">
            <i class="bi bi-box-arrow-up-right me-1"></i>Buka login sekolah
        </a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-label">Siswa aktif</div>
            <div class="kpi-value"><?= (int) $usage['students'] ?></div>
            <div class="small text-muted-soft">
                <?= $maxStudents > 0 ? 'Kuota ' . $maxStudents : 'Tanpa batas kuota' ?>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-label">Guru &amp; pegawai</div>
            <div class="kpi-value"><?= (int) $usage['employees'] ?></div>
            <div class="small text-muted-soft">Aktif</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-label">Pengguna</div>
            <div class="kpi-value"><?= (int) $usage['users'] ?></div>
            <div class="small text-muted-soft">
                <?= $maxUsers > 0 ? 'Kuota ' . $maxUsers : 'Tanpa batas kuota' ?>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-label">Rombel</div>
            <div class="kpi-value"><?= (int) $usage['classes'] ?></div>
            <div class="small text-muted-soft">Kelas aktif</div>
        </div>
    </div>
</div>

<?php if (!$usage['available']): ?>
    <div class="alert alert-warning small">
        <i class="bi bi-plug me-1"></i>
        Statistik penggunaan belum tersedia: koneksi ke database tenant gagal. Periksa konfigurasi
        <span class="mono">TENANT_DB_*</span> dan hak akses akun runtime.
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="panel p-4 h-100">
            <div class="panel-title mb-3">Lisensi &amp; langganan</div>
            <dl class="row small mb-0">
                <dt class="col-5 text-muted-soft fw-normal">Status</dt>
                <dd class="col-7"><?= e((string) $evaluation['label']) ?></dd>
                <dt class="col-5 text-muted-soft fw-normal">Paket</dt>
                <dd class="col-7"><?= e((string) ($license['plan_name'] ?? '—')) ?></dd>
                <dt class="col-5 text-muted-soft fw-normal">Mulai</dt>
                <dd class="col-7"><?= e(format_date((string) ($license['starts_at'] ?? null))) ?></dd>
                <dt class="col-5 text-muted-soft fw-normal">Berakhir</dt>
                <dd class="col-7"><?= e(format_date((string) ($license['ends_at'] ?? null))) ?></dd>
                <dt class="col-5 text-muted-soft fw-normal">Masa tenggang</dt>
                <dd class="col-7"><?= (int) ($license['grace_days'] ?? 0) ?> hari</dd>
                <dt class="col-5 text-muted-soft fw-normal">Kuota AI</dt>
                <dd class="col-7"><?= (int) ($evaluation['ai_quota'] ?? 0) ?> permintaan</dd>
                <dt class="col-5 text-muted-soft fw-normal">Mode baca saja</dt>
                <dd class="col-7"><?= $evaluation['read_only'] ? 'Ya (tulis diblokir)' : 'Tidak' ?></dd>
                <?php if ($subscription !== null): ?>
                    <dt class="col-5 text-muted-soft fw-normal">Langganan</dt>
                    <dd class="col-7">
                        <?= e((string) $subscription['status']) ?> ·
                        periode s/d <?= e(format_date((string) ($subscription['current_period_end'] ?? null))) ?>
                    </dd>
                <?php endif; ?>
            </dl>
            <div class="mt-3">
                <div class="small text-muted-soft mb-1">Fitur aktif</div>
                <div class="d-flex flex-wrap gap-1">
                    <?php if (in_array('*', $evaluation['features'], true)): ?>
                        <span class="badge text-bg-primary">Semua modul</span>
                    <?php else: ?>
                        <?php foreach ($evaluation['features'] as $feature): ?>
                            <span class="badge text-bg-light border"><?= e((string) $feature) ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="panel p-4 h-100">
            <div class="panel-title mb-3">Profil sekolah</div>
            <dl class="row small mb-0">
                <dt class="col-5 text-muted-soft fw-normal">Slug</dt>
                <dd class="col-7 mono"><?= e((string) $tenant['slug']) ?></dd>
                <dt class="col-5 text-muted-soft fw-normal">Domain khusus</dt>
                <dd class="col-7"><?= e((string) ($tenant['domain'] ?? '—')) ?></dd>
                <dt class="col-5 text-muted-soft fw-normal">Email</dt>
                <dd class="col-7"><?= e((string) ($tenant['email'] ?? '—')) ?></dd>
                <dt class="col-5 text-muted-soft fw-normal">Zona waktu</dt>
                <dd class="col-7"><?= e((string) $tenant['timezone']) ?></dd>
                <dt class="col-5 text-muted-soft fw-normal">Dibuat</dt>
                <dd class="col-7"><?= e(format_date((string) $tenant['created_at'], 'd M Y H:i')) ?></dd>
                <dt class="col-5 text-muted-soft fw-normal">Diperbarui</dt>
                <dd class="col-7"><?= e(format_date((string) $tenant['updated_at'], 'd M Y H:i')) ?></dd>
            </dl>
        </div>
    </div>

    <div class="col-12">
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Audit tenant ini</span>
                <span class="small text-muted-soft">10 terakhir</span>
            </div>
            <div class="p-3 pt-1">
                <?php if ($auditEvents === []): ?>
                    <div class="empty-state py-4">Belum ada audit untuk tenant ini.</div>
                <?php endif; ?>
                <?php foreach ($auditEvents as $event): ?>
                    <div class="audit-item">
                        <span class="audit-dot"><i class="bi bi-shield-check"></i></span>
                        <div class="flex-grow-1">
                            <div class="fw-semibold mono"><?= e((string) $event['action']) ?></div>
                            <div class="small text-muted-soft">
                                <?= e((string) ($event['actor_type'] ?? 'system')) ?> ·
                                <?= e(format_date((string) $event['created_at'], 'd M Y H:i')) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
