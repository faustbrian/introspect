<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Introspect\Query;

use ReflectionAttribute;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;

use function array_map;
use function explode;
use function implode;
use function mb_substr;
use function mb_trim;
use function preg_replace;
use function str_starts_with;

/**
 * Fluent query builder for method introspection.
 *
 * Provides detailed introspection of a single method including parameters,
 * return type, visibility, modifiers, attributes, and docblock parsing.
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class MethodIntrospector
{
    private ReflectionMethod $reflection;

    public function __construct(
        private string $className,
        private string $methodName,
    ) {
        $this->reflection = new ReflectionMethod($className, $methodName);
    }

    /**
     * Get detailed parameter information.
     *
     * @return list<array{name: string, type: ?string, default: mixed, is_variadic: bool, is_passed_by_reference: bool, is_optional: bool, position: int}>
     */
    public function parameters(): array
    {
        return array_map(
            fn (ReflectionParameter $param): array => [
                'name' => $param->getName(),
                'type' => $this->getParameterType($param),
                'default' => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
                'is_variadic' => $param->isVariadic(),
                'is_passed_by_reference' => $param->isPassedByReference(),
                'is_optional' => $param->isOptional(),
                'position' => $param->getPosition(),
            ],
            $this->reflection->getParameters(),
        );
    }

    /**
     * Get return type as string.
     *
     * Returns the method's return type as a string representation, including
     * nullable and union types. Returns null if no return type is declared.
     *
     * @return null|string Return type string or null if not declared
     */
    public function returnType(): ?string
    {
        $returnType = $this->reflection->getReturnType();

        if ($returnType === null) {
            return null;
        }

        if ($returnType instanceof ReflectionNamedType) {
            $type = $returnType->getName();

            // mixed type already allows null, so don't prefix with ?
            if ($type === 'mixed') {
                return $type;
            }

            return $returnType->allowsNull() ? '?'.$type : $type;
        }

        if ($returnType instanceof ReflectionUnionType) {
            return implode('|', array_map(
                fn (ReflectionIntersectionType|ReflectionNamedType $type) => $type instanceof ReflectionNamedType ? $type->getName() : (string) $type,
                $returnType->getTypes(),
            ));
        }

        return null;
    }

    /**
     * Get method visibility.
     *
     * @return 'private'|'protected'|'public'
     */
    public function visibility(): string
    {
        if ($this->reflection->isPublic()) {
            return 'public';
        }

        if ($this->reflection->isProtected()) {
            return 'protected';
        }

        return 'private';
    }

    /**
     * Check if method is static.
     *
     * @return bool True if method is static
     */
    public function isStatic(): bool
    {
        return $this->reflection->isStatic();
    }

    /**
     * Check if method is final.
     *
     * @return bool True if method is final
     */
    public function isFinal(): bool
    {
        return $this->reflection->isFinal();
    }

    /**
     * Check if method is abstract.
     *
     * @return bool True if method is abstract
     */
    public function isAbstract(): bool
    {
        return $this->reflection->isAbstract();
    }

    /**
     * Get method attributes.
     *
     * @return list<array{name: class-string, arguments: array<array-key, mixed>}>
     */
    public function attributes(): array
    {
        return array_map(
            fn (ReflectionAttribute $attribute): array => [
                'name' => $attribute->getName(),
                'arguments' => $attribute->getArguments(),
            ],
            $this->reflection->getAttributes(),
        );
    }

    /**
     * Parse and return docblock information.
     *
     * @return array{description: ?string, params: list<string>, return: ?string, throws: list<string>}
     */
    public function docBlock(): array
    {
        $docComment = $this->reflection->getDocComment();

        if ($docComment === false) {
            return [
                'description' => null,
                'params' => [],
                'return' => null,
                'throws' => [],
            ];
        }

        $lines = explode("\n", $docComment);
        $description = [];
        $params = [];
        $return = null;
        $throws = [];

        foreach ($lines as $line) {
            $line = mb_trim($line);
            $line = preg_replace('/^\*+\s*/', '', $line);
            $line = mb_trim($line ?? '');

            if ($line === '') {
                continue;
            }

            if ($line === '0') {
                continue;
            }

            if ($line === '/**') {
                continue;
            }

            if ($line === '*/') {
                continue;
            }

            if (str_starts_with($line, '@param')) {
                $params[] = mb_trim(mb_substr($line, 6));
            } elseif (str_starts_with($line, '@return')) {
                $return = mb_trim(mb_substr($line, 7));
            } elseif (str_starts_with($line, '@throws')) {
                $throws[] = mb_trim(mb_substr($line, 7));
            } elseif (!str_starts_with($line, '@')) {
                $description[] = $line;
            }
        }

        return [
            'description' => $description === [] ? null : implode(' ', $description),
            'params' => $params,
            'return' => $return,
            'throws' => $throws,
        ];
    }

    /**
     * Get all method information as array.
     *
     * @return array{name: string, class: string, visibility: 'private'|'protected'|'public', is_static: bool, is_final: bool, is_abstract: bool, parameters: list<array{name: string, type: ?string, default: mixed, is_variadic: bool, is_passed_by_reference: bool, is_optional: bool, position: int}>, return_type: ?string, attributes: list<array{name: class-string, arguments: array<array-key, mixed>}>, doc_block: array{description: ?string, params: list<string>, return: ?string, throws: list<string>}}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->methodName,
            'class' => $this->className,
            'visibility' => $this->visibility(),
            'is_static' => $this->isStatic(),
            'is_final' => $this->isFinal(),
            'is_abstract' => $this->isAbstract(),
            'parameters' => $this->parameters(),
            'return_type' => $this->returnType(),
            'attributes' => $this->attributes(),
            'doc_block' => $this->docBlock(),
        ];
    }

    /**
     * Get the reflection method.
     *
     * @return ReflectionMethod Underlying ReflectionMethod instance
     */
    public function getReflection(): ReflectionMethod
    {
        return $this->reflection;
    }

    /**
     * Get parameter type as string.
     *
     * Converts a parameter's type hint into a string representation, including
     * nullable and union types.
     *
     * @param  ReflectionParameter $param Parameter to inspect
     * @return null|string         Type string or null if not typed
     */
    private function getParameterType(ReflectionParameter $param): ?string
    {
        $type = $param->getType();

        if ($type === null) {
            return null;
        }

        if ($type instanceof ReflectionNamedType) {
            $typeName = $type->getName();

            // mixed type already allows null, so don't prefix with ?
            if ($typeName === 'mixed') {
                return $typeName;
            }

            return $type->allowsNull() ? '?'.$typeName : $typeName;
        }

        if ($type instanceof ReflectionUnionType) {
            return implode('|', array_map(
                fn (ReflectionIntersectionType|ReflectionNamedType $t) => $t instanceof ReflectionNamedType ? $t->getName() : (string) $t,
                $type->getTypes(),
            ));
        }

        return null;
    }
}
