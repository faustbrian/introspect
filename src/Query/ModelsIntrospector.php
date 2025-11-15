<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Introspect\Query;

use Illuminate\Support\Collection;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

use function Cline\Introspect\extendsClass;

/**
 * Fluent query builder for Eloquent model introspection.
 *
 * Provides chainable methods for discovering and filtering Eloquent models with support
 * for property, fillable, hidden, appended, readable, writable, and relationship filters.
 *
 * ```php
 * // Find models with specific properties
 * $models = Introspect::models()
 *     ->whereHasProperty('email')
 *     ->whereHasFillable('name')
 *     ->get();
 *
 * // Filter by relationships
 * $models = Introspect::models()
 *     ->whereHasRelationship('posts')
 *     ->get();
 *
 * // Use OR logic
 * $models = Introspect::models()
 *     ->whereHasProperty('title')
 *     ->or(fn($query) => $query->whereHasProperty('name'))
 *     ->get();
 * ```
 */
class ModelsIntrospector
{
    /** @var array<int, callable> */
    private array $filters = [];

    /** @var array<int, callable> */
    private array $orFilters = [];

    /** @var array<string> */
    private array $models = [];

    /**
     * Set the models to search within.
     *
     * @param  array<string> $models Array of model class names
     * @return static
     */
    public function in(array $models): static
    {
        $this->models = $models;

        return $this;
    }

    /**
     * Filter models that have a specific property.
     *
     * @param  string $name Property name to check
     * @return static
     */
    public function whereHasProperty(string $name): static
    {
        $this->filters[] = fn (string $model) => $this->hasProperty($model, $name);

        return $this;
    }

    /**
     * Filter models that don't have a specific property.
     *
     * @param  string $name Property name to check
     * @return static
     */
    public function whereDoesntHaveProperty(string $name): static
    {
        $this->filters[] = fn (string $model) => ! $this->hasProperty($model, $name);

        return $this;
    }

    /**
     * Filter models that have specific properties.
     *
     * @param  array<string> $names Array of property names
     * @param  bool          $all   If true, model must have ALL properties. If false, model must have ANY property.
     * @return static
     */
    public function whereHasProperties(array $names, bool $all = true): static
    {
        $this->filters[] = function (string $model) use ($names, $all) {
            $matches = array_filter($names, fn ($name) => $this->hasProperty($model, $name));

            return $all ? count($matches) === count($names) : count($matches) > 0;
        };

        return $this;
    }

    /**
     * Filter models that don't have specific properties.
     *
     * @param  array<string> $names Array of property names
     * @param  bool          $all   If true, model must have NONE of the properties. If false, model must be missing at least one.
     * @return static
     */
    public function whereDoesntHaveProperties(array $names, bool $all = true): static
    {
        $this->filters[] = function (string $model) use ($names, $all) {
            $matches = array_filter($names, fn ($name) => ! $this->hasProperty($model, $name));

            return $all ? count($matches) === count($names) : count($matches) > 0;
        };

        return $this;
    }

    /**
     * Filter models that have a fillable property.
     *
     * @param  string $property Fillable property name
     * @return static
     */
    public function whereHasFillable(string $property): static
    {
        $this->filters[] = fn (string $model) => $this->hasFillable($model, $property);

        return $this;
    }

    /**
     * Filter models that don't have a fillable property.
     *
     * @param  string $property Fillable property name
     * @return static
     */
    public function whereDoesntHaveFillable(string $property): static
    {
        $this->filters[] = fn (string $model) => ! $this->hasFillable($model, $property);

        return $this;
    }

    /**
     * Filter models that have specific fillable properties.
     *
     * @param  array<string> $names Array of fillable property names
     * @param  bool          $all   If true, model must have ALL fillable. If false, model must have ANY fillable.
     * @return static
     */
    public function whereHasFillableProperties(array $names, bool $all = true): static
    {
        $this->filters[] = function (string $model) use ($names, $all) {
            $matches = array_filter($names, fn ($name) => $this->hasFillable($model, $name));

            return $all ? count($matches) === count($names) : count($matches) > 0;
        };

        return $this;
    }

