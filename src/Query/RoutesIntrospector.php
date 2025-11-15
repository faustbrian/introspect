<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Introspect\Query;

use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route as RouteFacade;

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
 */
class RoutesIntrospector
{
    /**
     * @var array<string, mixed>
     */
    private array $filters = [];

    /**
     * @var array<callable>
     */
    private array $orFilters = [];

    /**
     * Filter routes that use a specific controller and optionally a specific method.
     *
     * @param  class-string $controllerClass The fully qualified controller class name
     * @param  string|null $method The controller method name (optional)
     * @return static
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
     * @param  string $middleware The middleware name or class
     * @return static
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
     * @param  array<string> $middlewares Array of middleware names or classes
     * @param  bool $all If true, route must have ALL middlewares; if false, route must have ANY middleware
     * @return static
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
     * @param  string $middleware The middleware name or class
     * @return static
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
     * @param  string $pattern Pattern to match (e.g., 'admin.*', '*.show', 'users.index')
     * @return static
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
     * @param  string $prefix The prefix to match
     * @return static
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
     * @param  string $suffix The suffix to match
     * @return static
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
     * @param  string $pattern Pattern to exclude (supports wildcards)
     * @return static
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
     * @param  string $prefix The path prefix to match (supports wildcards)
     * @return static
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
     * @param  string $suffix The path suffix to match
     * @return static
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
     * @param  string $substring The substring to search for
     * @return static
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
     * @param  string $pattern The path pattern to match
     * @return static
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
     * @param  string $httpMethod The HTTP method (GET, POST, PUT, PATCH, DELETE, etc.)
     * @return static
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
        $this->filters['method'] = strtoupper($httpMethod);

        return $this;
    }

    /**
     * Add OR logic to the query.
     *
     * The callback receives a new query builder instance. Routes matching
     * either the main filters OR the callback filters will be returned.
     *
     * @param  callable $callback Receives a RoutesIntrospector instance
     * @return static
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
        $this->orFilters[] = fn (Route $route) => $query->matchesFilters($route);

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

        return collect($routes)->filter(fn (Route $route) => $this->matchesFilters($route));
    }

    /**
     * Get the first matching route.
     *
     * @return Route|null
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
     * @return bool
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
     * @return int
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
     * @return array<Route>
     */
    private function getAllRoutes(): array
    {
        return iterator_to_array(RouteFacade::getRoutes());
    }

    /**
     * Check if a route matches all filters.
     *
     * @param  Route $route
     * @return bool
     */
    private function matchesFilters(Route $route): bool
    {
        // Check OR filters first - if any OR filter matches, the route passes
        if (! empty($this->orFilters)) {
            $matchesOr = false;
            foreach ($this->orFilters as $orFilter) {
                if ($orFilter($route)) {
                    $matchesOr = true;
                    break;
                }
            }

            // If we have main filters AND OR filters, check if main filters match OR any OR filter matches
            if (! empty($this->filters)) {
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
     * Check if a route matches the main filters (non-OR filters).
     *
     * @param  Route $route
     * @return bool
     */
    private function matchesMainFilters(Route $route): bool
    {
        if (isset($this->filters['controller'])) {
            $controller = $this->filters['controller'];
            if (! $this->matchesController($route, $controller['class'], $controller['method'])) {
                return false;
            }
        }

        if (isset($this->filters['middleware'])) {
            if (! $this->hasMiddleware($route, $this->filters['middleware'])) {
                return false;
            }
        }

        if (isset($this->filters['middlewares'])) {
            $config = $this->filters['middlewares'];
            if (! $this->hasMiddlewares($route, $config['list'], $config['all'])) {
                return false;
            }
        }

        if (isset($this->filters['not_middleware'])) {
            if ($this->hasMiddleware($route, $this->filters['not_middleware'])) {
                return false;
            }
        }

        if (isset($this->filters['name'])) {
            $name = $route->getName();
            if ($name === null || ! $this->matchesPattern($name, $this->filters['name'])) {
                return false;
            }
        }

        if (isset($this->filters['name_starts_with'])) {
            $name = $route->getName();
            if ($name === null || ! str_starts_with($name, $this->filters['name_starts_with'])) {
                return false;
            }
        }

        if (isset($this->filters['name_ends_with'])) {
            $name = $route->getName();
            if ($name === null || ! str_ends_with($name, $this->filters['name_ends_with'])) {
                return false;
            }
        }

        if (isset($this->filters['name_not'])) {
            $name = $route->getName();
            if ($name !== null && $this->matchesPattern($name, $this->filters['name_not'])) {
                return false;
            }
        }

        if (isset($this->filters['path_starts_with'])) {
            $path = $route->uri();
            $prefix = $this->filters['path_starts_with'];

            // Support wildcards in path prefix
            if (str_contains($prefix, '*')) {
                if (! $this->matchesPattern($path, $prefix)) {
                    return false;
                }
            } elseif (! str_starts_with($path, $prefix)) {
                return false;
            }
        }

        if (isset($this->filters['path_ends_with'])) {
            if (! str_ends_with($route->uri(), $this->filters['path_ends_with'])) {
                return false;
            }
        }

        if (isset($this->filters['path_contains'])) {
            if (! str_contains($route->uri(), $this->filters['path_contains'])) {
                return false;
            }
        }

        if (isset($this->filters['path'])) {
            if (! $this->matchesPattern($route->uri(), $this->filters['path'])) {
                return false;
            }
        }

        if (isset($this->filters['method'])) {
            if (! in_array($this->filters['method'], $route->methods(), true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a route uses the specified controller and optionally method.
     *
     * @param  Route $route
     * @param  string $controllerClass
     * @param  string|null $method
     * @return bool
     */
    private function matchesController(Route $route, string $controllerClass, ?string $method): bool
    {
        $action = $route->getAction();

        if (! isset($action['controller'])) {
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
     *
     * @param  Route $route
     * @param  string $middleware
     * @return bool
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
     * @param  Route $route
     * @param  array<string> $middlewares
     * @param  bool $all
     * @return bool
     */
    private function hasMiddlewares(Route $route, array $middlewares, bool $all): bool
    {
        if ($all) {
            // Route must have ALL specified middlewares
            foreach ($middlewares as $middleware) {
                if (! $this->hasMiddleware($route, $middleware)) {
                    return false;
                }
            }

            return true;
        }

        // Route must have ANY of the specified middlewares
        foreach ($middlewares as $middleware) {
            if ($this->hasMiddleware($route, $middleware)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all middleware for a route.
     *
     * @param  Route $route
     * @return array<string>
     */
    private function getRouteMiddleware(Route $route): array
    {
        return $route->gatherMiddleware();
    }

    /**
     * Check if a value matches a wildcard pattern.
     *
     * @param  string $value
     * @param  string $pattern
     * @return bool
     */
    private function matchesPattern(string $value, string $pattern): bool
    {
        // Convert wildcard pattern to regex
        $regex = '/^'.str_replace(['\\', '*'], ['\\\\', '.*'], $pattern).'$/';

        return (bool) preg_match($regex, $value);
    }
}
