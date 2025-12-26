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
use function array_values;
use function collect;
use function get_declared_traits;
use function preg_match;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function str_starts_with;

/**
 * Fluent query builder for trait introspection.
 *
 * Allows querying traits with wildcard support and filtering by classes
 * that use specific traits.
 * @author Brian Faust <brian@cline.sh>
 */
final class TraitIntrospector
{
    /** @var array<string, string> */
    private array $filters = [];

    /** @var null|array<int, class-string> */
    private ?array $classes = null;

    /**
     * Filter traits by name pattern (supports wildcards).
     *
     * @param string $pattern Pattern to match (e.g., 'App\Traits\*', '*Auditable')
     */
    public function whereNameEquals(string $pattern): static
    {
        $this->filters['name'] = $pattern;

        return $this;
    }

    /**
     * Filter traits by name prefix.
     */
    public function whereNameStartsWith(string $prefix): static
    {
        $this->filters['starts_with'] = $prefix;

        return $this;
    }

    /**
     * Filter traits by name suffix.
     */
    public function whereNameEndsWith(string $suffix): static
    {
        $this->filters['ends_with'] = $suffix;

        return $this;
    }

    /**
     * Filter traits by name containing substring.
     */
    public function whereNameContains(string $substring): static
    {
        $this->filters['contains'] = $substring;

        return $this;
    }

    /**
     * Filter traits used by a specific class.
     */
    public function whereUsedBy(string $className): static
    {
        $this->filters['used_by'] = $className;

        return $this;
    }

    /**
     * Set the classes to search within.
     *
     * @param array<int, class-string> $classes
     */
    public function in(array $classes): static
    {
        $this->classes = $classes;

        return $this;
    }

    /**
     * Get all traits matching the filters.
     *
     * @return Collection<int, class-string>
     */
    public function get(): Collection
    {
        $traits = $this->collectTraits();

        return collect($traits)->filter(fn (string $trait): bool => $this->matchesFilters($trait))->values();
    }

    /**
     * Get the first matching trait.
     *
     * @return null|class-string
     */
    public function first(): ?string
    {
        /** @var null|class-string */
        return $this->get()->first();
    }

    /**
     * Check if any traits match the filters.
     */
    public function exists(): bool
    {
        return $this->get()->isNotEmpty();
    }

    /**
     * Count matching traits.
     */
    public function count(): int
    {
        return $this->get()->count();
    }

    /**
     * @return array<int, class-string>
     */
    private function collectTraits(): array
    {
        // If classes was never set (null), get all declared traits
        if ($this->classes === null) {
            return $this->getAllDeclaredTraits();
        }

        // If classes was explicitly set to empty array, return empty
        if ($this->classes === []) {
            return [];
        }

        $traits = [];

        foreach ($this->classes as $class) {
            $traits = [...$traits, ...Reflection::allTraits($class)];
        }

        /** @var array<int, class-string> */
        return array_values(array_unique($traits));
    }

    /**
     * @return array<int, class-string>
     */
    private function getAllDeclaredTraits(): array
    {
        return get_declared_traits();
    }

    private function matchesFilters(string $trait): bool
    {
        $name = $this->filters['name'] ?? null;

        if ($name !== null && !$this->matchesPattern($trait, $name)) {
            return false;
        }

        $startsWith = $this->filters['starts_with'] ?? null;

        if ($startsWith !== null && !str_starts_with($trait, $startsWith)) {
            return false;
        }

        $endsWith = $this->filters['ends_with'] ?? null;

        if ($endsWith !== null && !str_ends_with($trait, $endsWith)) {
            return false;
        }

        $contains = $this->filters['contains'] ?? null;

        if ($contains !== null && !str_contains($trait, $contains)) {
            return false;
        }

        $usedBy = $this->filters['used_by'] ?? null;

        if ($usedBy !== null) {
            /** @var class-string $usedBy */
            if (!Reflection::usesTrait($usedBy, $trait)) {
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
