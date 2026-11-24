<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\ValidationException;
use Throwable;

/**
 * Renders PHP templates inside a layout.
 */
final class View
{
    public function __construct(private string $directory)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $content = $this->renderFile($template, $data);

        if ($layout === null) {
            return $content;
        }

        return $this->renderFile($layout, $data + ['content' => $content]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderFile(string $template, array $data): string
    {
        $path = $this->directory . '/' . str_replace('.', '/', $template) . '.php';
        if (!is_file($path)) {
            throw new \RuntimeException('Template tidak ditemukan: ' . $template);
        }

        extract($data, EXTR_SKIP);

        ob_start();
        try {
            require $path;
        } catch (Throwable $throwable) {
            ob_end_clean();
            throw $throwable;
        }

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, string> $errors
     */
    public static function errorList(array $errors): string
    {
        if ($errors === []) {
            return '';
        }

        $items = '';
        foreach ($errors as $message) {
            $items .= '<li>' . e($message) . '</li>';
        }

        return '<ul class="mb-0 ps-3">' . $items . '</ul>';
    }

    /**
     * @param array<string, string> $errors
     */
    public static function fieldError(array $errors, string $field): string
    {
        return isset($errors[$field])
            ? '<div class="invalid-feedback d-block">' . e($errors[$field]) . '</div>'
            : '';
    }

    public static function invalidClass(array $errors, string $field): string
    {
        return isset($errors[$field]) ? ' is-invalid' : '';
    }

    /**
     * Convenience helper for validation failures raised inside controllers.
     *
     * @param array<string, string> $errors
     */
    public static function fail(array $errors): never
    {
        throw new ValidationException($errors);
    }
}
