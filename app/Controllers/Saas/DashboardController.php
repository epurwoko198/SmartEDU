<?php
declare(strict_types=1);

namespace App\Controllers\Saas;

use App\Controllers\Controller;
use App\Core\Auth\PlatformAuth;
use App\Core\Exceptions\ForbiddenHttpException;
use App\Core\Exceptions\ValidationException;
use App\Core\License\LicenseEngine;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use Throwable;

final class DashboardController extends Controller
{
    public function index(): Response
    {
        $user = $this->requireUser();
        $core = $this->container->get('core.db');
        $now = date('Y-m-d H:i:s');

        $tenantTotals = $core->selectOne(
            "SELECT
                COUNT(*) AS total,
                SUM(status = 'active') AS active,
                SUM(status = 'suspended') AS suspended
             FROM tenants"
        ) ?? [];

        $licenseTotals = $core->selectOne(
            "SELECT
                SUM(status = 'trial' AND (ends_at IS NULL OR ends_at > ?)) AS trial,
                SUM(status IN ('expired', 'suspended', 'cancelled') OR (ends_at IS NOT NULL AND ends_at <= ?)) AS need_attention
             FROM licenses",
            [$now, $now]
        ) ?? [];

        $revenue = $core->selectOne(
            "SELECT COALESCE(SUM(amount), 0) AS total
             FROM invoices
             WHERE status = 'paid' AND paid_at >= ?",
            [date('Y-m-01 00:00:00')]
        ) ?? [];

        $mrr = $core->selectOne(
            "SELECT COALESCE(SUM(p.price_monthly), 0) AS total
             FROM subscriptions s
             INNER JOIN plans p ON p.id = s.plan_id
             WHERE s.status = 'active'"
        ) ?? [];

        $tenants = $core->select(
            'SELECT t.*, l.status AS license_status, l.ends_at, l.grace_days, l.features,
                    l.max_students, l.max_users, l.ai_quota, p.name AS plan_name
             FROM tenants t
             LEFT JOIN licenses l ON l.tenant_id = t.id
             LEFT JOIN plans p ON p.id = l.plan_id
             ORDER BY t.created_at DESC
             LIMIT 8'
        );

        $engine = $this->container->get('licenses');
        foreach ($tenants as $index => $tenant) {
            $tenants[$index]['license'] = $engine->evaluate($this->licenseRow($tenant));
        }

        return $this->render('saas/dashboard', [
            'title' => 'Dashboard SaaS',
            'area' => 'saas',
            'user' => $user,
            'nav' => 'dashboard',
            'stats' => [
                'tenants' => (int) ($tenantTotals['total'] ?? 0),
                'active' => (int) ($tenantTotals['active'] ?? 0),
                'suspended' => (int) ($tenantTotals['suspended'] ?? 0),
                'trial' => (int) ($licenseTotals['trial'] ?? 0),
                'need_attention' => (int) ($licenseTotals['need_attention'] ?? 0),
                'revenue_month' => (float) ($revenue['total'] ?? 0),
                'mrr' => (float) ($mrr['total'] ?? 0),
            ],
            'tenants' => $tenants,
            'auditEvents' => $this->safeAudit(),
        ]);
    }

    public function tenants(Request $request): Response
    {
        $user = $this->requireUser();
        $search = (string) $request->queryParam('q', '');

        $sql = 'SELECT t.*, l.status AS license_status, l.ends_at, l.grace_days, l.features,
                       l.max_students, l.max_users, l.ai_quota, p.name AS plan_name
                FROM tenants t
                LEFT JOIN licenses l ON l.tenant_id = t.id
                LEFT JOIN plans p ON p.id = l.plan_id';
        $params = [];

        if ($search !== '') {
            $sql .= ' WHERE t.name LIKE ? OR t.slug LIKE ?';
            $params = ['%' . $search . '%', '%' . $search . '%'];
        }

        $sql .= ' ORDER BY t.created_at DESC LIMIT 50';

        $engine = $this->container->get('licenses');
        $tenants = $this->container->get('core.db')->select($sql, $params);
        foreach ($tenants as $index => $tenant) {
            $tenants[$index]['license'] = $engine->evaluate($this->licenseRow($tenant));
        }

        return $this->render('saas/tenants/index', [
            'title' => 'Tenant & Sekolah',
            'area' => 'saas',
            'user' => $user,
            'nav' => 'tenants',
            'tenants' => $tenants,
            'search' => $search,
        ]);
    }

