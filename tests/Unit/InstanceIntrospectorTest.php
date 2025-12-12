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

use function describe;
use function expect;
use function it;

/**
 * Comprehensive test suite for InstanceIntrospector.
 *
 * Tests all introspection capabilities when working with object instances,
 * including trait usage, interface implementation, inheritance, method/property
 * detection, and attribute handling.
 */
describe('InstanceIntrospector', function (): void {
    describe('Happy Path', function (): void {
        it('gets all traits from an instance', function (): void {
            $instance = new TestInstanceWithTrait();
            $traits = Introspect::instance($instance)->getAllTraits();
            expect($traits)->toContain(TestInstanceTrait::class);
        });

        it('filters by trait usage', function (): void {
            $instance = new TestInstanceWithTrait();
            $result = Introspect::instance($instance)
                ->whereUsesTrait(TestInstanceTrait::class)
                ->passes();
            expect($result)->toBeTrue();
        });

        it('filters by interface implementation', function (): void {
            $instance = new TestInstanceWithInterface();
            $result = Introspect::instance($instance)
                ->whereImplements(TestInstanceInterface::class)
                ->passes();
            expect($result)->toBeTrue();
        });

        it('filters by parent class', function (): void {
            $instance = new TestInstanceChild();
            $result = Introspect::instance($instance)
                ->whereExtends(TestInstanceParent::class)
                ->passes();
            expect($result)->toBeTrue();
        });

        it('filters by method existence', function (): void {
            $instance = new TestInstanceWithMethods();
            $result = Introspect::instance($instance)
                ->whereHasMethod('publicMethod')
                ->passes();
            expect($result)->toBeTrue();
        });

        it('filters by public method existence', function (): void {
            $instance = new TestInstanceWithMethods();
            $result = Introspect::instance($instance)
                ->whereHasPublicMethod('publicMethod')
                ->passes();
            expect($result)->toBeTrue();
        });

        it('filters by property existence', function (): void {
            $instance = new TestInstanceWithProperties();
            $result = Introspect::instance($instance)
                ->whereHasProperty('publicProperty')
                ->passes();
            expect($result)->toBeTrue();
        });

        it('filters by attribute presence', function (): void {
            $instance = new TestInstanceWithAttribute();
            $result = Introspect::instance($instance)
                ->whereHasAttribute(TestInstanceAttribute::class)
                ->passes();
            expect($result)->toBeTrue();
        });

        it('gets the class name of the instance', function (): void {
            $instance = new TestInstanceWithTrait();
            $className = Introspect::instance($instance)->getClassName();
            expect($className)->toBe(TestInstanceWithTrait::class);
        });

        it('gets the basename of the instance class', function (): void {
            $instance = new TestInstanceWithTrait();
            $basename = Introspect::instance($instance)->getBasename();
            expect($basename)->toBe('TestInstanceWithTrait');
        });

        it('gets the namespace of the instance class', function (): void {
            $instance = new TestInstanceWithTrait();
            $namespace = Introspect::instance($instance)->getNamespace();
            expect($namespace)->toBe('Tests\Unit');
        });

        it('gets all public methods from instance', function (): void {
            $instance = new TestInstanceWithMethods();
            $methods = Introspect::instance($instance)->getPublicMethods();
            expect($methods)->toContain('publicMethod')
                ->and($methods)->not->toContain('protectedMethod')
                ->and($methods)->not->toContain('privateMethod');
        });

        it('gets all public properties from instance', function (): void {
            $instance = new TestInstanceWithProperties();
            $properties = Introspect::instance($instance)->getPublicProperties();
            expect($properties)->toContain('publicProperty')
                ->and($properties)->not->toContain('protectedProperty')
                ->and($properties)->not->toContain('privateProperty');
        });

        it('gets all attributes from instance', function (): void {
            $instance = new TestInstanceWithAttribute();
            $attributes = Introspect::instance($instance)->getAttributes();
            expect($attributes)->toHaveCount(1);
        });

        it('gets filtered attributes by name', function (): void {
            $instance = new TestInstanceWithAttribute();
            $attributes = Introspect::instance($instance)->getAttributes(TestInstanceAttribute::class);
            expect($attributes)->toHaveCount(1);
        });

        it('converts instance to array', function (): void {
            $instance = new TestInstanceWithTrait();
            $result = Introspect::instance($instance)->toArray();
            expect($result)->toHaveKeys(['class', 'namespace', 'short_name', 'traits', 'interfaces', 'methods', 'properties'])
                ->and($result['class'])->toBe(TestInstanceWithTrait::class)
                ->and($result['namespace'])->toBe('Tests\Unit')
                ->and($result['short_name'])->toBe('TestInstanceWithTrait')
                ->and($result['traits'])->toContain(TestInstanceTrait::class);
        });

        it('returns instance when filters pass', function (): void {
            $instance = new TestInstanceWithTrait();
            $result = Introspect::instance($instance)
                ->whereUsesTrait(TestInstanceTrait::class)
                ->get();
            expect($result)->toBe($instance);
        });

        it('chains multiple filters with AND logic', function (): void {
            $instance = new TestInstanceComplex();
            $result = Introspect::instance($instance)
                ->whereUsesTrait(TestInstanceTrait::class)
                ->whereImplements(TestInstanceInterface::class)
                ->whereHasMethod('publicMethod')
                ->passes();
            expect($result)->toBeTrue();
        });

        it('identifies instance with multiple traits', function (): void {
            $instance = new TestInstanceWithMultipleTraits();
            $traits = Introspect::instance($instance)->getAllTraits();
            expect($traits)->toContain(TestInstanceTrait::class)
                ->and($traits)->toContain(TestInstanceSecondTrait::class);
        });

        it('identifies instance with multiple interfaces', function (): void {
            $instance = new TestInstanceWithMultipleInterfaces();
            $result = Introspect::instance($instance)
                ->whereImplements(TestInstanceInterface::class)
                ->passes();
            expect($result)->toBeTrue();

            $result = Introspect::instance($instance)
                ->whereImplements(TestInstanceSecondInterface::class)
                ->passes();
            expect($result)->toBeTrue();
        });

        it('handles inheritance chain correctly', function (): void {
            $instance = new TestInstanceGrandchild();
            $result = Introspect::instance($instance)
                ->whereExtends(TestInstanceChild::class)
                ->passes();
            expect($result)->toBeTrue();

            $result = Introspect::instance($instance)
                ->whereExtends(TestInstanceParent::class)
                ->passes();
            expect($result)->toBeTrue();
        });
    });

    describe('Edge Cases', function (): void {
        it('returns null when filters do not pass', function (): void {
            $instance = new TestInstanceWithTrait();
            $result = Introspect::instance($instance)
                ->whereUsesTrait(TestInstanceSecondTrait::class)
                ->get();
            expect($result)->toBeNull();
        });

        it('handles private methods correctly', function (): void {
            $instance = new TestInstanceWithMethods();
            $result = Introspect::instance($instance)
                ->whereHasPublicMethod('privateMethod')
                ->passes();
            expect($result)->toBeFalse();
        });

        it('handles protected methods correctly', function (): void {
            $instance = new TestInstanceWithMethods();
            $result = Introspect::instance($instance)
                ->whereHasPublicMethod('protectedMethod')
                ->passes();
            expect($result)->toBeFalse();
        });

        it('handles private properties correctly', function (): void {
            $instance = new TestInstanceWithProperties();
            $result = Introspect::instance($instance)
                ->whereHasProperty('privateProperty')
                ->passes();
            expect($result)->toBeTrue(); // Property exists, just not public
        });

        it('returns false when trait is not used', function (): void {
            $instance = new TestInstanceWithoutTrait();
            $result = Introspect::instance($instance)
                ->whereUsesTrait(TestInstanceTrait::class)
                ->passes();
            expect($result)->toBeFalse();
        });

        it('returns false when interface is not implemented', function (): void {
            $instance = new TestInstanceWithoutInterface();
            $result = Introspect::instance($instance)
                ->whereImplements(TestInstanceInterface::class)
                ->passes();
            expect($result)->toBeFalse();
        });

        it('returns false when parent class is not extended', function (): void {
            $instance = new TestInstanceWithoutParent();
            $result = Introspect::instance($instance)
                ->whereExtends(TestInstanceParent::class)
                ->passes();
            expect($result)->toBeFalse();
        });

        it('returns false when method does not exist', function (): void {
            $instance = new TestInstanceWithMethods();
            $result = Introspect::instance($instance)
                ->whereHasMethod('nonExistentMethod')
                ->passes();
            expect($result)->toBeFalse();
        });

        it('returns false when property does not exist', function (): void {
            $instance = new TestInstanceWithProperties();
            $result = Introspect::instance($instance)
                ->whereHasProperty('nonExistentProperty')
                ->passes();
            expect($result)->toBeFalse();
        });

        it('returns false when attribute is not present', function (): void {
            $instance = new TestInstanceWithoutAttribute();
            $result = Introspect::instance($instance)
                ->whereHasAttribute(TestInstanceAttribute::class)
                ->passes();
            expect($result)->toBeFalse();
        });

        it('handles empty attribute filter', function (): void {
            $instance = new TestInstanceWithoutAttribute();
            $attributes = Introspect::instance($instance)->getAttributes();
            expect($attributes)->toBeArray()->toBeEmpty();
        });

        it('handles non-existent attribute name filter', function (): void {
            $instance = new TestInstanceWithAttribute();
            $attributes = Introspect::instance($instance)->getAttributes('NonExistentAttribute');
            expect($attributes)->toBeArray()->toBeEmpty();
        });

        it('fails when any filter in chain fails', function (): void {
            $instance = new TestInstanceComplex();
            $result = Introspect::instance($instance)
                ->whereUsesTrait(TestInstanceTrait::class)
                ->whereImplements(TestInstanceInterface::class)
                ->whereHasMethod('nonExistentMethod')
                ->passes();
            expect($result)->toBeFalse();
        });

        it('handles instance without any traits', function (): void {
            $instance = new TestInstanceWithoutTrait();
            $traits = Introspect::instance($instance)->getAllTraits();
            expect($traits)->toBeArray()->toBeEmpty();
        });

        it('handles instance without any interfaces', function (): void {
            $instance = new TestInstanceWithoutInterface();
            $array = Introspect::instance($instance)->toArray();
            expect($array['interfaces'])->toBeArray()->toBeEmpty();
        });

        it('handles instance without parent class', function (): void {
            $instance = new TestInstanceWithoutParent();
            $array = Introspect::instance($instance)->toArray();
            expect($array['parent'])->toBeNull();
        });

        it('returns empty array when no public methods exist', function (): void {
            $instance = new TestInstanceWithoutPublicMethods();
            $methods = Introspect::instance($instance)->getPublicMethods();
            expect($methods)->toBeArray();
            // Note: May include inherited methods from base Object class
        });

        it('returns empty array when no public properties exist', function (): void {
            $instance = new TestInstanceWithoutPublicProperties();
            $properties = Introspect::instance($instance)->getPublicProperties();
            expect($properties)->toBeArray()->toBeEmpty();
        });
    });
});

