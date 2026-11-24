<?php
/** @var int $status */
/** @var string $message */
$icons = [
    403 => 'bi-shield-lock',
    404 => 'bi-compass',
    422 => 'bi-exclamation-circle',
    500 => 'bi-tools',
    503 => 'bi-hourglass-split',
];
$icon = $icons[$status] ?? 'bi-exclamation-triangle';
?>
<section class="d-flex align-items-center" style="min-height: 70vh;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="card-elevated p-4 p-md-5 text-center">
                    <div class="kpi-icon bg-soft-primary mx-auto mb-3" style="width:56px;height:56px;font-size:1.5rem">
                        <i class="bi <?= e($icon) ?>"></i>
                    </div>
                    <div class="display-5 fw-bold text-dark mb-2"><?= (int) $status ?></div>
                    <h1 class="h4 fw-bold mb-3"><?= e($title ?? 'Terjadi kesalahan') ?></h1>
                    <p class="text-muted-soft mb-4"><?= e($message) ?></p>
                    <div class="d-flex justify-content-center gap-2">
                        <a class="btn btn-outline-secondary" href="javascript:history.back()">
                            <i class="bi bi-arrow-left me-1"></i> Kembali
                        </a>
                        <a class="btn btn-primary" href="<?= e(app_url('/')) ?>">Beranda</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
