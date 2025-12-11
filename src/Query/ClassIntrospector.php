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
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;

use function array_all;
use function array_filter;
use function array_map;
use function array_values;
use function in_array;

/**
 * Fluent query builder for class introspection.
 *
 * Provides chainable methods for inspecting classes with support for filtering
 * by traits, interfaces, parent classes, methods, properties, and attributes.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ClassIntrospector
{
    /** @var array<int, callable(): bool> */
    private array $filters = [];

    /**
     * @param class-string $className
     */
    public function __construct(
        private readonly string $className,
    ) {}

    /**
     * Filter by trait usage.
     */
    public function whereUsesTrait(string $trait): static
    {
        $this->filters[] = fn (): bool => Reflection::usesTrait($this->className, $trait);

        return $this;
    }

    /**
     * Filter by interface implementation.
     */
    public function whereImplements(string $interface): static
    {
        $this->filters[] = fn (): bool => Reflection::implementsInterface($this->className, $interface);

        return $this;
    }

    /**
     * Filter by parent class extension.
     */
    public function whereExtends(string $parent): static
    {
        $this->filters[] = fn (): bool => Reflection::extendsClass($this->className, $parent);

        return $this;
    }

    /**
     * Filter by concrete class (not abstract).
     */
    public function whereConcrete(): static
    {
        $this->filters[] = fn (): bool => Reflection::isConcrete($this->className);

        return $this;
    }

    /**
     * Filter by abstract class.
     */
    public function whereAbstract(): static
    {
        $this->filters[] = fn (): bool => !Reflection::isConcrete($this->className);

        return $this;
    }

    /**
     * Filter by instantiable class.
     */
    public function whereInstantiable(): static
    {
        $this->filters[] = fn (): bool => Reflection::isInstantiable($this->className);

        return $this;
    }

    /**
     * Filter by method existence.
     */
    public function whereHasMethod(string $method): static
    {
        $this->filters[] = fn (): bool => Reflection::hasMethod($this->className, $method);

        return $this;
    }

    /**
     * Filter by public method existence.
     */
    public function whereHasPublicMethod(string $method): static
    {
        $this->filters[] = fn (): bool => Reflection::methodIsPublic($this->className, $method);

        return $this;
    }

    /**
     * Filter by property existence.
     */
    public function whereHasProperty(string $property): static
    {
        $this->filters[] = fn (): bool => Reflection::hasProperty($this->className, $property);

        return $this;
    }

    /**
     * Filter by attribute presence.
     */
    public function whereHasAttribute(string $attribute): static
    {
        $this->filters[] = fn (): bool => Reflection::hasAttribute($this->className, $attribute);

        return $this;
    }

    /**
     * Negative filter: class does NOT use trait.
     */
    public function whereDoesNotUseTrait(string $trait): static
    {
        $this->filters[] = fn (): bool => !Reflection::usesTrait($this->className, $trait);

        return $this;
    }

    /**
     * Negative filter: class does NOT implement interface.
     */
    public function whereDoesNotImplement(string $interface): static
    {
        $this->filters[] = fn (): bool => !Reflection::implementsInterface($this->className, $interface);

        return $this;
    }

    /**
     * Negative filter: class does NOT extend parent.
     */
    public function whereDoesNotExtend(string $parent): static
    {
        $this->filters[] = fn (): bool => !Reflection::extendsClass($this->className, $parent);

        return $this;
    }

    /**
     * Negative filter: class does NOT have method.
     */
    public function whereDoesNotHaveMethod(string $method): static
    {
        $this->filters[] = fn (): bool => !Reflection::hasMethod($this->className, $method);

        return $this;
    }

    /**
     * Negative filter: class does NOT have property.
     */
    public function whereDoesNotHaveProperty(string $property): static
    {
        $this->filters[] = fn (): bool => !Reflection::hasProperty($this->className, $property);

        return $this;
    }

    /**
     * Negative filter: class does NOT have attribute.
     */
    public function whereDoesNotHaveAttribute(string $attribute): static
    {
        $this->filters[] = fn (): bool => !Reflection::hasAttribute($this->className, $attribute);

        return $this;
    }

    /**
     * Filter by having static methods.
     */
    public function whereHasStaticMethods(): static
    {
        $this->filters[] = fn (): bool => $this->getStaticMethods() !== [];

        return $this;
    }

    /**
     * Filter by having static properties.
     */
    public function whereHasStaticProperties(): static
    {
        $this->filters[] = fn (): bool => $this->getStaticProperties() !== [];

        return $this;
    }

    /**
     * Filter by having a constructor.
     */
    public function whereHasConstructor(): static
    {
        $this->filters[] = function (): bool {
            /** @var class-string $className */
            $className = $this->className;

            return new ReflectionClass($className)->getConstructor() instanceof ReflectionMethod;
        };

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
     * Get all traits used by the class.
     *
     * @return array<string>
     */
    public function getAllTraits(): array
    {
        return Reflection::allTraits($this->className);
    }

    /**
     * Get all public methods.
     *
     * @return array<string>
     */
    public function getPublicMethods(): array
    {
        return Reflection::publicMethods($this->className);
    }

    /**
     * Get all public properties.
     *
     * @return array<string>
     */
    public function getPublicProperties(): array
    {
        return Reflection::publicProperties($this->className);
    }

    /**
     * Get all attributes or filter by name.
     *
     * @return array<object>
     */
    public function getAttributes(?string $name = null): array
    {
        return Reflection::attributes($this->className, $name);
    }

    /**
     * Get all parent classes in the inheritance chain.
     *
     * @return array<int, string> Array of parent class names from immediate parent to root
     */
    public function getParentClasses(): array
    {
        $parents = [];

        /** @var class-string $className */
        $className = $this->className;
        $reflection = new ReflectionClass($className);

        while ($parent = $reflection->getParentClass()) {
            $parents[] = $parent->getName();
            $reflection = $parent;
        }

        return $parents;
    }

    /**
     * Get all static methods.
     *
     * @return array<int, string> Array of static method names
     */
    public function getStaticMethods(): array
    {
        /** @var class-string $className */
        $className = $this->className;
        $reflection = new ReflectionClass($className);
        $methods = $reflection->getMethods(ReflectionMethod::IS_STATIC);

        return array_map(fn (ReflectionMethod $method): string => $method->getName(), $methods);
    }

    /**
     * Get all static properties.
     *
     * @return array<int, string> Array of static property names
     */
    public function getStaticProperties(): array
    {
        /** @var class-string $className */
        $className = $this->className;
        $reflection = new ReflectionClass($className);
        $properties = $reflection->getProperties(ReflectionProperty::IS_STATIC);

        return array_map(fn (ReflectionProperty $prop): string => $prop->getName(), $properties);
    }

    /**
     * Get constructor parameter information.
     *
     * @return array<int, array{name: string, type: ?string, has_default: bool, default: mixed, is_promoted: bool, is_variadic: bool, is_by_reference: bool}>
     */
    public function getConstructorParameters(): array
    {
        /** @var class-string $className */
        $className = $this->className;
        $reflection = new ReflectionClass($className);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return [];
        }

        return array_map(fn (ReflectionParameter $param): array => [
            'name' => $param->getName(),
            'type' => $param->getType()?->__toString(),
            'has_default' => $param->isDefaultValueAvailable(),
            'default' => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
            'is_promoted' => $param->isPromoted(),
            'is_variadic' => $param->isVariadic(),
            'is_by_reference' => $param->isPassedByReference(),
        ], $constructor->getParameters());
    }

    /**
     * Get interfaces directly implemented by this class (not inherited).
     *
     * @return array<int, string> Array of directly implemented interface names
     */
    public function getDirectInterfaces(): array
    {
        /** @var class-string $className */
        $className = $this->className;
        $reflection = new ReflectionClass($className);
        $allInterfaces = $reflection->getInterfaceNames();
        $parentInterfaces = [];

        if ($parent = $reflection->getParentClass()) {
            $parentInterfaces = $parent->getInterfaceNames();
        }

        return array_values(array_filter(
            $allInterfaces,
            fn (string $interface): bool => !in_array($interface, $parentInterfaces, true),
        ));
    }

    /**
     * Get the reflection class.
     *
     * @return ReflectionClass<object>
     */
    public function getReflection(): ReflectionClass
    {
        /** @var class-string $className */
        $className = $this->className;

        return new ReflectionClass($className);
    }

    /**
     * Get class information as array.
     *
     * @return array<string, mixed>
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
            'interfaces' => $reflection->getInterfaceNames(),
            'direct_interfaces' => $this->getDirectInterfaces(),
            'parent' => $reflection->getParentClass() ? $reflection->getParentClass()->getName() : null,
            'parent_classes' => $this->getParentClasses(),
            'methods' => $this->getPublicMethods(),
            'static_methods' => $this->getStaticMethods(),
            'properties' => $this->getPublicProperties(),
            'static_properties' => $this->getStaticProperties(),
            'constructor_parameters' => $this->getConstructorParameters(),
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
