<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Exceptions\NotFoundHttpException;
use App\Core\Request;
use App\Core\Response;

abstract class Controller
{
    public function __construct(protected Container $container)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function render(string $template, array $data = [], string $layout = 'layouts/app'): Response
    {
        return Response::html($this->container->get('view')->render($template, $data, $layout));
    }

    protected function redirect(string $path): Response
    {
        return Response::redirect(app_url($path));
    }

    protected function csrf(Request $request): void
    {
        Csrf::verify($request);
    }

    protected function notFound(string $message = 'Data tidak ditemukan.'): never
    {
        throw new NotFoundHttpException($message);
    }
}
