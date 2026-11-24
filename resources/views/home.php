<?php
/** @var array<string, string> $modules */
$icons = [
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
<section class="hero py-5">
    <div class="container py-4 py-lg-5">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <span class="eyebrow"><i class="bi bi-stars"></i> Fondasi SaaS Fase 1</span>
                <h1 class="display-5 mt-3 mb-3">
                    Administrasi sekolah rapi,<br>terisolasi, dan teraudit.
                </h1>
                <p class="lead text-muted-soft mb-4" style="max-width: 560px">
                    EDUSMART ERP menyatukan PPDB, data induk, akademik, keuangan, tata usaha,
                    dan kelulusan dalam satu platform multi-sekolah — dengan database terpisah
                    untuk setiap sekolah.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="#masuk" class="btn btn-primary btn-lg">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Masuk ke sekolah
                    </a>
                    <a href="<?= e(app_url('/saas/login')) ?>" class="btn btn-outline-secondary btn-lg">
                        Super Admin SaaS
                    </a>
                </div>
                <div class="d-flex flex-wrap gap-4 mt-4 small text-muted-soft">
                    <span><i class="bi bi-shield-check text-success me-1"></i>RBAC di sisi server</span>
                    <span><i class="bi bi-hdd-network text-primary me-1"></i>DB per tenant</span>
                    <span><i class="bi bi-patch-check text-warning me-1"></i>License engine</span>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card-elevated p-4" id="masuk">
                    <h2 class="h5 fw-bold mb-1">Masuk sebagai sekolah</h2>
                    <p class="small text-muted-soft mb-3">
                        Masukkan slug sekolah yang diberikan saat onboarding.
                    </p>
                    <form method="get" action="<?= e(app_url('/')) ?>" id="school-login-form"
                          onsubmit="event.preventDefault();
                              const slug = this.slug.value.trim().toLowerCase();
                              if (!slug) { this.slug.focus(); return; }
                              window.location.href = '<?= e(app_url('/t')) ?>/' + encodeURIComponent(slug) + '/login';">
                        <label class="form-label" for="slug">Slug sekolah</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light mono">/t/</span>
                            <input class="form-control" id="slug" name="slug" placeholder="sman-1-contoh"
                                   autocomplete="off" required pattern="[a-z0-9\-]+">
                            <button class="btn btn-primary" type="submit">Lanjut</button>
                        </div>
                    </form>
                    <hr class="my-4">
                    <div class="small text-muted-soft">
                        <div class="d-flex gap-2 mb-2">
                            <i class="bi bi-info-circle text-primary"></i>
                            <span>Belum punya akun sekolah? Super Admin dapat membuat tenant baru
                                melalui wizard onboarding.</span>
                        </div>
                        <div class="d-flex gap-2">
                            <i class="bi bi-lock text-primary"></i>
                            <span>Setiap sekolah memiliki database terpisah sehingga data tidak
                                bercampur antar-tenant.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5 bg-white border-bottom">
    <div class="container">
        <div class="row g-4 text-center">
            <div class="col-6 col-lg-3">
                <div class="kpi-value"><?= count($modules) ?></div>
                <div class="small text-muted-soft">Modul dalam roadmap</div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="kpi-value">1 : 1</div>
                <div class="small text-muted-soft">Sekolah : database</div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="kpi-value">40+</div>
                <div class="small text-muted-soft">Permission granular</div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="kpi-value">100%</div>
                <div class="small text-muted-soft">Aksi sensitif teraudit</div>
            </div>
        </div>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="row mb-4">
            <div class="col-lg-8">
                <span class="eyebrow">Modul</span>
                <h2 class="h3 fw-bold mt-3 mb-2">Satu alur kerja, dari pendaftaran hingga kelulusan</h2>
                <p class="text-muted-soft mb-0">
                    Fase 1 menyiapkan fondasi: autentikasi, isolasi tenant, RBAC, lisensi, audit,
                    dan provisioning sekolah. Modul operasional dibangun bertahap di atas fondasi ini.
                </p>
            </div>
        </div>
        <div class="row g-3">
            <?php foreach ($modules as $code => $name): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="module-tile d-flex gap-3">
                        <span class="module-icon"><i class="bi <?= e($icons[$code] ?? 'bi-grid') ?>"></i></span>
                        <div>
                            <div class="fw-semibold"><?= e($name) ?></div>
                            <div class="small text-muted-soft">
                                <?php
                                $descriptions = [
                                    'ppdb' => 'Pendaftaran online, verifikasi berkas, seleksi, dan daftar ulang.',
                                    'master_data' => 'Siswa, wali, guru, kelas, mata pelajaran, dan tahun ajaran.',
                                    'academic' => 'Jadwal, absensi, penilaian, rapor, dan kenaikan kelas.',
                                    'finance' => 'Tagihan, SPP, pembayaran, tunggakan, dan laporan kas.',
                                    'letters' => 'Surat masuk/keluar, disposisi, arsip, dan nomor surat.',
                                    'graduation' => 'Validasi kelulusan dan sertifikat dengan QR verifikasi.',
                                    'communication' => 'Portal siswa/orang tua, pengumuman, dan notifikasi.',
                                    'analytics' => 'Dashboard KPI, tren per tahun ajaran, ekspor laporan.',
                                    'ai' => 'Asisten AI dengan konteks tenant dan kontrol akses.',
                                ];
                                echo e($descriptions[$code] ?? '');
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="py-5 bg-white border-top">
    <div class="container">
        <div class="row g-4 align-items-center">
            <div class="col-lg-5">
                <span class="eyebrow">Cara kerja</span>
                <h2 class="h3 fw-bold mt-3">Onboarding sekolah dalam satu wizard</h2>
                <p class="text-muted-soft">
                    Super Admin membuat tenant, sistem menyiapkan database terpisah, menjalankan
                    migrasi, menanam role default, dan membuat akun admin sekolah pertama.
                </p>
            </div>
            <div class="col-lg-7">
                <div class="row g-3">
                    <div class="col-sm-4">
                        <div class="card-elevated p-3 h-100">
                            <div class="badge text-bg-primary mb-2">01</div>
                            <div class="fw-semibold small">Daftarkan sekolah</div>
                            <div class="small text-muted-soft">Nama, jenjang, slug, dan paket.</div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="card-elevated p-3 h-100">
                            <div class="badge text-bg-primary mb-2">02</div>
                            <div class="fw-semibold small">Provisioning otomatis</div>
                            <div class="small text-muted-soft">Database, migrasi, role, dan admin.</div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="card-elevated p-3 h-100">
                            <div class="badge text-bg-primary mb-2">03</div>
                            <div class="fw-semibold small">Sekolah siap dipakai</div>
                            <div class="small text-muted-soft">Login lewat <span class="mono">/t/&lt;slug&gt;</span>.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
