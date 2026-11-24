<?php
declare(strict_types=1);

/**
 * Usage:
 *   php scripts/migrate.php core
 *   php scripts/migrate.php tenant --slug=demo
 *   php scripts/migrate.php tenant --all
 */

use App\Core\Container;
use App\Core\Exceptions\HttpException;

/** @var Container $container */
$container = require __DIR__ . '/../app/bootstrap.php';

$target = $argv[1] ?? 'core';

if ($target === 'core') {
    $applied = $container->get('migrations.core')->run($container->get('provision.db'));
    echo $applied === []
        ? "Core database sudah pada versi terbaru.\n"
        : 'Migrasi core diterapkan: ' . implode(', ', $applied) . "\n";
    exit(0);
}

if ($target !== 'tenant') {
    fwrite(STDERR, "Target tidak dikenal. Gunakan: core | tenant\n");
    exit(1);
}

$options = getopt('', ['slug::', 'all']);
$tenants = [];

if (isset($options['all'])) {
    $tenants = $container->get('core.db')->select('SELECT * FROM tenants ORDER BY id ASC');
    if ($tenants === []) {
        echo "Belum ada tenant.\n";
        exit(0);
    }
} else {
    $slug = (string) ($options['slug'] ?? '');
    if ($slug === '') {
        fwrite(STDERR, "Sertakan --slug=<slug> atau --all.\n");
        exit(1);
    }

    $tenant = $container->get('tenants')->findBySlug(strtolower($slug));
    if ($tenant === null) {
        fwrite(STDERR, "Tenant dengan slug '{$slug}' tidak ditemukan.\n");
        exit(1);
    }

    $tenants = [$tenant];
}

$exitCode = 0;
foreach ($tenants as $tenant) {
    try {
        $db = $container->get('tenant.connector')->connectForProvisioning($tenant);
        $applied = $container->get('migrations.tenant')->run($db);
        echo sprintf(
            "[%s] %s\n",
            $tenant['slug'],
            $applied === [] ? 'sudah terbaru' : 'migrasi: ' . implode(', ', $applied)
        );
    } catch (Throwable $throwable) {
        fwrite(STDERR, sprintf("[%s] gagal: %s\n", $tenant['slug'] ?? '?', $throwable->getMessage()));
        $exitCode = 1;
    }
}

exit($exitCode);
