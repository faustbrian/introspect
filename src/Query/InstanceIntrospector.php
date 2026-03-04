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

    /**
     * Create a new instance introspector.
     *
     * @param object $instance Object instance to introspect. Can be any PHP object instance,
     *                         providing the same introspection capabilities as ClassIntrospector
     *                         but operating on actual instances rather than class names.
     */
    public function __construct(
        private readonly object $instance,
    ) {}

    /**
     * Filter by trait usage.
     *
     * Checks if the instance's class uses the specified trait directly or through parent classes.
     *
     * @param string $trait Fully-qualified trait name
     *
     * @return static Fluent interface for method chaining
     */
    public function whereUsesTrait(string $trait): static
    {
        $this->filters[] = fn (): bool => Reflection::usesTrait($this->instance, $trait);

        return $this;
    }

    /**
     * Filter by interface implementation.
     *
     * Checks if the instance's class implements the specified interface.
     *
     * @param string $interface Fully-qualified interface name
     *
     * @return static Fluent interface for method chaining
     */
    public function whereImplements(string $interface): static
    {
        $this->filters[] = fn (): bool => Reflection::implementsInterface($this->instance, $interface);

        return $this;
    }

    /**
     * Filter by parent class extension.
     *
     * Checks if the instance's class extends the specified parent class.
     *
     * @param string $parent Fully-qualified parent class name
     *
     * @return static Fluent interface for method chaining
     */
    public function whereExtends(string $parent): static
    {
        $this->filters[] = fn (): bool => Reflection::extendsClass($this->instance, $parent);

        return $this;
    }

    /**
     * Filter by method existence.
     *
     * @param string $method Method name to check (case-sensitive)
     *
     * @return static Fluent interface for method chaining
     */
    public function whereHasMethod(string $method): static
    {
        $this->filters[] = fn (): bool => Reflection::hasMethod($this->instance, $method);

        return $this;
    }

    /**
     * Filter by public method existence.
     *
     * @param string $method Public method name to check (case-sensitive)
     *
     * @return static Fluent interface for method chaining
     */
    public function whereHasPublicMethod(string $method): static
    {
        $this->filters[] = fn (): bool => Reflection::methodIsPublic($this->instance, $method);

        return $this;
    }

    /**
     * Filter by property existence.
     *
     * @param string $property Property name to check (case-sensitive)
     *
     * @return static Fluent interface for method chaining
     */
    public function whereHasProperty(string $property): static
    {
        $this->filters[] = fn (): bool => Reflection::hasProperty($this->instance, $property);

        return $this;
    }

    /**
     * Filter by attribute presence.
     *
     * Checks if the instance's class has the specified PHP 8 attribute.
     *
     * @param string $attribute Fully-qualified attribute class name
     *
     * @return static Fluent interface for method chaining
     */
    public function whereHasAttribute(string $attribute): static
    {
        $this->filters[] = fn (): bool => Reflection::hasAttribute($this->instance, $attribute);

        return $this;
    }

    /**
     * Check if all filters pass.
     *
     * Evaluates all registered filter conditions and returns true only if all pass.
     *
     * @return bool True if all filters pass, false otherwise
     */
    public function passes(): bool
    {
        return array_all($this->filters, fn (callable $filter): bool => $filter());
    }

    /**
     * Get the class name of the instance.
     *
     * Returns the fully-qualified class name of the object instance.
     *
     * @return string Fully-qualified class name
     */
    public function getClassName(): string
    {
        return $this->instance::class;
    }

    /**
     * Get the short class name (without namespace).
     *
     * Returns only the class name portion without the namespace prefix.
     *
     * @return string Short class name (e.g., 'User' from 'App\Models\User')
     */
    public function getBasename(): string
    {
        return Reflection::classBasename($this->instance);
    }

    /**
     * Get the namespace of the instance's class.
     *
     * Returns the namespace portion of the fully-qualified class name.
     *
     * @return string Namespace (e.g., 'App\Models' from 'App\Models\User')
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
     * Returns instantiated attribute objects from the instance's class.
     * Optionally filter by attribute class name.
     *
     * @param null|string $name Optional attribute class name to filter by
     *
     * @return array<object> Array of instantiated attribute objects
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
     *
     * Evaluates all filters and returns the instance if all pass, otherwise returns null.
     *
     * @return null|object Instance if filters pass, null otherwise
     */
    public function get(): ?object
    {
        return $this->passes() ? $this->instance : null;
    }
}
