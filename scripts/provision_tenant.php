<?php
declare(strict_types=1);

/**
 * Usage:
 *   php scripts/provision_tenant.php --name="SMP Negeri 1" --slug=smpn-1 \
 *       --admin-name="Admin Sekolah" --admin-email=admin@smpn1.test \
 *       --admin-password="RahasiaKuat!2026" [--level=SMP] [--plan=starter] [--trial=30]
 */

use App\Core\Container;
use App\Core\Exceptions\ValidationException;

/** @var Container $container */
$container = require __DIR__ . '/../app/bootstrap.php';

$options = getopt('', [
    'name:', 'slug:', 'level::', 'plan::', 'trial::',
    'admin-name:', 'admin-email:', 'admin-password:',
]);

$required = ['name', 'slug', 'admin-name', 'admin-email', 'admin-password'];
foreach ($required as $key) {
    if (!isset($options[$key]) || trim((string) $options[$key]) === '') {
        fwrite(STDERR, "Opsi --{$key} wajib diisi.\n");
        exit(1);
    }
}

$input = [
    'name' => (string) $options['name'],
    'slug' => (string) $options['slug'],
    'level' => (string) ($options['level'] ?? 'SMP'),
    'plan_code' => (string) ($options['plan'] ?? 'starter'),
    'trial_days' => (int) ($options['trial'] ?? 30),
    'admin_name' => (string) $options['admin-name'],
    'admin_email' => (string) $options['admin-email'],
    'admin_password' => (string) $options['admin-password'],
];

try {
    $result = $container->get('provisioner')->provision($input);
} catch (ValidationException $exception) {
    foreach ($exception->errors() as $field => $message) {
        fwrite(STDERR, "- {$field}: {$message}\n");
    }
    exit(1);
} catch (Throwable $throwable) {
    fwrite(STDERR, 'Provisioning gagal: ' . $throwable->getMessage() . "\n");
    exit(1);
}

echo "Tenant berhasil dibuat.\n";
echo '  Sekolah   : ' . $result['tenant']['name'] . "\n";
echo '  Slug      : ' . $result['tenant']['slug'] . "\n";
echo '  Database  : ' . $result['database'] . "\n";
echo '  Admin     : ' . $result['admin_email'] . "\n";
echo '  Login URL : ' . $container->get('tenants')->displayUrl($result['tenant']) . "/login\n";
