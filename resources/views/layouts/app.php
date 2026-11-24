<?php
/** @var string $content */
/** @var array<string, mixed> $user */
/** @var string $area */
$area = $area ?? 'tenant';
$nav = $nav ?? 'dashboard';
$user = $user ?? [];
$pageTitle = $title ?? 'Dashboard';
$isSaas = $area === 'saas';
$tenant = $tenant ?? null;
$licenseInfo = $evaluation ?? null;
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e($pageTitle) ?> · EDUSMART ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= e(app_url('/assets/app.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand-mark" href="<?= e($isSaas ? app_url('/saas') : app_url('/')) ?>">
            <span class="brand-badge">E</span>
            <span>EDUSMART <span class="text-primary-emphasis">ERP</span></span>
        </a>

        <div class="sidebar-label"><?= $isSaas ? 'Platform SaaS' : 'Sekolah' ?></div>
        <nav class="nav flex-column gap-1">
            <?php if ($isSaas): ?>
                <a class="nav-link <?= $nav === 'dashboard' ? 'active' : '' ?>" href="<?= e(app_url('/saas')) ?>">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a class="nav-link <?= $nav === 'tenants' ? 'active' : '' ?>" href="<?= e(app_url('/saas/tenants')) ?>">
                    <i class="bi bi-buildings"></i> Tenant &amp; Sekolah
                </a>
                <a class="nav-link <?= $nav === 'onboarding' ? 'active' : '' ?>" href="<?= e(app_url('/saas/tenants/create')) ?>">
                    <i class="bi bi-plus-circle"></i> Onboarding Sekolah
                </a>
                <span class="nav-link disabled"><i class="bi bi-receipt"></i> Billing &amp; Invoice <span class="badge bg-secondary ms-auto">1.1</span></span>
                <span class="nav-link disabled"><i class="bi bi-key"></i> Lisensi &amp; Renewal <span class="badge bg-secondary ms-auto">1.1</span></span>
                <span class="nav-link disabled"><i class="bi bi-clipboard-data"></i> Audit Platform <span class="badge bg-secondary ms-auto">1.1</span></span>
            <?php else: ?>
                <?php
                $slug = rawurlencode((string) ($tenant['slug'] ?? ''));
                ?>
                <a class="nav-link <?= $nav === 'dashboard' ? 'active' : '' ?>" href="<?= e(app_url('/t/' . $slug . '/dashboard')) ?>">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <span class="nav-link disabled"><i class="bi bi-people"></i> Data Siswa <span class="badge bg-secondary ms-auto">Fase 2</span></span>
                <span class="nav-link disabled"><i class="bi bi-person-vcard"></i> Guru &amp; Pegawai <span class="badge bg-secondary ms-auto">Fase 2</span></span>
                <span class="nav-link disabled"><i class="bi bi-journal-bookmark"></i> Akademik <span class="badge bg-secondary ms-auto">Fase 3</span></span>
                <span class="nav-link disabled"><i class="bi bi-cash-stack"></i> Keuangan <span class="badge bg-secondary ms-auto">Fase 4</span></span>
                <span class="nav-link disabled"><i class="bi bi-envelope-paper"></i> Persuratan <span class="badge bg-secondary ms-auto">Fase 5</span></span>
                <span class="nav-link disabled"><i class="bi bi-robot"></i> AI Assistant <span class="badge bg-secondary ms-auto">Fase 6</span></span>
            <?php endif; ?>
        </nav>

        <div class="sidebar-footer">
            <div class="d-flex align-items-center gap-2 px-2 mb-2">
                <span class="avatar-chip"><?= e(initials((string) ($user['name'] ?? '?'))) ?></span>
                <div class="small lh-sm">
                    <div class="text-white"><?= e((string) ($user['name'] ?? '')) ?></div>
                    <div class="text-secondary"><?= e((string) ($user['email'] ?? '')) ?></div>
                </div>
            </div>
            <form method="post" action="<?= e($isSaas ? app_url('/saas/logout') : app_url('/t/' . rawurlencode((string) ($tenant['slug'] ?? '')) . '/logout')) ?>">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="btn btn-sm btn-outline-light w-100">
                    <i class="bi bi-box-arrow-left me-1"></i> Keluar
                </button>
            </form>
        </div>
    </aside>

    <div class="content">
        <header class="topbar">
            <div class="flex-grow-1">
                <div class="page-title"><?= e($pageTitle) ?></div>
                <div class="small text-muted-soft">
                    <?php if ($isSaas): ?>
                        Area platform · kelola tenant, paket, dan lisensi
                    <?php else: ?>
                        <?= e((string) ($tenant['name'] ?? 'Sekolah')) ?> · <?= e((string) ($tenant['level'] ?? '')) ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!$isSaas && $licenseInfo !== null): ?>
                <span class="badge rounded-pill text-bg-<?= e(status_badge_class((string) $licenseInfo['status'])) ?>">
                    <?= e((string) $licenseInfo['label']) ?>
                </span>
            <?php endif; ?>
            <a class="btn btn-sm btn-outline-secondary d-none d-md-inline-flex" href="<?= e(app_url('/')) ?>">
                <i class="bi bi-globe2 me-1"></i> Situs
            </a>
        </header>

        <div class="page-body">
            <?php require EDUSMART_ROOT . '/resources/views/partials/flashes.php'; ?>
            <?= $content ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
