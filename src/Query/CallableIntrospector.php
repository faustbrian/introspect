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
     * @param  callable            $callable
     * @throws ReflectionException
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
     * @throws ReflectionException
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
