<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit;

use Cline\Introspect\Introspect;
use Exception;
use stdClass;

use function describe;
use function expect;
use function it;

describe('ClassIntrospector', function (): void {
    describe('Happy Path', function (): void {
        it('gets all traits from a class', function (): void {
            $traits = Introspect::class(TestClassWithTrait::class)->getAllTraits();
            expect($traits)->toContain(TestTrait::class);
        });

        it('filters by trait usage', function (): void {
            $result = Introspect::class(TestClassWithTrait::class)
                ->whereUsesTrait(TestTrait::class)
                ->passes();
            expect($result)->toBeTrue();
        });

        it('filters by interface implementation', function (): void {
            $result = Introspect::class(TestClassWithInterface::class)
                ->whereImplements(TestInterface::class)
                ->passes();
            expect($result)->toBeTrue();
        });

        it('filters by parent class', function (): void {
            $result = Introspect::class(TestChildClass::class)
                ->whereExtends(TestParentClass::class)
                ->passes();
            expect($result)->toBeTrue();
        });

        it('identifies concrete classes', function (): void {
            $result = Introspect::class(TestConcreteClass::class)
                ->whereConcrete()
                ->passes();
            expect($result)->toBeTrue();
        });

        it('identifies abstract classes', function (): void {
            $result = Introspect::class(TestAbstractClass::class)
                ->whereAbstract()
                ->passes();
            expect($result)->toBeTrue();
        });

        it('identifies instantiable classes', function (): void {
            $result = Introspect::class(TestConcreteClass::class)
                ->whereInstantiable()
                ->passes();
            expect($result)->toBeTrue();
        });

        it('filters by method existence', function (): void {
            $result = Introspect::class(TestClassWithMethods::class)
                ->whereHasMethod('publicMethod')
                ->passes();
            expect($result)->toBeTrue();
        });

        it('filters by public method', function (): void {
            $result = Introspect::class(TestClassWithMethods::class)
                ->whereHasPublicMethod('publicMethod')
                ->passes();
            expect($result)->toBeTrue();
        });

        it('gets all public methods', function (): void {
            $methods = Introspect::class(TestClassWithMethods::class)->getPublicMethods();
            expect($methods)->toContain('publicMethod');
        });

        it('converts to array', function (): void {
            $result = Introspect::class(TestClassWithTrait::class)->toArray();
            expect($result)->toHaveKeys(['name', 'namespace', 'short_name', 'traits', 'interfaces']);
        });

        it('returns class name when filters pass', function (): void {
            $result = Introspect::class(TestClassWithTrait::class)
                ->whereUsesTrait(TestTrait::class)
                ->get();
            expect($result)->toBe(TestClassWithTrait::class);
        });
    });

    describe('Edge Cases', function (): void {
        it('returns null when filters do not pass', function (): void {
            $result = Introspect::class(TestConcreteClass::class)
                ->whereUsesTrait(TestTrait::class)
                ->get();
            expect($result)->toBeNull();
        });

        it('identifies non-concrete classes correctly', function (): void {
            $result = Introspect::class(TestConcreteClass::class)
                ->whereAbstract()
                ->passes();
            expect($result)->toBeFalse();
        });

        it('handles private methods correctly', function (): void {
            $result = Introspect::class(TestClassWithMethods::class)
                ->whereHasPublicMethod('privateMethod')
                ->passes();
            expect($result)->toBeFalse();
        });

        it('chains multiple filters with AND logic', function (): void {
            $result = Introspect::class(TestClassWithTrait::class)
                ->whereUsesTrait(TestTrait::class)
                ->whereConcrete()
                ->whereInstantiable()
                ->passes();
            expect($result)->toBeTrue();
        });
    });
});

// Test fixtures
trait TestTrait {}

interface TestInterface {}

class TestClassWithTrait
{
    use TestTrait;
}

class TestClassWithInterface implements TestInterface {}

class TestParentClass {}

class TestChildClass extends TestParentClass {}

class TestConcreteClass {}

abstract class TestAbstractClass {}

class TestClassWithMethods
{
    public function publicMethod(): void {}

    protected function protectedMethod(): void {}

    private function privateMethod(): void {}
}
