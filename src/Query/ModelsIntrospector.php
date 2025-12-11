<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Introspect\Query;

use Cline\Introspect\Reflection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use ReflectionClass;
use ReflectionNamedType;
use Throwable;

use function array_all;
use function array_any;
use function array_filter;
use function collect;
use function count;
use function get_declared_classes;
use function in_array;
use function str_contains;
use function str_replace;
use function ucwords;

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
 * @author Brian Faust <brian@cline.sh>
 */
final class ModelsIntrospector
{
    /** @var array<int, callable> */
    private array $filters = [];

    /** @var array<int, callable> */
    private array $orFilters = [];

    /** @var array<string> */
    private array $models = [];

    private bool $modelsExplicitlySet = false;

    /**
     * Set the models to search within.
     *
     * @param array<string> $models Array of model class names
     */
    public function in(array $models): static
    {
        $this->models = $models;
        $this->modelsExplicitlySet = true;

        return $this;
    }

    /**
     * Filter models that have a specific property.
     *
     * @param string $name Property name to check
     */
    public function whereHasProperty(string $name): static
    {
        $this->filters[] =
            /** @param class-string $model */
            fn (string $model): bool => Reflection::hasProperty($model, $name);

        return $this;
    }

    /**
     * Filter models that don't have a specific property.
     *
     * @param string $name Property name to check
     */
    public function whereDoesntHaveProperty(string $name): static
    {
        $this->filters[] = /** @param class-string $model */ fn (string $model): bool => !Reflection::hasProperty($model, $name);

        return $this;
    }

    /**
     * Filter models that have specific properties.
     *
     * @param array<string> $names Array of property names
     * @param bool          $all   If true, model must have ALL properties. If false, model must have ANY property.
     */
    public function whereHasProperties(array $names, bool $all = true): static
    {
        $this->filters[] = /** @param class-string $model */ function (string $model) use ($names, $all): bool {
            $matches = array_filter($names, fn (string $name): bool => Reflection::hasProperty($model, $name));

            return $all ? count($matches) === count($names) : $matches !== [];
        };

        return $this;
    }

    /**
     * Filter models that don't have specific properties.
     *
     * @param array<string> $names Array of property names
     * @param bool          $all   If true, model must have NONE of the properties. If false, model must be missing at least one.
     */
    public function whereDoesntHaveProperties(array $names, bool $all = true): static
    {
        $this->filters[] = /** @param class-string $model */ function (string $model) use ($names, $all): bool {
            $matches = array_filter($names, fn (string $name): bool => !Reflection::hasProperty($model, $name));

            return $all ? count($matches) === count($names) : $matches !== [];
        };

        return $this;
    }

    /**
     * Filter models that have a fillable property.
     *
     * @param string $property Fillable property name
     */
    public function whereHasFillable(string $property): static
    {
        $this->filters[] = /** @param class-string $model */ fn (string $model): bool => $this->hasFillable($model, $property);

        return $this;
    }

    /**
     * Filter models that don't have a fillable property.
     *
     * @param string $property Fillable property name
     */
    public function whereDoesntHaveFillable(string $property): static
    {
        $this->filters[] = /** @param class-string $model */ fn (string $model): bool => !$this->hasFillable($model, $property);

        return $this;
    }

    /**
     * Filter models that have specific fillable properties.
     *
     * @param array<string> $names Array of fillable property names
     * @param bool          $all   If true, model must have ALL fillable. If false, model must have ANY fillable.
     */
    public function whereHasFillableProperties(array $names, bool $all = true): static
    {
        $this->filters[] = /** @param class-string $model */ function (string $model) use ($names, $all): bool {
            $matches = array_filter($names, fn (string $name): bool => $this->hasFillable($model, $name));

            return $all ? count($matches) === count($names) : $matches !== [];
        };

        return $this;
    }

    /**
     * Filter models that have a hidden property.
     *
     * @param string $property Hidden property name
     */
    public function whereHasHidden(string $property): static
    {
        $this->filters[] = /** @param class-string $model */ fn (string $model): bool => $this->hasHidden($model, $property);

        return $this;
    }

    /**
     * Filter models that don't have a hidden property.
     *
     * @param string $property Hidden property name
     */
    public function whereDoesntHaveHidden(string $property): static
    {
        $this->filters[] = /** @param class-string $model */ fn (string $model): bool => !$this->hasHidden($model, $property);

        return $this;
    }

