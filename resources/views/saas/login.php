<?php
$errors = \App\Core\Session::pull('_errors', []);
$email = \App\Core\Session::old('email');
?>
<div class="card-elevated p-4 p-md-4">
    <div class="d-flex align-items-center gap-2 mb-3">
        <span class="kpi-icon bg-soft-primary"><i class="bi bi-shield-lock"></i></span>
        <div>
            <h1 class="h5 fw-bold mb-0">Super Admin Platform</h1>
            <div class="small text-muted-soft">Kelola tenant, paket, dan lisensi SaaS</div>
        </div>
    </div>

    <?php require EDUSMART_ROOT . '/resources/views/partials/flashes.php'; ?>
    <?php if (is_array($errors) && $errors !== []): ?>
        <div class="alert alert-danger"><?= \App\Core\View::errorList($errors) ?></div>
    <?php endif; ?>
    <?php if (!empty($database_offline)): ?>
        <div class="alert alert-warning d-flex align-items-start gap-2" role="alert">
            <i class="bi bi-database-exclamation mt-1"></i>
            <div class="small">
                Database sedang tidak dapat dihubungi. Halaman login tetap tersedia, tetapi
                percobaan masuk akan gagal sampai koneksi database pulih.
            </div>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e(app_url('/saas/login')) ?>" novalidate>
        <?= \App\Core\Csrf::field() ?>
        <div class="mb-3">
            <label class="form-label" for="email">Email</label>
            <input class="form-control" type="email" id="email" name="email" value="<?= e($email) ?>"
                   autocomplete="username" required autofocus>
        </div>
        <div class="mb-4">
            <label class="form-label" for="password">Kata sandi</label>
            <input class="form-control" type="password" id="password" name="password"
                   autocomplete="current-password" required>
        </div>
        <button class="btn btn-primary w-100" type="submit">
            <i class="bi bi-box-arrow-in-right me-1"></i> Masuk
        </button>
    </form>

    <div class="text-center small text-muted-soft mt-3">
        <a href="<?= e(app_url('/')) ?>" class="text-decoration-none">
            <i class="bi bi-arrow-left me-1"></i>Kembali ke beranda
        </a>
    </div>
</div>
