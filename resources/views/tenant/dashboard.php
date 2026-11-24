<?php
/** @var array<string, mixed> $stats */
/** @var list<array{code: string, name: string, enabled: bool}> $modules */
/** @var array<string, mixed> $profile */
/** @var list<array<string, mixed>> $recentAudit */
/** @var array<string, mixed> $evaluation */
$maxTotal = 1;
foreach ($stats['class_distribution'] as $row) {
    $maxTotal = max($maxTotal, (int) $row['total']);
}
$licenseDays = (int) $evaluation['days_left'];
$showExpiryWarning = in_array($evaluation['status'], ['trial', 'grace_period'], true) && $licenseDays <= 14;
$moduleIcons = [
    'ppdb' => 'bi-person-plus',
    'master_data' => 'bi-database',
    'academic' => 'bi-journal-bookmark',
    'finance' => 'bi-cash-coin',
    'letters' => 'bi-envelope-paper',
    'graduation' => 'bi-award',
    'communication' => 'bi-chat-dots',
    'analytics' => 'bi-graph-up-arrow',
    'ai' => 'bi-robot',
];
?>
<div class="license-strip mb-4">
    <span class="kpi-icon bg-soft-<?= e(status_badge_class((string) $evaluation['status'])) ?>"><i class="bi bi-patch-check"></i></span>
    <div class="flex-grow-1">
        <div class="fw-semibold"><?= e((string) $evaluation['label']) ?></div>
        <div class="small text-muted-soft">
            <?php if ($licenseDays > 0): ?>
                Berlaku <?= $licenseDays ?> hari lagi · berakhir <?= e(format_date((string) $evaluation['ends_at'])) ?>
            <?php elseif ($evaluation['ends_at'] !== null): ?>
                Berakhir <?= e(format_date((string) $evaluation['ends_at'])) ?>
            <?php else: ?>
                Lisensi tanpa batas waktu
            <?php endif; ?>
        </div>
    </div>
    <div class="d-flex gap-2 small">
        <span class="badge text-bg-light border">
            <?= (int) $evaluation['max_students'] > 0 ? 'Kuota siswa ' . (int) $evaluation['max_students'] : 'Siswa tanpa batas' ?>
        </span>
        <span class="badge text-bg-light border">Kuota AI <?= (int) $evaluation['ai_quota'] ?></span>
    </div>
</div>

<?php if ($showExpiryWarning): ?>
    <div class="alert alert-warning d-flex gap-2 align-items-start">
        <i class="bi bi-exclamation-triangle-fill mt-1"></i>
        <div>
            <strong>Lisensi hampir berakhir.</strong>
            Tersisa <?= $licenseDays ?> hari. Hubungi penyelenggara untuk perpanjangan agar akses tulis tidak terhenti.
        </div>
    </div>
<?php endif; ?>