    /**
     * Filter models that have specific hidden properties.
     *
     * @param array<string> $names Array of hidden property names
     * @param bool          $all   If true, model must have ALL hidden. If false, model must have ANY hidden.
     */
    public function whereHasHiddenProperties(array $names, bool $all = true): static
    {
        $this->filters[] = /** @param class-string $model */ function (string $model) use ($names, $all): bool {
            $matches = array_filter($names, fn (string $name): bool => $this->hasHidden($model, $name));

            return $all ? count($matches) === count($names) : $matches !== [];
        };

        return $this;
    }

    /**
     * Filter models that have an appended attribute.
     *
     * @param string $property Appended attribute name
     */
    public function whereHasAppended(string $property): static
    {
        $this->filters[] = /** @param class-string $model */ fn (string $model): bool => $this->hasAppended($model, $property);

        return $this;
    }

    /**
     * Filter models that don't have an appended attribute.
     *
     * @param string $property Appended attribute name
     */
    public function whereDoesntHaveAppended(string $property): static
    {
        $this->filters[] = /** @param class-string $model */ fn (string $model): bool => !$this->hasAppended($model, $property);

        return $this;
    }

    /**
     * Filter models that have specific appended attributes.
     *
     * @param array<string> $names Array of appended attribute names
     * @param bool          $all   If true, model must have ALL appended. If false, model must have ANY appended.
     */
    public function whereHasAppendedProperties(array $names, bool $all = true): static
    {
        $this->filters[] = /** @param class-string $model */ function (string $model) use ($names, $all): bool {
            $matches = array_filter($names, fn (string $name): bool => $this->hasAppended($model, $name));

            return $all ? count($matches) === count($names) : $matches !== [];
        };

        return $this;
    }

    /**
     * Filter models that have a readable property (public or has accessor).
     *
     * @param string $property Property name
     */
    public function whereHasReadable(string $property): static
    {
        $this->filters[] = /** @param class-string $model */ fn (string $model): bool => $this->hasReadable($model, $property);

        return $this;
    }

    /**
     * Filter models that don't have a readable property.
     *
     * @param string $property Property name
     */
    public function whereDoesntHaveReadable(string $property): static
    {
        $this->filters[] = /** @param class-string $model */ fn (string $model): bool => !$this->hasReadable($model, $property);

        return $this;
    }

    /**
     * Filter models that have specific readable properties.
     *
     * @param array<string> $names Array of readable property names
     * @param bool          $all   If true, model must have ALL readable. If false, model must have ANY readable.
     */
    public function whereHasReadableProperties(array $names, bool $all = true): static
    {
        $this->filters[] = /** @param class-string $model */ function (string $model) use ($names, $all): bool {
            $matches = array_filter($names, fn (string $name): bool => $this->hasReadable($model, $name));

            return $all ? count($matches) === count($names) : $matches !== [];
        };

        return $this;
    }

    /**
     * Filter models that have a writable property (fillable or public).
     *
     * @param string $property Property name
     */
    public function whereHasWritable(string $property): static
    {
        $this->filters[] = /** @param class-string $model */ fn (string $model): bool => $this->hasWritable($model, $property);

        return $this;
    }

    /**
     * Filter models that don't have a writable property.
     *
     * @param string $property Property name
     */
    public function whereDoesntHaveWritable(string $property): static
    {
        $this->filters[] = /** @param class-string $model */ fn (string $model): bool => !$this->hasWritable($model, $property);

        return $this;
    }

    /**
     * Filter models that have specific writable properties.
     *
     * @param array<string> $names Array of writable property names
     * @param bool          $all   If true, model must have ALL writable. If false, model must have ANY writable.
     */
    public function whereHasWritableProperties(array $names, bool $all = true): static
    {
        $this->filters[] = /** @param class-string $model */ function (string $model) use ($names, $all): bool {
            $matches = array_filter($names, fn (string $name): bool => $this->hasWritable($model, $name));

            return $all ? count($matches) === count($names) : $matches !== [];
        };

        return $this;
    }

    /**
     * Filter models that have a specific relationship.
     *
     * @param string $relationshipName Relationship method name
     */
    public function whereHasRelationship(string $relationshipName): static
    {
        $this->filters[] = /** @param class-string $model */ fn (string $model): bool => $this->hasRelationship($model, $relationshipName);

        return $this;
    }

    /**
     * Filter models that don't have a specific relationship.
     *
     * @param string $relationshipName Relationship method name
     */
    public function whereDoesntHaveRelationship(string $relationshipName): static
    {
        $this->filters[] = /** @param class-string $model */ fn (string $model): bool => !$this->hasRelationship($model, $relationshipName);

        return $this;
    }

