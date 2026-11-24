<?php
declare(strict_types=1);

/**
 * Seeds the demo environment:
 *   php scripts/seed_demo.php
 *
 * Credentials come from DEMO_* environment variables with safe defaults that
 * must be changed before any public deployment.
 */

use App\Core\Config;
use App\Core\Container;
use App\Services\DemoDataSeeder;

/** @var Container $container */
$container = require __DIR__ . '/../app/bootstrap.php';

$core = $container->get('core.db');

echo "Menjalankan migrasi core...\n";
$applied = $container->get('migrations.core')->run($container->get('provision.db'));
echo $applied === [] ? "  sudah terbaru\n" : '  diterapkan: ' . implode(', ', $applied) . "\n";

echo "Menanam paket langganan...\n";
$plans = require __DIR__ . '/../database/seeds/plans.php';
foreach ($plans as $plan) {
    $core->execute(
        'INSERT INTO plans (code, name, price_monthly, features, max_students, max_users, ai_quota, is_active, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)
         ON DUPLICATE KEY UPDATE name = VALUES(name), price_monthly = VALUES(price_monthly),
             features = VALUES(features), max_students = VALUES(max_students),
             max_users = VALUES(max_users), ai_quota = VALUES(ai_quota)',
        [
            $plan['code'],
            $plan['name'],
            $plan['price_monthly'],
            json_encode($plan['features']),
            $plan['max_students'],
            $plan['max_users'],
            $plan['ai_quota'],
            date('Y-m-d H:i:s'),
        ]
    );
}
echo '  ' . count($plans) . " paket\n";

$saasEmail = (string) Config::get('DEMO_SAAS_EMAIL', 'admin@edusmart.local');
$saasPassword = (string) Config::get('DEMO_SAAS_PASSWORD', 'ChangeMe!123456');

$existingAdmin = $core->selectOne('SELECT id FROM platform_users WHERE email = ? LIMIT 1', [strtolower($saasEmail)]);
if ($existingAdmin === null) {
    $core->insert(
        'INSERT INTO platform_users (name, email, password_hash, role, is_active, created_at)
         VALUES (?, ?, ?, ?, 1, ?)',
        ['Super Admin', strtolower($saasEmail), password_hash($saasPassword, PASSWORD_DEFAULT), 'super_admin', date('Y-m-d H:i:s')]
    );
    echo "Super Admin dibuat: {$saasEmail}\n";
} else {
    echo "Super Admin sudah ada: {$saasEmail}\n";
}

$tenantSlug = (string) Config::get('DEMO_TENANT_SLUG', 'demo');
$tenant = $container->get('tenants')->findBySlug($tenantSlug);

if ($tenant === null) {
    echo "Membuat tenant demo '{$tenantSlug}'...\n";
    try {
        $result = $container->get('provisioner')->provision([
            'name' => 'SMP Demo EDUSMART',
            'slug' => $tenantSlug,
            'level' => 'SMP',
            'plan_code' => 'professional',
            'trial_days' => 45,
            'admin_name' => 'Administrator Sekolah',
            'admin_email' => (string) Config::get('DEMO_TENANT_EMAIL', 'sekolah@demo.edusmart.local'),
            'admin_password' => (string) Config::get('DEMO_TENANT_PASSWORD', 'ChangeMe!123456'),
        ]);
        $tenant = $result['tenant'];
        echo '  database: ' . $result['database'] . "\n";
    } catch (Throwable $throwable) {
        fwrite(STDERR, 'Gagal membuat tenant demo: ' . $throwable->getMessage() . "\n");
        exit(1);
    }
} else {
    echo "Tenant demo sudah ada.\n";
}

echo "Menanam data sintetis sekolah...\n";
$tenantDb = $container->get('tenant.connector')->connect($tenant);
$migrations = $container->get('migrations.tenant')->run($container->get('tenant.connector')->connectForProvisioning($tenant));
if ($migrations !== []) {
    echo '  migrasi tenant: ' . implode(', ', $migrations) . "\n";
}

$summary = (new DemoDataSeeder())->seed($tenantDb);
if ($summary['skipped']) {
    echo '  data demo sudah ada (' . $summary['students'] . " siswa)\n";
} else {
    echo sprintf(
        "  %d siswa, %d rombel, %d pegawai, %d pendaftar PPDB\n",
        $summary['students'],
        $summary['classes'],
        $summary['employees'],
        $summary['applicants']
    );
}

echo "\nSelesai. Kredensial demo:\n";
echo "  SaaS Super Admin : {$saasEmail} / {$saasPassword}\n";
echo '  Admin sekolah    : ' . Config::get('DEMO_TENANT_EMAIL', 'sekolah@demo.edusmart.local') . ' / ' . Config::get('DEMO_TENANT_PASSWORD', 'ChangeMe!123456') . "\n";
echo "  Staf demo        : guru@demo.edusmart.local, bendahara@demo.edusmart.local, tu@demo.edusmart.local / DemoPass!123456\n";
echo '  URL sekolah      : ' . $container->get('tenants')->displayUrl($tenant) . "/login\n";
echo "\nGanti seluruh kredensial demo sebelum digunakan di lingkungan nyata.\n";
