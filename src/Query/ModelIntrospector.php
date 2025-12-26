<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Introspect\Query;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

use function array_any;
use function array_merge;
use function array_slice;
use function array_unique;
use function array_values;
use function end;
use function explode;
use function file;
use function file_get_contents;
use function implode;
use function in_array;
use function is_array;
use function is_subclass_of;
use function lcfirst;
use function mb_strtolower;
use function mb_substr;
use function mb_trim;
use function method_exists;
use function preg_match;
use function preg_quote;
use function preg_replace;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function throw_unless;

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
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
/**
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 * @template T of object
 */
final readonly class ModelIntrospector
{
    /** @var ReflectionClass<T> */
    private ReflectionClass $reflection;

    private object $instance;

    /**
     * Create a new model introspector.
     *
     * @param class-string<T> $modelClass Fully-qualified model class name
     *
     * @throws InvalidArgumentException If class is not instantiable
     * @throws ReflectionException      If class doesn't exist
     */
    public function __construct(
        private string $modelClass,
    ) {
        $this->reflection = new ReflectionClass($modelClass);

        throw_unless($this->reflection->isInstantiable(), InvalidArgumentException::class, sprintf('Class %s is not instantiable', $modelClass));

        $this->instance = $this->reflection->newInstanceWithoutConstructor();
    }

    /**
     * Get all properties discovered from the model.
     *
     * Returns an array containing all fillable, hidden, appended, and casted properties.
     *
     * @return array{fillable: array<int, string>, hidden: array<int, string>, appended: array<int, string>, casts: array<string, string>}
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
     * @return array{type: string, class: string, properties: array<string, mixed>, table: string, primaryKey: string, relationships: array<string, array{method: string, type: string, related: null|string}>}
     */
    public function schema(): array
    {
        $properties = [];

        // Add fillable properties
        foreach ($this->fillable() as $field) {
            $properties[$field] = [
                'type' => $this->casts()[$field] ?? 'string',
                'fillable' => true,
                'hidden' => in_array($field, $this->hidden(), true),
            ];
        }

        // Add casted properties not in fillable
        foreach ($this->casts() as $field => $cast) {
            if (isset($properties[$field])) {
                continue;
            }

            $properties[$field] = [
                'type' => $cast,
                'fillable' => false,
                'hidden' => in_array($field, $this->hidden(), true),
            ];
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
        /** @var array<int, string> */
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
        /** @var array<int, string> */
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
        /** @var array<int, string> */
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
        /** @var array<string, string> */
        $casts = $this->getModelProperty('casts', []);

        // Also check for casts() method (Laravel 10+)
        if (method_exists($this->instance, 'casts') && $this->reflection->hasMethod('casts')) {
            $method = $this->reflection->getMethod('casts');

            if ($method->isPublic() || $method->isProtected()) {
                $methodCasts = $method->invoke($this->instance);

                if (is_array($methodCasts)) {
                    /** @var array<string, string> */
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
     * @return array<string, array{method: string, type: string, related: null|string}>
     */
    public function relationships(): array
    {
        $relationships = [];
        $methods = $this->reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            // Skip constructors, magic methods, and inherited Model methods
            if ($method->isStatic()) {
                continue;
            }

            if ($method->isAbstract()) {
                continue;
            }

            if (str_starts_with($method->getName(), '__')) {
                continue;
            }

            if ($method->getDeclaringClass()->getName() !== $this->modelClass) {
                continue;
            }

            // Check if method returns a relation type
            $returnType = $method->getReturnType();

            if (!$returnType instanceof ReflectionNamedType) {
                continue;
            }

            $typeName = $returnType->getName();

            // Common Eloquent relation types
            if (!$this->isRelationType($typeName)) {
                continue;
            }

            $relationships[$method->getName()] = [
                'method' => $method->getName(),
                'type' => $this->getRelationShortName($typeName),
                'related' => $this->extractRelatedModel($method),
            ];
        }

        return $relationships;
    }

    /**
     * Get the table name.
     *
     * Returns the database table name for this model, either from the explicit
     * $table property or using Laravel's default naming convention.
     *
     * @return string Database table name
     */
    public function table(): string
    {
        /** @var null|string */
        $table = $this->getModelProperty('table');

        if ($table !== null) {
            return $table;
        }

        // If no table property, try to get from instance
        if (method_exists($this->instance, 'getTable')) {
            /** @var string */
            return $this->instance->getTable();
        }

        // Fallback to pluralized class name
        return $this->getDefaultTableName();
    }

    /**
     * Get the primary key.
     *
     * Returns the primary key column name, defaulting to 'id' if not specified.
     *
     * @return string Primary key column name
     */
    public function primaryKey(): string
    {
        /** @var null|string */
        $primaryKey = $this->getModelProperty('primaryKey');

        if ($primaryKey !== null) {
            return $primaryKey;
        }

        // Default to 'id'
        return 'id';
    }

    /**
     * Get guarded attributes.
     *
     * Returns the attributes that are not mass-assignable. Defaults to ['*']
     * which guards all attributes.
     *
     * @return array<int, string> Array of guarded attribute names
     */
    public function guarded(): array
    {
        /** @var array<int, string> */
        return $this->getModelProperty('guarded', ['*']);
    }

    /**
     * Get the database connection name.
     *
     * @return null|string Connection name or null for default
     */
    public function connection(): ?string
    {
        /** @var null|string */
        return $this->getModelProperty('connection');
    }

    /**
     * Check if the model uses timestamps.
     *
     * Returns true if the model maintains created_at and updated_at timestamps.
     *
     * @return bool True if timestamps are enabled (default: true)
     */
    public function usesTimestamps(): bool
    {
        return (bool) $this->getModelProperty('timestamps', true);
    }

    /**
     * Check if the model uses soft deletes.
     *
     * Determines if the model uses the SoftDeletes trait for soft deletion functionality.
     *
     * @return bool True if model uses SoftDeletes trait
     */
    public function usesSoftDeletes(): bool
    {
        return in_array(SoftDeletes::class, $this->reflection->getTraitNames(), true);
    }

    /**
     * Get local query scopes defined on the model.
     *
     * @return array<int, string> Array of scope names (without 'scope' prefix)
     */
    public function scopes(): array
    {
        $scopes = [];
        $methods = $this->reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            if ($method->getDeclaringClass()->getName() !== $this->modelClass) {
                continue;
            }

            $name = $method->getName();

            if (!str_starts_with($name, 'scope')) {
                continue;
            }

            if ($name === 'scope') {
                continue;
            }

            $scopes[] = lcfirst(mb_substr($name, 5));
        }

        return $scopes;
    }

    /**
     * Get global scopes registered on the model.
     *
     * Note: This requires a booted model and may not work in all contexts.
     *
     * @return array<string, string> Array of scope name => scope class
     */
    public function globalScopes(): array
    {
        if (!method_exists($this->instance, 'getGlobalScopes')) {
            return [];
        }

        // Check if the globalScopes static property exists
        if (!$this->reflection->hasProperty('globalScopes')) {
            return [];
        }

        $prop = $this->reflection->getProperty('globalScopes');

        if (!$prop->isStatic()) {
            return [];
        }

        /** @var array<string, array<string, string>> */
        $globalScopes = $prop->getValue() ?? [];

        return $globalScopes[$this->modelClass] ?? [];
    }

    /**
     * Get accessor methods defined on the model.
     *
     * Detects both old-style (getXAttribute) and new-style (Attribute casts).
     *
     * @return array<int, string> Array of attribute names that have accessors
     */
    public function accessors(): array
    {
        $accessors = [];
        $methods = $this->reflection->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED);

        foreach ($methods as $method) {
            if ($method->getDeclaringClass()->getName() !== $this->modelClass) {
                continue;
            }

            $name = $method->getName();

            // Old-style: getXAttribute
            if (str_starts_with($name, 'get') && str_ends_with($name, 'Attribute') && $name !== 'getAttribute') {
                $attr = mb_substr($name, 3, -9);
                $accessors[] = mb_strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $attr));
            }

            // New-style: methods that return Attribute
            $returnType = $method->getReturnType();

            if (!$returnType instanceof ReflectionNamedType) {
                continue;
            }

            if ($returnType->getName() !== Attribute::class) {
                continue;
            }

            $accessors[] = mb_strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
        }

        return array_values(array_unique($accessors));
    }

    /**
     * Get mutator methods defined on the model.
     *
     * Detects old-style (setXAttribute) mutators.
     *
     * @return array<int, string> Array of attribute names that have mutators
     */
    public function mutators(): array
    {
        $mutators = [];
        $methods = $this->reflection->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED);

        foreach ($methods as $method) {
            if ($method->getDeclaringClass()->getName() !== $this->modelClass) {
                continue;
            }

            $name = $method->getName();

            // Old-style: setXAttribute
            if (!str_starts_with($name, 'set')) {
                continue;
            }

            if (!str_ends_with($name, 'Attribute')) {
                continue;
            }

            if ($name === 'setAttribute') {
                continue;
            }

            $attr = mb_substr($name, 3, -9);
            $mutators[] = mb_strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $attr));
        }

        return $mutators;
    }

    /**
     * Get model events that have listeners registered.
     *
     * @return array<string, array<int, string>> Array of event name => listener methods
     */
    public function events(): array
    {
        /** @var array<string, array<int, string>> */
        $events = [];
        $eventNames = ['creating', 'created', 'updating', 'updated', 'saving', 'saved', 'deleting', 'deleted', 'restoring', 'restored', 'replicating'];

        // Check for boot method event registrations (static analysis)
        foreach ($eventNames as $event) {
            $methodName = $event;

            // Check if there's a corresponding method
            if (!$this->reflection->hasMethod($methodName)) {
                continue;
            }

            $method = $this->reflection->getMethod($methodName);

            if (!$method->isStatic()) {
                continue;
            }

            if ($method->getDeclaringClass()->getName() !== $this->modelClass) {
                continue;
            }

            $events[$event] = [$methodName];
        }

        // Check dispatchesEvents property
        /** @var array<string, string> */
        $dispatchesEvents = $this->getModelProperty('dispatchesEvents', []);

        foreach ($dispatchesEvents as $event => $eventClass) {
            if (!isset($events[$event])) {
                $events[$event] = [];
            }

            $events[$event][] = $eventClass;
        }

        return $events;
    }

    /**
     * Get observers registered for this model.
     *
     * Note: This reads from the $observers static property if available.
     *
     * @return array<int, string> Array of observer class names
     */
    public function observers(): array
    {
        if (!$this->reflection->hasProperty('observers')) {
            return [];
        }

        $prop = $this->reflection->getProperty('observers');

        if (!$prop->isStatic()) {
            return [];
        }

        /** @var array<string, array<int, string>> */
        $observers = $prop->getValue();

        return $observers[$this->modelClass] ?? [];
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
     *     connection: null|string,
     *     timestamps: bool,
     *     soft_deletes: bool,
     *     fillable: array<int, string>,
     *     guarded: array<int, string>,
     *     hidden: array<int, string>,
     *     appended: array<int, string>,
     *     casts: array<string, string>,
     *     relationships: array<string, array{method: string, type: string, related: null|string}>,
     *     scopes: array<int, string>,
     *     accessors: array<int, string>,
     *     mutators: array<int, string>,
     *     events: array<string, array<int, string>>,
     *     observers: array<int, string>,
     *     schema: array{type: string, class: string, properties: array<string, mixed>, table: string, primaryKey: string, relationships: array<string, array{method: string, type: string, related: null|string}>}
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
            'connection' => $this->connection(),
            'timestamps' => $this->usesTimestamps(),
            'soft_deletes' => $this->usesSoftDeletes(),
            'fillable' => $this->fillable(),
            'guarded' => $this->guarded(),
            'hidden' => $this->hidden(),
            'appended' => $this->appended(),
            'casts' => $this->casts(),
            'relationships' => $this->relationships(),
            'scopes' => $this->scopes(),
            'accessors' => $this->accessors(),
            'mutators' => $this->mutators(),
            'events' => $this->events(),
            'observers' => $this->observers(),
            'schema' => $this->schema(),
        ];
    }

    /**
     * Get a property value from the model instance.
     *
     * Safely extracts a property value from the model without triggering
     * the constructor or accessor methods.
     *
     * @param  string $property Property name
     * @param  mixed  $default  Default value if property doesn't exist or is null
     * @return mixed  Property value or default
     */
    private function getModelProperty(string $property, mixed $default = null): mixed
    {
        if (!$this->reflection->hasProperty($property)) {
            return $default;
        }

        $prop = $this->reflection->getProperty($property);

        return $prop->getValue($this->instance) ?? $default;
    }

    /**
     * Check if a type name represents an Eloquent relation.
     *
     * Determines if the given type is one of Laravel's Eloquent relation classes
     * or a subclass thereof.
     *
     * @param  string $typeName Fully-qualified type name
     * @return bool   True if type is an Eloquent relation
     */
    private function isRelationType(string $typeName): bool
    {
        $relationTypes = [
            HasOne::class,
            HasMany::class,
            BelongsTo::class,
            BelongsToMany::class,
            MorphTo::class,
            MorphOne::class,
            MorphMany::class,
            MorphToMany::class,
            HasOneThrough::class,
            HasManyThrough::class,
        ];

        return array_any($relationTypes, fn (string $relationType): bool => $typeName === $relationType || is_subclass_of($typeName, $relationType));
    }

    /**
     * Get short name of relation type.
     *
     * Extracts the class name from a fully-qualified relation type.
     *
     * @param  string $fullTypeName Fully-qualified type name
     * @return string Short class name (e.g., 'HasMany', 'BelongsTo')
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
     * source code for class references. Parses the method body to find
     * Model::class patterns in relation definitions.
     *
     * @param  ReflectionMethod $method Relationship method
     * @return null|string      Related model class name or null if not determined
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

            $methodBody = implode('', array_slice($fileContents, $startLine - 1, $endLine - $startLine + 1));

            // Look for patterns like: return $this->hasOne(User::class)
            if (preg_match('/(?:hasOne|hasMany|belongsTo|belongsToMany|morphTo|morphOne|morphMany|morphToMany|hasOneThrough|hasManyThrough)\s*\(\s*([^:]+)::class/', $methodBody, $matches)) {
                $className = mb_trim($matches[1]);

                // If it's not fully qualified, try to resolve it
                if (!str_contains($className, '\\')) {
                    // Check use statements at the top of the file
                    $fullFileContents = file_get_contents($fileName);

                    if ($fullFileContents !== false && preg_match('/use\s+([^;]+\\\\'.preg_quote($className, '/').')\s*;/', $fullFileContents, $useMatches)) {
                        return $useMatches[1];
                    }
                }

                return $className;
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * Get default table name based on class name.
     *
     * Follows Laravel's convention of converting the class name to snake_case
     * and pluralizing it. This is a basic implementation covering common cases.
     *
     * @return string Pluralized snake_case table name
     */
    private function getDefaultTableName(): string
    {
        $shortName = $this->reflection->getShortName();

        // Convert to snake_case
        $snakeCase = mb_strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName));

        // Basic pluralization (can be enhanced)
        if (str_ends_with($snakeCase, 'y')) {
            return mb_substr($snakeCase, 0, -1).'ies';
        }

        if (str_ends_with($snakeCase, 's')) {
            return $snakeCase.'es';
        }

        return $snakeCase.'s';
    }
}