<?php if (!$stats['available']): ?>
    <div class="alert alert-warning small">
        <i class="bi bi-plug me-1"></i>
        Data operasional belum dapat dibaca dari database sekolah. Periksa konfigurasi koneksi tenant.
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card d-flex justify-content-between align-items-start">
            <div>
                <div class="kpi-label">Siswa aktif</div>
                <div class="kpi-value"><?= (int) $stats['students'] ?></div>
            </div>
            <span class="kpi-icon bg-soft-primary"><i class="bi bi-people"></i></span>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card d-flex justify-content-between align-items-start">
            <div>
                <div class="kpi-label">Guru &amp; pegawai</div>
                <div class="kpi-value"><?= (int) $stats['employees'] ?></div>
            </div>
            <span class="kpi-icon bg-soft-info"><i class="bi bi-person-video3"></i></span>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card d-flex justify-content-between align-items-start">
            <div>
                <div class="kpi-label">Rombel</div>
                <div class="kpi-value"><?= (int) $stats['classes'] ?></div>
            </div>
            <span class="kpi-icon bg-soft-purple"><i class="bi bi-diagram-3"></i></span>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card d-flex justify-content-between align-items-start">
            <div>
                <div class="kpi-label">Tunggakan</div>
                <div class="kpi-value" style="font-size:1.35rem"><?= e(rupiah($stats['outstanding'])) ?></div>
                <div class="small text-muted-soft"><?= (int) $stats['unpaid_invoices'] ?> tagihan belum lunas</div>
            </div>
            <span class="kpi-icon bg-soft-warning"><i class="bi bi-receipt-cutoff"></i></span>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="panel h-100">
            <div class="panel-header">
                <span class="panel-title">Sebaran siswa per rombel</span>
                <span class="small text-muted-soft"><?= count($stats['class_distribution']) ?> rombel</span>
            </div>
            <div class="p-3">
                <?php if ($stats['class_distribution'] === []): ?>
                    <div class="empty-state py-4">Belum ada data rombel.</div>
                <?php endif; ?>
                <?php foreach ($stats['class_distribution'] as $row): ?>
                    <div class="chart-row">
                        <span class="text-truncate"><?= e($row['name']) ?></span>
                        <span class="chart-track">
                            <span class="chart-fill" style="width: <?= (int) round(($row['total'] / $maxTotal) * 100) ?>%"></span>
                        </span>
                        <span class="text-end fw-semibold"><?= (int) $row['total'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="panel h-100">
            <div class="panel-header">
                <span class="panel-title">Penerimaan peserta didik</span>
                <span class="badge text-bg-light border">PPDB</span>
            </div>
            <div class="p-3">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <span class="kpi-icon bg-soft-success"><i class="bi bi-clipboard-check"></i></span>
                    <div>
                        <div class="kpi-value" style="font-size:1.4rem"><?= (int) $stats['applicants'] ?></div>
                        <div class="small text-muted-soft">Pendaftar menunggu verifikasi/seleksi</div>
                    </div>
                </div>
                <div class="small text-muted-soft mb-2">Alur PPDB yang disiapkan:</div>
                <ol class="small text-muted-soft ps-3 mb-0" style="line-height:1.9">
                    <li>Pendaftaran online &amp; unggah berkas</li>
                    <li>Verifikasi berkas dan tes/wawancara</li>
                    <li>Pemeringkatan dan pengumuman</li>
                    <li>Daftar ulang → siswa aktif</li>
                </ol>
                <span class="badge text-bg-secondary mt-3">Modul PPDB tersedia di Fase 2</span>
            </div>
        </div>
    </div>
</div>

<div class="panel mb-4">
    <div class="panel-header">
        <span class="panel-title">Modul sekolah</span>
        <span class="small text-muted-soft">Ketersediaan mengikuti paket lisensi</span>
    </div>
    <div class="p-3">
        <div class="row g-3">
            <?php foreach ($modules as $module): ?>
                <div class="col-sm-6 col-lg-4">
                    <div class="module-tile d-flex gap-3 align-items-start">
                        <span class="module-icon"><i class="bi <?= e($moduleIcons[$module['code']] ?? 'bi-grid') ?>"></i></span>
                        <div class="flex-grow-1">
                            <div class="fw-semibold small"><?= e($module['name']) ?></div>
                            <div class="module-status mt-1 <?= $module['enabled'] ? 'text-success' : 'text-muted-soft' ?>">
                                <i class="bi <?= $module['enabled'] ? 'bi-check-circle-fill' : 'bi-dash-circle' ?> me-1"></i>
                                <?= $module['enabled'] ? 'Termasuk paket' : 'Tidak termasuk paket' ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="panel h-100">
            <div class="panel-header"><span class="panel-title">Profil sekolah</span></div>
            <div class="p-3">
                <dl class="row small mb-0">
                    <dt class="col-5 text-muted-soft fw-normal">Nama</dt>
                    <dd class="col-7"><?= e((string) ($profile['name'] ?? $tenant['name'])) ?></dd>
                    <dt class="col-5 text-muted-soft fw-normal">NPSN</dt>
                    <dd class="col-7"><?= e((string) ($profile['npsn'] ?? '—')) ?></dd>
                    <dt class="col-5 text-muted-soft fw-normal">Jenjang</dt>
                    <dd class="col-7"><?= e((string) ($profile['level'] ?? $tenant['level'])) ?></dd>
                    <dt class="col-5 text-muted-soft fw-normal">Kepala sekolah</dt>
                    <dd class="col-7"><?= e((string) ($profile['headmaster'] ?? '—')) ?></dd>
                    <dt class="col-5 text-muted-soft fw-normal">Zona waktu</dt>
                    <dd class="col-7"><?= e((string) ($profile['timezone'] ?? $tenant['timezone'])) ?></dd>
                </dl>
                <div class="alert alert-light border small mt-3 mb-0">
                    <i class="bi bi-sliders me-1"></i>
                    Pengaturan profil sekolah lengkap tersedia pada modul Tata Usaha (Fase 2/5).
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="panel h-100">
            <div class="panel-header">
                <span class="panel-title">Aktivitas terakhir</span>
                <span class="small text-muted-soft">Audit sekolah</span>
            </div>
            <div class="p-3 pt-2">
                <?php if ($recentAudit === []): ?>
                    <div class="empty-state py-4">Belum ada aktivitas.</div>
                <?php endif; ?>
                <?php foreach ($recentAudit as $event): ?>
                    <div class="audit-item">
                        <span class="audit-dot"><i class="bi bi-activity"></i></span>
                        <div class="flex-grow-1">
                            <div class="fw-semibold mono"><?= e((string) $event['action']) ?></div>
                            <div class="small text-muted-soft">
                                <?= e(format_date((string) $event['created_at'], 'd M Y H:i')) ?>
                                <?= !empty($event['ip']) ? ' · ' . e((string) $event['ip']) : '' ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
