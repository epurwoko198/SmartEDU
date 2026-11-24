<?php
/** @var string $content */
/** @var string $title */
$pageTitle = $title ?? 'Masuk';
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
<body class="public-shell bg-light">
<div class="auth-wrap">
    <div class="container">
        <div class="row justify-content-center align-items-stretch g-4">
            <div class="col-lg-5 d-none d-lg-block">
                <div class="auth-visual h-100">
                    <div>
                        <a class="brand-mark text-white mb-4 d-inline-flex" href="<?= e(app_url('/')) ?>">
                            <span class="brand-badge">E</span>
                            <span>EDUSMART ERP</span>
                        </a>
                        <h4 class="fw-bold mt-4 mb-2" style="color:#fff">Satu platform untuk seluruh administrasi sekolah.</h4>
                        <p class="small mb-4" style="color:#a5b4fc">
                            Fondasi SaaS dengan isolasi data per sekolah, kontrol akses berbasis peran,
                            dan mesin lisensi yang teraudit.
                        </p>
                    </div>
                    <ul>
                        <li><i class="bi bi-database-check"></i><span>Database terpisah untuk setiap sekolah</span></li>
                        <li><i class="bi bi-person-badge"></i><span>RBAC granular per modul dan aksi</span></li>
                        <li><i class="bi bi-patch-check"></i><span>Lisensi, trial, dan masa tenggang terkendali</span></li>
                        <li><i class="bi bi-journal-text"></i><span>Audit trail untuk aksi sensitif</span></li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-5 col-md-8 d-flex align-items-center">
                <div class="w-100">
                    <?= $content ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
