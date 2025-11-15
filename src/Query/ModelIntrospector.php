<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Introspect\Query;

use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Detailed introspector for Laravel Eloquent models.
 *
 * Provides comprehensive introspection of a single Eloquent model including
 * properties, schema, relationships, attributes, casts, and more.
 *
 * ```php
 * use Cline\Introspect\Introspect;
 *
 * $introspector = Introspect::model(User::class);
 *
 * // Get model properties
 * $fillable = $introspector->fillable();
 * $hidden = $introspector->hidden();
 * $appended = $introspector->appended();
 * $casts = $introspector->casts();
 *
 * // Get model schema
 * $schema = $introspector->schema();
 *
 * // Get relationships
 * $relationships = $introspector->relationships();
 *
 * // Get database info
 * $table = $introspector->table();
 * $primaryKey = $introspector->primaryKey();
 *
 * // Get all properties
 * $properties = $introspector->properties();
 *
 * // Export everything
 * $data = $introspector->toArray();
 * ```
 */
class ModelIntrospector
{
    private ReflectionClass $reflection;

    private object $instance;

    /**
     * Create a new model introspector.
     *
     * @param  string $modelClass Fully-qualified model class name
     *
     * @throws \ReflectionException If class doesn't exist
     * @throws \InvalidArgumentException If class is not instantiable
     */
    public function __construct(private readonly string $modelClass)
    {
        $this->reflection = new ReflectionClass($modelClass);

        if (! $this->reflection->isInstantiable()) {
            throw new \InvalidArgumentException("Class {$modelClass} is not instantiable");
        }

        $this->instance = $this->reflection->newInstanceWithoutConstructor();
    }

    /**
     * Get all properties discovered from the model.
     *
     * Returns an array containing all fillable, hidden, appended, and casted properties.
     *
     * @return array{fillable: array, hidden: array, appended: array, casts: array}
     */
    public function properties(): array
    {
        return [
            'fillable' => $this->fillable(),
            'hidden' => $this->hidden(),
            'appended' => $this->appended(),
            'casts' => $this->casts(),
        ];
    }

    /**
     * Export model structure as JSON schema.
     *
     * Provides a structured representation of the model including all properties,
     * relationships, and metadata in a schema format.
     *
     * @return array{type: string, properties: array, table: string, primaryKey: string, relationships: array}
     */
    public function schema(): array
    {
        $properties = [];

        // Add fillable properties
        foreach ($this->fillable() as $field) {
            $properties[$field] = [
                'type' => $this->casts()[$field] ?? 'string',
                'fillable' => true,
                'hidden' => \in_array($field, $this->hidden(), true),
            ];
        }

        // Add casted properties not in fillable
        foreach ($this->casts() as $field => $cast) {
            if (! isset($properties[$field])) {
                $properties[$field] = [
                    'type' => $cast,
                    'fillable' => false,
                    'hidden' => \in_array($field, $this->hidden(), true),
                ];
            }
        }

        // Add appended attributes
        foreach ($this->appended() as $field) {
            $properties[$field] = [
                'type' => 'computed',
                'fillable' => false,
                'hidden' => false,
                'appended' => true,
            ];
        }

        return [
            'type' => 'model',
            'class' => $this->modelClass,
            'table' => $this->table(),
            'primaryKey' => $this->primaryKey(),
            'properties' => $properties,
            'relationships' => $this->relationships(),
        ];
    }

    /**
     * Get fillable attributes.
     *
     * Returns the mass-assignable attributes defined on the model.
     *
     * @return array<int, string>
     */
    public function fillable(): array
    {
        return $this->getModelProperty('fillable', []);
    }

    /**
     * Get hidden attributes.
     *
     * Returns attributes that should be hidden from array/JSON representation.
     *
     * @return array<int, string>
     */
    public function hidden(): array
    {
        return $this->getModelProperty('hidden', []);
    }

    /**
     * Get appended attributes.
     *
     * Returns computed attributes that are appended to array/JSON representation.
     *
     * @return array<int, string>
     */
    public function appended(): array
    {
        return $this->getModelProperty('appends', []);
    }

    /**
     * Get cast definitions.
     *
     * Returns the attribute casting configuration.
     *
     * @return array<string, string>
     */
    public function casts(): array
    {
        // Try to get casts from protected property
        $casts = $this->getModelProperty('casts', []);

        // Also check for casts() method (Laravel 10+)
        if (method_exists($this->instance, 'casts') && $this->reflection->hasMethod('casts')) {
            $method = $this->reflection->getMethod('casts');
            if ($method->isPublic() || $method->isProtected()) {
                $method->setAccessible(true);
                $methodCasts = $method->invoke($this->instance);
                if (\is_array($methodCasts)) {
                    $casts = array_merge($casts, $methodCasts);
                }
            }
        }

        return $casts;
    }

    /**
     * Get relationship methods.
     *
     * Discovers all relationship methods defined on the model by inspecting
     * public methods that return Eloquent relation types.
     *
     * @return array<string, array{method: string, type: string, related: string|null}>
     */
    public function relationships(): array
    {
        $relationships = [];
        $methods = $this->reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            // Skip constructors, magic methods, and inherited Model methods
            if ($method->isStatic() ||
                $method->isAbstract() ||
                str_starts_with($method->getName(), '__') ||
                $method->getDeclaringClass()->getName() !== $this->modelClass
            ) {
                continue;
            }

            // Check if method returns a relation type
            $returnType = $method->getReturnType();
            if ($returnType instanceof ReflectionNamedType) {
                $typeName = $returnType->getName();

                // Common Eloquent relation types
                if ($this->isRelationType($typeName)) {
                    $relationships[$method->getName()] = [
                        'method' => $method->getName(),
                        'type' => $this->getRelationShortName($typeName),
                        'related' => $this->extractRelatedModel($method),
                    ];
                }
            }
        }

