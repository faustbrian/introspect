<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Introspect\Query;

use ReflectionAttribute;
use ReflectionClass;
use ReflectionClassConstant;

use function array_all;
use function array_filter;
use function array_map;

/**
 * Fluent query builder for class constant introspection.
 *
 * Provides chainable methods for inspecting class constants with support for filtering
 * by visibility, final status, and attributes (PHP 8.0+). Supports both PHP 8.1+ final
 * constants and PHP 8.3+ typed constants.
 * @author Brian Faust <brian@cline.sh>
 */
final class ConstantIntrospector
{
    /** @var array<callable(ReflectionClassConstant): bool> */
    private array $filters = [];

    public function __construct(
        private readonly string $className,
    ) {}

    /**
     * Filter by public visibility.
     */
    public function wherePublic(): static
    {
        $this->filters[] = fn (ReflectionClassConstant $constant): bool => $constant->isPublic();

        return $this;
    }

    /**
     * Filter by protected visibility.
     */
    public function whereProtected(): static
    {
        $this->filters[] = fn (ReflectionClassConstant $constant): bool => $constant->isProtected();

        return $this;
    }

    /**
     * Filter by private visibility.
     */
    public function wherePrivate(): static
    {
        $this->filters[] = fn (ReflectionClassConstant $constant): bool => $constant->isPrivate();

        return $this;
    }

    /**
     * Filter by final constants (PHP 8.1+).
     */
    public function whereFinal(): static
    {
        $this->filters[] = fn (ReflectionClassConstant $constant): bool => $constant->isFinal();

        return $this;
    }

    /**
     * Filter by attribute presence.
     */
    public function whereHasAttribute(string $attribute): static
    {
        $this->filters[] = fn (ReflectionClassConstant $constant): bool => $constant->getAttributes($attribute) !== [];

        return $this;
    }

    /**
     * Get all constants as name => value array.
     * @return array<string, mixed>
     */
    public function all(): array
    {
        /** @var class-string $className */
        $className = $this->className;
        $reflection = new ReflectionClass($className);
        $constants = $reflection->getReflectionConstants();

        $filtered = array_filter($constants, fn (ReflectionClassConstant $constant): bool => array_all($this->filters, fn (callable $filter, int|string $key): bool => $filter($constant)));

        $result = [];

        foreach ($filtered as $constant) {
            $result[$constant->getName()] = $constant->getValue();
        }

        return $result;
    }

    /**
     * Get all constant names.
     * @return array<int, string>
     */
    public function names(): array
    {
        /** @var class-string $className */
        $className = $this->className;
        $reflection = new ReflectionClass($className);
        $constants = $reflection->getReflectionConstants();

        $filtered = array_filter($constants, fn (ReflectionClassConstant $constant): bool => array_all($this->filters, fn (callable $filter, int|string $key): bool => $filter($constant)));

        return array_map(fn (ReflectionClassConstant $constant): string => $constant->getName(), $filtered);
    }

    /**
     * Get detailed information about a specific constant.
     * @return null|array<string, mixed>
     */
    public function get(string $name): ?array
    {
        /** @var class-string $className */
        $className = $this->className;
        $reflection = new ReflectionClass($className);

        if (!$reflection->hasConstant($name)) {
            return null;
        }

        $constant = $reflection->getReflectionConstant($name);

        if (!$constant) {
            return null;
        }

        $visibility = 'public';

        if ($constant->isProtected()) {
            $visibility = 'protected';
        } elseif ($constant->isPrivate()) {
            $visibility = 'private';
        }

        $isFinal = $constant->isFinal();

        $type = null;

        $reflectionType = $constant->getType();

        if ($reflectionType) {
            $type = $reflectionType->__toString();
        }

        return [
            'name' => $constant->getName(),
            'value' => $constant->getValue(),
            'visibility' => $visibility,
            'final' => $isFinal,
            'type' => $type,
            'attributes' => array_map(
                fn (ReflectionAttribute $attr): object => $attr->newInstance(),
                $constant->getAttributes(),
            ),
        ];
    }

    /**
     * Get all constants with detailed information.
     * @return array<string, array<string, mixed>>
     */
    public function toArray(): array
    {
        /** @var class-string $className */
        $className = $this->className;
        $reflection = new ReflectionClass($className);
        $constants = $reflection->getReflectionConstants();

        $filtered = array_filter($constants, fn (ReflectionClassConstant $constant): bool => array_all($this->filters, fn (callable $filter, int|string $key): bool => $filter($constant)));

        $result = [];

        foreach ($filtered as $constant) {
            $visibility = 'public';

            if ($constant->isProtected()) {
                $visibility = 'protected';
            } elseif ($constant->isPrivate()) {
                $visibility = 'private';
            }

            $isFinal = $constant->isFinal();

            $type = null;

            $reflectionType = $constant->getType();

            if ($reflectionType) {
                $type = $reflectionType->__toString();
            }

            $result[$constant->getName()] = [
                'name' => $constant->getName(),
                'value' => $constant->getValue(),
                'visibility' => $visibility,
                'final' => $isFinal,
                'type' => $type,
                'attributes' => array_map(
                    fn (ReflectionAttribute $attr): object => $attr->newInstance(),
                    $constant->getAttributes(),
                ),
            ];
        }

        return $result;
    }
}
