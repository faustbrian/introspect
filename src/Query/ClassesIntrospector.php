<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Introspect\Query;

use Cline\Introspect\Reflection;
use Illuminate\Support\Collection;

use function array_any;
use function collect;
use function get_declared_classes;
use function preg_match;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function str_starts_with;

/**
 * Fluent query builder for class introspection.
 *
 * Allows querying classes with wildcard support and filtering by inheritance,
 * interfaces, and traits. Provides Laravel Introspect-compatible API.
 *
 * @example
 * ```php
 * // Find all classes in a namespace
 * $classes = Introspect::classes()
 *     ->whereName('App\Models\*')
 *     ->get();
 *
 * // Find classes that extend a parent
 * $classes = Introspect::classes()
 *     ->whereExtends(Model::class)
 *     ->get();
 *
 * // Complex filtering with OR logic
 * $classes = Introspect::classes()
 *     ->whereName('App\Models\*')
 *     ->or(fn($query) => $query
 *         ->whereExtends(Model::class)
 *         ->whereUses(SoftDeletes::class)
 *     )
 *     ->get();
 * ```
 * @author Brian Faust <brian@cline.sh>
 */
final class ClassesIntrospector
{
    /** @var array<string, mixed> Primary filter conditions (AND logic) */
    private array $filters = [];

    /** @var array<self> OR filter groups */
    private array $orFilters = [];

    /**
     * Filter classes by name pattern (supports wildcards).
     *
     * Matches class names using wildcard patterns. Supports both namespace
     * and class name matching.
     *
     * @param  string $pattern Pattern to match (e.g., 'App\Models\*', '*Controller', 'App\*\User')
     * @return static Fluent interface
     *
     * @example
     * ```php
     * // Match all classes in App\Models namespace
     * ->whereName('App\Models\*')
     *
     * // Match all Controller classes
     * ->whereName('*Controller')
     *
     * // Match User class in any namespace
     * ->whereName('*\User')
     * ```
     */
    public function whereName(string $pattern): static
    {
        $this->filters['name'] = $pattern;

        return $this;
    }

    /**
     * Filter classes by name prefix.
     *
     * @param  string $prefix Prefix to match (e.g., 'App\Models')
     * @return static Fluent interface
     *
     * @example
     * ```php
     * ->whereNameStartsWith('App\Models')
     * ```
     */
    public function whereNameStartsWith(string $prefix): static
    {
        $this->filters['starts_with'] = $prefix;

        return $this;
    }

    /**
     * Filter classes by name suffix.
     *
     * @param  string $suffix Suffix to match (e.g., 'Controller', 'Service')
     * @return static Fluent interface
     *
     * @example
     * ```php
     * ->whereNameEndsWith('Controller')
     * ```
     */
    public function whereNameEndsWith(string $suffix): static
    {
        $this->filters['ends_with'] = $suffix;

        return $this;
    }

    /**
     * Filter classes by name containing substring.
     *
     * @param  string $substring Substring to search for
     * @return static Fluent interface
     *
     * @example
     * ```php
     * ->whereNameContains('Repository')
     * ```
     */
    public function whereNameContains(string $substring): static
    {
        $this->filters['contains'] = $substring;

        return $this;
    }

    /**
     * Filter classes that extend a specific parent class.
     *
     * @param  string $parentClass Fully-qualified parent class name
     * @return static Fluent interface
     *
     * @example
     * ```php
     * use Illuminate\Database\Eloquent\Model;
     *
     * ->whereExtends(Model::class)
     * ```
     */
    public function whereExtends(string $parentClass): static
    {
        $this->filters['extends'] = $parentClass;

        return $this;
    }

    /**
     * Filter classes that implement a specific interface.
     *
     * @param  string $interfaceClass Fully-qualified interface name
     * @return static Fluent interface
     *
     * @example
     * ```php
     * use JsonSerializable;
     *
     * ->whereImplements(JsonSerializable::class)
     * ```
     */
    public function whereImplements(string $interfaceClass): static
    {
        $this->filters['implements'] = $interfaceClass;

        return $this;
    }

    /**
     * Filter classes that use a specific trait.
     *
     * @param  string $traitClass Fully-qualified trait name
     * @return static Fluent interface
     *
     * @example
     * ```php
     * use Illuminate\Database\Eloquent\SoftDeletes;
     *
     * ->whereUses(SoftDeletes::class)
     * ```
     */
    public function whereUses(string $traitClass): static
    {
        $this->filters['uses'] = $traitClass;

        return $this;
    }

    /**
     * Add OR logic for complex filtering.
     *
     * Accepts a callback that receives a fresh query builder instance.
     * Results match if ANY of the OR conditions are satisfied.
     *
     * @param  callable $callback Callback receiving a new query builder instance
     * @return static   Fluent interface
     *
     * @example
     * ```php
     * // Find classes that are EITHER in App\Models OR extend Model
     * ->whereName('App\Models\*')
     * ->or(fn($query) => $query->whereExtends(Model::class))
     *
     * // Complex OR logic
     * ->or(fn($query) => $query
     *     ->whereExtends(Controller::class)
     *     ->whereNameEndsWith('Controller')
     * )
     * ```
     */
    public function or(callable $callback): static
    {
        $orQuery = new self();
        $callback($orQuery);
        $this->orFilters[] = $orQuery;

        return $this;
    }

