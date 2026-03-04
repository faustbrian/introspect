<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Introspect\Query;

use Cline\Introspect\Reflection;
use ReflectionEnum;
use ReflectionEnumBackedCase;
use ReflectionEnumUnitCase;
use ReflectionNamedType;

use function array_all;
use function array_map;
use function array_values;
use function assert;

/**
 * Fluent query builder for enum introspection.
 *
 * Provides chainable methods for inspecting PHP 8.1+ enums with support for filtering
 * by traits, interfaces, methods, and attributes. Handles both unit and backed enums.
 * @author Brian Faust <brian@cline.sh>
 *
 * @template T of \UnitEnum
 */
final class EnumIntrospector
{
    /** @var array<int, callable(): bool> */
    private array $filters = [];

    /**
     * Create a new enum introspector.
     *
     * @param class-string<T> $enumName Fully-qualified enum name to introspect. Must be a valid
     *                                  PHP 8.1+ enum (either unit or backed enum) that exists
     *                                  in the current runtime environment.
     */
    public function __construct(
        private readonly string $enumName,
    ) {}

    /**
     * Filter by trait usage.
     *
     * Checks if the enum uses the specified trait. Enums can use traits to share behavior.
     *
     * @param string $trait Fully-qualified trait name
     *
     * @return static Fluent interface for method chaining
     */
    public function whereUsesTrait(string $trait): static
    {
        $this->filters[] = fn (): bool => Reflection::usesTrait($this->enumName, $trait);

        return $this;
    }

    /**
     * Filter by interface implementation.
     *
     * Checks if the enum implements the specified interface. Enums automatically implement UnitEnum.
     *
     * @param string $interface Fully-qualified interface name
     *
     * @return static Fluent interface for method chaining
     */
    public function whereImplements(string $interface): static
    {
        $this->filters[] = fn (): bool => Reflection::implementsInterface($this->enumName, $interface);

        return $this;
    }

    /**
     * Filter by backed enum (has int or string backing).
     *
     * Checks if the enum is a backed enum with explicit int or string values for each case.
     *
     * @return static Fluent interface for method chaining
     */
    public function whereBacked(): static
    {
        $this->filters[] = fn (): bool => $this->isBacked();

        return $this;
    }

    /**
     * Filter by unit enum (no backing type).
     *
     * Checks if the enum is a pure unit enum without explicit backing values.
     *
     * @return static Fluent interface for method chaining
     */
    public function whereUnit(): static
    {
        $this->filters[] = fn (): bool => !$this->isBacked();

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
        $this->filters[] = fn (): bool => Reflection::hasMethod($this->enumName, $method);

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
        $this->filters[] = fn (): bool => Reflection::methodIsPublic($this->enumName, $method);

        return $this;
    }

    /**
     * Filter by attribute presence.
     *
     * Checks if the enum has the specified PHP 8 attribute.
     *
     * @param string $attribute Fully-qualified attribute class name
     *
     * @return static Fluent interface for method chaining
     */
    public function whereHasAttribute(string $attribute): static
    {
        $this->filters[] = fn (): bool => Reflection::hasAttribute($this->enumName, $attribute);

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
     * Get all enum case names.
     *
     * @return array<int, string>
     */
    public function cases(): array
    {
        $reflection = $this->getReflection();

        return array_values(
            array_map(fn (ReflectionEnumUnitCase $case): string => $case->getName(), $reflection->getCases()),
        );
    }

    /**
     * Get all enum case values (for backed enums only).
     *
     * @return array<int, int|string>
     */
    public function values(): array
    {
        if (!$this->isBacked()) {
            return [];
        }

        $reflection = $this->getReflection();

        /** @var array<ReflectionEnumBackedCase> $cases */
        $cases = $reflection->getCases();

        return array_values(
            array_map(
                fn (ReflectionEnumBackedCase $case): int|string => $case->getBackingValue(),
                $cases,
            ),
        );
    }

    /**
     * Get the backing type (int, string, or null for unit enums).
     *
     * Returns 'int' or 'string' for backed enums, null for unit enums without backing values.
     *
     * @return null|string Backing type name or null for unit enums
     */
    public function backedType(): ?string
    {
        $reflection = $this->getReflection();

        if (!$reflection->isBacked()) {
            return null;
        }

        $backingType = $reflection->getBackingType();
        assert($backingType instanceof ReflectionNamedType);

        return $backingType->getName();
    }

    /**
     * Check if enum is backed.
     *
     * Returns true for enums with int or string backing values, false for pure unit enums.
     *
     * @return bool True if backed enum, false if unit enum
     */
    public function isBacked(): bool
    {
        return $this->getReflection()->isBacked();
    }

    /**
     * Get all methods.
     *
     * @return array<int, string>
     */
    public function methods(): array
    {
        return array_values(Reflection::publicMethods($this->enumName));
    }

    /**
     * Get all traits used by the enum.
     *
     * @return list<class-string>
     */
    public function traits(): array
    {
        $traits = Reflection::allTraits($this->enumName);

        /** @var list<class-string> */
        return array_values($traits);
    }

    /**
     * Get all interfaces implemented by the enum.
     *
     * @return array<int, class-string>
     */
    public function interfaces(): array
    {
        return $this->getReflection()->getInterfaceNames();
    }

    /**
     * Get all attributes or filter by name.
     *
     * @return array<int, object>
     */
    public function attributes(?string $name = null): array
    {
        return array_values(Reflection::attributes($this->enumName, $name));
    }

    /**
     * Get the reflection enum.
     *
     * @return ReflectionEnum<T>
     */
    public function getReflection(): ReflectionEnum
    {
        return new ReflectionEnum($this->enumName);
    }

    /**
     * Get enum information as array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $reflection = $this->getReflection();

        return [
            'name' => $this->enumName,
            'namespace' => $reflection->getNamespaceName(),
            'short_name' => $reflection->getShortName(),
            'is_backed' => $reflection->isBacked(),
            'backed_type' => $this->backedType(),
            'cases' => $this->cases(),
            'values' => $this->values(),
            'traits' => $this->traits(),
            'interfaces' => $this->interfaces(),
            'methods' => $this->methods(),
        ];
    }

    /**
     * Execute and return the enum name if filters pass.
     *
     * Evaluates all filters and returns the enum name if all pass, otherwise returns null.
     *
     * @return null|string Enum name if filters pass, null otherwise
     */
    public function get(): ?string
    {
        return $this->passes() ? $this->enumName : null;
    }
}
