<?php
/** @var list<array<string, mixed>> $tenants */
/** @var string $search */
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="small text-muted-soft"><?= count($tenants) ?> sekolah terdaftar</div>
    <div class="d-flex flex-wrap gap-2">
        <form class="d-flex gap-2" method="get" action="<?= e(app_url('/saas/tenants')) ?>">
            <input class="form-control form-control-sm" name="q" value="<?= e($search) ?>" placeholder="Cari nama atau slug">
            <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
        </form>
        <a class="btn btn-sm btn-primary" href="<?= e(app_url('/saas/tenants/create')) ?>">
            <i class="bi bi-plus-lg me-1"></i>Onboarding
        </a>
    </div>
</div>

<div class="panel">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
            <tr>
                <th>Sekolah</th>
                <th>Jenjang</th>
                <th>Paket</th>
                <th>Status</th>
                <th>Lisensi</th>
                <th>Dibuat</th>
                <th class="text-end"></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($tenants === []): ?>
                <tr><td colspan="7"><div class="empty-state">Tidak ada tenant yang cocok.</div></td></tr>
            <?php endif; ?>
            <?php foreach ($tenants as $tenant): ?>
                <?php $license = $tenant['license']; ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e((string) $tenant['name']) ?></div>
                        <div class="small text-muted-soft mono"><?= e((string) $tenant['db_name']) ?></div>
                    </td>
                    <td class="small"><?= e((string) $tenant['level']) ?></td>
                    <td class="small"><?= e((string) ($tenant['plan_name'] ?? '—')) ?></td>
                    <td>
                        <span class="badge text-bg-<?= $tenant['status'] === 'active' ? 'success' : 'secondary' ?>">
                            <?= $tenant['status'] === 'active' ? 'Aktif' : 'Ditangguhkan' ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge text-bg-<?= e(status_badge_class((string) ($license['status'] ?? 'unlicensed'))) ?>">
                            <?= e((string) ($license['label'] ?? 'Tanpa lisensi')) ?>
                        </span>
                        <?php if ($license !== null && $license['days_left'] > 0): ?>
                            <div class="small text-muted-soft"><?= (int) $license['days_left'] ?> hari lagi</div>
                        <?php endif; ?>
                    </td>
                    <td class="small text-muted-soft"><?= e(format_date((string) $tenant['created_at'])) ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(app_url('/saas/tenants/' . (int) $tenant['id'])) ?>">
                            Kelola
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