    /**
     * Filter models that have a hidden property.
     *
     * @param  string $property Hidden property name
     * @return static
     */
    public function whereHasHidden(string $property): static
    {
        $this->filters[] = fn (string $model) => $this->hasHidden($model, $property);

        return $this;
    }

    /**
     * Filter models that don't have a hidden property.
     *
     * @param  string $property Hidden property name
     * @return static
     */
    public function whereDoesntHaveHidden(string $property): static
    {
        $this->filters[] = fn (string $model) => ! $this->hasHidden($model, $property);

        return $this;
    }

    /**
     * Filter models that have specific hidden properties.
     *
     * @param  array<string> $names Array of hidden property names
     * @param  bool          $all   If true, model must have ALL hidden. If false, model must have ANY hidden.
     * @return static
     */
    public function whereHasHiddenProperties(array $names, bool $all = true): static
    {
        $this->filters[] = function (string $model) use ($names, $all) {
            $matches = array_filter($names, fn ($name) => $this->hasHidden($model, $name));

            return $all ? count($matches) === count($names) : count($matches) > 0;
        };

        return $this;
    }

    /**
     * Filter models that have an appended attribute.
     *
     * @param  string $property Appended attribute name
     * @return static
     */
    public function whereHasAppended(string $property): static
    {
        $this->filters[] = fn (string $model) => $this->hasAppended($model, $property);

        return $this;
    }

    /**
     * Filter models that don't have an appended attribute.
     *
     * @param  string $property Appended attribute name
     * @return static
     */
    public function whereDoesntHaveAppended(string $property): static
    {
        $this->filters[] = fn (string $model) => ! $this->hasAppended($model, $property);

        return $this;
    }

    /**
     * Filter models that have specific appended attributes.
     *
     * @param  array<string> $names Array of appended attribute names
     * @param  bool          $all   If true, model must have ALL appended. If false, model must have ANY appended.
     * @return static
     */
    public function whereHasAppendedProperties(array $names, bool $all = true): static
    {
        $this->filters[] = function (string $model) use ($names, $all) {
            $matches = array_filter($names, fn ($name) => $this->hasAppended($model, $name));

            return $all ? count($matches) === count($names) : count($matches) > 0;
        };

        return $this;
    }

    /**
     * Filter models that have a readable property (public or has accessor).
     *
     * @param  string $property Property name
     * @return static
     */
    public function whereHasReadable(string $property): static
    {
        $this->filters[] = fn (string $model) => $this->hasReadable($model, $property);

        return $this;
    }

    /**
     * Filter models that don't have a readable property.
     *
     * @param  string $property Property name
     * @return static
     */
    public function whereDoesntHaveReadable(string $property): static
    {
        $this->filters[] = fn (string $model) => ! $this->hasReadable($model, $property);

        return $this;
    }

    /**
     * Filter models that have specific readable properties.
     *
     * @param  array<string> $names Array of readable property names
     * @param  bool          $all   If true, model must have ALL readable. If false, model must have ANY readable.
     * @return static
     */
    public function whereHasReadableProperties(array $names, bool $all = true): static
    {
        $this->filters[] = function (string $model) use ($names, $all) {
            $matches = array_filter($names, fn ($name) => $this->hasReadable($model, $name));

            return $all ? count($matches) === count($names) : count($matches) > 0;
        };

        return $this;
    }

    /**
     * Filter models that have a writable property (fillable or public).
     *
     * @param  string $property Property name
     * @return static
     */
    public function whereHasWritable(string $property): static
    {
        $this->filters[] = fn (string $model) => $this->hasWritable($model, $property);

        return $this;
    }

    /**
     * Filter models that don't have a writable property.
     *
     * @param  string $property Property name
     * @return static
     */
    public function whereDoesntHaveWritable(string $property): static
    {
        $this->filters[] = fn (string $model) => ! $this->hasWritable($model, $property);

        return $this;
    }

