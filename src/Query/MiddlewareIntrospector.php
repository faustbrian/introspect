<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Introspect\Query;

use Illuminate\Contracts\Http\Kernel as KernelContract;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

use const ARRAY_FILTER_USE_BOTH;

use function array_filter;
use function array_key_exists;
use function array_values;
use function collect;
use function in_array;
use function is_string;
use function preg_match;
use function resolve;
use function str_replace;
use function str_starts_with;

/**
 * Fluent query builder for middleware introspection.
 *
 * Allows querying Laravel middleware with support for filtering by pattern,
 * namespace, groups, and usage. Provides access to middleware aliases,
 * groups, priority, and global middleware configuration.
 *
 * @example
 * ```php
 * // Get all middleware
 * Introspect::middleware()->all();
 *
 * // Get middleware groups
 * Introspect::middleware()->groups();
 *
 * // Get middleware aliases
 * Introspect::middleware()->aliases();
 *
 * // Get middleware priority
 * Introspect::middleware()->priority();
 *
 * // Filter by pattern
 * Introspect::middleware()->whereNameEquals('auth*')->get();
 *
 * // Find global middleware
 * Introspect::middleware()->whereGlobal()->get();
 *
 * // Find middleware in specific group
 * Introspect::middleware()->whereInGroup('web')->get();
 *
 * // Get all middleware info
 * Introspect::middleware()->toArray();
 * ```
 * @author Brian Faust <brian@cline.sh>
 */
final class MiddlewareIntrospector
{
    /** @var array<string, bool|string> */
    private array $filters = [];

    /**
     * Get all registered middleware classes.
     *
     * Returns all middleware from aliases, groups, and global middleware,
     * deduplicated and indexed by class name.
     *
     * @return array<string, string> Middleware classes indexed by alias/class name
     *
     * @example
     * ```php
     * $middleware = Introspect::middleware()->all();
     * // ['auth' => Authenticate::class, 'verified' => EnsureEmailIsVerified::class, ...]
     * ```
     */
    public function all(): array
    {
        $router = $this->getRouter();
        $kernel = $this->getKernel();

        /** @var array<string, string> $all */
        $all = [];

        // Add aliased middleware
        foreach ($router->getMiddleware() as $alias => $class) {
            $all[$alias] = $class;
        }

        // Add middleware from groups
        /** @var array<string, array<int, string>> $groups */
        $groups = $router->getMiddlewareGroups();

        foreach ($groups as $middlewares) {
            foreach ($middlewares as $middleware) {
                if (array_key_exists($middleware, $all)) {
                    continue;
                }

                if (in_array($middleware, $all, true)) {
                    continue;
                }

                $all[$middleware] = $middleware;
            }
        }

        // Add global middleware
        /** @var array<int, string> $globalMiddleware */
        $globalMiddleware = $kernel->getGlobalMiddleware();

        foreach ($globalMiddleware as $middleware) {
            if (array_key_exists($middleware, $all)) {
                continue;
            }

            if (in_array($middleware, $all, true)) {
                continue;
            }

            $all[$middleware] = $middleware;
        }

        /** @var array<string, string> $all */
        return $this->applyFilters($all);
    }

    /**
     * Get middleware groups configuration.
     *
     * @return array<string, array<string>> Groups indexed by name with their middleware
     *
     * @example
     * ```php
     * $groups = Introspect::middleware()->groups();
     * // ['web' => [...], 'api' => [...]]
     * ```
     */
    public function groups(): array
    {
        /** @var array<string, array<int, string>> */
        return $this->getRouter()->getMiddlewareGroups();
    }

    /**
     * Get middleware aliases.
     *
     * @return array<string, string> Aliases indexed by name with their class
     *
     * @example
     * ```php
     * $aliases = Introspect::middleware()->aliases();
     * // ['auth' => Authenticate::class, ...]
     * ```
     */
    public function aliases(): array
    {
        /** @var array<string, string> */
        return $this->getRouter()->getMiddleware();
    }