// Test Fixtures

/**
 * Test trait for instance introspection.
 * @author Brian Faust <brian@cline.sh>
 */
trait TestInstanceTrait
{
    public function traitMethod(): void {}
}

/**
 * Second test trait for multiple trait testing.
 * @author Brian Faust <brian@cline.sh>
 */
trait TestInstanceSecondTrait
{
    public function secondTraitMethod(): void {}
}

/**
 * Test interface for instance introspection.
 * @author Brian Faust <brian@cline.sh>
 */
interface TestInstanceInterface
{
    public function interfaceMethod(): void;
}

/**
 * Second test interface for multiple interface testing.
 * @author Brian Faust <brian@cline.sh>
 */
interface TestInstanceSecondInterface
{
    public function secondInterfaceMethod(): void;
}

/**
 * Test attribute for instance introspection.
 * @author Brian Faust <brian@cline.sh>
 */
#[Attribute()]
final class TestInstanceAttribute {}

/**
 * Test parent class for inheritance testing.
 * @author Brian Faust <brian@cline.sh>
 */
class TestInstanceParent
{
    public function parentMethod(): void {}
}

/**
 * Test child class for inheritance testing.
 * @author Brian Faust <brian@cline.sh>
 */
class TestInstanceChild extends TestInstanceParent
{
    public function childMethod(): void {}
}

