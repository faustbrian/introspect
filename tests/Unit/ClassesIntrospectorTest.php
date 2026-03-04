<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit;

use Cline\Introspect\Introspect;
use Tests\Fixtures\TestChildWithInterface;
use Tests\Fixtures\TestInterfaceA;
use Tests\Fixtures\TestInterfaceB;
use Tests\Fixtures\TestInterfaceImplementation;
use Tests\Fixtures\TestParentInterface;
use Tests\Fixtures\TestParentWithInterface;
use Tests\Fixtures\TraitTestAuditableTrait;
use Tests\Fixtures\TraitTestClassWithMultipleTraits;
use Tests\Fixtures\TraitTestClassWithNestedTraits;
use Tests\Fixtures\TraitTestClassWithoutTraits;
use Tests\Fixtures\TraitTestClassWithSingleTrait;

use function beforeEach;
use function class_exists;
use function describe;
use function expect;
use function it;

/**
 * Comprehensive test suite for ClassesIntrospector.
 *
 * Tests the fluent interface query builder including:
 * - Name filtering (equals, starts with, ends with, contains)
 * - Wildcard pattern matching
 * - Inheritance filtering (whereExtends)
 * - Interface filtering (whereImplements)
 * - Trait filtering (whereUses)
 * - OR logic support
 * - Result methods (get, first, exists, count)
 * - Edge cases and filter chaining
 */
