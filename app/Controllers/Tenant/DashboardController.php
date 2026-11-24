<?php
declare(strict_types=1);

namespace App\Controllers\Tenant;

use App\Controllers\Controller;
use App\Core\Auth\Rbac;
use App\Core\Auth\TenantAuth;
use App\Core\Contracts\ConnectionInterface;
use App\Core\Exceptions\ForbiddenHttpException;
use App\Core\License\LicenseEngine;
use App\Core\Request;
use App\Core\Response;
use Throwable;

final class DashboardController extends Controller
{
    /**
     * @param array<string, mixed> $tenant
     */
    public function __construct(
        \App\Core\Container $container,
        private array $tenant
    ) {
        parent::__construct($container);
    }

    public function entry(): Response
    {
        return $this->index(Request::fromGlobals());
    }

    public function index(Request $request): Response
    {
        if (($this->tenant['status'] ?? '') !== 'active') {
            $html = $this->container->get('view')->render('tenant/suspended', [
                'title' => 'Akun sekolah ditangguhkan',
                'tenant' => $this->tenant,
            ], 'layouts/auth');

            return Response::html($html, 403);
        }

        $db = $this->container->get('tenant.connector')->connect($this->tenant);
        $auth = new TenantAuth($db, $this->tenant);

        if (!$auth->check()) {
            return Response::redirect(app_url('/t/' . rawurlencode((string) $this->tenant['slug']) . '/login'));
        }

        $user = $auth->user();
        $license = $this->licenseRow();
        $evaluation = $this->container->get('licenses')->evaluate($license);

        if ($evaluation['blocked']) {
            return Response::redirect(app_url('/t/' . rawurlencode((string) $this->tenant['slug']) . '/license'));
        }

        $permissions = (new Rbac($db))->permissionsForUser((int) $user['id']);
        if (!Rbac::allows($permissions, 'dashboard.view')) {
            throw new ForbiddenHttpException('Role Anda belum memiliki izin melihat dashboard.');
        }

        $stats = $this->stats($db);
        $modules = $this->modules($evaluation);

        return $this->render('tenant/dashboard', [
            'title' => 'Dashboard Sekolah',
            'area' => 'tenant',
            'nav' => 'dashboard',
            'user' => $user,
            'tenant' => $this->tenant,
            'license' => $license,
            'evaluation' => $evaluation,
            'permissions' => $permissions,
            'stats' => $stats,
            'modules' => $modules,
            'profile' => $this->schoolProfile($db),
            'recentAudit' => $this->recentAudit($db),
        ]);
    }

    public function license(): Response
    {
        $db = $this->container->get('tenant.connector')->connect($this->tenant);
        $auth = new TenantAuth($db, $this->tenant);

        if (!$auth->check()) {
            return Response::redirect(app_url('/t/' . rawurlencode((string) $this->tenant['slug']) . '/login'));
        }

        return $this->render('tenant/license-blocked', [
            'title' => 'Lisensi tidak aktif',
            'tenant' => $this->tenant,
            'evaluation' => $this->container->get('licenses')->evaluate($this->licenseRow()),
        ], 'layouts/auth');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function licenseRow(): ?array
    {
        $row = $this->container->get('core.db')->selectOne(
            'SELECT l.*, p.name AS plan_name, p.code AS plan_code
             FROM licenses l INNER JOIN plans p ON p.id = l.plan_id
             WHERE l.tenant_id = ? ORDER BY l.id DESC LIMIT 1',
            [(int) $this->tenant['id']]
        );

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function stats(ConnectionInterface $db): array
    {
        $defaults = [
            'available' => false,
            'students' => 0,
            'employees' => 0,
            'classes' => 0,
            'applicants' => 0,
            'outstanding' => 0.0,
            'unpaid_invoices' => 0,
            'class_distribution' => [],
        ];

        try {
            $students = $db->selectOne("SELECT COUNT(*) AS total FROM students WHERE status = 'active'");
            $employees = $db->selectOne('SELECT COUNT(*) AS total FROM employees WHERE is_active = 1');
            $classes = $db->selectOne('SELECT COUNT(*) AS total FROM classes');
            $applicants = $db->selectOne("SELECT COUNT(*) AS total FROM applicants WHERE status IN ('new', 'verified')");
            $outstanding = $db->selectOne(
                "SELECT COALESCE(SUM(amount - paid_amount), 0) AS total, COUNT(*) AS invoices
                 FROM invoices WHERE status <> 'paid'"
            );
            $distribution = $db->select(
                'SELECT c.name, COUNT(s.id) AS total
                 FROM classes c
                 LEFT JOIN students s ON s.class_id = c.id AND s.status = \'active\'
                 GROUP BY c.id, c.name
                 ORDER BY c.name ASC
                 LIMIT 12'
            );

            return [
                'available' => true,
                'students' => (int) ($students['total'] ?? 0),
                'employees' => (int) ($employees['total'] ?? 0),
                'classes' => (int) ($classes['total'] ?? 0),
                'applicants' => (int) ($applicants['total'] ?? 0),
                'outstanding' => (float) ($outstanding['total'] ?? 0),
                'unpaid_invoices' => (int) ($outstanding['invoices'] ?? 0),
                'class_distribution' => array_map(
                    static fn (array $row): array => [
                        'name' => (string) $row['name'],
                        'total' => (int) $row['total'],
                    ],
                    $distribution
                ),
            ];
        } catch (Throwable) {
            return $defaults;
        }
    }

    /**
     * @param array<string, mixed> $evaluation
     * @return list<array{code: string, name: string, enabled: bool}>
     */
    private function modules(array $evaluation): array
    {
        $engine = $this->container->get('licenses');
        $modules = [];

        foreach (LicenseEngine::MODULE_FEATURES as $code => $name) {
            $modules[] = [
                'code' => $code,
                'name' => $name,
                'enabled' => $engine->hasFeature($evaluation, $code),
            ];
        }

        return $modules;
    }

    /**
     * @return array<string, mixed>
     */
    private function schoolProfile(ConnectionInterface $db): array
    {
        try {
            return $db->selectOne('SELECT * FROM school_profile ORDER BY id ASC LIMIT 1') ?? [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentAudit(ConnectionInterface $db): array
    {
        try {
            return (new \App\Core\Audit\AuditLog($db, false))->recent(8);
        } catch (Throwable) {
            return [];
        }
    }
}