    /**
     * Add OR logic to the query.
     *
     * Models will match if they pass either the main filters OR the filters in the callback.
     *
     * @param callable $callback Callback that receives a new query instance
     */
    public function or(callable $callback): static
    {
        $query = new self();
        $callback($query);
        $this->orFilters[] = /** @param class-string $model */ fn (string $model): bool => $query->matches($model);

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

        return collect($models)->filter(fn (string $model): bool => $this->matchesFilters($model))->values();
    }

    /**
     * Get the first matching model.
     *
     * @return null|string Model class name or null if no match
     */
    public function first(): ?string
    {
        return $this->get()->first();
    }

    /**
     * Check if any models match the filters.
     */
    public function exists(): bool
    {
        return $this->get()->isNotEmpty();
    }

    /**
     * Count matching models.
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
        // If in() was called (even with empty array), use that explicitly
        if ($this->modelsExplicitlySet) {
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
            if (!$this->isEloquentModel($class)) {
                continue;
            }

            $models[] = $class;
        }

        return $models;
    }

    /**
     * Check if a class is an Eloquent model.
     */
    private function isEloquentModel(string $class): bool
    {
        if ($class === Model::class) {
            return false;
        }

        return Reflection::extendsClass($class, Model::class);
    }

    /**
     * Check if model matches all filters.
     */
    private function matchesFilters(string $model): bool
    {
        // If no filters at all, match everything
        if ($this->filters === [] && $this->orFilters === []) {
            return true;
        }

        // Check main filters (AND logic)
        $mainMatches = $this->matches($model);

        // If no OR filters, return main filter result
        if ($this->orFilters === []) {
            return $mainMatches;
        }

        // With OR filters, match if main filters pass OR any OR filter passes
        if ($mainMatches) {
            return true;
        }

        return array_any($this->orFilters, fn (callable $orFilter): bool => (bool) $orFilter($model));
    }

    /**
     * Check if model matches all main filters.
     */
    private function matches(string $model): bool
    {
        return array_all($this->filters, fn (callable $filter): bool => (bool) $filter($model));
    }

    /**
     * Check if model has a fillable property.
     */
    private function hasFillable(string $model, string $property): bool
    {
        try {
            /** @var Model $instance */
            $instance = new $model();

            /** @var array<int|string, string> $fillable */
            $fillable = $instance->getFillable();

            return in_array($property, $fillable, true);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Check if model has a hidden property.
     */
    private function hasHidden(string $model, string $property): bool
    {
        try {
            /** @var Model $instance */
            $instance = new $model();

            /** @var array<int|string, string> $hidden */
            $hidden = $instance->getHidden();

            return in_array($property, $hidden, true);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Check if model has an appended attribute.
     */
    private function hasAppended(string $model, string $property): bool
    {
        try {
            /** @var class-string $model */
            $reflection = new ReflectionClass($model);
            $property_reflection = $reflection->getProperty('appends');

            /** @var array<int|string, string> $appends */
            $appends = $property_reflection->getDefaultValue() ?? [];

            return in_array($property, $appends, true);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Check if model has a readable property (fillable, public, or has accessor).
     */
    private function hasReadable(string $model, string $property): bool
    {
        try {
            // Fillable properties are readable through Eloquent's magic __get
            if ($this->hasFillable($model, $property)) {
                return true;
            }

            /** @var class-string $model */
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
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Check if model has a writable property (fillable or public).
     */
    private function hasWritable(string $model, string $property): bool
    {
        try {
            // Check if fillable
            if ($this->hasFillable($model, $property)) {
                return true;
            }

            // Check for public property
            /** @var class-string $model */
            $reflection = new ReflectionClass($model);

            if ($reflection->hasProperty($property)) {
                $prop = $reflection->getProperty($property);

                if ($prop->isPublic()) {
                    return true;
                }
            }

            // Check for mutator (setXxxAttribute)
            $mutatorName = 'set'.str_replace('_', '', ucwords($property, '_')).'Attribute';

            return $reflection->hasMethod($mutatorName);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Check if model has a relationship method.
     */
    private function hasRelationship(string $model, string $relationshipName): bool
    {
        try {
            /** @var class-string $model */
            $reflection = new ReflectionClass($model);

            if (!$reflection->hasMethod($relationshipName)) {
                return false;
            }

            $method = $reflection->getMethod($relationshipName);

            // Must be public
            if (!$method->isPublic()) {
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

            $returnTypeName = $returnType instanceof ReflectionNamedType
                ? $returnType->getName()
                : (string) $returnType;

            // Check if return type is a Relation
            return str_contains($returnTypeName, 'Illuminate\Database\Eloquent\Relations')
                || str_contains($returnTypeName, 'Relation');
        } catch (Throwable) {
            return false;
        }
    }
}
