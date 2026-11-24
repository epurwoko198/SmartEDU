<?php
declare(strict_types=1);

use App\Core\Config;

if (!function_exists('e')) {
    /**
     * Escape a value for HTML output.
     */
    function e(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('app_url')) {
    function app_url(string $path = ''): string
    {
        $base = rtrim((string) Config::get('APP_URL', ''), '/');
        $path = '/' . ltrim($path, '/');

        return $base . $path;
    }
}

if (!function_exists('tenant_path')) {
    /**
     * @param array<string, mixed> $tenant
     */
    function tenant_path(array $tenant, string $path = ''): string
    {
        $slug = rawurlencode((string) ($tenant['slug'] ?? ''));
        $suffix = $path === '' ? '' : '/' . ltrim($path, '/');

        return app_url('/t/' . $slug . $suffix);
    }
}

if (!function_exists('rupiah')) {
    function rupiah(float|int|string|null $amount): string
    {
        $value = is_numeric($amount) ? (float) $amount : 0.0;

        return 'Rp ' . number_format($value, 0, ',', '.');
    }
}

if (!function_exists('format_date')) {
    function format_date(?string $date, string $format = 'd M Y'): string
    {
        if ($date === null || $date === '' || $date === '0000-00-00') {
            return '-';
        }

        $timestamp = strtotime($date);

        return $timestamp === false ? '-' : date($format, $timestamp);
    }
}

if (!function_exists('initials')) {
    function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $letters = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            if ($part !== '') {
                $letters .= mb_strtoupper(mb_substr($part, 0, 1));
            }
        }

        return $letters === '' ? '?' : $letters;
    }
}

if (!function_exists('status_badge_class')) {
    function status_badge_class(string $status): string
    {
        return match ($status) {
            'active', 'paid', 'approved' => 'success',
            'trial', 'pending', 'grace_period' => 'warning',
            'suspended', 'expired', 'overdue', 'rejected' => 'danger',
            'cancelled' => 'secondary',
            default => 'secondary',
        };
    }
}
