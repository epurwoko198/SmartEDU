<?php
declare(strict_types=1);

namespace App\Core\Exceptions;

final class NotFoundHttpException extends HttpException
{
    public function __construct(string $message = 'Halaman tidak ditemukan.')
    {
        parent::__construct($message, 404);
    }
}
