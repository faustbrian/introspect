<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit;

use Cline\Introspect\Introspect;

use function describe;
use function expect;
use function it;

/**
 * Comprehensive test suite for InterfaceIntrospector.
 *
 * Tests the fluent interface query builder including:
 * - Name filtering (equals, starts with, ends with, contains)
 * - Wildcard pattern matching
 * - Implementation filtering (whereImplementedBy)
 * - Result methods (get, first, exists, count)
 * - Edge cases and filter chaining
 *
 * Uses test fixtures defined at bottom of file to ensure consistent
 * and predictable test behavior.
 */
describe('InterfaceIntrospector', function (): void {
    describe('Happy Path', function (): void {
        it('gets interfaces by exact name match', function (): void {
            $interfaces = Introspect::interfaces()
                ->in([TestInterfaceImplementation::class])
                ->whereNameEquals(TestInterfaceA::class)
                ->get();

            expect($interfaces)->toContain(TestInterfaceA::class)
                ->and($interfaces)->toHaveCount(1);
        });

        it('gets interfaces by wildcard pattern with asterisk prefix', function (): void {
            $interfaces = Introspect::interfaces()
                ->in([TestInterfaceImplementation::class])
                ->whereNameEquals('*InterfaceA')
                ->get();

            expect($interfaces)->toContain(TestInterfaceA::class);
        });

        it('gets interfaces by wildcard pattern with asterisk suffix', function (): void {
            $interfaces = Introspect::interfaces()
                ->in([TestInterfaceImplementation::class])
                ->whereNameEquals('Tests\Unit\TestInterface*')
                ->get();

            expect($interfaces)->toContain(TestInterfaceA::class)
                ->and($interfaces)->toContain(TestInterfaceB::class);
        });

        it('gets interfaces by wildcard pattern with asterisk in middle', function (): void {
            $interfaces = Introspect::interfaces()
                ->in([TestInterfaceImplementation::class])
                ->whereNameEquals('Tests\*\TestInterfaceA')
                ->get();

            expect($interfaces)->toContain(TestInterfaceA::class);
        });

        it('gets interfaces by name prefix', function (): void {
            $interfaces = Introspect::interfaces()
                ->in([TestInterfaceImplementation::class])
                ->whereNameStartsWith('Tests\Unit\TestInterface')
                ->get();

            expect($interfaces)->toContain(TestInterfaceA::class)
                ->and($interfaces)->toContain(TestInterfaceB::class);
        });

        it('gets interfaces by name suffix', function (): void {
            $interfaces = Introspect::interfaces()
                ->in([TestInterfaceImplementation::class])
                ->whereNameEndsWith('InterfaceA')
                ->get();

            expect($interfaces)->toContain(TestInterfaceA::class)
                ->and($interfaces)->not->toContain(TestInterfaceB::class);
        });

        it('gets interfaces by name containing substring', function (): void {
            $interfaces = Introspect::interfaces()
                ->in([TestInterfaceImplementation::class])
                ->whereNameContains('InterfaceB')
                ->get();

            expect($interfaces)->toContain(TestInterfaceB::class)
                ->and($interfaces)->not->toContain(TestInterfaceA::class);
        });

        it('filters interfaces implemented by specific class', function (): void {
            $interfaces = Introspect::interfaces()
                ->whereImplementedBy(TestInterfaceImplementation::class)
                ->get();

            expect($interfaces)->toContain(TestInterfaceA::class)
                ->and($interfaces)->toContain(TestInterfaceB::class);
        });

        it('returns first matching interface', function (): void {
            $interface = Introspect::interfaces()
                ->in([TestInterfaceImplementation::class])
                ->whereNameStartsWith('Tests\Unit\TestInterface')
                ->first();

            expect($interface)->toBeString()
                ->and($interface)->toContain('TestInterface');
        });

        it('checks if matching interfaces exist', function (): void {
            $exists = Introspect::interfaces()
                ->in([TestInterfaceImplementation::class])
                ->whereNameEquals(TestInterfaceA::class)
                ->exists();

            expect($exists)->toBeTrue();
        });

        it('counts matching interfaces', function (): void {
            $count = Introspect::interfaces()
                ->in([TestInterfaceImplementation::class])
                ->whereNameStartsWith('Tests\Unit\TestInterface')
                ->count();

            expect($count)->toBeGreaterThanOrEqual(2);
        });

        it('gets all interfaces from a class with multiple interfaces', function (): void {
            $interfaces = Introspect::interfaces()
                ->in([TestMultipleInterfaces::class])
                ->get();

            expect($interfaces)->toContain(TestInterfaceA::class)
                ->and($interfaces)->toContain(TestInterfaceB::class)
                ->and($interfaces)->toContain(TestInterfaceC::class);
        });

        it('gets interfaces from class hierarchy', function (): void {
            $interfaces = Introspect::interfaces()
                ->in([TestChildWithInterface::class])
                ->whereNameEquals(TestParentInterface::class)
                ->get();

            expect($interfaces)->toContain(TestParentInterface::class);
        });

        it('filters by suffix pattern matching', function (): void {
            $interfaces = Introspect::interfaces()
                ->in([TestSuffixedInterfaces::class])
                ->whereNameEndsWith('able')
                ->get();

            expect($interfaces)->toContain(TestReadable::class)
                ->and($interfaces)->toContain(TestWritable::class);
        });

        it('filters by contains pattern for namespaced interfaces', function (): void {
            $interfaces = Introspect::interfaces()
                ->in([TestNamespacedInterface::class])
                ->whereNameContains('Namespaced')
                ->get();

            expect($interfaces)->toContain(TestNamespacedInterface::class);
        });
    });

    describe('Edge Cases', function (): void {
        it('returns empty collection when no interfaces match', function (): void {
            $interfaces = Introspect::interfaces()
                ->in([TestInterfaceImplementation::class])
                ->whereNameEquals('NonExistentInterface')
                ->get();

            expect($interfaces)->toBeEmpty();
        });

        it('returns null when first() finds no matches', function (): void {
            $interface = Introspect::interfaces()
                ->in([TestInterfaceImplementation::class])
                ->whereNameEquals('NonExistentInterface')
                ->first();

            expect($interface)->toBeNull();
        });

        it('returns false when exists() finds no matches', function (): void {
            $exists = Introspect::interfaces()
                ->in([TestInterfaceImplementation::class])
                ->whereNameEquals('NonExistentInterface')
                ->exists();

            expect($exists)->toBeFalse();
        });

        it('returns zero when count() finds no matches', function (): void {
            $count = Introspect::interfaces()
                ->in([TestInterfaceImplementation::class])
                ->whereNameEquals('NonExistentInterface')
                ->count();

            expect($count)->toBe(0);
        });

        it('handles class without any interfaces', function (): void {
            $interfaces = Introspect::interfaces()
                ->in([TestClassWithoutInterfaces::class])
                ->get();

            expect($interfaces)->toBeEmpty();
        });

        it('chains multiple filters with AND logic', function (): void {
            $interfaces = Introspect::interfaces()
                ->in([TestInterfaceImplementation::class])
                ->whereNameStartsWith('Tests\Unit')
                ->whereNameEndsWith('InterfaceA')
                ->whereImplementedBy(TestInterfaceImplementation::class)
                ->get();

            expect($interfaces)->toContain(TestInterfaceA::class)
                ->and($interfaces)->not->toContain(TestInterfaceB::class);
        });

        it('handles wildcard pattern with no matches', function (): void {
            $interfaces = Introspect::interfaces()
                ->in([TestInterfaceImplementation::class])
                ->whereNameEquals('*NonExistent*')
                ->get();

            expect($interfaces)->toBeEmpty();
        });

        it('handles whereImplementedBy with class not implementing any interfaces', function (): void {
            $interfaces = Introspect::interfaces()
                ->whereImplementedBy(TestClassWithoutInterfaces::class)
                ->get();

            expect($interfaces)->toBeEmpty();
        });

        it('handles whereNameStartsWith with no matches', function (): void {
            $interfaces = Introspect::interfaces()
                ->in([TestInterfaceImplementation::class])
                ->whereNameStartsWith('NonExistent\Namespace')
                ->get();

            expect($interfaces)->toBeEmpty();
        });

        it('handles whereNameEndsWith with no matches', function (): void {
            $interfaces = Introspect::interfaces()
                ->in([TestInterfaceImplementation::class])
                ->whereNameEndsWith('NonExistentSuffix')
                ->get();

            expect($interfaces)->toBeEmpty();
        });

        it('handles whereNameContains with no matches', function (): void {
            $interfaces = Introspect::interfaces()
                ->in([TestInterfaceImplementation::class])
                ->whereNameContains('NonExistentSubstring')
                ->get();

            expect($interfaces)->toBeEmpty();
        });

        it('handles complex wildcard pattern', function (): void {
            $interfaces = Introspect::interfaces()
                ->in([TestInterfaceImplementation::class])
                ->whereNameEquals('Tests\*\*Interface*')
                ->get();

            expect($interfaces)->toContain(TestInterfaceA::class)
                ->and($interfaces)->toContain(TestInterfaceB::class);
        });

        it('handles case sensitivity in name matching', function (): void {
            $interfaces = Introspect::interfaces()
                ->in([TestInterfaceImplementation::class])
                ->whereNameEquals('tests\unit\testinterfacea')
                ->get();

            expect($interfaces)->toBeEmpty();
        });

        it('filters with multiple constraints narrowing results', function (): void {
            $count = Introspect::interfaces()
                ->in([TestMultipleInterfaces::class])
                ->whereNameStartsWith('Tests\Unit')
                ->whereNameContains('Interface')
                ->whereImplementedBy(TestMultipleInterfaces::class)
                ->count();

            expect($count)->toBeGreaterThan(0);
        });

        it('handles empty in() array', function (): void {
            $interfaces = Introspect::interfaces()
                ->in([])
                ->get();

            expect($interfaces)->toBeEmpty();
        });
    });
});

