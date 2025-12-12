<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Introspect\Query;

use Closure;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route as RouteFacade;

use function array_all;
use function array_any;
use function array_key_exists;
use function array_pad;
use function assert;
use function collect;
use function explode;
use function in_array;
use function is_array;
use function is_bool;
use function is_string;
use function iterator_to_array;
use function mb_ltrim;
use function mb_strtoupper;
use function preg_match;
use function preg_quote;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function str_starts_with;

/**
 * Fluent query builder for route introspection.
 *
 * Allows querying Laravel routes with support for filtering by controller,
 * middleware, name patterns, path patterns, and HTTP methods. Supports
 * complex OR logic through the or() method.
 *
 * @example
 * ```php
 * // Find routes using a specific controller
 * routes()->whereUsesController(UserController::class)->get();
 *
 * // Find routes with specific middleware
 * routes()->whereUsesMiddleware('auth')->get();
 *
 * // Find routes by name pattern with wildcards
 * routes()->whereNameEquals('admin.*')->get();
 *
 * // Find routes by path pattern
 * routes()->wherePathStartsWith('/api')->get();
 *
 * // Complex OR logic
 * routes()
 *     ->whereUsesMiddleware('auth')
 *     ->or(fn($query) => $query->whereNameStartsWith('public.'))
 *     ->get();
 * ```
 * @author Brian Faust <brian@cline.sh>
 */
final class RoutesIntrospector
{
    /** @var array<string, mixed> */
    private array $filters = [];

    /** @var array<callable> */
    private array $orFilters = [];

    /**
     * Filter routes that use a specific controller and optionally a specific method.
     *
     * @param class-string $controllerClass The fully qualified controller class name
     * @param null|string  $method          The controller method name (optional)
     *
     * @example
     * ```php
     * routes()->whereUsesController(UserController::class)->get();
     * routes()->whereUsesController(UserController::class, 'index')->get();
     * ```
     */
    public function whereUsesController(string $controllerClass, ?string $method = null): static
    {
        $this->filters['controller'] = ['class' => $controllerClass, 'method' => $method];

        return $this;
    }

    /**
     * Filter routes that use a specific middleware.
     *
     * @param string $middleware The middleware name or class
     *
     * @example
     * ```php
     * routes()->whereUsesMiddleware('auth')->get();
     * routes()->whereUsesMiddleware('throttle:60,1')->get();
     * ```
     */
    public function whereUsesMiddleware(string $middleware): static
    {
        $this->filters['middleware'] = $middleware;

        return $this;
    }

    /**
     * Filter routes that use all or any of the specified middlewares.
     *
     * @param array<string> $middlewares Array of middleware names or classes
     * @param bool          $all         If true, route must have ALL middlewares; if false, route must have ANY middleware
     *
     * @example
     * ```php
     * // Routes with ALL specified middlewares
     * routes()->whereUsesMiddlewares(['auth', 'verified'], all: true)->get();
     *
     * // Routes with ANY of the specified middlewares
     * routes()->whereUsesMiddlewares(['auth', 'guest'], all: false)->get();
     * ```
     */
    public function whereUsesMiddlewares(array $middlewares, bool $all = true): static
    {
        $this->filters['middlewares'] = ['list' => $middlewares, 'all' => $all];

        return $this;
    }

    /**
     * Filter routes that do NOT use a specific middleware.
     *
     * @param string $middleware The middleware name or class
     *
     * @example
     * ```php
     * routes()->whereDoesntUseMiddleware('auth')->get();
     * ```
     */
    public function whereDoesntUseMiddleware(string $middleware): static
    {
        $this->filters['not_middleware'] = $middleware;

        return $this;
    }

    /**
     * Filter routes by name pattern (supports wildcards).
     *
     * @param string $pattern Pattern to match (e.g., 'admin.*', '*.show', 'users.index')
     *
     * @example
     * ```php
     * routes()->whereNameEquals('admin.*')->get(); // All admin routes
     * routes()->whereNameEquals('*.show')->get(); // All show routes
     * routes()->whereNameEquals('users.index')->get(); // Exact match
     * ```
     */
    public function whereNameEquals(string $pattern): static
    {
        $this->filters['name'] = $pattern;

        return $this;
    }