    /**
     * Get middleware priority order.
     *
     * @return array<string> Middleware classes in priority order
     * @phpstan-return array<string>
     *
     * @example
     * ```php
     * $priority = Introspect::middleware()->priority();
     * // [HandleCors::class, StartSession::class, ...]
     * ```
     */
    public function priority(): array
    {
        /** @phpstan-ignore-next-line */
        return $this->getRouter()->middlewarePriority;
    }

    /**
     * Get global middleware.
     *
     * @return array<string> Global middleware classes
     *
     * @example
     * ```php
     * $global = Introspect::middleware()->global();
     * // [TrustProxies::class, HandleCors::class, ...]
     * ```
     */
    public function global(): array
    {
        /** @var array<int, string> */
        return $this->getKernel()->getGlobalMiddleware();
    }

    /**
     * Filter middleware that are registered as global.
     *
     * @example
     * ```php
     * Introspect::middleware()->whereGlobal()->get();
     * ```
     */
    public function whereGlobal(): static
    {
        $this->filters['global'] = true;

        return $this;
    }

    /**
     * Filter middleware that belong to a specific group.
     *
     * @param string $group The middleware group name
     *
     * @example
     * ```php
     * Introspect::middleware()->whereInGroup('web')->get();
     * Introspect::middleware()->whereInGroup('api')->get();
     * ```
     */
    public function whereInGroup(string $group): static
    {
        $this->filters['group'] = $group;

        return $this;
    }

    /**
     * Filter middleware by name pattern (supports wildcards).
     *
     * @param string $pattern Pattern to match (e.g., 'auth*', '*.throttle', 'verified')
     *
     * @example
     * ```php
     * Introspect::middleware()->whereNameEquals('auth*')->get(); // All auth middleware
     * Introspect::middleware()->whereNameEquals('*.throttle')->get(); // All throttle middleware
     * Introspect::middleware()->whereNameEquals('verified')->get(); // Exact match
     * ```
     */
    public function whereNameEquals(string $pattern): static
    {
        $this->filters['name'] = $pattern;

        return $this;
    }

    /**
     * Filter middleware by namespace.
     *
     * @param string $namespace The namespace to match
     *
     * @example
     * ```php
     * Introspect::middleware()->whereNamespace('App\\Http\\Middleware')->get();
     * ```
     */
    public function whereNamespace(string $namespace): static
    {
        $this->filters['namespace'] = $namespace;

        return $this;
    }

    /**
     * Filter middleware that are used by any routes.
     *
     * @example
     * ```php
     * Introspect::middleware()->whereUsedByRoutes()->get();
     * ```
     */
    public function whereUsedByRoutes(): static
    {
        $this->filters['used_by_routes'] = true;

        return $this;
    }

    /**
     * Get all middleware matching the filters.
     *
     * @return Collection<int, string>
     *
     * @example
     * ```php
     * $middleware = Introspect::middleware()->whereInGroup('web')->get();
     * ```
     */
    public function get(): Collection
    {
        return collect(array_values($this->all()));
    }

    /**
     * Get the first matching middleware.
     *
     * @example
     * ```php
     * $middleware = Introspect::middleware()->whereNameEquals('auth')->first();
     * ```
     */
    public function first(): ?string
    {
        return $this->get()->first();
    }

    /**
     * Check if any middleware match the filters.
     *
     * @example
     * ```php
     * if (Introspect::middleware()->whereNameEquals('auth')->exists()) {
     *     // Auth middleware exists
     * }
     * ```
     */
    public function exists(): bool
    {
        return $this->get()->isNotEmpty();
    }

    /**
     * Count matching middleware.
     *
     * @example
     * ```php
     * $count = Introspect::middleware()->whereInGroup('web')->count();
     * ```
     */
    public function count(): int
    {
        return $this->get()->count();
    }

