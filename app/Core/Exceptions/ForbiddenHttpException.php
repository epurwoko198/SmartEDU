<?php
declare(strict_types=1);

namespace App\Core\Exceptions;

final class ForbiddenHttpException extends HttpException
{
    public function __construct(string $message = 'Anda tidak memiliki akses ke halaman ini.')
    {
        parent::__construct($message, 403);
    }
}
