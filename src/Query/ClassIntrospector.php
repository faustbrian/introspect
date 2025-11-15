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
use ReflectionProperty;

use function Cline\Introspect\extendsClass;
use function Cline\Introspect\getAllTraits;
use function Cline\Introspect\getAttributes;
use function Cline\Introspect\getPublicMethods;
use function Cline\Introspect\getPublicProperties;
use function Cline\Introspect\hasAttribute;
use function Cline\Introspect\hasMethod;
use function Cline\Introspect\hasProperty;
use function Cline\Introspect\implementsInterface;
use function Cline\Introspect\isConcrete;
use function Cline\Introspect\isInstantiable;
use function Cline\Introspect\methodIsPublic;
use function Cline\Introspect\usesTrait;

/**
 * Fluent query builder for class introspection.
 *
 * Provides chainable methods for inspecting classes with support for filtering
 * by traits, interfaces, parent classes, methods, properties, and attributes.
 */
class ClassIntrospector
{
    private array $filters = [];

    public function __construct(private readonly string $className) {}

    /**
     * Filter by trait usage.
     */
    public function whereUsesTrait(string $trait): static
    {
        $this->filters[] = fn () => usesTrait($this->className, $trait);

        return $this;
    }

    /**
     * Filter by interface implementation.
     */
    public function whereImplements(string $interface): static
    {
        $this->filters[] = fn () => implementsInterface($this->className, $interface);

        return $this;
    }

    /**
     * Filter by parent class extension.
     */
    public function whereExtends(string $parent): static
    {
        $this->filters[] = fn () => extendsClass($this->className, $parent);

        return $this;
    }

    /**
     * Filter by concrete class (not abstract).
     */
    public function whereConcrete(): static
    {
        $this->filters[] = fn () => isConcrete($this->className);

        return $this;
    }

    /**
     * Filter by abstract class.
     */
    public function whereAbstract(): static
    {
        $this->filters[] = fn () => ! isConcrete($this->className);

        return $this;
    }

    /**
     * Filter by instantiable class.
     */
    public function whereInstantiable(): static
    {
        $this->filters[] = fn () => isInstantiable($this->className);

        return $this;
    }

    /**
     * Filter by method existence.
     */
    public function whereHasMethod(string $method): static
    {
        $this->filters[] = fn () => hasMethod($this->className, $method);

        return $this;
    }

    /**
     * Filter by public method existence.
     */
    public function whereHasPublicMethod(string $method): static
    {
        $this->filters[] = fn () => methodIsPublic($this->className, $method);

        return $this;
    }

    /**
     * Filter by property existence.
     */
    public function whereHasProperty(string $property): static
    {
        $this->filters[] = fn () => hasProperty($this->className, $property);

        return $this;
    }

    /**
     * Filter by attribute presence.
     */
    public function whereHasAttribute(string $attribute): static
    {
        $this->filters[] = fn () => hasAttribute($this->className, $attribute);

        return $this;
    }

    /**
     * Check if all filters pass.
     */
    public function passes(): bool
    {
        foreach ($this->filters as $filter) {
            if (! $filter()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get all traits used by the class.
     */
    public function getAllTraits(): array
    {
        return getAllTraits($this->className);
    }

    /**
     * Get all public methods.
     */
    public function getPublicMethods(): array
    {
        return getPublicMethods($this->className);
    }

    /**
     * Get all public properties.
     */
    public function getPublicProperties(): array
    {
        return getPublicProperties($this->className);
    }

    /**
     * Get all attributes or filter by name.
     */
    public function getAttributes(?string $name = null): array
    {
        return getAttributes($this->className, $name);
    }

    /**
     * Get the reflection class.
     */
    public function getReflection(): ReflectionClass
    {
        return new ReflectionClass($this->className);
    }

    /**
     * Get class information as array.
     */
    public function toArray(): array
    {
        $reflection = $this->getReflection();

        return [
            'name' => $this->className,
            'namespace' => $reflection->getNamespaceName(),
            'short_name' => $reflection->getShortName(),
            'is_abstract' => $reflection->isAbstract(),
            'is_final' => $reflection->isFinal(),
            'is_instantiable' => $reflection->isInstantiable(),
            'traits' => $this->getAllTraits(),
            'interfaces' => array_values($reflection->getInterfaceNames()),
            'parent' => $reflection->getParentClass() ? $reflection->getParentClass()->getName() : null,
            'methods' => $this->getPublicMethods(),
            'properties' => $this->getPublicProperties(),
        ];
    }

    /**
     * Execute and return the class name if filters pass.
     */
    public function get(): ?string
    {
        return $this->passes() ? $this->className : null;
    }
}