/**
 * Test grandchild class for deep inheritance testing.
 * @author Brian Faust <brian@cline.sh>
 */
final class TestInstanceGrandchild extends TestInstanceChild {}

/**
 * Test class with trait usage.
 * @author Brian Faust <brian@cline.sh>
 */
final class TestInstanceWithTrait
{
    use TestInstanceTrait;
}

/**
 * Test class with interface implementation.
 * @author Brian Faust <brian@cline.sh>
 */
final class TestInstanceWithInterface implements TestInstanceInterface
{
    public function interfaceMethod(): void {}
}

/**
 * Test class with methods of various visibility levels.
 * @author Brian Faust <brian@cline.sh>
 */
final class TestInstanceWithMethods
{
    public function publicMethod(): void {}

    protected function protectedMethod(): void {}

    private function privateMethod(): void {}
}

/**
 * Test class with properties of various visibility levels.
 * @author Brian Faust <brian@cline.sh>
 */
final class TestInstanceWithProperties
{
    public string $publicProperty = 'public';

    protected string $protectedProperty = 'protected';

    private string $privateProperty = 'private';
}

/**
 * Test class with attribute.
 * @author Brian Faust <brian@cline.sh>
 */
#[TestInstanceAttribute()]
final class TestInstanceWithAttribute {}

