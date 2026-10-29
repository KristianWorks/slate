<?php
/**
 * app/core/Router.php
 *
 * Tiny dependency-free HTTP router. Supports:
 *   - GET, POST, PUT, DELETE
 *   - URL parameters like /book/{date} or /appointments/{id}
 *   - Callable or [Controller::class, 'method'] handlers
 *   - A pluggable 404 handler
 */

declare(strict_types=1);

namespace App\Core;

/** Supported HTTP verbs. */
enum HttpMethod: string
{
    case GET    = 'GET';
    case POST   = 'POST';
    case PUT    = 'PUT';
    case PATCH  = 'PATCH';
    case DELETE = 'DELETE';
}

/**
 * Internal compiled route record.
 *
 * @internal
 */
final class Route
{
    /**
     * @param list<string>                $paramNames Ordered names captured from `{param}`.
     * @param callable|array{class-string,string} $handler   Closure or [Class, method].
     */
    public function __construct(
        public readonly HttpMethod $method,
        public readonly string $pattern,
        public readonly string $regex,
        public readonly array $paramNames,
        public readonly mixed $handler,
    ) {}
}

/**
 * Vanilla PHP router.
 */
final class Router
{
    /** @var list<Route> */
    private array $routes = [];

    /** @var callable|null */
    private $notFoundHandler = null;

    /** Register a GET route. */
    public function get(string $pattern, callable|array $handler): void
    {
        $this->add(HttpMethod::GET, $pattern, $handler);
    }

    /** Register a POST route. */
    public function post(string $pattern, callable|array $handler): void
    {
        $this->add(HttpMethod::POST, $pattern, $handler);
    }

    /** Register a PUT route. */
    public function put(string $pattern, callable|array $handler): void
    {
        $this->add(HttpMethod::PUT, $pattern, $handler);
    }

    /** Register a PATCH route. */
    public function patch(string $pattern, callable|array $handler): void
    {
        $this->add(HttpMethod::PATCH, $pattern, $handler);
    }

    /** Register a DELETE route. */
    public function delete(string $pattern, callable|array $handler): void
    {
        $this->add(HttpMethod::DELETE, $pattern, $handler);
    }

    /** Override the default 404 response. */
    public function setNotFoundHandler(callable $handler): void
    {
        $this->notFoundHandler = $handler;
    }

    /**
     * Dispatch the current request.
     *
     * @return mixed Whatever the matched handler returns, or null on 404.
     */
    public function dispatch(string $method, string $uri): mixed
    {
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';
        // Normalise: collapse trailing slash except for the root.
        $path = $path !== '/' ? rtrim($path, '/') : '/';

        $httpMethod = HttpMethod::tryFrom(strtoupper($method));
        if ($httpMethod === null) {
            return $this->handleNotFound();
        }

        foreach ($this->routes as $route) {
            if ($route->method !== $httpMethod) {
                continue;
            }
            if (preg_match($route->regex, $path, $matches) === 1) {
                array_shift($matches); // drop full match
                $params = [];
                foreach ($route->paramNames as $i => $name) {
                    $params[$name] = $matches[$i] ?? '';
                }
                return $this->invoke($route->handler, $params);
            }
        }

        return $this->handleNotFound();
    }

    /**
     * Register a route under the given HTTP method.
     *
     * @param callable|array{class-string,string} $handler
     */
    private function add(HttpMethod $method, string $pattern, callable|array $handler): void
    {
        $paramNames = [];

        // Convert `{name}` to a named-less capture group; PHP cannot have
        // duplicate named groups across one pattern, so we capture by index.
        $regexBody = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#',
            function (array $m) use (&$paramNames): string {
                $paramNames[] = $m[1];
                return '([^/]+)';
            },
            $pattern,
        );

        $regex = '#^' . $regexBody . '$#';

        $this->routes[] = new Route(
            method:     $method,
            pattern:    $pattern,
            regex:      $regex,
            paramNames: $paramNames,
            handler:    $handler,
        );
    }

    /**
     * @param callable|array{class-string,string} $handler
     * @param array<string,string> $params
     */
    private function invoke(callable|array $handler, array $params): mixed
    {
        // Controller-style [Class::class, 'method']
        if (is_array($handler) && count($handler) === 2 && is_string($handler[0]) && is_string($handler[1])) {
            [$class, $method] = $handler;
            if (!class_exists($class)) {
                throw new \RuntimeException("Controller {$class} does not exist.");
            }
            $instance = new $class();
            if (!method_exists($instance, $method)) {
                throw new \RuntimeException("Method {$class}::{$method}() does not exist.");
            }
            return $instance->{$method}(...array_values($params));
        }

        // Closures / invokables — call with positional params.
        return ($handler)(...array_values($params));
    }

    private function handleNotFound(): mixed
    {
        http_response_code(404);
        if ($this->notFoundHandler !== null) {
            return ($this->notFoundHandler)();
        }
        echo '404 Not Found';
        return null;
    }
}
