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

use function array_unique;
use function class_implements;
use function collect;
use function preg_match;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function str_starts_with;

/**
 * Fluent query builder for interface introspection.
 *
 * Allows querying interfaces with wildcard support and filtering by classes
 * that implement specific interfaces.
 * @author Brian Faust <brian@cline.sh>
 */
final class InterfaceIntrospector
{
    /** @var array<string, string> */
    private array $filters = [];

    /** @var array<int, class-string> */
    private array $classes = [];

    /**
     * Filter interfaces by name pattern (supports wildcards).
     *
     * @param string $pattern Pattern to match (e.g., 'App\Contracts\*', '*able')
     *
     * @return static Fluent interface for method chaining
     */
    public function whereNameEquals(string $pattern): static
    {
        $this->filters['name'] = $pattern;

        return $this;
    }

    /**
     * Filter interfaces by name prefix.
     *
     * @param string $prefix Prefix to match (e.g., 'App\Contracts', 'Illuminate\Contracts')
     *
     * @return static Fluent interface for method chaining
     */
    public function whereNameStartsWith(string $prefix): static
    {
        $this->filters['starts_with'] = $prefix;

        return $this;
    }

    /**
     * Filter interfaces by name suffix.
     *
     * @param string $suffix Suffix to match (e.g., 'Interface', 'able', 'Contract')
     *
     * @return static Fluent interface for method chaining
     */
    public function whereNameEndsWith(string $suffix): static
    {
        $this->filters['ends_with'] = $suffix;

        return $this;
    }

    /**
     * Filter interfaces by name containing substring.
     *
     * @param string $substring Substring to search for within interface names
     *
     * @return static Fluent interface for method chaining
     */
    public function whereNameContains(string $substring): static
    {
        $this->filters['contains'] = $substring;

        return $this;
    }

    /**
     * Filter interfaces implemented by a specific class.
     *
     * Only includes interfaces that are implemented by the specified class.
     *
     * @param string $className Fully-qualified class name
     *
     * @return static Fluent interface for method chaining
     */
    public function whereImplementedBy(string $className): static
    {
        $this->filters['implemented_by'] = $className;

        return $this;
    }

    /**
     * Set the classes to search within.
     *
     * Limits the interface search to only those implemented by the specified classes.
     * Useful for scoping queries to a specific set of classes.
     *
     * @param array<int, class-string> $classes Array of class names to search for interfaces
     *
     * @return static Fluent interface for method chaining
     */
    public function in(array $classes): static
    {
        $this->classes = $classes;

        return $this;
    }

    /**
     * Get all interfaces matching the filters.
     *
     * @return Collection<int, class-string>
     */
    public function get(): Collection
    {
        $interfaces = $this->collectInterfaces();

        return collect($interfaces)->filter(fn (string $interface): bool => $this->matchesFilters($interface))->values();
    }

    /**
     * Get the first matching interface.
     *
     * @return null|class-string
     */
    public function first(): ?string
    {
        /** @var null|class-string */
        return $this->get()->first();
    }

    /**
     * Check if any interfaces match the filters.
     *
     * @return bool True if at least one interface matches, false otherwise
     */
    public function exists(): bool
    {
        return $this->get()->isNotEmpty();
    }

    /**
     * Count matching interfaces.
     *
     * @return int Number of interfaces matching the filters
     */
    public function count(): int
    {
        return $this->get()->count();
    }

    /**
     * @return array<int, class-string>
     */
    private function collectInterfaces(): array
    {
        // If classes is explicitly set to empty array, return empty
        if ($this->classes === []) {
            return [];
        }

        $interfaces = [];

        foreach ($this->classes as $class) {
            $implemented = class_implements($class);

            if ($implemented === false) {
                continue;
            }

            $interfaces = [...$interfaces, ...$implemented];
        }

        /** @var array<int, class-string> */
        return array_unique($interfaces);
    }

    private function matchesFilters(string $interface): bool
    {
        $name = $this->filters['name'] ?? null;

        if ($name !== null && !$this->matchesPattern($interface, $name)) {
            return false;
        }

        $startsWith = $this->filters['starts_with'] ?? null;

        if ($startsWith !== null && !str_starts_with($interface, $startsWith)) {
            return false;
        }

        $endsWith = $this->filters['ends_with'] ?? null;

        if ($endsWith !== null && !str_ends_with($interface, $endsWith)) {
            return false;
        }

        $contains = $this->filters['contains'] ?? null;

        if ($contains !== null && !str_contains($interface, $contains)) {
            return false;
        }

        $implementedBy = $this->filters['implemented_by'] ?? null;

        if ($implementedBy !== null) {
            /** @var class-string $implementedBy */
            if (!Reflection::implementsInterface($implementedBy, $interface)) {
                return false;
            }
        }

        return true;
    }

    private function matchesPattern(string $value, string $pattern): bool
    {
        // Convert wildcard pattern to regex
        $regex = '/^'.str_replace(['\\', '*'], ['\\\\', '.*'], $pattern).'$/';

        return (bool) preg_match($regex, $value);
    }
}
