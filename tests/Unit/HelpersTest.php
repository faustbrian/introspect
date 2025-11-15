<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit;

use Attribute;

use function Cline\Introspect\classBasename;
use function Cline\Introspect\classNamespace;
use function Cline\Introspect\extendsClass;
use function Cline\Introspect\getAllTraits;
use function Cline\Introspect\getAttributes;
use function Cline\Introspect\getPublicMethods;
use function Cline\Introspect\getPublicProperties;
use function Cline\Introspect\hasAttribute;
use function Cline\Introspect\hasMethod;
use function Cline\Introspect\hasProperty;
use function Cline\Introspect\implementsInterface;
use function Cline\Introspect\isConcrete;
use function Cline\Introspect\isInstantiable;
use function Cline\Introspect\methodIsPublic;
use function Cline\Introspect\usesAnyTrait;
use function Cline\Introspect\usesTrait;
use function Cline\Introspect\usesTraits;
use function describe;
use function expect;
use function it;

/**
 * Comprehensive tests for standalone introspection helper functions.
 *
 * This test suite validates all helper functions from the Cline\Introspect namespace,
 * ensuring they work correctly with both class names (strings) and object instances.
 *
 * Test coverage includes:
 * - Trait detection and analysis (usesTrait, usesTraits, usesAnyTrait, getAllTraits)
 * - Interface and inheritance checks (implementsInterface, extendsClass)
 * - Class type analysis (isConcrete, isInstantiable)
 * - Method introspection (hasMethod, methodIsPublic, getPublicMethods)
 * - Property introspection (hasProperty, getPublicProperties)
 * - Class naming utilities (classBasename, classNamespace)
 * - Attribute inspection (hasAttribute, getAttributes)
 *
 * Examples:
 * ```php
 * // Trait detection
 * usesTrait(User::class, SoftDeletes::class); // true/false
 * usesTraits($user, SoftDeletes::class, HasFactory::class); // all must match
 * usesAnyTrait($model, Notifiable::class, Authenticatable::class); // any match
 *
 * // Class analysis
 * isConcrete(AbstractClass::class); // false
 * isInstantiable(MyClass::class); // true
 * extendsClass(ChildClass::class, ParentClass::class); // true
 *
 * // Method introspection
 * hasMethod($object, 'save'); // true/false
 * methodIsPublic(User::class, 'delete'); // true/false
 * getPublicMethods($model); // ['save', 'update', 'delete', ...]
 *
 * // Naming utilities
 * classBasename(App\Models\User::class); // 'User'
 * classNamespace($user); // 'App\Models'
 *
 * // PHP 8 Attributes
 * hasAttribute(UserController::class, Route::class); // true
 * getAttributes($controller, Route::class); // [Route instance, ...]
 * ```
 */
