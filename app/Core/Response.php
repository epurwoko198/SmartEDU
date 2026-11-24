<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly string $body = '',
        public readonly int $status = 200,
        public readonly array $headers = []
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function redirect(string $location, int $status = 303): self
    {
        return new self('', $status, ['Location' => $location]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function json(array $data, int $status = 200): self
    {
        $encoded = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return new self($encoded === false ? '{}' : $encoded, $status, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value, true);
        }

        if (!headers_sent() && !isset($this->headers['Cache-Control']) && $this->status >= 400) {
            header('Cache-Control: no-store');
        }

        if ($this->body !== '') {
            echo $this->body;
        }
    }

    public function header(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }
}
