<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Introspect\Query;

use Illuminate\Support\Collection;

use function Cline\Introspect\usesTrait;

/**
 * Fluent query builder for trait introspection.
 *
 * Allows querying traits with wildcard support and filtering by classes
 * that use specific traits.
 */
class TraitIntrospector
{
    private array $filters = [];
    private array $classes = [];

    /**
     * Filter traits by name pattern (supports wildcards).
     *
     * @param  string $pattern Pattern to match (e.g., 'App\Traits\*', '*Auditable')
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
     */
    public function in(array $classes): static
    {
        $this->classes = $classes;

        return $this;
    }

    /**
     * Get all traits matching the filters.
     */
    public function get(): Collection
    {
        $traits = $this->collectTraits();

        return collect($traits)->filter(fn ($trait) => $this->matchesFilters($trait));
    }

    /**
     * Get the first matching trait.
     */
    public function first(): ?string
    {
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

    private function collectTraits(): array
    {
        if (empty($this->classes)) {
            return $this->getAllDeclaredTraits();
        }

        $traits = [];
        foreach ($this->classes as $class) {
            $traits = [...$traits, ...\Cline\Introspect\getAllTraits($class)];
        }

        return array_unique($traits);
    }

    private function getAllDeclaredTraits(): array
    {
        return get_declared_traits();
    }

    private function matchesFilters(string $trait): bool
    {
        if (isset($this->filters['name']) && ! $this->matchesPattern($trait, $this->filters['name'])) {
            return false;
        }

        if (isset($this->filters['starts_with']) && ! str_starts_with($trait, $this->filters['starts_with'])) {
            return false;
        }

        if (isset($this->filters['ends_with']) && ! str_ends_with($trait, $this->filters['ends_with'])) {
            return false;
        }

        if (isset($this->filters['contains']) && ! str_contains($trait, $this->filters['contains'])) {
            return false;
        }

        if (isset($this->filters['used_by']) && ! usesTrait($this->filters['used_by'], $trait)) {
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