describe('Standalone Helper Functions', function (): void {
    describe('usesTrait()', function (): void {
        it('detects trait usage with class name', function (): void {
            expect(usesTrait(HelperTestClassWithTrait::class, HelperTestTrait::class))->toBeTrue();
        });

        it('detects trait usage with object instance', function (): void {
            $object = new HelperTestClassWithTrait();
            expect(usesTrait($object, HelperTestTrait::class))->toBeTrue();
        });

        it('detects recursively inherited trait usage', function (): void {
            expect(usesTrait(HelperTestChildWithTrait::class, HelperTestTrait::class))->toBeTrue();
        });

        it('returns false when trait is not used', function (): void {
            expect(usesTrait(HelperTestEmptyClass::class, HelperTestTrait::class))->toBeFalse();
        });

        it('detects trait used by parent class', function (): void {
            expect(usesTrait(HelperTestChildOfTraitUser::class, HelperTestTrait::class))->toBeTrue();
        });
    });

    describe('usesTraits()', function (): void {
        it('returns true when class uses all specified traits', function (): void {
            expect(usesTraits(HelperTestMultiTraitClass::class, HelperTestTrait::class, HelperTestSecondTrait::class))->toBeTrue();
        });

        it('returns true when given single trait', function (): void {
            expect(usesTraits(HelperTestClassWithTrait::class, HelperTestTrait::class))->toBeTrue();
        });

        it('returns false when missing one trait', function (): void {
            expect(usesTraits(HelperTestClassWithTrait::class, HelperTestTrait::class, HelperTestSecondTrait::class))->toBeFalse();
        });

        it('works with object instances', function (): void {
            $object = new HelperTestMultiTraitClass();
            expect(usesTraits($object, HelperTestTrait::class, HelperTestSecondTrait::class))->toBeTrue();
        });
    });

    describe('usesAnyTrait()', function (): void {
        it('returns true when class uses at least one trait', function (): void {
            expect(usesAnyTrait(HelperTestClassWithTrait::class, HelperTestTrait::class, HelperTestSecondTrait::class))->toBeTrue();
        });

        it('returns true when class uses all traits', function (): void {
            expect(usesAnyTrait(HelperTestMultiTraitClass::class, HelperTestTrait::class, HelperTestSecondTrait::class))->toBeTrue();
        });

        it('returns false when class uses none of the traits', function (): void {
            expect(usesAnyTrait(HelperTestEmptyClass::class, HelperTestTrait::class, HelperTestSecondTrait::class))->toBeFalse();
        });

        it('works with object instances', function (): void {
            $object = new HelperTestClassWithTrait();
            expect(usesAnyTrait($object, HelperTestTrait::class, HelperTestSecondTrait::class))->toBeTrue();
        });
    });

    describe('getAllTraits()', function (): void {
        it('returns all traits used by class', function (): void {
            $traits = getAllTraits(HelperTestMultiTraitClass::class);
            expect($traits)->toContain(HelperTestTrait::class)
                ->and($traits)->toContain(HelperTestSecondTrait::class);
        });

        it('returns empty array when no traits used', function (): void {
            expect(getAllTraits(HelperTestEmptyClass::class))->toBeEmpty();
        });

        it('includes traits from parent classes', function (): void {
            $traits = getAllTraits(HelperTestChildOfTraitUser::class);
            expect($traits)->toContain(HelperTestTrait::class);
        });

        it('works with object instances', function (): void {
            $object = new HelperTestClassWithTrait();
            $traits = getAllTraits($object);
            expect($traits)->toContain(HelperTestTrait::class);
        });
    });

    describe('implementsInterface()', function (): void {
        it('detects direct interface implementation', function (): void {
            expect(implementsInterface(HelperTestClassWithInterface::class, HelperTestInterface::class))->toBeTrue();
        });

        it('detects inherited interface implementation', function (): void {
            expect(implementsInterface(HelperTestChildOfInterfaceClass::class, HelperTestInterface::class))->toBeTrue();
        });

        it('returns false when interface is not implemented', function (): void {
            expect(implementsInterface(HelperTestEmptyClass::class, HelperTestInterface::class))->toBeFalse();
        });

        it('works with object instances', function (): void {
            $object = new HelperTestClassWithInterface();
            expect(implementsInterface($object, HelperTestInterface::class))->toBeTrue();
        });
    });

    describe('extendsClass()', function (): void {
        it('detects direct parent class', function (): void {
            expect(extendsClass(HelperTestChild::class, HelperTestParent::class))->toBeTrue();
        });

        it('detects grandparent class', function (): void {
            expect(extendsClass(HelperTestGrandchild::class, HelperTestParent::class))->toBeTrue();
        });

        it('returns false when class does not extend parent', function (): void {
            expect(extendsClass(HelperTestEmptyClass::class, HelperTestParent::class))->toBeFalse();
        });

        it('returns false for same class', function (): void {
            expect(extendsClass(HelperTestParent::class, HelperTestParent::class))->toBeFalse();
        });

        it('works with object instances', function (): void {
            $object = new HelperTestChild();
            expect(extendsClass($object, HelperTestParent::class))->toBeTrue();
        });
    });

    describe('isConcrete()', function (): void {
        it('returns true for concrete class', function (): void {
            expect(isConcrete(HelperTestConcreteClass::class))->toBeTrue();
        });

        it('returns false for abstract class', function (): void {
            expect(isConcrete(HelperTestAbstractClass::class))->toBeFalse();
        });

        it('returns true for interface (not abstract)', function (): void {
            // Note: isConcrete() only checks !isAbstract(), interfaces return true
            expect(isConcrete(HelperTestInterface::class))->toBeTrue();
        });

        it('returns true for trait (not abstract)', function (): void {
            // Note: isConcrete() only checks !isAbstract(), traits return true
            expect(isConcrete(HelperTestTrait::class))->toBeTrue();
        });
    });

    describe('isInstantiable()', function (): void {
        it('returns true for concrete class with public constructor', function (): void {
            expect(isInstantiable(HelperTestConcreteClass::class))->toBeTrue();
        });

        it('returns false for abstract class', function (): void {
            expect(isInstantiable(HelperTestAbstractClass::class))->toBeFalse();
        });

        it('returns false for interface', function (): void {
            expect(isInstantiable(HelperTestInterface::class))->toBeFalse();
        });

        it('returns false for trait', function (): void {
            expect(isInstantiable(HelperTestTrait::class))->toBeFalse();
        });

        it('returns false for class with private constructor', function (): void {
            expect(isInstantiable(HelperTestPrivateConstructor::class))->toBeFalse();
        });
    });

    describe('hasMethod()', function (): void {
        it('detects public method with class name', function (): void {
            expect(hasMethod(HelperTestMethodClass::class, 'publicMethod'))->toBeTrue();
        });

        it('detects protected method', function (): void {
            expect(hasMethod(HelperTestMethodClass::class, 'protectedMethod'))->toBeTrue();
        });

        it('detects private method', function (): void {
            expect(hasMethod(HelperTestMethodClass::class, 'privateMethod'))->toBeTrue();
        });

        it('returns false for non-existent method', function (): void {
            expect(hasMethod(HelperTestMethodClass::class, 'nonExistentMethod'))->toBeFalse();
        });

        it('works with object instances', function (): void {
            $object = new HelperTestMethodClass();
            expect(hasMethod($object, 'publicMethod'))->toBeTrue();
        });

        it('detects inherited methods', function (): void {
            expect(hasMethod(HelperTestChild::class, 'parentMethod'))->toBeTrue();
        });
    });

    describe('methodIsPublic()', function (): void {
        it('returns true for public method', function (): void {
            expect(methodIsPublic(HelperTestMethodClass::class, 'publicMethod'))->toBeTrue();
        });

        it('returns false for protected method', function (): void {
            expect(methodIsPublic(HelperTestMethodClass::class, 'protectedMethod'))->toBeFalse();
        });

        it('returns false for private method', function (): void {
            expect(methodIsPublic(HelperTestMethodClass::class, 'privateMethod'))->toBeFalse();
        });

        it('returns false for non-existent method', function (): void {
            expect(methodIsPublic(HelperTestMethodClass::class, 'nonExistentMethod'))->toBeFalse();
        });

        it('works with object instances', function (): void {
            $object = new HelperTestMethodClass();
            expect(methodIsPublic($object, 'publicMethod'))->toBeTrue();
        });
    });

    describe('getPublicMethods()', function (): void {
        it('returns all public methods', function (): void {
            $methods = getPublicMethods(HelperTestMethodClass::class);
            expect($methods)->toContain('publicMethod')
                ->and($methods)->not->toContain('protectedMethod')
                ->and($methods)->not->toContain('privateMethod');
        });

        it('includes inherited public methods', function (): void {
            $methods = getPublicMethods(HelperTestChild::class);
            expect($methods)->toContain('parentMethod')
                ->and($methods)->toContain('childMethod');
        });

        it('works with object instances', function (): void {
            $object = new HelperTestMethodClass();
            $methods = getPublicMethods($object);
            expect($methods)->toContain('publicMethod');
        });

        it('returns empty array for class with no public methods', function (): void {
            $methods = getPublicMethods(HelperTestPrivateOnlyClass::class);
            expect($methods)->toBeEmpty();
        });
    });

    describe('hasProperty()', function (): void {
        it('detects public property with class name', function (): void {
            expect(hasProperty(HelperTestPropertyClass::class, 'publicProperty'))->toBeTrue();
        });

        it('detects protected property', function (): void {
            expect(hasProperty(HelperTestPropertyClass::class, 'protectedProperty'))->toBeTrue();
        });

        it('detects private property', function (): void {
            expect(hasProperty(HelperTestPropertyClass::class, 'privateProperty'))->toBeTrue();
        });

        it('returns false for non-existent property', function (): void {
            expect(hasProperty(HelperTestPropertyClass::class, 'nonExistentProperty'))->toBeFalse();
        });

        it('works with object instances', function (): void {
            $object = new HelperTestPropertyClass();
            expect(hasProperty($object, 'publicProperty'))->toBeTrue();
        });

        it('detects inherited properties', function (): void {
            expect(hasProperty(HelperTestChild::class, 'parentProperty'))->toBeTrue();
        });
    });

    describe('getPublicProperties()', function (): void {
        it('returns all public properties', function (): void {
            $properties = getPublicProperties(HelperTestPropertyClass::class);
            expect($properties)->toContain('publicProperty')
                ->and($properties)->not->toContain('protectedProperty')
                ->and($properties)->not->toContain('privateProperty');
        });

        it('includes inherited public properties', function (): void {
            $properties = getPublicProperties(HelperTestChild::class);
            expect($properties)->toContain('parentProperty')
                ->and($properties)->toContain('childProperty');
        });

        it('works with object instances', function (): void {
            $object = new HelperTestPropertyClass();
            $properties = getPublicProperties($object);
            expect($properties)->toContain('publicProperty');
        });

        it('returns empty array for class with no public properties', function (): void {
            $properties = getPublicProperties(HelperTestPrivateOnlyClass::class);
            expect($properties)->toBeEmpty();
        });
    });

    describe('classBasename()', function (): void {
        it('extracts basename from fully qualified class name', function (): void {
            expect(classBasename(HelperTestClassWithTrait::class))->toBe('HelperTestClassWithTrait');
        });

        it('returns name for class without namespace', function (): void {
            expect(classBasename('SimpleClass'))->toBe('SimpleClass');
        });

        it('works with object instances', function (): void {
            $object = new HelperTestEmptyClass();
            expect(classBasename($object))->toBe('HelperTestEmptyClass');
        });

        it('handles nested namespaces correctly', function (): void {
            $fqcn = 'Very\\Deep\\Namespace\\Structure\\MyClass';
            expect(classBasename($fqcn))->toBe('MyClass');
        });
    });

    describe('classNamespace()', function (): void {
        it('extracts namespace from fully qualified class name', function (): void {
            expect(classNamespace(HelperTestEmptyClass::class))->toBe('Tests\Unit');
        });

        it('works with object instances', function (): void {
            $object = new HelperTestEmptyClass();
            expect(classNamespace($object))->toBe('Tests\Unit');
        });

        it('handles deeply nested namespaces', function (): void {
            $namespace = classNamespace(HelperTestClassWithTrait::class);
            expect($namespace)->toContain('Tests');
        });
    });

    describe('hasAttribute()', function (): void {
        it('detects class attribute with class name', function (): void {
            expect(hasAttribute(HelperTestClassWithAttribute::class, HelperTestAttribute::class))->toBeTrue();
        });

        it('returns false when attribute is not present', function (): void {
            expect(hasAttribute(HelperTestEmptyClass::class, HelperTestAttribute::class))->toBeFalse();
        });

        it('works with object instances', function (): void {
            $object = new HelperTestClassWithAttribute();
            expect(hasAttribute($object, HelperTestAttribute::class))->toBeTrue();
        });

        it('detects multiple attributes', function (): void {
            expect(hasAttribute(HelperTestMultiAttributeClass::class, HelperTestAttribute::class))->toBeTrue()
                ->and(hasAttribute(HelperTestMultiAttributeClass::class, HelperTestSecondAttribute::class))->toBeTrue();
        });
    });

    describe('getAttributes()', function (): void {
        it('returns all class attributes', function (): void {
            $attributes = getAttributes(HelperTestMultiAttributeClass::class);
            expect($attributes)->toHaveCount(2)
                ->and($attributes[0])->toBeInstanceOf(HelperTestAttribute::class)
                ->and($attributes[1])->toBeInstanceOf(HelperTestSecondAttribute::class);
        });

        it('returns empty array when no attributes present', function (): void {
            expect(getAttributes(HelperTestEmptyClass::class))->toBeEmpty();
        });

        it('filters by specific attribute name', function (): void {
            $attributes = getAttributes(HelperTestMultiAttributeClass::class, HelperTestAttribute::class);
            expect($attributes)->toHaveCount(1)
                ->and($attributes[0])->toBeInstanceOf(HelperTestAttribute::class);
        });

        it('works with object instances', function (): void {
            $object = new HelperTestClassWithAttribute();
            $attributes = getAttributes($object);
            expect($attributes)->toHaveCount(1)
                ->and($attributes[0])->toBeInstanceOf(HelperTestAttribute::class);
        });

        it('returns attribute instances with correct properties', function (): void {
            $attributes = getAttributes(HelperTestClassWithAttribute::class);
            expect($attributes[0]->value)->toBe('test-value');
        });
    });
});

