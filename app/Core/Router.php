<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;
use RuntimeException;

/**
 * HTTP Router supporting GET/POST/PUT/PATCH/DELETE, typed regex parameters,
 * route groups with prefixes and middleware stacks, named routes, and 404/405 handling.
 */
final class Router
{
    private static ?self $instance = null;

    /**
     * @var array<int, array{
     *   method: string,
     *   uri: string,
     *   regex: string,
     *   paramNames: array<int, string>,
     *   action: callable|array{0: class-string, 1: string},
     *   middlewares: array<int, string|callable>,
     *   name: ?string
     * }>
     */
    private array $routes = [];

    /** @var array<string, string> */
    private array $namedRoutes = [];

    /** @var array<int, array{prefix: string, middlewares: array<int, string|callable>}> */
    private array $groupStack = [];

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function setInstance(self $router): void
    {
        self::$instance = $router;
    }

    /**
     * Register a route group with shared prefix and/or middlewares.
     *
     * @param array{prefix?: string, middleware?: array<int, string|callable>|string|callable} $attributes
     */
    public function group(array $attributes, callable $callback): void
    {
        $prefix = isset($attributes['prefix']) ? '/' . trim((string) $attributes['prefix'], '/') : '';
        if ($prefix === '/') {
            $prefix = '';
        }

        $middlewares = [];
        if (isset($attributes['middleware'])) {
            $middlewares = is_array($attributes['middleware'])
                ? $attributes['middleware']
                : [$attributes['middleware']];
        }

        $parent = end($this->groupStack);
        if ($parent !== false) {
            $prefix = $parent['prefix'] . $prefix;
            $middlewares = array_merge($parent['middlewares'], $middlewares);
        }

        $this->groupStack[] = [
            'prefix' => $prefix,
            'middlewares' => $middlewares,
        ];

        $callback($this);

        array_pop($this->groupStack);
    }

    /**
     * @param callable|array{0: class-string, 1: string} $action
     * @param array<int, string|callable> $middlewares
     */
    public function get(string $uri, callable|array $action, ?string $name = null, array $middlewares = []): self
    {
        return $this->addRoute('GET', $uri, $action, $name, $middlewares);
    }

    /**
     * @param callable|array{0: class-string, 1: string} $action
     * @param array<int, string|callable> $middlewares
     */
    public function post(string $uri, callable|array $action, ?string $name = null, array $middlewares = []): self
    {
        return $this->addRoute('POST', $uri, $action, $name, $middlewares);
    }

    /**
     * @param callable|array{0: class-string, 1: string} $action
     * @param array<int, string|callable> $middlewares
     */
    public function put(string $uri, callable|array $action, ?string $name = null, array $middlewares = []): self
    {
        return $this->addRoute('PUT', $uri, $action, $name, $middlewares);
    }

    /**
     * @param callable|array{0: class-string, 1: string} $action
     * @param array<int, string|callable> $middlewares
     */
    public function patch(string $uri, callable|array $action, ?string $name = null, array $middlewares = []): self
    {
        return $this->addRoute('PATCH', $uri, $action, $name, $middlewares);
    }

    /**
     * @param callable|array{0: class-string, 1: string} $action
     * @param array<int, string|callable> $middlewares
     */
    public function delete(string $uri, callable|array $action, ?string $name = null, array $middlewares = []): self
    {
        return $this->addRoute('DELETE', $uri, $action, $name, $middlewares);
    }

    /**
     * Generate a relative URL for a named route with parameter substitution.
     *
     * @param array<string, string|int> $params
     */
    public function routeUrl(string $name, array $params = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new InvalidArgumentException("Route nommée introuvable : {$name}");
        }

        $uri = $this->namedRoutes[$name];
        foreach ($params as $key => $value) {
            $replaced = preg_replace('/\{' . preg_quote($key, '/') . '(?::[^}]+)?\}/', (string) $value, $uri, 1, $count);
            if (is_string($replaced) && $count > 0) {
                $uri = $replaced;
                unset($params[$key]);
            }
        }

        if (preg_match('/\{[^}]+\}/', $uri)) {
            throw new InvalidArgumentException("Paramètre manquant pour la route : {$name}");
        }

