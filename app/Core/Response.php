<?php

declare(strict_types=1);

namespace App\Core;

/**
 * HTTP Response builder for HTML, unified JSON API, redirects, and binary downloads.
 */
final class Response
{
    private int $statusCode;

    /** @var array<string, string> */
    private array $headers;

    private string $body;

    private ?string $filePath = null;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(string $body = '', int $statusCode = 200, array $headers = [])
    {
        $this->body = $body;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    /**
     * Create an HTML response.
     *
     * @param array<string, string> $headers
     */
    public static function html(string $html, int $statusCode = 200, array $headers = []): self
    {
        $headers['Content-Type'] = $headers['Content-Type'] ?? 'text/html; charset=UTF-8';
        return new self($html, $statusCode, $headers);
    }

    /**
     * Create a raw JSON response.
     *
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    public static function json(array $payload, int $statusCode = 200, array $headers = []): self
    {
        $headers['Content-Type'] = 'application/json; charset=UTF-8';
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        return new self($encoded, $statusCode, $headers);
    }

    /**
     * Create a standardized Prime Care API success response: {"ok":true,"data":{...}}.
     *
     * @param array<string, mixed> $data
     */
    public static function jsonSuccess(array $data = [], int $statusCode = 200): self
    {
        return self::json([
            'ok' => true,
            'data' => $data,
        ], $statusCode);
    }

    /**
     * Create a standardized Prime Care API error response: {"ok":false,"error":{"code":"...","message":"..."}}.
     *
     * @param array<string, mixed> $details
     */
    public static function jsonError(
        string $code,
        string $message,
        int $statusCode = 400,
        array $details = []
    ): self {
        $error = [
            'code' => $code,
            'message' => $message,
        ];
        if (!empty($details)) {
            $error['details'] = $details;
        }

        return self::json([
            'ok' => false,
            'error' => $error,
        ], $statusCode);
    }

    /**
     * Create an HTTP redirect response.
     */
    public static function redirect(string $url, int $statusCode = 302): self
    {
        return new self('', $statusCode, ['Location' => $url]);
    }

    /**
     * Create a binary file response (inline or attachment).
     */
    public static function download(
        string $filePath,
        string $mimeType = 'application/octet-stream',
        ?string $downloadName = null,
        bool $inline = true
    ): self {
        $disposition = $inline ? 'inline' : 'attachment';
        if ($downloadName !== null) {
            $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $downloadName) ?: 'file';
            $disposition .= '; filename="' . $safeName . '"';
        }

        $resp = new self('', 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => $disposition,
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $resp->filePath = $filePath;
        return $resp;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function status(): int
    {
        return $this->statusCode;
    }

    public function body(): string
    {
        if ($this->body === '' && $this->filePath !== null && is_file($this->filePath)) {
            return (string) @file_get_contents($this->filePath);
        }
        return $this->body;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Send status code, headers, and body to the client.
     */
    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }

        if ($this->filePath !== null && is_file($this->filePath)) {
            readfile($this->filePath);
            return;
        }

        $contentType = (string) ($this->headers['Content-Type'] ?? '');
        $acceptEnc = (string) ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '');
        if (
            PHP_SAPI !== 'cli'
            && !headers_sent()
            && $this->body !== ''
            && (str_starts_with($contentType, 'text/') || str_starts_with($contentType, 'application/json'))
            && str_contains($acceptEnc, 'gzip')
            && function_exists('gzencode')
        ) {
            $gz = gzencode($this->body, 6);
            if ($gz !== false) {
                header('Content-Encoding: gzip');
                header('Vary: Accept-Encoding');
                header('Content-Length: ' . strlen($gz));
                echo $gz;
                return;
            }
        }

        echo $this->body;
    }
}
