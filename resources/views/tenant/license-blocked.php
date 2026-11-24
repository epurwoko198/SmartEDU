<?php
/** @var array<string, mixed> $tenant */
/** @var array<string, mixed> $evaluation */
?>
<div class="card-elevated p-4 text-center">
    <div class="kpi-icon bg-soft-danger mx-auto mb-3" style="width:56px;height:56px;font-size:1.4rem">
        <i class="bi bi-shield-x"></i>
    </div>
    <h1 class="h5 fw-bold mb-2">Akses sekolah dibatasi</h1>
    <p class="small text-muted-soft mb-3">
        Lisensi <strong><?= e((string) $tenant['name']) ?></strong> berstatus
        <span class="badge text-bg-<?= e(status_badge_class((string) $evaluation['status'])) ?>">
            <?= e((string) $evaluation['label']) ?>
        </span>.
    </p>
    <p class="small text-muted-soft">
        Akses baca dihentikan sementara sampai lisensi diperpanjang atau sekolah diaktifkan kembali.
        Hubungi penyelenggara/platform untuk tindak lanjut.
    </p>
    <div class="d-flex justify-content-center gap-2 mt-3">
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(app_url('/')) ?>">Beranda</a>
        <form method="post" action="<?= e(app_url('/t/' . rawurlencode((string) $tenant['slug']) . '/logout')) ?>">
            <?= \App\Core\Csrf::field() ?>
            <button class="btn btn-primary btn-sm" type="submit">Keluar</button>
        </form>
    </div>
</div>