    /**
     * Get all classes matching the filters.
     *
     * @return Collection<int, class-string> Collection of class names
     *
     * @example
     * ```php
     * $classes = Introspect::classes()
     *     ->whereName('App\Models\*')
     *     ->get();
     * // => Collection(['App\Models\User', 'App\Models\Post', ...])
     * ```
     */
    public function get(): Collection
    {
        /** @var array<class-string> $classes */
        $classes = get_declared_classes();

        return collect($classes)->filter(fn (string $class): bool => $this->matchesFilters($class));
    }

    /**
     * Get the first matching class.
     *
     * @return null|string First class name or null if none found
     *
     * @example
     * ```php
     * $class = Introspect::classes()
     *     ->whereName('App\Models\User')
     *     ->first();
     * // => 'App\Models\User' or null
     * ```
     */
    public function first(): ?string
    {
        return $this->get()->first();
    }

    /**
     * Check if any classes match the filters.
     *
     * @return bool True if at least one class matches
     *
     * @example
     * ```php
     * $hasControllers = Introspect::classes()
     *     ->whereNameEndsWith('Controller')
     *     ->exists();
     * // => true/false
     * ```
     */
    public function exists(): bool
    {
        return $this->get()->isNotEmpty();
    }

    /**
     * Count matching classes.
     *
     * @return int Number of classes matching filters
     *
     * @example
     * ```php
     * $count = Introspect::classes()
     *     ->whereName('App\Models\*')
     *     ->count();
     * // => 15
     * ```
     */
    public function count(): int
    {
        return $this->get()->count();
    }

    /**
     * Check if a class matches all filter conditions.
     *
     * Evaluates primary filters (AND logic) and OR filters separately.
     * Returns true if primary filters pass AND at least one OR filter passes
     * (or if no OR filters are defined).
     *
     * @param  class-string $class Fully-qualified class name
     * @return bool         True if class matches all conditions
     */
    private function matchesFilters(string $class): bool
    {
        // Check primary filters (AND logic)
        $matchesPrimary = $this->matchesPrimaryFilters($class);

        // If no OR filters, just return primary match result
        if ($this->orFilters === []) {
            return $matchesPrimary;
        }

        // With OR filters: primary matches OR any OR filter matches
        if ($matchesPrimary) {
            return true;
        }

        return array_any($this->orFilters, fn (ClassesIntrospector $orQuery): bool => $orQuery->matchesPrimaryFilters($class));
    }

    /**
     * Check if a class matches the primary filter conditions.
     *
     * All primary filters must pass (AND logic).
     *
     * @param  class-string $class Fully-qualified class name
     * @return bool         True if class matches all primary conditions
     */
    private function matchesPrimaryFilters(string $class): bool
    {
        if (isset($this->filters['name'])) {
            /** @var string $pattern */
            $pattern = $this->filters['name'];

            if (!$this->matchesPattern($class, $pattern)) {
                return false;
            }
        }

        if (isset($this->filters['starts_with'])) {
            /** @var string $prefix */
            $prefix = $this->filters['starts_with'];

            if (!str_starts_with($class, $prefix)) {
                return false;
            }
        }

        if (isset($this->filters['ends_with'])) {
            /** @var string $suffix */
            $suffix = $this->filters['ends_with'];

            if (!str_ends_with($class, $suffix)) {
                return false;
            }
        }

        if (isset($this->filters['contains'])) {
            /** @var string $substring */
            $substring = $this->filters['contains'];

            if (!str_contains($class, $substring)) {
                return false;
            }
        }

        if (isset($this->filters['extends'])) {
            /** @var string $parent */
            $parent = $this->filters['extends'];

            if (!Reflection::extendsClass($class, $parent)) {
                return false;
            }
        }

        if (isset($this->filters['implements'])) {
            /** @var string $interface */
            $interface = $this->filters['implements'];

            if (!Reflection::implementsInterface($class, $interface)) {
                return false;
            }
        }

        if (isset($this->filters['uses'])) {
            /** @var string $trait */
            $trait = $this->filters['uses'];

            if (!Reflection::usesTrait($class, $trait)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a value matches a wildcard pattern.
     *
     * Converts wildcard pattern to regex for matching.
     * Supports * as wildcard character.
     *
     * @param  string $value   Value to test
     * @param  string $pattern Wildcard pattern (e.g., 'App\Models\*', '*Controller')
     * @return bool   True if value matches pattern
     */
    private function matchesPattern(string $value, string $pattern): bool
    {
        // Convert wildcard pattern to regex
        $regex = '/^'.str_replace(['\\', '*'], ['\\\\', '.*'], $pattern).'$/';

        return (bool) preg_match($regex, $value);
    }
}
