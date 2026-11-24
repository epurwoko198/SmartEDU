<?php
/** @var array<string, mixed> $tenant */
?>
<div class="card-elevated p-4 text-center">
    <div class="kpi-icon bg-soft-warning mx-auto mb-3" style="width:56px;height:56px;font-size:1.4rem">
        <i class="bi bi-pause-octagon"></i>
    </div>
    <h1 class="h5 fw-bold mb-2">Akun sekolah ditangguhkan</h1>
    <p class="small text-muted-soft mb-3">
        Akses untuk <strong><?= e((string) $tenant['name']) ?></strong> sedang dinonaktifkan oleh
        administrator platform.
    </p>
    <p class="small text-muted-soft">
        Data sekolah tetap tersimpan dan tidak dihapus. Hubungi Super Admin SaaS untuk
        mengaktifkan kembali akun ini.
    </p>
    <a class="btn btn-primary btn-sm mt-3" href="<?= e(app_url('/')) ?>">Kembali ke beranda</a>
</div>
