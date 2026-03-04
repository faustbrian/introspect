<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Introspect\Query;

use Closure;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;

use function count;
use function is_array;
use function is_object;
use function is_string;
use function throw_if;
use function throw_unless;

/**
 * Fluent query builder for callable introspection.
 *
 * Provides methods for inspecting callables including closures, invokable objects,
 * callable arrays, and callable strings.
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class CallableIntrospector
{
    private ReflectionFunction|ReflectionMethod $reflection;

    /**
     * Create a new callable introspector.
     *
     * @param callable $callable Closure, invokable object, callable array ([Class, 'method']),
     *                           or callable string to introspect. Supports all PHP callable types
     *                           including closures, first-class callables, and invokable objects.
     *
     * @throws ReflectionException If the callable cannot be reflected or is invalid
     */
    public function __construct(
        private mixed $callable,
    ) {
        $this->reflection = $this->createReflection($callable);
    }

    /**
     * Get all parameters with their details.
     *
     * @return array<int, array{name: string, type: ?string, default: mixed, variadic: bool, byReference: bool, hasDefault: bool}>
     */
    public function parameters(): array
    {
        $params = [];

        foreach ($this->reflection->getParameters() as $param) {
            $type = $param->getType();
            $typeName = null;

            if ($type !== null) {
                $typeName = $type instanceof ReflectionNamedType
                    ? $type->getName()
                    : (string) $type;
            }

            $params[] = [
                'name' => $param->getName(),
                'type' => $typeName,
                'default' => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
                'variadic' => $param->isVariadic(),
                'byReference' => $param->isPassedByReference(),
                'hasDefault' => $param->isDefaultValueAvailable(),
            ];
        }

        return $params;
    }

    /**
     * Get the return type.
     *
     * @return null|string Return type name or null if no return type is declared
     */
    public function returnType(): ?string
    {
        $type = $this->reflection->getReturnType();

        if ($type === null) {
            return null;
        }

        return $type instanceof ReflectionNamedType
            ? $type->getName()
            : (string) $type;
    }

    /**
     * Get bound variables for closures (via use()).
     *
     * @return array<string, mixed>
     */
    public function boundVariables(): array
    {
        if (!$this->reflection instanceof ReflectionFunction) {
            return [];
        }

        if (!$this->callable instanceof Closure) {
            return [];
        }

        /** @var array<string, mixed> */
        return $this->reflection->getClosureUsedVariables();
    }

    /**
     * Get the scope class for closures.
     *
     * Returns the class context in which a closure was bound using bindTo()
     * or the class where the closure was defined. Returns null for non-closures.
     *
     * @return null|string Scope class name or null if not a closure or unbound
     */
    public function scopeClass(): ?string
    {
        if (!$this->reflection instanceof ReflectionFunction) {
            return null;
        }

        if (!$this->callable instanceof Closure) {
            return null;
        }

        $scope = $this->reflection->getClosureScopeClass();

        return $scope?->getName();
    }

    /**
     * Check if callable is static.
     *
     * Returns true for static methods, static closures (created with static fn),
     * and callables that don't depend on object context.
     *
     * @return bool True if callable is static, false otherwise
     */
    public function isStatic(): bool
    {
        if ($this->reflection instanceof ReflectionMethod) {
            return $this->reflection->isStatic();
        }

        if ($this->callable instanceof Closure) {
            return $this->reflection->isStatic();
        }

        return false;
    }

    /**
     * Get source file path.
     *
     * Returns the absolute path to the file where the callable is defined.
     * Returns null for internal PHP functions.
     *
     * @return null|string Absolute file path or null for internal functions
     */
    public function sourceFile(): ?string
    {
        $file = $this->reflection->getFileName();

        return $file !== false ? $file : null;
    }

    /**
     * Get source line numbers [start, end].
     *
     * @return null|array{int, int}
     */
    public function sourceLines(): ?array
    {
        $start = $this->reflection->getStartLine();
        $end = $this->reflection->getEndLine();

        if ($start === false || $end === false) {
            return null;
        }

        return [$start, $end];
    }

    /**
     * Get the reflection instance.
     *
     * Returns the underlying ReflectionFunction or ReflectionMethod instance
     * for advanced reflection operations not covered by this introspector.
     *
     * @return ReflectionFunction|ReflectionMethod Reflection instance for the callable
     */
    public function getReflection(): ReflectionFunction|ReflectionMethod
    {
        return $this->reflection;
    }

    /**
     * Get callable information as array.
     *
     * @return array{parameters: array<int, array{name: string, type: ?string, default: mixed, variadic: bool, byReference: bool, hasDefault: bool}>, returnType: ?string, boundVariables: array<string, mixed>, scopeClass: ?string, isStatic: bool, sourceFile: ?string, sourceLines: null|array{int, int}}
     */
    public function toArray(): array
    {
        return [
            'parameters' => $this->parameters(),
            'returnType' => $this->returnType(),
            'boundVariables' => $this->boundVariables(),
            'scopeClass' => $this->scopeClass(),
            'isStatic' => $this->isStatic(),
            'sourceFile' => $this->sourceFile(),
            'sourceLines' => $this->sourceLines(),
        ];
    }

    /**
     * Create appropriate reflection instance for the callable.
     *
     * Determines the correct reflection type based on callable form and instantiates
     * the appropriate ReflectionFunction or ReflectionMethod instance.
     *
     * @param mixed $callable The callable to reflect
     *
     * @throws ReflectionException                 If callable type is invalid or cannot be reflected
     * @return ReflectionFunction|ReflectionMethod Reflection instance for the callable
     */
    private function createReflection(mixed $callable): ReflectionFunction|ReflectionMethod
    {
        // Closure or function
        if ($callable instanceof Closure || is_string($callable)) {
            return new ReflectionFunction($callable);
        }

        // Invokable object
        if (is_object($callable)) {
            return new ReflectionMethod($callable, '__invoke');
        }

        // Callable array [Class, method] or [object, method]
        if (is_array($callable) && count($callable) === 2) {
            [$class, $method] = $callable;

            throw_unless(is_string($method), ReflectionException::class, 'Method name must be a string');

            throw_if(!is_object($class) && !is_string($class), ReflectionException::class, 'Class must be an object or string');

            return new ReflectionMethod($class, $method);
        }

        throw new ReflectionException('Invalid callable type');
    }
}