    public function create(): Response
    {
        $user = $this->requireUser();
        if (!$this->container->get('platform.auth')->can($user, 'tenants.view')) {
            throw new ForbiddenHttpException('Anda tidak memiliki izin untuk membuat tenant.');
        }

        return $this->render('saas/tenants/create', [
            'title' => 'Onboarding Sekolah Baru',
            'area' => 'saas',
            'user' => $user,
            'nav' => 'tenants',
            'plans' => $this->container->get('core.db')->select(
                'SELECT * FROM plans WHERE is_active = 1 ORDER BY id ASC'
            ),
        ]);
    }

    public function store(Request $request): Response
    {
        $user = $this->requireUser();
        $this->csrf($request);

        $input = [
            'name' => (string) $request->input('name', ''),
            'slug' => (string) $request->input('slug', ''),
            'level' => (string) $request->input('level', 'SMP'),
            'plan_code' => (string) $request->input('plan_code', 'starter'),
            'trial_days' => (int) $request->input('trial_days', 30),
            'admin_name' => (string) $request->input('admin_name', ''),
            'admin_email' => (string) $request->input('admin_email', ''),
            'admin_password' => (string) ($request->input['admin_password'] ?? ''),
        ];

        try {
            $result = $this->container->get('provisioner')->provision($input);
        } catch (ValidationException $exception) {
            Session::flash('danger', $exception->getMessage());
            Session::put('_errors', $exception->errors());
            Session::flashOld($input);

            return $this->redirect('/saas/tenants/create');
        } catch (Throwable $throwable) {
            Session::flash('danger', 'Provisioning gagal: ' . $throwable->getMessage());
            Session::flashOld($input);

            return $this->redirect('/saas/tenants/create');
        }

        $this->audit($request, [
            'actor_type' => 'platform',
            'actor_id' => (int) $user['id'],
            'tenant_id' => (int) $result['tenant']['id'],
            'action' => 'tenant.provisioned',
            'entity' => 'tenant',
            'entity_id' => (int) $result['tenant']['id'],
            'metadata' => ['slug' => $result['tenant']['slug'], 'database' => $result['database']],
        ]);

        Session::flash('success', sprintf(
            'Sekolah %s berhasil dibuat. Admin: %s, database: %s.',
            $result['tenant']['name'],
            $result['admin_email'],
            $result['database']
        ));

        return $this->redirect('/saas/tenants/' . (int) $result['tenant']['id']);
    }

    public function show(Request $request, string $id): Response
    {
        $user = $this->requireUser();
        $tenantId = (int) $id;
        $core = $this->container->get('core.db');

        $tenant = $core->selectOne('SELECT * FROM tenants WHERE id = ? LIMIT 1', [$tenantId]);
        if ($tenant === null) {
            $this->notFound('Tenant tidak ditemukan.');
        }

        $license = $core->selectOne(
            'SELECT l.*, p.name AS plan_name, p.code AS plan_code
             FROM licenses l INNER JOIN plans p ON p.id = l.plan_id
             WHERE l.tenant_id = ? ORDER BY l.id DESC LIMIT 1',
            [$tenantId]
        );

        $subscription = $core->selectOne(
            'SELECT s.*, p.name AS plan_name FROM subscriptions s
             INNER JOIN plans p ON p.id = s.plan_id
             WHERE s.tenant_id = ? ORDER BY s.id DESC LIMIT 1',
            [$tenantId]
        );

        $usage = ['available' => false, 'students' => 0, 'employees' => 0, 'users' => 0, 'classes' => 0];
        try {
            $tenantDb = $this->container->get('tenant.connector')->connect($tenant);
            $students = $tenantDb->selectOne("SELECT COUNT(*) AS total FROM students WHERE status = 'active'");
            $employees = $tenantDb->selectOne('SELECT COUNT(*) AS total FROM employees WHERE is_active = 1');
            $users = $tenantDb->selectOne('SELECT COUNT(*) AS total FROM users WHERE is_active = 1');
            $classes = $tenantDb->selectOne('SELECT COUNT(*) AS total FROM classes');
            $usage = [
                'available' => true,
                'students' => (int) ($students['total'] ?? 0),
                'employees' => (int) ($employees['total'] ?? 0),
                'users' => (int) ($users['total'] ?? 0),
                'classes' => (int) ($classes['total'] ?? 0),
            ];
        } catch (Throwable) {
            // Usage stays unavailable; the page still renders tenant metadata.
        }

        return $this->render('saas/tenants/show', [
            'title' => $tenant['name'],
            'area' => 'saas',
            'user' => $user,
            'nav' => 'tenants',
            'tenant' => $tenant,
            'license' => $license,
            'evaluation' => $this->container->get('licenses')->evaluate($license),
            'subscription' => $subscription,
            'usage' => $usage,
            'auditEvents' => $this->safeAudit($tenantId),
        ]);
    }

