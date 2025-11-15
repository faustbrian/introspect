<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Introspect\Query;

use ReflectionClass;

use function Cline\Introspect\classBasename;
use function Cline\Introspect\classNamespace;
use function Cline\Introspect\extendsClass;
use function Cline\Introspect\getAllTraits;
use function Cline\Introspect\getAttributes;
use function Cline\Introspect\getPublicMethods;
use function Cline\Introspect\getPublicProperties;
use function Cline\Introspect\hasAttribute;
use function Cline\Introspect\hasMethod;
use function Cline\Introspect\hasProperty;
use function Cline\Introspect\implementsInterface;
use function Cline\Introspect\methodIsPublic;
use function Cline\Introspect\usesTrait;

/**
 * Fluent query builder for instance introspection.
 *
 * Provides chainable methods for inspecting object instances with the same
 * capabilities as ClassIntrospector but working directly with instances.
 */
class InstanceIntrospector
{
    private array $filters = [];

    public function __construct(private readonly object $instance) {}

    /**
     * Filter by trait usage.
     */
    public function whereUsesTrait(string $trait): static
    {
        $this->filters[] = fn () => usesTrait($this->instance, $trait);

        return $this;
    }

    /**
     * Filter by interface implementation.
     */
    public function whereImplements(string $interface): static
    {
        $this->filters[] = fn () => implementsInterface($this->instance, $interface);

        return $this;
    }

    /**
     * Filter by parent class extension.
     */
    public function whereExtends(string $parent): static
    {
        $this->filters[] = fn () => extendsClass($this->instance, $parent);

        return $this;
    }

    /**
     * Filter by method existence.
     */
    public function whereHasMethod(string $method): static
    {
        $this->filters[] = fn () => hasMethod($this->instance, $method);

        return $this;
    }

    /**
     * Filter by public method existence.
     */
    public function whereHasPublicMethod(string $method): static
    {
        $this->filters[] = fn () => methodIsPublic($this->instance, $method);

        return $this;
    }

    /**
     * Filter by property existence.
     */
    public function whereHasProperty(string $property): static
    {
        $this->filters[] = fn () => hasProperty($this->instance, $property);

        return $this;
    }

    /**
     * Filter by attribute presence.
     */
    public function whereHasAttribute(string $attribute): static
    {
        $this->filters[] = fn () => hasAttribute($this->instance, $attribute);

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
     * Get the class name of the instance.
     */
    public function getClassName(): string
    {
        return $this->instance::class;
    }

    /**
     * Get the short class name (without namespace).
     */
    public function getBasename(): string
    {
        return classBasename($this->instance);
    }

    /**
     * Get the namespace of the instance's class.
     */
    public function getNamespace(): string
    {
        return classNamespace($this->instance);
    }

    /**
     * Get all traits used by the instance.
     */
    public function getAllTraits(): array
    {
        return getAllTraits($this->instance);
    }

    /**
     * Get all public methods.
     */
    public function getPublicMethods(): array
    {
        return getPublicMethods($this->instance);
    }

    /**
     * Get all public properties.
     */
    public function getPublicProperties(): array
    {
        return getPublicProperties($this->instance);
    }

    /**
     * Get all attributes or filter by name.
     */
    public function getAttributes(?string $name = null): array
    {
        return getAttributes($this->instance, $name);
    }

    /**
     * Get the reflection class.
     */
    public function getReflection(): ReflectionClass
    {
        return new ReflectionClass($this->instance);
    }

    /**
     * Get instance information as array.
     */
    public function toArray(): array
    {
        $reflection = $this->getReflection();

        return [
            'class' => $this->getClassName(),
            'namespace' => $this->getNamespace(),
            'short_name' => $this->getBasename(),
            'is_abstract' => $reflection->isAbstract(),
            'is_final' => $reflection->isFinal(),
            'traits' => $this->getAllTraits(),
            'interfaces' => array_values($reflection->getInterfaceNames()),
            'parent' => $reflection->getParentClass() ? $reflection->getParentClass()->getName() : null,
            'methods' => $this->getPublicMethods(),
            'properties' => $this->getPublicProperties(),
        ];
    }

    /**
     * Execute and return the instance if filters pass.
     */
    public function get(): ?object
    {
        return $this->passes() ? $this->instance : null;
    }
}
