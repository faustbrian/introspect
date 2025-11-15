<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Introspect\Query;

use Illuminate\Support\Collection;

use function Cline\Introspect\implementsInterface;

/**
 * Fluent query builder for interface introspection.
 *
 * Allows querying interfaces with wildcard support and filtering by classes
 * that implement specific interfaces.
 */
class InterfaceIntrospector
{
    private array $filters = [];
    private array $classes = [];

    /**
     * Filter interfaces by name pattern (supports wildcards).
     *
     * @param  string $pattern Pattern to match (e.g., 'App\Contracts\*', '*able')
     */
    public function whereNameEquals(string $pattern): static
    {
        $this->filters['name'] = $pattern;

        return $this;
    }

    /**
     * Filter interfaces by name prefix.
     */
    public function whereNameStartsWith(string $prefix): static
    {
        $this->filters['starts_with'] = $prefix;

        return $this;
    }

    /**
     * Filter interfaces by name suffix.
     */
    public function whereNameEndsWith(string $suffix): static
    {
        $this->filters['ends_with'] = $suffix;

        return $this;
    }

    /**
     * Filter interfaces by name containing substring.
     */
    public function whereNameContains(string $substring): static
    {
        $this->filters['contains'] = $substring;

        return $this;
    }

    /**
     * Filter interfaces implemented by a specific class.
     */
    public function whereImplementedBy(string $className): static
    {
        $this->filters['implemented_by'] = $className;

        return $this;
    }

    /**
     * Set the classes to search within.
     */
    public function in(array $classes): static
    {
        $this->classes = $classes;

        return $this;
    }

    /**
     * Get all interfaces matching the filters.
     */
    public function get(): Collection
    {
        $interfaces = $this->collectInterfaces();

        return collect($interfaces)->filter(fn ($interface) => $this->matchesFilters($interface));
    }

    /**
     * Get the first matching interface.
     */
    public function first(): ?string
    {
        return $this->get()->first();
    }

    /**
     * Check if any interfaces match the filters.
     */
    public function exists(): bool
    {
        return $this->get()->isNotEmpty();
    }

    /**
     * Count matching interfaces.
     */
    public function count(): int
    {
        return $this->get()->count();
    }

    private function collectInterfaces(): array
    {
        // If classes is explicitly set to empty array, return empty
        if ($this->classes === []) {
            return [];
        }

        if (empty($this->classes)) {
            return $this->getAllDeclaredInterfaces();
        }

        $interfaces = [];
        foreach ($this->classes as $class) {
            $interfaces = [...$interfaces, ...class_implements($class) ?: []];
        }

        return array_unique($interfaces);
    }

    private function getAllDeclaredInterfaces(): array
    {
        return get_declared_interfaces();
    }

    private function matchesFilters(string $interface): bool
    {
        if (isset($this->filters['name']) && ! $this->matchesPattern($interface, $this->filters['name'])) {
            return false;
        }

        if (isset($this->filters['starts_with']) && ! str_starts_with($interface, $this->filters['starts_with'])) {
            return false;
        }

        if (isset($this->filters['ends_with']) && ! str_ends_with($interface, $this->filters['ends_with'])) {
            return false;
        }

        if (isset($this->filters['contains']) && ! str_contains($interface, $this->filters['contains'])) {
            return false;
        }

        if (isset($this->filters['implemented_by']) && ! implementsInterface($this->filters['implemented_by'], $interface)) {
            return false;
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
