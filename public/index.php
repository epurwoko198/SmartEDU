<?php
declare(strict_types=1);

use App\Core\Config;
use App\Core\Container;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;

/** @var Container $container */
$container = require dirname(__DIR__) . '/app/bootstrap.php';

$request = Request::fromGlobals();

$router = new Router();
require dirname(__DIR__) . '/app/routes.php';
registerRoutes($router, $container);

try {
    $response = $router->dispatch($request->method, $request->path, $request->server);
} catch (HttpException $exception) {
    $response = error_response($container, $exception->statusCode(), $exception->getMessage());
} catch (Throwable $throwable) {
    error_log(sprintf(
        '[edusmart] %s in %s:%d',
        $throwable->getMessage(),
        $throwable->getFile(),
        $throwable->getLine()
    ));
    $response = error_response($container, 500, 'Terjadi kesalahan pada server. Silakan coba lagi.');
}

if (Config::bool('APP_DEBUG', false)) {
    header('X-Debug-Mode: enabled');
}

$response->send();

/**
 * Render a safe error page without leaking internals.
 */
function error_response(Container $container, int $status, string $message): Response
{
    $titles = [
        403 => 'Akses ditolak',
        404 => 'Halaman tidak ditemukan',
        422 => 'Data tidak valid',
        500 => 'Kesalahan server',
        503 => 'Layanan tidak tersedia',
    ];

    try {
        /** @var \App\Core\View $view */
        $view = $container->get('view');
        $html = $view->render('errors/error', [
            'title' => $titles[$status] ?? 'Terjadi kesalahan',
            'status' => $status,
            'message' => $message,
        ], 'layouts/guest');

        return Response::html($html, $status);
    } catch (Throwable) {
        return Response::html(
            '<!doctype html><html lang="id"><head><meta charset="utf-8"><title>' . $status . '</title></head>'
            . '<body style="font-family:sans-serif;padding:3rem"><h1>' . $status . '</h1><p>'
            . htmlspecialchars($message, ENT_QUOTES) . '</p></body></html>',
            $status
        );
    }
}