    /**
     * Get comprehensive middleware information.
     *
     * @return array{
     *     aliases: array<string, string>,
     *     groups: array<string, array<string>>,
     *     priority: array<string>,
     *     global: array<string>
     * }
     *
     * @example
     * ```php
     * $info = Introspect::middleware()->toArray();
     * // [
     * //     'aliases' => [...],
     * //     'groups' => [...],
     * //     'priority' => [...],
     * //     'global' => [...]
     * // ]
     * ```
     */
    public function toArray(): array
    {
        return [
            'aliases' => $this->aliases(),
            'groups' => $this->groups(),
            'priority' => $this->priority(),
            'global' => $this->global(),
        ];
    }

    /**
     * Get the Laravel Router instance.
     */
    private function getRouter(): Router
    {
        return resolve(Router::class);
    }

    /**
     * Get the Laravel HTTP Kernel instance.
     */
    private function getKernel(): Kernel
    {
        /** @var Kernel */
        return resolve(KernelContract::class);
    }

    /**
     * Apply filters to the middleware collection.
     *
     * @param  array<string, string> $middleware
     * @return array<string, string>
     */
    private function applyFilters(array $middleware): array
    {
        foreach ($this->filters as $filter => $value) {
            $middleware = match ($filter) {
                'global' => $this->filterGlobal($middleware),
                'group' => is_string($value) ? $this->filterByGroup($middleware, $value) : $middleware,
                'name' => is_string($value) ? $this->filterByName($middleware, $value) : $middleware,
                'namespace' => is_string($value) ? $this->filterByNamespace($middleware, $value) : $middleware,
                'used_by_routes' => $this->filterUsedByRoutes($middleware),
                default => $middleware,
            };
        }

        return $middleware;
    }

    /**
     * Filter to only global middleware.
     *
     * @param  array<string, string> $middleware
     * @return array<string, string>
     */
    private function filterGlobal(array $middleware): array
    {
        $global = $this->global();

        return array_filter($middleware, fn (string $class, string $key): bool => in_array($class, $global, true) || in_array($key, $global, true), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Filter by middleware group.
     *
     * @param  array<string, string> $middleware
     * @return array<string, string>
     */
    private function filterByGroup(array $middleware, string $group): array
    {
        /** @var array<string, array<int, string>> $groups */
        $groups = $this->groups();

        if (!isset($groups[$group])) {
            return [];
        }

        $groupMiddleware = $groups[$group];

        return array_filter($middleware, fn (string $class, string $key): bool => in_array($class, $groupMiddleware, true) || in_array($key, $groupMiddleware, true), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Filter by name pattern.
     *
     * @param  array<string, string> $middleware
     * @return array<string, string>
     */
    private function filterByName(array $middleware, string $pattern): array
    {
        return array_filter($middleware, fn (string $class, string $key): bool => $this->matchesPattern($key, $pattern) || $this->matchesPattern($class, $pattern), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Filter by namespace.
     *
     * @param  array<string, string> $middleware
     * @return array<string, string>
     */
    private function filterByNamespace(array $middleware, string $namespace): array
    {
        return array_filter($middleware, fn (string $class): bool => str_starts_with($class, $namespace), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Filter middleware that are used by routes.
     *
     * @param  array<string, string> $middleware
     * @return array<string, string>
     */
    private function filterUsedByRoutes(array $middleware): array
    {
        /** @var array<string, string> $usedMiddleware */
        $usedMiddleware = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            /** @var array<int, string> $routeMiddleware */
            $routeMiddleware = $route->gatherMiddleware();

            foreach ($routeMiddleware as $m) {
                $usedMiddleware[$m] = $m;
            }
        }

        return array_filter($middleware, fn (string $class, string $key): bool => isset($usedMiddleware[$class]) || isset($usedMiddleware[$key]), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Check if a value matches a wildcard pattern.
     */
    private function matchesPattern(string $value, string $pattern): bool
    {
        // Convert wildcard pattern to regex
        $regex = '/^'.str_replace(['\\', '*'], ['\\\\', '.*'], $pattern).'$/';

        return (bool) preg_match($regex, $value);
    }
}