        if (!empty($params)) {
            $uri .= '?' . http_build_query($params);
        }

        return $uri;
    }

    /**
     * Dispatch an incoming Request through matched route middlewares and controller action.
     */
    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $path = $request->path();
        $allowedMethodsForPath = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches)) {
                if ($route['method'] !== $method && !($method === 'HEAD' && $route['method'] === 'GET')) {
                    $allowedMethodsForPath[] = $route['method'];
                    continue;
                }

                $params = [];
                foreach ($route['paramNames'] as $idx => $paramName) {
                    $params[$paramName] = $matches[$idx + 1] ?? '';
                }
                $request->setRouteParams($params);

                return $this->runPipeline($request, $route['middlewares'], $route['action'], $params);
            }
        }

        if (!empty($allowedMethodsForPath)) {
            $allowed = implode(', ', array_unique($allowedMethodsForPath));
            if ($request->wantsJson()) {
                return Response::jsonError('METHOD_NOT_ALLOWED', 'Méthode HTTP non autorisée.', 405)
                    ->withHeader('Allow', $allowed);
            }
            throw new RuntimeException('Méthode HTTP non autorisée (' . $allowed . ').', 405);
        }

        if ($request->wantsJson()) {
            return Response::jsonError('NOT_FOUND', 'Ressource ou route introuvable.', 404);
        }
        throw new RuntimeException('La page demandée est introuvable.', 404);
    }

    /**
     * @param callable|array{0: class-string, 1: string} $action
     * @param array<int, string|callable> $middlewares
     */
    private function addRoute(
        string $method,
        string $uri,
        callable|array $action,
        ?string $name,
        array $middlewares
    ): self {
        $group = end($this->groupStack);
        $prefix = $group !== false ? $group['prefix'] : '';
        $groupMiddlewares = $group !== false ? $group['middlewares'] : [];

        $normalizedUri = '/' . trim($prefix . '/' . ltrim($uri, '/'), '/');
        if ($normalizedUri !== '/') {
            $normalizedUri = rtrim($normalizedUri, '/');
        }

        $paramNames = [];
        $pattern = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}/',
            static function (array $m) use (&$paramNames): string {
                $paramNames[] = $m[1];
                $constraint = $m[2] ?? '[^/]+';
                return '(' . $constraint . ')';
            },
            $normalizedUri
        );

        $regex = '#^' . $pattern . '$#';

        $this->routes[] = [
            'method' => strtoupper($method),
            'uri' => $normalizedUri,
            'regex' => $regex,
            'paramNames' => $paramNames,
            'action' => $action,
            'middlewares' => array_merge($groupMiddlewares, $middlewares),
            'name' => $name,
        ];

        if ($name !== null && $name !== '') {
            $this->namedRoutes[$name] = $normalizedUri;
        }

        return $this;
    }

    /**
     * Execute middleware stack followed by the route handler.
     *
     * @param array<int, string|callable> $middlewares
     * @param callable|array{0: class-string, 1: string} $action
     * @param array<string, string> $params
     */
    private function runPipeline(
        Request $request,
        array $middlewares,
        callable|array $action,
        array $params
    ): Response {
        $coreHandler = function (Request $req) use ($action, $params): Response {
            if (is_array($action)) {
                [$class, $method] = $action;
                $controller = new $class();
                $result = $controller->{$method}($req, ...array_values($params));
            } else {
                $result = $action($req, ...array_values($params));
            }

            if ($result instanceof Response) {
                return $result;
            }

            return Response::html((string) $result);
        };

        $pipeline = array_reduce(
            array_reverse($middlewares),
            static function (callable $next, string|callable $middleware): callable {
                return static function (Request $req) use ($middleware, $next): Response {
                    if (is_callable($middleware)) {
                        return $middleware($req, $next);
                    }

                    // Support parameterized middleware e.g. "App\Middleware\RoleMiddleware:admin,coordinator"
                    $parts = explode(':', $middleware, 2);
                    $className = $parts[0];
                    $args = isset($parts[1]) ? explode(',', $parts[1]) : [];

                    $instance = new $className(...$args);
                    return $instance->handle($req, $next);
                };
            },
            $coreHandler
        );

        return $pipeline($request);
    }
}
