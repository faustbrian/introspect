<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Introspect\Query;

use Cline\Introspect\Reflection;
use ReflectionClass;

use function array_all;

/**
 * Fluent query builder for instance introspection.
 *
 * Provides chainable methods for inspecting object instances with the same
 * capabilities as ClassIntrospector but working directly with instances.
 * @author Brian Faust <brian@cline.sh>
 */
final class InstanceIntrospector
{
    /** @var array<callable(): bool> */
    private array $filters = [];

    public function __construct(
        private readonly object $instance,
    ) {}

    /**
     * Filter by trait usage.
     */
    public function whereUsesTrait(string $trait): static
    {
        $this->filters[] = fn (): bool => Reflection::usesTrait($this->instance, $trait);

        return $this;
    }

    /**
     * Filter by interface implementation.
     */
    public function whereImplements(string $interface): static
    {
        $this->filters[] = fn (): bool => Reflection::implementsInterface($this->instance, $interface);

        return $this;
    }

    /**
     * Filter by parent class extension.
     */
    public function whereExtends(string $parent): static
    {
        $this->filters[] = fn (): bool => Reflection::extendsClass($this->instance, $parent);

        return $this;
    }

    /**
     * Filter by method existence.
     */
    public function whereHasMethod(string $method): static
    {
        $this->filters[] = fn (): bool => Reflection::hasMethod($this->instance, $method);

        return $this;
    }

    /**
     * Filter by public method existence.
     */
    public function whereHasPublicMethod(string $method): static
    {
        $this->filters[] = fn (): bool => Reflection::methodIsPublic($this->instance, $method);

        return $this;
    }

    /**
     * Filter by property existence.
     */
    public function whereHasProperty(string $property): static
    {
        $this->filters[] = fn (): bool => Reflection::hasProperty($this->instance, $property);

        return $this;
    }

    /**
     * Filter by attribute presence.
     */
    public function whereHasAttribute(string $attribute): static
    {
        $this->filters[] = fn (): bool => Reflection::hasAttribute($this->instance, $attribute);

        return $this;
    }

    /**
     * Check if all filters pass.
     */
    public function passes(): bool
    {
        return array_all($this->filters, fn (callable $filter): bool => $filter());
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
        return Reflection::classBasename($this->instance);
    }

    /**
     * Get the namespace of the instance's class.
     */
    public function getNamespace(): string
    {
        return Reflection::classNamespace($this->instance);
    }

    /**
     * Get all traits used by the instance.
     *
     * @return array<string>
     */
    public function getAllTraits(): array
    {
        return Reflection::allTraits($this->instance);
    }

    /**
     * Get all public methods.
     *
     * @return array<string>
     */
    public function getPublicMethods(): array
    {
        return Reflection::publicMethods($this->instance);
    }

    /**
     * Get all public properties.
     *
     * @return array<string>
     */
    public function getPublicProperties(): array
    {
        return Reflection::publicProperties($this->instance);
    }

    /**
     * Get all attributes or filter by name.
     *
     * @return array<object>
     */
    public function getAttributes(?string $name = null): array
    {
        return Reflection::attributes($this->instance, $name);
    }

    /**
     * Get the reflection class.
     *
     * @return ReflectionClass<object>
     */
    public function getReflection(): ReflectionClass
    {
        return new ReflectionClass($this->instance);
    }

    /**
     * Get instance information as array.
     *
     * @return array<string, mixed>
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
            'interfaces' => $reflection->getInterfaceNames(),
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
