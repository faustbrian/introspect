<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit;

use Cline\Introspect\Introspect;
use Closure;
use Exception;

use function array_sum;
use function describe;
use function expect;
use function it;
use function mb_strlen;
use function mb_strtoupper;

describe('CallableIntrospector', function (): void {
    describe('Happy Path', function (): void {
        it('introspects closure with typed parameters', function (): void {
            $callable = Introspect::callable(fn (int $x, string $y): int => $x);
            $params = $callable->parameters();

            expect($params)->toHaveCount(2);
            expect($params[0]['name'])->toBe('x');
            expect($params[0]['type'])->toBe('int');
            expect($params[1]['name'])->toBe('y');
            expect($params[1]['type'])->toBe('string');
        });

        it('introspects closure with return type', function (): void {
            $callable = Introspect::callable(fn (int $x): int => $x * 2);
            expect($callable->returnType())->toBe('int');
        });

        it('introspects closure without return type', function (): void {
            $callable = Introspect::callable(fn (int $x) => $x * 2);
            expect($callable->returnType())->toBeNull();
        });

        it('introspects closure with default parameters', function (): void {
            $callable = Introspect::callable(fn (int $x = 42, string $y = 'test'): int => $x);
            $params = $callable->parameters();

            expect($params[0]['hasDefault'])->toBeTrue();
            expect($params[0]['default'])->toBe(42);
            expect($params[1]['hasDefault'])->toBeTrue();
            expect($params[1]['default'])->toBe('test');
        });

        it('introspects closure with variadic parameter', function (): void {
            $callable = Introspect::callable(fn (int ...$numbers): int => array_sum($numbers));
            $params = $callable->parameters();

            expect($params[0]['variadic'])->toBeTrue();
            expect($params[0]['name'])->toBe('numbers');
        });

        it('introspects closure with by-reference parameter', function (): void {
            $callable = Introspect::callable(function (int &$x): void {
                $x = 42;
            });
            $params = $callable->parameters();

            expect($params[0]['byReference'])->toBeTrue();
        });

        it('introspects closure with bound variables', function (): void {
            $x = 42;
            $y = 'test';
            $closure = fn (): int => $x + mb_strlen($y);
            $callable = Introspect::callable($closure);

            $bound = $callable->boundVariables();
            expect($bound)->toHaveKey('x');
            expect($bound)->toHaveKey('y');
            expect($bound['x'])->toBe(42);
            expect($bound['y'])->toBe('test');
        });

        it('identifies static closure', function (): void {
            $callable = Introspect::callable(static fn (): int => 42);
            expect($callable->isStatic())->toBeTrue();
        });

        it('identifies non-static closure', function (): void {
            $callable = Introspect::callable(fn (): int => 42);
            expect($callable->isStatic())->toBeFalse();
        });

        it('introspects invokable object', function (): void {
            $callable = Introspect::callable(
                new TestInvokable(),
            );
            $params = $callable->parameters();

            expect($params)->toHaveCount(1);
            expect($params[0]['name'])->toBe('value');
            expect($params[0]['type'])->toBe('int');
            expect($callable->returnType())->toBe('string');
        });

        it('introspects callable array with static method', function (): void {
            $callable = Introspect::callable(TestStaticCallable::staticMethod(...));
            $params = $callable->parameters();

            expect($params)->toHaveCount(1);
            expect($params[0]['name'])->toBe('x');
            expect($callable->returnType())->toBe('int');
            expect($callable->isStatic())->toBeTrue();
        });

        it('introspects callable array with instance method', function (): void {
            $callable = Introspect::callable([new TestInstanceCallable(), 'instanceMethod']);
            $params = $callable->parameters();

            expect($params)->toHaveCount(1);
            expect($params[0]['name'])->toBe('value');
            expect($callable->returnType())->toBe('string');
        });

        it('introspects callable string (function name)', function (): void {
            $callable = Introspect::callable('strlen');
            $params = $callable->parameters();

            expect($params)->toHaveCount(1);
            expect($callable->returnType())->toContain('int');
        });

        it('gets source file for closure', function (): void {
            $callable = Introspect::callable(fn (): int => 42);
            expect($callable->sourceFile())->toContain('CallableIntrospectorTest.php');
        });

        it('gets source lines for closure', function (): void {
            $callable = Introspect::callable(fn (): int => 42);
            $lines = $callable->sourceLines();

            expect($lines)->toBeArray();
            expect($lines)->toHaveCount(2);
            expect($lines[0])->toBeInt();
            expect($lines[1])->toBeInt();
            expect($lines[1])->toBeGreaterThanOrEqual($lines[0]);
        });

        it('converts to array with all information', function (): void {
            $x = 10;
            $callable = Introspect::callable(fn (int $y = 5): int => $x + $y);
            $result = $callable->toArray();

            expect($result)->toHaveKeys([
                'parameters',
                'returnType',
                'boundVariables',
                'scopeClass',
                'isStatic',
                'sourceFile',
                'sourceLines',
            ]);
            expect($result['parameters'])->toBeArray();
            expect($result['returnType'])->toBe('int');
            expect($result['boundVariables'])->toHaveKey('x');
            expect($result['sourceFile'])->toBeString();
            expect($result['sourceLines'])->toBeArray();
        });

        it('gets scope class for bound closure', function (): void {
            $instance = new TestScopeClass();
            $closure = $instance->getClosure();
            $callable = Introspect::callable($closure);

            expect($callable->scopeClass())->toBe(TestScopeClass::class);
        });
    });

    describe('Edge Cases', function (): void {
        it('handles closure with no parameters', function (): void {
            $callable = Introspect::callable(fn (): int => 42);
            expect($callable->parameters())->toBeEmpty();
        });

        it('handles closure with no bound variables', function (): void {
            $callable = Introspect::callable(fn (int $x): int => $x);
            expect($callable->boundVariables())->toBeEmpty();
        });

        it('handles closure with nullable type', function (): void {
            $callable = Introspect::callable(fn (?int $x): ?int => $x);
            $params = $callable->parameters();

            expect($params[0]['type'])->toContain('int');
        });

        it('handles closure with union type', function (): void {
            $callable = Introspect::callable(fn (int|string $x): int|string => $x);
            $params = $callable->parameters();

            expect($params[0]['type'])->toContain('int');
            expect($params[0]['type'])->toContain('string');
        });

        it('handles closure with mixed type', function (): void {
            $callable = Introspect::callable(fn (mixed $x): mixed => $x);
            $params = $callable->parameters();

            expect($params[0]['type'])->toBe('mixed');
        });

        it('handles parameter without default correctly', function (): void {
            $callable = Introspect::callable(fn (int $x): int => $x);
            $params = $callable->parameters();

            expect($params[0]['hasDefault'])->toBeFalse();
            expect($params[0]['default'])->toBeNull();
        });

        it('handles non-variadic parameter', function (): void {
            $callable = Introspect::callable(fn (int $x): int => $x);
            $params = $callable->parameters();

            expect($params[0]['variadic'])->toBeFalse();
        });

        it('handles parameter not by reference', function (): void {
            $callable = Introspect::callable(fn (int $x): int => $x);
            $params = $callable->parameters();

            expect($params[0]['byReference'])->toBeFalse();
        });

        it('gets scope class for static closure', function (): void {
            $callable = Introspect::callable(static fn (): int => 42);
            // Static closures may still have a scope class in test context
            expect($callable->scopeClass())->toBeString();
        });

        it('gets scope class for closure created in test', function (): void {
            $callable = Introspect::callable(fn (): int => 42);
            // Closures in test context are bound to the test class
            expect($callable->scopeClass())->toBeString();
        });

        it('returns empty array for bound variables on invokable', function (): void {
            $callable = Introspect::callable(
                new TestInvokable(),
            );
            expect($callable->boundVariables())->toBeEmpty();
        });

        it('returns null for scope class on invokable', function (): void {
            $callable = Introspect::callable(
                new TestInvokable(),
            );
            expect($callable->scopeClass())->toBeNull();
        });

        it('handles callable with no return type', function (): void {
            $callable = Introspect::callable(fn ($x) => $x);
            expect($callable->returnType())->toBeNull();
        });

        it('handles closure with void return type', function (): void {
            $callable = Introspect::callable(function (): void {});
            expect($callable->returnType())->toBe('void');
        });

        it('handles closure with never return type', function (): void {
            $callable = Introspect::callable(function (): never {
                throw new Exception('test');
            });
            expect($callable->returnType())->toBe('never');
        });
    });
});

// Test fixtures
/**
 * @author Brian Faust <brian@cline.sh>
 */
final class TestInvokable
{
    public function __invoke(int $value): string
    {
        return (string) $value;
    }
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class TestStaticCallable
{
    public static function staticMethod(int $x): int
    {
        return $x * 2;
    }
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class TestInstanceCallable
{
    public function instanceMethod(string $value): string
    {
        return mb_strtoupper($value);
    }
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class TestScopeClass
{
    private int $value = 42;

    public function getClosure(): Closure
    {
        return fn (): int => $this->value;
    }
}
