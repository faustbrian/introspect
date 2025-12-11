<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit;

use Attribute;
use Cline\Introspect\Introspect;
use Exception;
use ReflectionMethod;

use function describe;
use function expect;
use function it;

describe('MethodIntrospector', function (): void {
    describe('Happy Path', function (): void {
        it('gets method parameters', function (): void {
            $params = Introspect::method(TestMethodClass::class, 'methodWithParams')->parameters();

            expect($params)->toHaveCount(3);
            expect($params[0]['name'])->toBe('name');
            expect($params[0]['type'])->toBe('string');
            expect($params[1]['name'])->toBe('age');
            expect($params[1]['type'])->toBe('int');
            expect($params[2]['name'])->toBe('active');
            expect($params[2]['type'])->toBe('bool');
            expect($params[2]['default'])->toBeTrue();
        });

        it('gets method with nullable parameter', function (): void {
            $params = Introspect::method(TestMethodClass::class, 'methodWithNullable')->parameters();

            expect($params)->toHaveCount(1);
            expect($params[0]['name'])->toBe('value');
            expect($params[0]['type'])->toBe('?string');
        });

        it('gets method with variadic parameter', function (): void {
            $params = Introspect::method(TestMethodClass::class, 'methodWithVariadic')->parameters();

            expect($params)->toHaveCount(1);
            expect($params[0]['name'])->toBe('items');
            expect($params[0]['is_variadic'])->toBeTrue();
        });

        it('gets method with reference parameter', function (): void {
            $params = Introspect::method(TestMethodClass::class, 'methodWithReference')->parameters();

            expect($params)->toHaveCount(1);
            expect($params[0]['name'])->toBe('value');
            expect($params[0]['is_passed_by_reference'])->toBeTrue();
        });

        it('gets return type for typed method', function (): void {
            $returnType = Introspect::method(TestMethodClass::class, 'methodWithReturnType')->returnType();

            expect($returnType)->toBe('string');
        });

        it('gets nullable return type', function (): void {
            $returnType = Introspect::method(TestMethodClass::class, 'methodWithNullableReturn')->returnType();

            expect($returnType)->toBe('?int');
        });

        it('gets union return type', function (): void {
            $returnType = Introspect::method(TestMethodClass::class, 'methodWithUnionReturn')->returnType();

            expect($returnType)->toContain('string');
            expect($returnType)->toContain('int');
        });

        it('gets visibility for public method', function (): void {
            $visibility = Introspect::method(TestMethodClass::class, 'publicMethod')->visibility();

            expect($visibility)->toBe('public');
        });

        it('gets visibility for protected method', function (): void {
            $visibility = Introspect::method(TestMethodClass::class, 'protectedMethod')->visibility();

            expect($visibility)->toBe('protected');
        });

        it('gets visibility for private method', function (): void {
            $visibility = Introspect::method(TestMethodClass::class, 'privateMethod')->visibility();

            expect($visibility)->toBe('private');
        });

        it('identifies static method', function (): void {
            $isStatic = Introspect::method(TestMethodClass::class, 'staticMethod')->isStatic();

            expect($isStatic)->toBeTrue();
        });

        it('identifies final method', function (): void {
            $isFinal = Introspect::method(TestMethodClass::class, 'finalMethod')->isFinal();

            expect($isFinal)->toBeTrue();
        });

        it('identifies abstract method', function (): void {
            $isAbstract = Introspect::method(MethodTestAbstractClass::class, 'abstractMethod')->isAbstract();

            expect($isAbstract)->toBeTrue();
        });

        it('gets method attributes', function (): void {
            $attributes = Introspect::method(TestMethodClass::class, 'methodWithAttribute')->attributes();

            expect($attributes)->toHaveCount(1);
            expect($attributes[0]['name'])->toBe(TestAttribute::class);
            expect($attributes[0]['arguments'])->toHaveCount(1);
            expect($attributes[0]['arguments'][0])->toBe('test');
        });

        it('parses docblock description', function (): void {
            $docBlock = Introspect::method(TestMethodClass::class, 'methodWithDocBlock')->docBlock();

            expect($docBlock['description'])->toContain('This is a test method');
        });

        it('parses docblock params', function (): void {
            $docBlock = Introspect::method(TestMethodClass::class, 'methodWithDocBlock')->docBlock();

            expect($docBlock['params'])->toHaveCount(1);
            expect($docBlock['params'][0])->toContain('string $name');
        });

        it('parses docblock return', function (): void {
            $docBlock = Introspect::method(TestMethodClass::class, 'methodWithDocBlock')->docBlock();

            // NoSuperfluousPhpdocTagsFixer removes @return void when method has void return type
            expect($docBlock['return'])->toBeNull();
        });

        it('parses docblock throws', function (): void {
            $docBlock = Introspect::method(TestMethodClass::class, 'methodWithDocBlock')->docBlock();

            expect($docBlock['throws'])->toHaveCount(1);
            expect($docBlock['throws'][0])->toContain('Exception');
        });

        it('converts to array', function (): void {
            $result = Introspect::method(TestMethodClass::class, 'publicMethod')->toArray();

            expect($result)->toHaveKeys([
                'name',
                'class',
                'visibility',
                'is_static',
                'is_final',
                'is_abstract',
                'parameters',
                'return_type',
                'attributes',
                'doc_block',
            ]);
            expect($result['name'])->toBe('publicMethod');
            expect($result['class'])->toBe(TestMethodClass::class);
        });

        it('gets reflection method', function (): void {
            $reflection = Introspect::method(TestMethodClass::class, 'publicMethod')->getReflection();

            expect($reflection)->toBeInstanceOf(ReflectionMethod::class);
            expect($reflection->getName())->toBe('publicMethod');
        });
    });

    describe('Edge Cases', function (): void {
        it('handles method with no parameters', function (): void {
            $params = Introspect::method(TestMethodClass::class, 'publicMethod')->parameters();

            expect($params)->toBeArray();
            expect($params)->toHaveCount(0);
        });

        it('handles method with no return type', function (): void {
            $returnType = Introspect::method(TestMethodClass::class, 'methodWithoutReturnType')->returnType();

            expect($returnType)->toBeNull();
        });

        it('handles method with no docblock', function (): void {
            $docBlock = Introspect::method(TestMethodClass::class, 'publicMethod')->docBlock();

            expect($docBlock['description'])->toBeNull();
            expect($docBlock['params'])->toBeArray();
            expect($docBlock['params'])->toHaveCount(0);
            expect($docBlock['return'])->toBeNull();
            expect($docBlock['throws'])->toBeArray();
            expect($docBlock['throws'])->toHaveCount(0);
        });

        it('handles method with no attributes', function (): void {
            $attributes = Introspect::method(TestMethodClass::class, 'publicMethod')->attributes();

            expect($attributes)->toBeArray();
            expect($attributes)->toHaveCount(0);
        });

        it('identifies non-static method', function (): void {
            $isStatic = Introspect::method(TestMethodClass::class, 'publicMethod')->isStatic();

            expect($isStatic)->toBeFalse();
        });

        it('identifies non-final method', function (): void {
            $isFinal = Introspect::method(TestMethodClass::class, 'publicMethod')->isFinal();

            expect($isFinal)->toBeFalse();
        });

        it('identifies non-abstract method', function (): void {
            $isAbstract = Introspect::method(TestMethodClass::class, 'publicMethod')->isAbstract();

            expect($isAbstract)->toBeFalse();
        });

        it('handles optional parameters correctly', function (): void {
            $params = Introspect::method(TestMethodClass::class, 'methodWithParams')->parameters();

            expect($params[0]['is_optional'])->toBeFalse();
            expect($params[1]['is_optional'])->toBeFalse();
            expect($params[2]['is_optional'])->toBeTrue();
        });

        it('handles parameter positions correctly', function (): void {
            $params = Introspect::method(TestMethodClass::class, 'methodWithParams')->parameters();

            expect($params[0]['position'])->toBe(0);
            expect($params[1]['position'])->toBe(1);
            expect($params[2]['position'])->toBe(2);
        });

        it('handles mixed return type', function (): void {
            $returnType = Introspect::method(TestMethodClass::class, 'methodWithMixedReturn')->returnType();

            expect($returnType)->toBe('mixed');
        });

        it('handles void return type', function (): void {
            $returnType = Introspect::method(TestMethodClass::class, 'methodWithVoidReturn')->returnType();

            expect($returnType)->toBe('void');
        });
    });
});