        return $relationships;
    }

    /**
     * Get the table name.
     *
     * @return string
     */
    public function table(): string
    {
        $table = $this->getModelProperty('table');

        if ($table !== null) {
            return $table;
        }

        // If no table property, try to get from instance
        if (method_exists($this->instance, 'getTable')) {
            return $this->instance->getTable();
        }

        // Fallback to pluralized class name
        return $this->getDefaultTableName();
    }

    /**
     * Get the primary key.
     *
     * @return string
     */
    public function primaryKey(): string
    {
        $primaryKey = $this->getModelProperty('primaryKey');

        if ($primaryKey !== null) {
            return $primaryKey;
        }

        // Default to 'id'
        return 'id';
    }

    /**
     * Get comprehensive model information as array.
     *
     * Returns all introspected data about the model including properties,
     * relationships, schema, and metadata.
     *
     * @return array{
     *     class: string,
     *     namespace: string,
     *     short_name: string,
     *     table: string,
     *     primary_key: string,
     *     fillable: array,
     *     hidden: array,
     *     appended: array,
     *     casts: array,
     *     relationships: array,
     *     schema: array
     * }
     */
    public function toArray(): array
    {
        return [
            'class' => $this->modelClass,
            'namespace' => $this->reflection->getNamespaceName(),
            'short_name' => $this->reflection->getShortName(),
            'table' => $this->table(),
            'primary_key' => $this->primaryKey(),
            'fillable' => $this->fillable(),
            'hidden' => $this->hidden(),
            'appended' => $this->appended(),
            'casts' => $this->casts(),
            'relationships' => $this->relationships(),
            'schema' => $this->schema(),
        ];
    }

    /**
     * Get a property value from the model instance.
     *
     * @param  string $property Property name
     * @param  mixed  $default  Default value if property doesn't exist
     * @return mixed
     */
    private function getModelProperty(string $property, mixed $default = null): mixed
    {
        if (! $this->reflection->hasProperty($property)) {
            return $default;
        }

        $prop = $this->reflection->getProperty($property);
        $prop->setAccessible(true);

        return $prop->getValue($this->instance) ?? $default;
    }

    /**
     * Check if a type name represents an Eloquent relation.
     *
     * @param  string $typeName Fully-qualified type name
     * @return bool
     */
    private function isRelationType(string $typeName): bool
    {
        $relationTypes = [
            'Illuminate\Database\Eloquent\Relations\HasOne',
            'Illuminate\Database\Eloquent\Relations\HasMany',
            'Illuminate\Database\Eloquent\Relations\BelongsTo',
            'Illuminate\Database\Eloquent\Relations\BelongsToMany',
            'Illuminate\Database\Eloquent\Relations\MorphTo',
            'Illuminate\Database\Eloquent\Relations\MorphOne',
            'Illuminate\Database\Eloquent\Relations\MorphMany',
            'Illuminate\Database\Eloquent\Relations\MorphToMany',
            'Illuminate\Database\Eloquent\Relations\HasOneThrough',
            'Illuminate\Database\Eloquent\Relations\HasManyThrough',
        ];

        foreach ($relationTypes as $relationType) {
            if ($typeName === $relationType || is_subclass_of($typeName, $relationType)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get short name of relation type.
     *
     * @param  string $fullTypeName Fully-qualified type name
     * @return string
     */
    private function getRelationShortName(string $fullTypeName): string
    {
        $parts = explode('\\', $fullTypeName);

        return end($parts);
    }

    /**
     * Extract related model class from relationship method.
     *
     * Attempts to determine the related model by examining the method's
     * source code for class references.
     *
     * @param  ReflectionMethod $method Relationship method
     * @return string|null Related model class name or null if not determined
     */
    private function extractRelatedModel(ReflectionMethod $method): ?string
    {
        // This is a basic implementation
        // In production, you might want to actually invoke the method
        // or parse the method body more thoroughly
        $fileName = $method->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();

        if ($fileName === false || $startLine === false || $endLine === false) {
            return null;
        }

        try {
            $fileContents = file($fileName);
            if ($fileContents === false) {
                return null;
            }

            $methodBody = implode('', \array_slice($fileContents, $startLine - 1, $endLine - $startLine + 1));

            // Look for patterns like: return $this->hasOne(User::class)
            if (preg_match('/(?:hasOne|hasMany|belongsTo|belongsToMany|morphTo|morphOne|morphMany|morphToMany|hasOneThrough|hasManyThrough)\s*\(\s*([^:]+)::class/', $methodBody, $matches)) {
                $className = trim($matches[1]);

                // If it's not fully qualified, try to resolve it
                if (! str_contains($className, '\\')) {
                    // Check use statements at the top of the file
                    $fullFileContents = file_get_contents($fileName);
                    if ($fullFileContents !== false && preg_match('/use\s+([^;]+\\\\' . preg_quote($className, '/') . ')\s*;/', $fullFileContents, $useMatches)) {
                        return $useMatches[1];
                    }
                }

                return $className;
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    /**
     * Get default table name based on class name.
     *
     * Follows Laravel's convention of pluralizing the snake_case class name.
     *
     * @return string
     */
    private function getDefaultTableName(): string
    {
        $shortName = $this->reflection->getShortName();

        // Convert to snake_case
        $snakeCase = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName));

        // Basic pluralization (can be enhanced)
        if (str_ends_with($snakeCase, 'y')) {
            return substr($snakeCase, 0, -1) . 'ies';
        }

        if (str_ends_with($snakeCase, 's')) {
            return $snakeCase . 'es';
        }

        return $snakeCase . 's';
    }
}