// ============================================================================
// Test Fixtures
// ============================================================================

// Traits
trait HelperTestTrait {}

trait HelperTestSecondTrait {}

// Interfaces
interface HelperTestInterface {}

// Attributes (PHP 8.0+)
#[Attribute]
class HelperTestAttribute
{
    public function __construct(public string $value = 'test-value') {}
}

#[Attribute]
class HelperTestSecondAttribute
{
    public function __construct(public string $name = 'second') {}
}

// Basic classes
class HelperTestEmptyClass {}

class HelperTestConcreteClass {}

abstract class HelperTestAbstractClass {}

class HelperTestPrivateConstructor
{
    private function __construct() {}
}

// Trait usage
class HelperTestClassWithTrait
{
    use HelperTestTrait;
}

class HelperTestMultiTraitClass
{
    use HelperTestTrait;
    use HelperTestSecondTrait;
}

class HelperTestChildWithTrait extends HelperTestClassWithTrait {}

class HelperTestChildOfTraitUser extends HelperTestClassWithTrait {}

// Interface implementation
class HelperTestClassWithInterface implements HelperTestInterface {}

class HelperTestChildOfInterfaceClass extends HelperTestClassWithInterface {}

// Class hierarchy
class HelperTestParent
{
    public string $parentProperty = 'parent';

    public function parentMethod(): void {}
}

class HelperTestChild extends HelperTestParent
{
    public string $childProperty = 'child';

    public function childMethod(): void {}
}

class HelperTestGrandchild extends HelperTestChild {}

// Methods
class HelperTestMethodClass
{
    public function publicMethod(): void {}

    protected function protectedMethod(): void {}

    private function privateMethod(): void {}
}

class HelperTestPrivateOnlyClass
{
    private function privateMethod(): void {}

    private string $privateProperty = 'private';
}

// Properties
class HelperTestPropertyClass
{
    public string $publicProperty = 'public';

    protected string $protectedProperty = 'protected';

    private string $privateProperty = 'private';
}

// Attributes
#[HelperTestAttribute('test-value')]
class HelperTestClassWithAttribute {}

#[HelperTestAttribute('first')]
#[HelperTestSecondAttribute('second')]
class HelperTestMultiAttributeClass {}
