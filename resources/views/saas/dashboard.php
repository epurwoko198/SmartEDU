<?php
/** @var array<string, mixed> $stats */
/** @var list<array<string, mixed>> $tenants */
/** @var list<array<string, mixed>> $auditEvents */
?>
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card d-flex justify-content-between align-items-start">
            <div>
                <div class="kpi-label">Total tenant</div>
                <div class="kpi-value"><?= (int) $stats['tenants'] ?></div>
                <div class="small text-muted-soft"><?= (int) $stats['active'] ?> aktif · <?= (int) $stats['suspended'] ?> ditangguhkan</div>
            </div>
            <span class="kpi-icon bg-soft-primary"><i class="bi bi-buildings"></i></span>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card d-flex justify-content-between align-items-start">
            <div>
                <div class="kpi-label">Masa uji coba</div>
                <div class="kpi-value"><?= (int) $stats['trial'] ?></div>
                <div class="small text-muted-soft">Lisensi trial berjalan</div>
            </div>
            <span class="kpi-icon bg-soft-warning"><i class="bi bi-hourglass-split"></i></span>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card d-flex justify-content-between align-items-start">
            <div>
                <div class="kpi-label">Perlu perhatian</div>
                <div class="kpi-value"><?= (int) $stats['need_attention'] ?></div>
                <div class="small text-muted-soft">Lisensi kedaluwarsa / ditangguhkan</div>
            </div>
            <span class="kpi-icon bg-soft-danger"><i class="bi bi-exclamation-triangle"></i></span>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card d-flex justify-content-between align-items-start">
            <div>
                <div class="kpi-label">Pendapatan bulan ini</div>
                <div class="kpi-value" style="font-size:1.35rem"><?= e(rupiah($stats['revenue_month'])) ?></div>
                <div class="small text-muted-soft">MRR kontrak: <?= e(rupiah($stats['mrr'])) ?></div>
            </div>
            <span class="kpi-icon bg-soft-success"><i class="bi bi-cash-coin"></i></span>
        </div>
    </div>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h2 class="h6 fw-bold mb-0">Tenant terbaru</h2>
    <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(app_url('/saas/tenants')) ?>">Lihat semua</a>
        <a class="btn btn-sm btn-primary" href="<?= e(app_url('/saas/tenants/create')) ?>">
            <i class="bi bi-plus-lg me-1"></i>Onboarding sekolah
        </a>
    </div>
</div>

<div class="panel mb-4">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
            <tr>
                <th>Sekolah</th>
                <th>Paket</th>
                <th>Lisensi</th>
                <th>Sisa hari</th>
                <th>Dibuat</th>
                <th class="text-end">Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($tenants === []): ?>
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <i class="bi bi-buildings d-block fs-2 mb-2"></i>
                            Belum ada tenant. Mulai dengan membuat sekolah pertama.
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($tenants as $tenant): ?>
                <?php $license = $tenant['license']; ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e((string) $tenant['name']) ?></div>
                        <div class="small text-muted-soft mono">/t/<?= e((string) $tenant['slug']) ?></div>
                    </td>
                    <td><?= e((string) ($tenant['plan_name'] ?? '—')) ?></td>
                    <td>
                        <span class="badge text-bg-<?= e(status_badge_class((string) ($license['status'] ?? 'unlicensed'))) ?>">
                            <?= e((string) ($license['label'] ?? 'Tanpa lisensi')) ?>
                        </span>
                    </td>
                    <td class="small">
                        <?= $license !== null && $license['days_left'] > 0 ? (int) $license['days_left'] . ' hari' : '—' ?>
                    </td>
                    <td class="small text-muted-soft"><?= e(format_date((string) $tenant['created_at'])) ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(app_url('/saas/tenants/' . (int) $tenant['id'])) ?>">
                            Detail
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="panel h-100">
            <div class="panel-header">
                <span class="panel-title">Aktivitas platform terbaru</span>
                <span class="small text-muted-soft">Audit trail</span>
            </div>
            <div class="p-3 pt-1">
                <?php if ($auditEvents === []): ?>
                    <div class="empty-state py-4">Belum ada aktivitas tercatat.</div>
                <?php endif; ?>
                <?php foreach ($auditEvents as $event): ?>
                    <div class="audit-item">
                        <span class="audit-dot"><i class="bi bi-activity"></i></span>
                        <div class="flex-grow-1">
                            <div class="fw-semibold mono"><?= e((string) $event['action']) ?></div>
                            <div class="small text-muted-soft">
                                <?= e((string) ($event['actor_type'] ?? 'system')) ?>
                                <?= isset($event['actor_id']) && $event['actor_id'] !== null ? '#' . (int) $event['actor_id'] : '' ?>
                                · <?= e(format_date((string) $event['created_at'], 'd M Y H:i')) ?>
                            </div>
                        </div>
                        <?php if (!empty($event['ip'])): ?>
                            <span class="small text-muted-soft mono"><?= e((string) $event['ip']) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="panel h-100 p-3">
            <div class="panel-title mb-3">Ringkasan fase 1</div>
            <ul class="small text-muted-soft ps-3 mb-0" style="line-height:1.9">
                <li>Database hybrid: core SaaS + database per sekolah.</li>
                <li>Provisioning otomatis: migrasi, role default, admin awal.</li>
                <li>RBAC dengan pola permission <span class="mono">resource.action</span>.</li>
                <li>License engine: trial, aktif, masa tenggang, kedaluwarsa.</li>
                <li>Audit trail untuk login, provisioning, dan perubahan status.</li>
                <li>AI Gateway tersedia sebagai layanan terpisah (scaffold).</li>
            </ul>
            <div class="alert alert-light border mt-3 mb-0 small">
                <i class="bi bi-info-circle text-primary me-1"></i>
                Billing, renewal otomatis, dan UI role/permission lengkap masuk pada Fase 1.1.
            </div>
        </div>
    </div>
</div>