    /**
     * Filter routes by name prefix.
     *
     * @param string $prefix The prefix to match
     *
     * @example
     * ```php
     * routes()->whereNameStartsWith('admin.')->get();
     * routes()->whereNameStartsWith('api.v1.')->get();
     * ```
     */
    public function whereNameStartsWith(string $prefix): static
    {
        $this->filters['name_starts_with'] = $prefix;

        return $this;
    }

    /**
     * Filter routes by name suffix.
     *
     * @param string $suffix The suffix to match
     *
     * @example
     * ```php
     * routes()->whereNameEndsWith('.index')->get();
     * routes()->whereNameEndsWith('.show')->get();
     * ```
     */
    public function whereNameEndsWith(string $suffix): static
    {
        $this->filters['name_ends_with'] = $suffix;

        return $this;
    }

    /**
     * Filter routes that do NOT match the name pattern.
     *
     * @param string $pattern Pattern to exclude (supports wildcards)
     *
     * @example
     * ```php
     * routes()->whereNameDoesntEqual('admin.*')->get();
     * routes()->whereNameDoesntEqual('_*')->get(); // Exclude internal routes
     * ```
     */
    public function whereNameDoesntEqual(string $pattern): static
    {
        $this->filters['name_not'] = $pattern;

        return $this;
    }

    /**
     * Filter routes by URI path prefix (supports wildcards).
     *
     * @param string $prefix The path prefix to match (supports wildcards)
     *
     * @example
     * ```php
     * routes()->wherePathStartsWith('/api')->get();
     * routes()->wherePathStartsWith('/admin/*')->get(); // With wildcard
     * ```
     */
    public function wherePathStartsWith(string $prefix): static
    {
        $this->filters['path_starts_with'] = $prefix;

        return $this;
    }

    /**
     * Filter routes by URI path suffix.
     *
     * @param string $suffix The path suffix to match
     *
     * @example
     * ```php
     * routes()->wherePathEndsWith('/export')->get();
     * routes()->wherePathEndsWith('.json')->get();
     * ```
     */
    public function wherePathEndsWith(string $suffix): static
    {
        $this->filters['path_ends_with'] = $suffix;

        return $this;
    }

    /**
     * Filter routes by URI path containing a substring.
     *
     * @param string $substring The substring to search for
     *
     * @example
     * ```php
     * routes()->wherePathContains('/users/')->get();
     * routes()->wherePathContains('{id}')->get(); // Routes with ID parameter
     * ```
     */
    public function wherePathContains(string $substring): static
    {
        $this->filters['path_contains'] = $substring;

        return $this;
    }

    /**
     * Filter routes by exact URI path pattern (supports wildcards).
     *
     * @param string $pattern The path pattern to match
     *
     * @example
     * ```php
     * routes()->wherePathEquals('/users/{id}')->get();
     * routes()->wherePathEquals('/api/*')->get(); // With wildcard
     * ```
     */
    public function wherePathEquals(string $pattern): static
    {
        $this->filters['path'] = $pattern;

        return $this;
    }

    /**
     * Filter routes by HTTP method.
     *
     * @param string $httpMethod The HTTP method (GET, POST, PUT, PATCH, DELETE, etc.)
     *
     * @example
     * ```php
     * routes()->whereUsesMethod('GET')->get();
     * routes()->whereUsesMethod('POST')->get();
     * routes()->whereUsesMethod('DELETE')->get();
     * ```
     */
    public function whereUsesMethod(string $httpMethod): static
    {
        $this->filters['method'] = mb_strtoupper($httpMethod);

        return $this;
    }

    /**
     * Add OR logic to the query.
     *
     * The callback receives a new query builder instance. Routes matching
     * either the main filters OR the callback filters will be returned.
     *
     * @param callable $callback Receives a RoutesIntrospector instance
     *
     * @example
     * ```php
     * // Routes with auth middleware OR starting with 'public.'
     * routes()
     *     ->whereUsesMiddleware('auth')
     *     ->or(fn($query) => $query->whereNameStartsWith('public.'))
     *     ->get();
     *
     * // Multiple OR conditions
     * routes()
     *     ->whereUsesMiddleware('auth')
     *     ->or(fn($q) => $q->wherePathStartsWith('/api'))
     *     ->or(fn($q) => $q->whereNameStartsWith('admin.'))
     *     ->get();
     * ```
     */
    public function or(callable $callback): static
    {
        $query = new self();
        $callback($query);

        /** @var Closure(Route): bool */
        $filter = fn (Route $route): bool => $query->matchesFilters($route);
        $this->orFilters[] = $filter;

        return $this;
    }