    /**
     * Filter models that have specific writable properties.
     *
     * @param  array<string> $names Array of writable property names
     * @param  bool          $all   If true, model must have ALL writable. If false, model must have ANY writable.
     * @return static
     */
    public function whereHasWritableProperties(array $names, bool $all = true): static
    {
        $this->filters[] = function (string $model) use ($names, $all) {
            $matches = array_filter($names, fn ($name) => $this->hasWritable($model, $name));

            return $all ? count($matches) === count($names) : count($matches) > 0;
        };

        return $this;
    }

    /**
     * Filter models that have a specific relationship.
     *
     * @param  string $relationshipName Relationship method name
     * @return static
     */
    public function whereHasRelationship(string $relationshipName): static
    {
        $this->filters[] = fn (string $model) => $this->hasRelationship($model, $relationshipName);

        return $this;
    }

    /**
     * Filter models that don't have a specific relationship.
     *
     * @param  string $relationshipName Relationship method name
     * @return static
     */
    public function whereDoesntHaveRelationship(string $relationshipName): static
    {
        $this->filters[] = fn (string $model) => ! $this->hasRelationship($model, $relationshipName);

        return $this;
    }

    /**
     * Add OR logic to the query.
     *
     * Models will match if they pass either the main filters OR the filters in the callback.
     *
     * @param  callable $callback Callback that receives a new query instance
     * @return static
     */
    public function or(callable $callback): static
    {
        $query = new static();
        $callback($query);
        $this->orFilters[] = fn (string $model) => $query->matches($model);

        return $this;
    }

    /**
     * Get all models matching the filters.
     *
     * @return Collection<int, string> Collection of model class names
     */
    public function get(): Collection
    {
        $models = $this->collectModels();

        return collect($models)->filter(fn (string $model) => $this->matchesFilters($model))->values();
    }

    /**
     * Get the first matching model.
     *
     * @return string|null Model class name or null if no match
     */
    public function first(): ?string
    {
        return $this->get()->first();
    }

    /**
     * Check if any models match the filters.
     *
     * @return bool
     */
    public function exists(): bool
    {
        return $this->get()->isNotEmpty();
    }

    /**
     * Count matching models.
     *
     * @return int
     */
    public function count(): int
    {
        return $this->get()->count();
    }

    /**
     * Collect all Eloquent models.
     *
     * @return array<string>
     */
    private function collectModels(): array
    {
        if (! empty($this->models)) {
            return $this->models;
        }

        // Discover models by finding classes that extend Illuminate\Database\Eloquent\Model
        return $this->discoverModels();
    }

    /**
     * Discover all Eloquent models from declared classes.
     *
     * @return array<string>
     */
    private function discoverModels(): array
    {
        $models = [];

        foreach (get_declared_classes() as $class) {
            if ($this->isEloquentModel($class)) {
                $models[] = $class;
            }
        }

        return $models;
    }

    /**
     * Check if a class is an Eloquent model.
     *
     * @param  string $class
     * @return bool
     */
    private function isEloquentModel(string $class): bool
    {
        if ($class === 'Illuminate\Database\Eloquent\Model') {
            return false;
        }

        return extendsClass($class, 'Illuminate\Database\Eloquent\Model');
    }

