<?php
/** @var list<array<string, mixed>> $plans */
$errors = \App\Core\Session::pull('_errors', []);
$errors = is_array($errors) ? $errors : [];
$old = static fn (string $key, string $default = ''): string => \App\Core\Session::old($key, $default);
?>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="panel p-4">
            <h2 class="h6 fw-bold mb-1">Profil sekolah</h2>
            <p class="small text-muted-soft mb-4">
                Data ini membuat tenant baru, database terpisah, role default, dan akun admin sekolah.
            </p>

            <?php if ($errors !== []): ?>
                <div class="alert alert-danger"><?= \App\Core\View::errorList($errors) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= e(app_url('/saas/tenants')) ?>" autocomplete="off">
                <?= \App\Core\Csrf::field() ?>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" for="name">Nama sekolah</label>
                        <input class="form-control<?= e(\App\Core\View::invalidClass($errors, 'name')) ?>" id="name" name="name"
                               value="<?= e($old('name')) ?>" placeholder="SMP Negeri 1 Contoh" required>
                        <?= \App\Core\View::fieldError($errors, 'name') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="level">Jenjang</label>
                        <select class="form-select" id="level" name="level">
                            <?php foreach (['TK', 'SD', 'SMP', 'SMA', 'SMK', 'SLB', 'Pesantren'] as $level): ?>
                                <option value="<?= e($level) ?>" <?= $old('level', 'SMP') === $level ? 'selected' : '' ?>>
                                    <?= e($level) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label" for="slug">Slug (URL sekolah)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light mono">/t/</span>
                            <input class="form-control<?= e(\App\Core\View::invalidClass($errors, 'slug')) ?>" id="slug" name="slug"
                                   value="<?= e($old('slug')) ?>" placeholder="smpn-1-contoh"
                                   pattern="[a-z0-9][a-z0-9\-]{0,61}[a-z0-9]" required>
                        </div>
                        <?= \App\Core\View::fieldError($errors, 'slug') ?>
                        <div class="form-text">Huruf kecil, angka, dan tanda hubung. Tidak dapat diubah setelah dibuat.</div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="plan_code">Paket</label>
                        <select class="form-select" id="plan_code" name="plan_code">
                            <?php foreach ($plans as $plan): ?>
                                <option value="<?= e((string) $plan['code']) ?>" <?= $old('plan_code', 'starter') === $plan['code'] ? 'selected' : '' ?>>
                                    <?= e((string) $plan['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="trial_days">Masa trial (hari)</label>
                        <input class="form-control" type="number" id="trial_days" name="trial_days" min="0" max="365"
                               value="<?= e($old('trial_days', '30')) ?>">
                        <div class="form-text">Isi 0 untuk langsung aktif.</div>
                    </div>
                </div>

                <hr class="my-4">
                <h3 class="h6 fw-bold mb-3">Admin sekolah pertama</h3>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="admin_name">Nama admin</label>
                        <input class="form-control<?= e(\App\Core\View::invalidClass($errors, 'admin_name')) ?>" id="admin_name"
                               name="admin_name" value="<?= e($old('admin_name')) ?>" required>
                        <?= \App\Core\View::fieldError($errors, 'admin_name') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="admin_email">Email admin</label>
                        <input class="form-control<?= e(\App\Core\View::invalidClass($errors, 'admin_email')) ?>" type="email"
                               id="admin_email" name="admin_email" value="<?= e($old('admin_email')) ?>" required>
                        <?= \App\Core\View::fieldError($errors, 'admin_email') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="admin_password">Kata sandi awal</label>
                        <input class="form-control<?= e(\App\Core\View::invalidClass($errors, 'admin_password')) ?>" type="password"
                               id="admin_password" name="admin_password" minlength="12" required
                               autocomplete="new-password">
                        <?= \App\Core\View::fieldError($errors, 'admin_password') ?>
                        <div class="form-text">Minimal 12 karakter. Minta admin segera menggantinya.</div>
                    </div>
                </div>

                <div class="d-flex gap-2 mt-4">
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-hdd-network me-1"></i>Provisioning sekolah
                    </button>
                    <a class="btn btn-outline-secondary" href="<?= e(app_url('/saas/tenants')) ?>">Batal</a>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="panel p-4 h-100">
            <div class="panel-title mb-3">Yang terjadi saat provisioning</div>
            <ol class="small text-muted-soft ps-3 mb-4" style="line-height:1.9">
                <li>Database <span class="mono">edusmart_t_&lt;slug&gt;</span> dibuat khusus untuk sekolah.</li>
                <li>Seluruh migrasi skema tenant dijalankan dan dicatat pada <span class="mono">schema_migrations</span>.</li>
                <li>Role, permission, profil sekolah, dan tahun ajaran awal ditanam.</li>
                <li>Akun admin sekolah dibuat dan diberi role <span class="mono">school_super_admin</span>.</li>
                <li>Lisensi trial/aktif dan subscription dicatat pada database core.</li>
                <li>Audit event <span class="mono">tenant.provisioned</span> disimpan.</li>
            </ol>
            <div class="alert alert-warning small mb-0">
                <i class="bi bi-exclamation-triangle me-1"></i>
                Provisioning membutuhkan akun database provisioning (<span class="mono">PROVISION_DB_USER</span>)
                yang boleh membuat database dan menjalankan DDL.
            </div>
        </div>
    </div>
</div>