    /**
     * Get all routes matching the filters.
     *
     * @return Collection<int, Route>
     *
     * @example
     * ```php
     * $routes = routes()->whereUsesMiddleware('auth')->get();
     * ```
     */
    public function get(): Collection
    {
        $routes = $this->getAllRoutes();

        return collect($routes)->filter(fn (Route $route): bool => $this->matchesFilters($route));
    }

    /**
     * Get the first matching route.
     *
     * @example
     * ```php
     * $route = routes()->whereNameEquals('home')->first();
     * ```
     */
    public function first(): ?Route
    {
        return $this->get()->first();
    }

    /**
     * Check if any routes match the filters.
     *
     * @example
     * ```php
     * if (routes()->whereUsesController(UserController::class)->exists()) {
     *     // Controller has routes
     * }
     * ```
     */
    public function exists(): bool
    {
        return $this->get()->isNotEmpty();
    }

    /**
     * Count matching routes.
     *
     * @example
     * ```php
     * $count = routes()->whereUsesMiddleware('auth')->count();
     * ```
     */
    public function count(): int
    {
        return $this->get()->count();
    }

    /**
     * Get all registered routes from Laravel.
     *
     * @return array<int, Route>
     */
    private function getAllRoutes(): array
    {
        /** @var iterable<Route> $routes */
        $routes = RouteFacade::getRoutes();

        /** @var array<int, Route> */
        return iterator_to_array($routes);
    }

    /**
     * Check if a route matches all filters.
     */
    private function matchesFilters(Route $route): bool
    {
        // Check OR filters first - if any OR filter matches, the route passes
        if ($this->orFilters !== []) {
            $matchesOr = $this->matchesAnyOrFilter($route);

            // If we have main filters AND OR filters, check if main filters match OR any OR filter matches
            if ($this->filters !== []) {
                $matchesMain = $this->matchesMainFilters($route);

                return $matchesMain || $matchesOr;
            }

            // If we only have OR filters, at least one must match
            return $matchesOr;
        }

        // No OR filters, just check main filters
        return $this->matchesMainFilters($route);
    }

    /**
     * Check if a route matches any OR filter.
     */
    private function matchesAnyOrFilter(Route $route): bool
    {
        return array_any(
            $this->orFilters,
            fn (callable $orFilter): bool => (bool) $orFilter($route),
        );
    }

