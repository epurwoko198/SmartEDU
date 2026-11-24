<?php
/** @var string $content */
/** @var string $title */
$pageTitle = $title ?? 'EDUSMART ERP';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · EDUSMART ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= e(app_url('/assets/app.css')) ?>" rel="stylesheet">
</head>
<body class="public-shell">
<nav class="navbar public-nav py-2">
    <div class="container">
        <a class="brand-mark" href="<?= e(app_url('/')) ?>">
            <span class="brand-badge">E</span>
            <span>EDUSMART <span class="text-primary">ERP</span></span>
        </a>
        <div class="d-flex align-items-center gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(app_url('/saas/login')) ?>">
                <i class="bi bi-shield-lock me-1"></i>Super Admin
            </a>
            <a class="btn btn-sm btn-primary" href="<?= e(app_url('/#masuk')) ?>">Masuk Sekolah</a>
        </div>
    </div>
</nav>

<main class="flex-grow-1">
    <?= $content ?>
</main>

<footer class="border-top bg-white py-3">
    <div class="container d-flex flex-wrap justify-content-between align-items-center gap-2 small text-muted">
        <span>&copy; <?= date('Y') ?> EDUSMART ERP — Platform manajemen sekolah multi-tenant.</span>
        <span>Fase 1 · Fondasi SaaS, tenant, RBAC, dan lisensi</span>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
