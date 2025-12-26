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
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

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
            $result = Introspect::class(Request::class)
                ->whereExtends(SymfonyRequest::class)
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

        it('filters by property existence', function (): void {
            $result = Introspect::class(TestClassWithProperties::class)
                ->whereHasProperty('publicProperty')
                ->passes();
            expect($result)->toBeTrue();
        });

        it('filters by attribute presence', function (): void {
            $result = Introspect::class(TestClassWithAttribute::class)
                ->whereHasAttribute(TestClassAttribute::class)
                ->passes();
            expect($result)->toBeTrue();
        });

        it('filters with whereDoesNotUseTrait', function (): void {
            $result = Introspect::class(TestConcreteClass::class)
                ->whereDoesNotUseTrait(TestTrait::class)
                ->passes();
            expect($result)->toBeTrue();
        });

        it('filters with whereDoesNotImplement', function (): void {
            $result = Introspect::class(TestConcreteClass::class)
                ->whereDoesNotImplement(TestInterface::class)
                ->passes();
            expect($result)->toBeTrue();
        });

        it('filters with whereDoesNotExtend', function (): void {
            $result = Introspect::class(TestConcreteClass::class)
                ->whereDoesNotExtend(TestAbstractClass::class)
                ->passes();
            expect($result)->toBeTrue();
        });

        it('filters with whereDoesNotHaveMethod', function (): void {
            $result = Introspect::class(TestConcreteClass::class)
                ->whereDoesNotHaveMethod('nonExistentMethod')
                ->passes();
            expect($result)->toBeTrue();
        });

        it('filters with whereDoesNotHaveProperty', function (): void {
            $result = Introspect::class(TestConcreteClass::class)
                ->whereDoesNotHaveProperty('nonExistentProperty')
                ->passes();
            expect($result)->toBeTrue();
        });

        it('filters with whereDoesNotHaveAttribute', function (): void {
            $result = Introspect::class(TestConcreteClass::class)
                ->whereDoesNotHaveAttribute(TestClassAttribute::class)
                ->passes();
            expect($result)->toBeTrue();
        });

        it('filters by having static methods', function (): void {
            $result = Introspect::class(TestClassWithStaticMembers::class)
                ->whereHasStaticMethods()
                ->passes();
            expect($result)->toBeTrue();
        });

        it('filters by having static properties', function (): void {
            $result = Introspect::class(TestClassWithStaticMembers::class)
                ->whereHasStaticProperties()
                ->passes();
            expect($result)->toBeTrue();
        });

        it('filters by having constructor', function (): void {
            $result = Introspect::class(TestClassWithConstructor::class)
                ->whereHasConstructor()
                ->passes();
            expect($result)->toBeTrue();
        });

        it('gets public properties', function (): void {
            $properties = Introspect::class(TestClassWithProperties::class)->getPublicProperties();
            expect($properties)->toContain('publicProperty');
        });

        it('gets attributes', function (): void {
            $attributes = Introspect::class(TestClassWithAttribute::class)->getAttributes();
            expect($attributes)->not->toBeEmpty();
        });

        it('gets attributes filtered by name', function (): void {
            $attributes = Introspect::class(TestClassWithAttribute::class)->getAttributes(TestClassAttribute::class);
            expect($attributes)->not->toBeEmpty();
        });

        it('gets parent classes', function (): void {
            $parents = Introspect::class(TestChildWithParent::class)->getParentClasses();
            expect($parents)->toContain(TestParentClass::class);
        });

        it('gets static methods', function (): void {
            $methods = Introspect::class(TestClassWithStaticMembers::class)->getStaticMethods();
            expect($methods)->toContain('staticMethod');
        });

        it('gets static properties', function (): void {
            $properties = Introspect::class(TestClassWithStaticMembers::class)->getStaticProperties();
            expect($properties)->toContain('staticProperty');
        });

        it('gets constructor parameters', function (): void {
            $params = Introspect::class(TestClassWithConstructor::class)->getConstructorParameters();
            expect($params)->toHaveCount(2);
            expect($params[0]['name'])->toBe('name');
            expect($params[0]['type'])->toBe('string');
            expect($params[1]['name'])->toBe('active');
            expect($params[1]['has_default'])->toBeTrue();
            expect($params[1]['default'])->toBeTrue();
        });

        it('returns empty array for constructor parameters when no constructor', function (): void {
            $params = Introspect::class(TestConcreteClass::class)->getConstructorParameters();
            expect($params)->toBeEmpty();
        });

        it('gets direct interfaces', function (): void {
            $interfaces = Introspect::class(TestChildWithInterface::class)->getDirectInterfaces();
            expect($interfaces)->toContain(TestInterface::class);
        });

        it('gets reflection', function (): void {
            $reflection = Introspect::class(TestConcreteClass::class)->getReflection();
            expect($reflection->getName())->toBe(TestConcreteClass::class);
        });
    });
});

// Test fixtures
/**
 * @author Brian Faust <brian@cline.sh>
 */
trait TestTrait {}

/**
 * @author Brian Faust <brian@cline.sh>
 */
interface TestInterface {}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class TestClassWithTrait
{
    use TestTrait;
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class TestClassWithInterface implements TestInterface {}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class TestConcreteClass {}

/**
 * @author Brian Faust <brian@cline.sh>
 */
abstract class TestAbstractClass {}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class TestClassWithMethods
{
    public static function staticMethod(): void {}

    public function publicMethod(): void {}

    protected function protectedMethod(): void {}

    private function privateMethod(): void {}
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class TestClassWithProperties
{
    public string $publicProperty = 'public';
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class TestClassAttribute {}

/**
 * @author Brian Faust <brian@cline.sh>
 */
#[TestClassAttribute()]
final class TestClassWithAttribute {}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class TestClassWithStaticMembers
{
    public static string $staticProperty = 'static';

    public static function staticMethod(): void {}
}

/**
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class TestClassWithConstructor
{
    public function __construct(
        public string $name,
        public bool $active = true,
    ) {}
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
class TestParentClass {}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class TestChildWithParent extends TestParentClass {}

/**
 * @author Brian Faust <brian@cline.sh>
 */
class TestChildWithInterface implements TestInterface {}