    /**
     * Check if a route matches the main filters (non-OR filters).
     */
    private function matchesMainFilters(Route $route): bool
    {
        if (isset($this->filters['controller'])) {
            $controller = $this->filters['controller'];
            assert(is_array($controller));
            assert(isset($controller['class']) && is_string($controller['class']));
            assert(array_key_exists('method', $controller) && (is_string($controller['method']) || $controller['method'] === null));

            if (!$this->matchesController($route, $controller['class'], $controller['method'])) {
                return false;
            }
        }

        if (isset($this->filters['middleware'])) {
            $middleware = $this->filters['middleware'];
            assert(is_string($middleware));

            if (!$this->hasMiddleware($route, $middleware)) {
                return false;
            }
        }

        if (isset($this->filters['middlewares'])) {
            $config = $this->filters['middlewares'];
            assert(is_array($config));
            assert(isset($config['list']) && is_array($config['list']));
            assert(isset($config['all']) && is_bool($config['all']));

            /** @var array<string> $middlewareList */
            $middlewareList = $config['list'];

            if (!$this->hasMiddlewares($route, $middlewareList, $config['all'])) {
                return false;
            }
        }

        if (isset($this->filters['not_middleware'])) {
            $middleware = $this->filters['not_middleware'];
            assert(is_string($middleware));

            if ($this->hasMiddleware($route, $middleware)) {
                return false;
            }
        }

        if (isset($this->filters['name'])) {
            $name = $route->getName();
            $pattern = $this->filters['name'];
            assert(is_string($pattern));

            if ($name === null || !$this->matchesPattern($name, $pattern)) {
                return false;
            }
        }

        if (isset($this->filters['name_starts_with'])) {
            $name = $route->getName();
            $prefix = $this->filters['name_starts_with'];
            assert(is_string($prefix));

            if ($name === null || !str_starts_with($name, $prefix)) {
                return false;
            }
        }

        if (isset($this->filters['name_ends_with'])) {
            $name = $route->getName();
            $suffix = $this->filters['name_ends_with'];
            assert(is_string($suffix));

            if ($name === null || !str_ends_with($name, $suffix)) {
                return false;
            }
        }

        if (isset($this->filters['name_not'])) {
            $name = $route->getName();
            $pattern = $this->filters['name_not'];
            assert(is_string($pattern));

            if ($name !== null && $this->matchesPattern($name, $pattern)) {
                return false;
            }
        }

        if (isset($this->filters['path_starts_with'])) {
            $path = $this->normalizePath($route->uri());
            $prefix = $this->filters['path_starts_with'];
            assert(is_string($prefix));

            // Support wildcards in path prefix
            if (str_contains($prefix, '*')) {
                if (!$this->matchesPattern($path, $prefix)) {
                    return false;
                }
            } elseif (!str_starts_with($path, $prefix)) {
                return false;
            }
        }

        if (isset($this->filters['path_ends_with'])) {
            $suffix = $this->filters['path_ends_with'];
            assert(is_string($suffix));

            if (!str_ends_with($this->normalizePath($route->uri()), $suffix)) {
                return false;
            }
        }

        if (isset($this->filters['path_contains'])) {
            $substring = $this->filters['path_contains'];
            assert(is_string($substring));

            if (!str_contains($this->normalizePath($route->uri()), $substring)) {
                return false;
            }
        }

        if (isset($this->filters['path'])) {
            $pattern = $this->filters['path'];
            assert(is_string($pattern));

            if (!$this->matchesPattern($this->normalizePath($route->uri()), $pattern)) {
                return false;
            }
        }

        if (isset($this->filters['method'])) {
            $method = $this->filters['method'];
            assert(is_string($method));

            if (!in_array($method, $route->methods(), true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a route uses the specified controller and optionally method.
     */
    private function matchesController(Route $route, string $controllerClass, ?string $method): bool
    {
        $action = $route->getAction();
        assert(is_array($action));

        if (!isset($action['controller'])) {
            return false;
        }

        $routeController = $action['controller'];

        // Handle controller@method format
        if (is_string($routeController)) {
            [$class, $routeMethod] = array_pad(explode('@', $routeController), 2, null);

            if ($class !== $controllerClass) {
                return false;
            }

            if ($method !== null && $routeMethod !== $method) {
                return false;
            }

            return true;
        }

        // Handle invokable controllers or array format
        if (is_array($routeController)) {
            if ($routeController[0] !== $controllerClass) {
                return false;
            }

            if ($method !== null && ($routeController[1] ?? null) !== $method) {
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * Check if a route has a specific middleware.
     */
    private function hasMiddleware(Route $route, string $middleware): bool
    {
        $routeMiddleware = $this->getRouteMiddleware($route);

        foreach ($routeMiddleware as $m) {
            // Exact match
            if ($m === $middleware) {
                return true;
            }

            // Match middleware with parameters (e.g., 'throttle:60,1' matches 'throttle')
            if (str_starts_with($m, $middleware.':')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a route has all or any of the specified middlewares.
     *
     * @param array<string> $middlewares
     */
    private function hasMiddlewares(Route $route, array $middlewares, bool $all): bool
    {
        if ($all) {
            return array_all($middlewares, fn (string $middleware): bool => $this->hasMiddleware($route, $middleware));
        }

        return array_any($middlewares, fn (string $middleware): bool => $this->hasMiddleware($route, $middleware));
    }

    /**
     * Get all middleware for a route.
     *
     * @return array<int, string>
     */
    private function getRouteMiddleware(Route $route): array
    {
        /** @var array<int, string> */
        return $route->gatherMiddleware();
    }

    /**
     * Check if a value matches a wildcard pattern.
     */
    private function matchesPattern(string $value, string $pattern): bool
    {
        // Convert wildcard pattern to regex - escape all special chars then convert * to .*
        $regex = '/^'.str_replace('\*', '.*', preg_quote($pattern, '/')).'$/';

        return (bool) preg_match($regex, $value);
    }

    /**
     * Normalize a route path to always have a leading slash.
     */
    private function normalizePath(string $path): string
    {
        return '/'.mb_ltrim($path, '/');
    }
}