    public function setStatus(Request $request, string $id): Response
    {
        $user = $this->requireUser();
        $this->csrf($request);

        if (!$this->container->get('platform.auth')->can($user, 'tenants.manage')) {
            throw new ForbiddenHttpException('Anda tidak memiliki izin mengubah status tenant.');
        }

        $tenantId = (int) $id;
        $status = (string) $request->input('status', 'active');
        if (!in_array($status, ['active', 'suspended'], true)) {
            Session::flash('danger', 'Status tenant tidak dikenal.');

            return $this->redirect('/saas/tenants/' . $tenantId);
        }

        $core = $this->container->get('core.db');
        $tenant = $core->selectOne('SELECT * FROM tenants WHERE id = ? LIMIT 1', [$tenantId]);
        if ($tenant === null) {
            $this->notFound('Tenant tidak ditemukan.');
        }

        $core->execute('UPDATE tenants SET status = ?, updated_at = ? WHERE id = ?', [
            $status,
            date('Y-m-d H:i:s'),
            $tenantId,
        ]);

        $this->audit($request, [
            'actor_type' => 'platform',
            'actor_id' => (int) $user['id'],
            'tenant_id' => $tenantId,
            'action' => $status === 'active' ? 'tenant.activated' : 'tenant.suspended',
            'entity' => 'tenant',
            'entity_id' => $tenantId,
        ]);

        Session::flash('success', $status === 'active' ? 'Tenant diaktifkan kembali.' : 'Tenant ditangguhkan.');

        return $this->redirect('/saas/tenants/' . $tenantId);
    }

    /**
     * @return array<string, mixed>
     */
    private function requireUser(): array
    {
        /** @var PlatformAuth $auth */
        $auth = $this->container->get('platform.auth');
        $user = $auth->user();

        if ($user === null) {
            throw new ForbiddenHttpException('Silakan masuk sebagai Super Admin.');
        }

        if (!$auth->can($user, 'tenants.view')) {
            throw new ForbiddenHttpException('Role Anda tidak memiliki akses ke area ini.');
        }

        return $user;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    private function licenseRow(array $row): ?array
    {
        if (!isset($row['license_status']) || $row['license_status'] === null) {
            return null;
        }

        return [
            'status' => $row['license_status'],
            'ends_at' => $row['ends_at'] ?? null,
            'grace_days' => $row['grace_days'] ?? 14,
            'features' => $row['features'] ?? null,
            'max_students' => $row['max_students'] ?? 0,
            'max_users' => $row['max_users'] ?? 0,
            'ai_quota' => $row['ai_quota'] ?? 0,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function safeAudit(?int $tenantId = null): array
    {
        try {
            return $this->container->get('audit')->recent(10, $tenantId);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param array<string, mixed> $event
     */
    private function audit(Request $request, array $event): void
    {
        try {
            $this->container->get('audit')->record($event + [
                'ip' => $request->ip,
                'user_agent' => $request->header('User-Agent'),
            ]);
        } catch (Throwable) {
            // Audit failures never block the operation.
        }
    }
}
