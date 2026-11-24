<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\License\LicenseEngine;
use App\Core\Response;
use Throwable;

final class HomeController extends Controller
{
    public function index(): Response
    {
        return $this->render('home', [
            'title' => 'EDUSMART ERP',
            'modules' => LicenseEngine::MODULE_FEATURES,
        ], 'layouts/guest');
    }

    public function health(): Response
    {
        $database = 'ok';

        try {
            $this->container->get('core.db')->select('SELECT 1 AS ping');
        } catch (Throwable) {
            $database = 'unavailable';
        }

        $healthy = $database === 'ok';

        return Response::json([
            'status' => $healthy ? 'ok' : 'degraded',
            'service' => 'edusmart-erp',
            'database' => $database,
            'time' => date(DATE_ATOM),
        ], $healthy ? 200 : 503);
    }
}
