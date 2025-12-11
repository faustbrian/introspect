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
     * @param class-string<T> $enumName
     */
    public function __construct(
        private readonly string $enumName,
    ) {}

    /**
     * Filter by trait usage.
     */
    public function whereUsesTrait(string $trait): static
    {
        $this->filters[] = fn (): bool => Reflection::usesTrait($this->enumName, $trait);

        return $this;
    }

    /**
     * Filter by interface implementation.
     */
    public function whereImplements(string $interface): static
    {
        $this->filters[] = fn (): bool => Reflection::implementsInterface($this->enumName, $interface);

        return $this;
    }

    /**
     * Filter by backed enum (has int or string backing).
     */
    public function whereBacked(): static
    {
        $this->filters[] = fn (): bool => $this->isBacked();

        return $this;
    }

    /**
     * Filter by unit enum (no backing type).
     */
    public function whereUnit(): static
    {
        $this->filters[] = fn (): bool => !$this->isBacked();

        return $this;
    }

    /**
     * Filter by method existence.
     */
    public function whereHasMethod(string $method): static
    {
        $this->filters[] = fn (): bool => Reflection::hasMethod($this->enumName, $method);

        return $this;
    }

    /**
     * Filter by public method existence.
     */
    public function whereHasPublicMethod(string $method): static
    {
        $this->filters[] = fn (): bool => Reflection::methodIsPublic($this->enumName, $method);

        return $this;
    }

    /**
     * Filter by attribute presence.
     */
    public function whereHasAttribute(string $attribute): static
    {
        $this->filters[] = fn (): bool => Reflection::hasAttribute($this->enumName, $attribute);

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
     */
    public function get(): ?string
    {
        return $this->passes() ? $this->enumName : null;
    }
}
