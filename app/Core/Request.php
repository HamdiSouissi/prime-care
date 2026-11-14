<?php

declare(strict_types=1);

namespace App\Core;

/**
 * HTTP Request abstraction with method override, input sanitization, JSON body parsing, and file access.
 */
final class Request
{
    private string $method;

    private string $path;

    /** @var array<string, mixed> */
    private array $query;

    /** @var array<string, mixed> */
    private array $post;

    /** @var array<string, mixed> */
    private array $json;

    /** @var array<string, mixed> */
    private array $files;

    /** @var array<string, mixed> */
    private array $server;

    /** @var array<string, string> */
    private array $routeParams = [];

    /**
     * @param array<string, mixed>|null $query
     * @param array<string, mixed>|null $post
     * @param array<string, mixed>|null $files
     * @param array<string, mixed>|null $server
     * @param array<string, mixed>|null $json
     */
    public function __construct(
        ?array $query = null,
        ?array $post = null,
        ?array $files = null,
        ?array $server = null,
        ?array $json = null
    ) {
        $this->query = $this->cleanArray($query ?? $_GET);
        $this->post = $this->cleanArray($post ?? $_POST);
        $this->files = $files ?? $_FILES;
        $this->server = $server ?? $_SERVER;

        if ($json !== null) {
            $this->json = $this->cleanArray($json);
        } else {
            $this->json = $this->parseJsonBody();
        }

        $rawMethod = strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));
        if ($rawMethod === 'POST') {
            $override = $this->post['_method'] ?? $this->json['_method'] ?? $this->header('X-HTTP-Method-Override');
            if (is_string($override)) {
                $upper = strtoupper(trim($override));
                if (in_array($upper, ['PUT', 'PATCH', 'DELETE'], true)) {
                    $rawMethod = $upper;
                }
            }
        }
        $this->method = $rawMethod;

        $uri = (string) ($this->server['REQUEST_URI'] ?? '/');
        $parsedPath = parse_url($uri, PHP_URL_PATH);
        $path = is_string($parsedPath) && $parsedPath !== '' ? $parsedPath : '/';
        $this->path = $path !== '/' ? rtrim($path, '/') : '/';
    }

    /**
     * Capture the current global HTTP request.
     */
    public static function capture(): self
    {
        return new self();
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * Set matched route parameters.
     *
     * @param array<string, string> $params
     */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function param(string $key, ?string $default = null): ?string
    {
        return $this->routeParams[$key] ?? $default;
    }

    /**
     * Get merged input from JSON, POST, and query parameters.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return array_merge($this->query, $this->post, $this->json);
    }

    /**
     * Retrieve a single input value.
     */
    public function input(string $key, mixed $default = null): mixed
    {
        $all = $this->all();
        return $all[$key] ?? $default;
    }

    /**
     * Retrieve a query string parameter.
     */
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * Retrieve an uploaded file array.
     *
     * @return array<string, mixed>|null
     */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;
        return is_array($file) ? $file : null;
    }

    /**
     * Retrieve an HTTP request header.
     */
    public function header(string $name, ?string $default = null): ?string
    {
        $normalized = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($this->server[$normalized])) {
            return (string) $this->server[$normalized];
        }
        if (strtoupper($name) === 'CONTENT-TYPE' && isset($this->server['CONTENT_TYPE'])) {
            return (string) $this->server['CONTENT_TYPE'];
        }
        return $default;
    }

    /**
     * Determine whether the client expects a JSON response.
     */
    public function wantsJson(): bool
    {
        if (str_starts_with($this->path, '/api/')) {
            return true;
        }
        $accept = (string) $this->header('Accept', '');
        $requestedWith = (string) $this->header('X-Requested-With', '');
        return str_contains($accept, 'application/json') || strtolower($requestedWith) === 'xmlhttprequest';
    }

    /**
     * Get the client IP address.
     */
    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '127.0.0.1');
    }

    /**
     * Get the client User-Agent string.
     */
    public function userAgent(): string
    {
        return substr((string) ($this->server['HTTP_USER_AGENT'] ?? 'Unknown'), 0, 255);
    }

    /**
     * Recursively trim string inputs and strip null bytes.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function cleanArray(array $input): array
    {
        $cleaned = [];
        foreach ($input as $key => $value) {
            if (is_array($value)) {
                $cleaned[$key] = $this->cleanArray($value);
            } elseif (is_string($value)) {
                $cleaned[$key] = trim(str_replace("\0", '', $value));
            } else {
                $cleaned[$key] = $value;
            }
        }
        return $cleaned;
    }

    /**
     * Parse incoming JSON payload if Content-Type is application/json.
     *
     * @return array<string, mixed>
     */
    private function parseJsonBody(): array
    {
        $contentType = (string) $this->header('Content-Type', '');
        if (!str_contains(strtolower($contentType), 'application/json')) {
            return [];
        }

        $raw = file_get_contents('php://input');
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $this->cleanArray($decoded) : [];
    }
}