// Test fixtures
/**
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
#[Attribute()]
final readonly class TestAttribute
{
    public function __construct(
        public string $value,
    ) {}
}

/**
 * Non-final to allow testing final methods properly.
 *
 * @author Brian Faust <brian@cline.sh>
 */
class TestMethodClass
{
    public static function staticMethod(): void {}

    public function publicMethod(): void {}

    final public function finalMethod(): void {}

    public function methodWithParams(string $name, int $age, bool $active = true): void {}

    public function methodWithNullable(?string $value): void {}

    public function methodWithVariadic(string ...$items): void {}

    public function methodWithReference(string &$value): void {}

    public function methodWithReturnType(): string
    {
        return 'test';
    }

    public function methodWithNullableReturn(): ?int
    {
        return null;
    }

    public function methodWithUnionReturn(): string|int
    {
        return 'test';
    }

    public function methodWithMixedReturn(): mixed
    {
        return null;
    }

    public function methodWithVoidReturn(): void {}

    /**
     * @noinspection ReturnTypeCanBeDeclaredInspection
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function methodWithoutReturnType()
    {
        // Intentionally no return type for testing
    }

    #[TestAttribute('test')]
    public function methodWithAttribute(): void {}

    /**
     * This is a test method with documentation.
     *
     * @param string $name The name parameter
     *
     * @throws Exception When something goes wrong
     */
    public function methodWithDocBlock(string $name): void {}

    protected function protectedMethod(): void {}

    private function privateMethod(): void {}
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
abstract class MethodTestAbstractClass
{
    abstract public function abstractMethod(): void;
}