    /**
     * Check if model matches all filters.
     *
     * @param  string $model
     * @return bool
     */
    private function matchesFilters(string $model): bool
    {
        // If no filters at all, match everything
        if (empty($this->filters) && empty($this->orFilters)) {
            return true;
        }

        // Check main filters (AND logic)
        $mainMatches = $this->matches($model);

        // If no OR filters, return main filter result
        if (empty($this->orFilters)) {
            return $mainMatches;
        }

        // With OR filters, match if main filters pass OR any OR filter passes
        if ($mainMatches) {
            return true;
        }

        foreach ($this->orFilters as $orFilter) {
            if ($orFilter($model)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if model matches all main filters.
     *
     * @param  string $model
     * @return bool
     */
    private function matches(string $model): bool
    {
        foreach ($this->filters as $filter) {
            if (! $filter($model)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if model has a property.
     *
     * @param  string $model
     * @param  string $name
     * @return bool
     */
    private function hasProperty(string $model, string $name): bool
    {
        return property_exists($model, $name);
    }

    /**
     * Check if model has a fillable property.
     *
     * @param  string $model
     * @param  string $property
     * @return bool
     */
    private function hasFillable(string $model, string $property): bool
    {
        try {
            $instance = new $model();
            $fillable = $instance->getFillable();

            return in_array($property, $fillable, true);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Check if model has a hidden property.
     *
     * @param  string $model
     * @param  string $property
     * @return bool
     */
    private function hasHidden(string $model, string $property): bool
    {
        try {
            $instance = new $model();
            $hidden = $instance->getHidden();

            return in_array($property, $hidden, true);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Check if model has an appended attribute.
     *
     * @param  string $model
     * @param  string $property
     * @return bool
     */
    private function hasAppended(string $model, string $property): bool
    {
        try {
            $reflection = new ReflectionClass($model);
            $property_reflection = $reflection->getProperty('appends');
            $property_reflection->setAccessible(true);
            $appends = $property_reflection->getDefaultValue() ?? [];

            return in_array($property, $appends, true);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Check if model has a readable property (public or has accessor).
     *
     * @param  string $model
     * @param  string $property
     * @return bool
     */
    private function hasReadable(string $model, string $property): bool
    {
        try {
            $reflection = new ReflectionClass($model);

            // Check for public property
            if ($reflection->hasProperty($property)) {
                $prop = $reflection->getProperty($property);
                if ($prop->isPublic()) {
                    return true;
                }
            }

            // Check for accessor (getXxxAttribute or xxxAttribute method for Laravel 11+)
            $accessorName = 'get'.str_replace('_', '', ucwords($property, '_')).'Attribute';
            if ($reflection->hasMethod($accessorName)) {
                return true;
            }

            // Check for modern accessor (Laravel 9+)
            if ($reflection->hasMethod($property)) {
                $method = $reflection->getMethod($property);
                $attributes = $method->getAttributes();
                foreach ($attributes as $attribute) {
                    if (str_contains($attribute->getName(), 'Attribute')) {
                        return true;
                    }
                }
            }

            return false;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Check if model has a writable property (fillable or public).
     *
     * @param  string $model
     * @param  string $property
     * @return bool
     */
    private function hasWritable(string $model, string $property): bool
    {
        try {
            // Check if fillable
            if ($this->hasFillable($model, $property)) {
                return true;
            }

            // Check for public property
            $reflection = new ReflectionClass($model);
            if ($reflection->hasProperty($property)) {
                $prop = $reflection->getProperty($property);
                if ($prop->isPublic()) {
                    return true;
                }
            }

            // Check for mutator (setXxxAttribute)
            $mutatorName = 'set'.str_replace('_', '', ucwords($property, '_')).'Attribute';
            if ($reflection->hasMethod($mutatorName)) {
                return true;
            }

            return false;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Check if model has a relationship method.
     *
     * @param  string $model
     * @param  string $relationshipName
     * @return bool
     */
    private function hasRelationship(string $model, string $relationshipName): bool
    {
        try {
            $reflection = new ReflectionClass($model);

            if (! $reflection->hasMethod($relationshipName)) {
                return false;
            }

            $method = $reflection->getMethod($relationshipName);

            // Must be public
            if (! $method->isPublic()) {
                return false;
            }

            // Must not be static
            if ($method->isStatic()) {
                return false;
            }

            // Check return type
            $returnType = $method->getReturnType();
            if ($returnType === null) {
                return false;
            }

            $returnTypeName = $returnType instanceof \ReflectionNamedType
                ? $returnType->getName()
                : (string) $returnType;

            // Check if return type is a Relation
            return str_contains($returnTypeName, 'Illuminate\Database\Eloquent\Relations')
                || str_contains($returnTypeName, 'Relation');
        } catch (\Throwable) {
            return false;
        }
    }
}