describe('ClassesIntrospector', function (): void {
    // Ensure all fixture classes are loaded before tests run
    beforeEach(function (): void {
        // Force autoloading of test fixtures
        class_exists(TestInterfaceImplementation::class);
        class_exists(TestInterfaceA::class);
        class_exists(TestInterfaceB::class);
        class_exists(TestParentInterface::class);
        class_exists(TestParentWithInterface::class);
        class_exists(TestChildWithInterface::class);
        class_exists(TraitTestAuditableTrait::class);
        class_exists(TraitTestClassWithSingleTrait::class);
        class_exists(TraitTestClassWithMultipleTraits::class);
        class_exists(TraitTestClassWithNestedTraits::class);
        class_exists(TraitTestClassWithoutTraits::class);
    });

    describe('Happy Path', function (): void {
        it('gets classes by exact name match', function (): void {
            $classes = Introspect::classes()
                ->whereName(TestInterfaceImplementation::class)
                ->get();

            expect($classes)->toContain(TestInterfaceImplementation::class)
                ->and($classes)->toHaveCount(1);
        });

        it('gets classes by wildcard pattern with asterisk prefix', function (): void {
            $classes = Introspect::classes()
                ->whereName('*TestInterfaceImplementation')
                ->get();

            expect($classes)->toContain(TestInterfaceImplementation::class);
        });

        it('gets classes by wildcard pattern with asterisk suffix', function (): void {
            $classes = Introspect::classes()
                ->whereName('Tests\Fixtures\TestInterface*')
                ->get();

            expect($classes)->toContain(TestInterfaceImplementation::class);
        });

        it('gets classes by wildcard pattern with asterisk in middle', function (): void {
            $classes = Introspect::classes()
                ->whereName('Tests\*\TestInterfaceImplementation')
                ->get();

            expect($classes)->toContain(TestInterfaceImplementation::class);
        });

        it('gets classes by name prefix', function (): void {
            $classes = Introspect::classes()
                ->whereNameStartsWith('Tests\Fixtures\TraitTest')
                ->get();

            expect($classes)->toContain(TraitTestClassWithSingleTrait::class)
                ->and($classes)->toContain(TraitTestClassWithMultipleTraits::class);
        });

        it('gets classes by name suffix', function (): void {
            $classes = Introspect::classes()
                ->whereNameEndsWith('Implementation')
                ->get();

            expect($classes)->toContain(TestInterfaceImplementation::class);
        });

        it('gets classes by name containing substring', function (): void {
            $classes = Introspect::classes()
                ->whereNameContains('Multiple')
                ->get();

            expect($classes)->toContain(TraitTestClassWithMultipleTraits::class);
        });

        it('filters classes that extend a parent class', function (): void {
            $classes = Introspect::classes()
                ->whereName('Tests\Fixtures\*')
                ->whereExtends(TestParentWithInterface::class)
                ->get();

            expect($classes)->toContain(TestChildWithInterface::class);
        });

        it('filters classes that implement an interface', function (): void {
            $classes = Introspect::classes()
                ->whereName('Tests\Fixtures\*')
                ->whereImplements(TestInterfaceA::class)
                ->get();

            expect($classes)->toContain(TestInterfaceImplementation::class);
        });

        it('filters classes that use a trait', function (): void {
            $classes = Introspect::classes()
                ->whereName('Tests\Fixtures\TraitTest*')
                ->whereUses(TraitTestAuditableTrait::class)
                ->get();

            expect($classes)->toContain(TraitTestClassWithSingleTrait::class)
                ->and($classes)->toContain(TraitTestClassWithMultipleTraits::class);
        });

        it('supports OR logic', function (): void {
            $classes = Introspect::classes()
                ->whereNameStartsWith('Tests\Fixtures\TraitTestClass')
                ->or(fn ($q) => $q->whereNameStartsWith('Tests\Fixtures\TestInterface'))
                ->get();

            expect($classes)->toContain(TraitTestClassWithSingleTrait::class)
                ->and($classes)->toContain(TestInterfaceImplementation::class);
        });

        it('returns first matching class', function (): void {
            $class = Introspect::classes()
                ->whereNameStartsWith('Tests\Fixtures\TraitTest')
                ->first();

            expect($class)->toBeString()
                ->and($class)->toContain('TraitTest');
        });

        it('checks if matching classes exist', function (): void {
            $exists = Introspect::classes()
                ->whereName(TestInterfaceImplementation::class)
                ->exists();

            expect($exists)->toBeTrue();
        });

        it('counts matching classes', function (): void {
            $count = Introspect::classes()
                ->whereNameStartsWith('Tests\Fixtures\TraitTest')
                ->count();

            // Only counts classes, not traits (4 TraitTestClass* classes exist)
            expect($count)->toBeGreaterThanOrEqual(4);
        });
    });

    describe('Edge Cases', function (): void {
        it('returns empty collection when no classes match', function (): void {
            $classes = Introspect::classes()
                ->whereName('NonExistentClass')
                ->get();

            expect($classes)->toBeEmpty();
        });

        it('returns null when first() finds no matches', function (): void {
            $class = Introspect::classes()
                ->whereName('NonExistentClass')
                ->first();

            expect($class)->toBeNull();
        });

        it('returns false when exists() finds no matches', function (): void {
            $exists = Introspect::classes()
                ->whereName('NonExistentClass')
                ->exists();

            expect($exists)->toBeFalse();
        });

        it('returns zero when count() finds no matches', function (): void {
            $count = Introspect::classes()
                ->whereName('NonExistentClass')
                ->count();

            expect($count)->toBe(0);
        });

        it('chains multiple filters with AND logic', function (): void {
            $classes = Introspect::classes()
                ->whereNameStartsWith('Tests\Fixtures\TraitTest')
                ->whereNameContains('Class')
                ->whereUses(TraitTestAuditableTrait::class)
                ->get();

            expect($classes)->toContain(TraitTestClassWithSingleTrait::class);
        });

        it('handles wildcard pattern with no matches', function (): void {
            $classes = Introspect::classes()
                ->whereName('*NonExistent*')
                ->get();

            expect($classes)->toBeEmpty();
        });

        it('handles whereExtends with non-existent class', function (): void {
            $classes = Introspect::classes()
                ->whereExtends('NonExistentParent')
                ->get();

            expect($classes)->toBeEmpty();
        });

        it('handles whereImplements with non-existent interface', function (): void {
            $classes = Introspect::classes()
                ->whereImplements('NonExistentInterface')
                ->get();

            expect($classes)->toBeEmpty();
        });

        it('handles whereUses with non-existent trait', function (): void {
            $classes = Introspect::classes()
                ->whereUses('NonExistentTrait')
                ->get();

            expect($classes)->toBeEmpty();
        });

        it('handles complex wildcard pattern', function (): void {
            $classes = Introspect::classes()
                ->whereName('Tests\*\*Test*')
                ->get();

            expect($classes)->not->toBeEmpty();
        });

        it('handles case sensitivity in name matching', function (): void {
            $classes = Introspect::classes()
                ->whereName('tests\fixtures\testinterfaceimplementation')
                ->get();

            expect($classes)->toBeEmpty();
        });

        it('filters with multiple constraints narrowing results', function (): void {
            $count = Introspect::classes()
                ->whereNameStartsWith('Tests\Fixtures')
                ->whereNameContains('Trait')
                ->whereNameEndsWith('Trait')
                ->count();

            // Only TraitTestClassWithSingleTrait matches (ends with "Trait", not "Traits")
            expect($count)->toBeGreaterThanOrEqual(1);
        });

        it('supports multiple OR conditions', function (): void {
            $classes = Introspect::classes()
                ->whereNameStartsWith('Tests\Fixtures\TraitTest')
                ->or(fn ($q) => $q->whereImplements(TestInterfaceA::class))
                ->or(fn ($q) => $q->whereNameContains('Parent'))
                ->get();

            expect($classes)->not->toBeEmpty();
        });

        it('handles whereNameContains with no matches', function (): void {
            $classes = Introspect::classes()
                ->whereNameContains('NonExistentSubstring')
                ->get();

            expect($classes)->toBeEmpty();
        });

        it('handles whereNameStartsWith with no matches', function (): void {
            $classes = Introspect::classes()
                ->whereNameStartsWith('NonExistent\Namespace')
                ->get();

            expect($classes)->toBeEmpty();
        });

        it('handles whereNameEndsWith with no matches', function (): void {
            $classes = Introspect::classes()
                ->whereNameEndsWith('NonExistentSuffix')
                ->get();

            expect($classes)->toBeEmpty();
        });

        it('combines multiple inheritance and interface filters', function (): void {
            $classes = Introspect::classes()
                ->whereName('Tests\Fixtures\*')
                ->whereImplements(TestParentInterface::class)
                ->whereExtends(TestParentWithInterface::class)
                ->get();

            expect($classes)->toContain(TestChildWithInterface::class);
        });

        it('handles OR logic with extends and implements', function (): void {
            $classes = Introspect::classes()
                ->whereExtends(TestParentWithInterface::class)
                ->or(fn ($q) => $q->whereImplements(TestInterfaceB::class))
                ->get();

            expect($classes)->not->toBeEmpty();
        });

        it('filters by trait usage with wildcard name pattern', function (): void {
            $classes = Introspect::classes()
                ->whereName('Tests\Fixtures\*')
                ->whereUses(TraitTestAuditableTrait::class)
                ->get();

            expect($classes)->toContain(TraitTestClassWithSingleTrait::class);
        });

        it('handles complex chained filters', function (): void {
            $classes = Introspect::classes()
                ->whereNameStartsWith('Tests\Fixtures')
                ->whereNameContains('Class')
                ->whereNameEndsWith('Traits')
                ->get();

            expect($classes)->toContain(TraitTestClassWithMultipleTraits::class);
        });

        it('handles empty OR condition', function (): void {
            $classes = Introspect::classes()
                ->whereName(TestInterfaceImplementation::class)
                ->or(fn ($q) => $q->whereName('NonExistent'))
                ->get();

            expect($classes)->toContain(TestInterfaceImplementation::class);
        });
    });
});