// Test fixtures

/**
 * Base test interface A.
 */
interface TestInterfaceA {}

/**
 * Base test interface B.
 */
interface TestInterfaceB {}

/**
 * Base test interface C.
 */
interface TestInterfaceC {}

/**
 * Parent interface for inheritance testing.
 */
interface TestParentInterface {}

/**
 * Readable interface for suffix testing.
 */
interface TestReadable {}

/**
 * Writable interface for suffix testing.
 */
interface TestWritable {}

/**
 * Namespaced interface for contains testing.
 */
interface TestNamespacedInterface {}

/**
 * Class implementing multiple interfaces.
 */
class TestInterfaceImplementation implements TestInterfaceA, TestInterfaceB {}

/**
 * Class implementing three interfaces.
 */
class TestMultipleInterfaces implements TestInterfaceA, TestInterfaceB, TestInterfaceC {}

/**
 * Parent class implementing an interface.
 */
class TestParentWithInterface implements TestParentInterface {}

/**
 * Child class inheriting parent's interface.
 */
class TestChildWithInterface extends TestParentWithInterface {}

/**
 * Class implementing suffixed interfaces.
 */
class TestSuffixedInterfaces implements TestReadable, TestWritable {}

/**
 * Class implementing namespaced interface.
 */
class TestNamespacedInterfaceImplementation implements TestNamespacedInterface {}

/**
 * Class without any interfaces for edge case testing.
 */
class TestClassWithoutInterfaces {}