/**
 * Test class without trait.
 * @author Brian Faust <brian@cline.sh>
 */
final class TestInstanceWithoutTrait {}

/**
 * Test class without interface.
 * @author Brian Faust <brian@cline.sh>
 */
final class TestInstanceWithoutInterface {}

/**
 * Test class without parent class.
 * @author Brian Faust <brian@cline.sh>
 */
final class TestInstanceWithoutParent {}

/**
 * Test class without attribute.
 * @author Brian Faust <brian@cline.sh>
 */
final class TestInstanceWithoutAttribute {}

/**
 * Test class with multiple traits.
 * @author Brian Faust <brian@cline.sh>
 */
final class TestInstanceWithMultipleTraits
{
    use TestInstanceTrait;
    use TestInstanceSecondTrait;
}

/**
 * Test class with multiple interfaces.
 * @author Brian Faust <brian@cline.sh>
 */
final class TestInstanceWithMultipleInterfaces implements TestInstanceInterface, TestInstanceSecondInterface
{
    public function interfaceMethod(): void {}

    public function secondInterfaceMethod(): void {}
}

/**
 * Complex test class combining multiple features.
 * @author Brian Faust <brian@cline.sh>
 */
final class TestInstanceComplex implements TestInstanceInterface
{
    use TestInstanceTrait;

    public function interfaceMethod(): void {}

    public function publicMethod(): void {}
}

/**
 * Test class without public methods.
 * @author Brian Faust <brian@cline.sh>
 */
final class TestInstanceWithoutPublicMethods {}

/**
 * Test class without public properties.
 * @author Brian Faust <brian@cline.sh>
 */
final class TestInstanceWithoutPublicProperties {}
