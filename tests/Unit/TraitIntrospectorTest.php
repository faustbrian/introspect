<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit;

use Cline\Introspect\Introspect;
use Tests\Fixtures\TraitTestAuditableTrait;
use Tests\Fixtures\TraitTestClassWithMultipleTraits;
use Tests\Fixtures\TraitTestClassWithNestedTraits;
use Tests\Fixtures\TraitTestClassWithoutTraits;
use Tests\Fixtures\TraitTestClassWithSingleTrait;
use Tests\Fixtures\TraitTestComposedTrait;
use Tests\Fixtures\TraitTestLoggableTrait;
use Tests\Fixtures\TraitTestTimestampableTrait;

use function describe;
use function expect;
use function it;

/**
 * Comprehensive test suite for TraitIntrospector.
 *
 * Tests all filtering methods, wildcard patterns, and query operations
 * for trait introspection functionality.
 */
describe('TraitIntrospector', function (): void {
    describe('Happy Path', function (): void {
        it('filters traits by exact name match', function (): void {
            $result = Introspect::traits()
                ->in([TraitTestClassWithSingleTrait::class])
                ->whereNameEquals(TraitTestAuditableTrait::class)
                ->exists();

            expect($result)->toBeTrue();
        });

        it('filters traits by wildcard pattern matching namespace', function (): void {
            $traits = Introspect::traits()
                ->in([TraitTestClassWithMultipleTraits::class])
                ->whereNameEquals('Tests\Fixtures\TraitTest*')
                ->get();

            expect($traits)->toContain(TraitTestAuditableTrait::class);
            expect($traits)->toContain(TraitTestLoggableTrait::class);
        });

        it('filters traits by wildcard pattern matching suffix', function (): void {
            $traits = Introspect::traits()
                ->in([TraitTestClassWithMultipleTraits::class])
                ->whereNameEquals('*Trait')
                ->get();

            expect($traits->count())->toBeGreaterThan(0);
            expect($traits)->toContain(TraitTestAuditableTrait::class);
        });

        it('filters traits by name starting with prefix', function (): void {
            $traits = Introspect::traits()
                ->in([TraitTestClassWithMultipleTraits::class])
                ->whereNameStartsWith('Tests\Fixtures\TraitTest')
                ->get();

            expect($traits)->toContain(TraitTestAuditableTrait::class);
            expect($traits)->toContain(TraitTestLoggableTrait::class);
        });

        it('filters traits by name ending with suffix', function (): void {
            $traits = Introspect::traits()
                ->in([TraitTestClassWithMultipleTraits::class])
                ->whereNameEndsWith('Trait')
                ->get();

            expect($traits->count())->toBeGreaterThan(0);
            expect($traits)->toContain(TraitTestAuditableTrait::class);
        });

        it('filters traits by name containing substring', function (): void {
            $traits = Introspect::traits()
                ->in([TraitTestClassWithMultipleTraits::class])
                ->whereNameContains('Auditable')
                ->get();

            expect($traits)->toContain(TraitTestAuditableTrait::class);
            expect($traits)->not->toContain(TraitTestLoggableTrait::class);
        });

        it('filters traits by class that uses them', function (): void {
            $traits = Introspect::traits()
                ->in([TraitTestClassWithSingleTrait::class])
                ->whereUsedBy(TraitTestClassWithSingleTrait::class)
                ->whereNameEquals(TraitTestAuditableTrait::class)
                ->get();

            expect($traits)->toContain(TraitTestAuditableTrait::class);
        });

        it('gets all traits from specified classes', function (): void {
            $traits = Introspect::traits()
                ->in([TraitTestClassWithMultipleTraits::class])
                ->get();

            expect($traits)->toContain(TraitTestAuditableTrait::class);
            expect($traits)->toContain(TraitTestLoggableTrait::class);
        });

        it('gets first matching trait', function (): void {
            $trait = Introspect::traits()
                ->in([TraitTestClassWithSingleTrait::class])
                ->whereNameContains('Auditable')
                ->first();

            expect($trait)->toBe(TraitTestAuditableTrait::class);
        });

        it('checks if any traits exist matching filters', function (): void {
            $exists = Introspect::traits()
                ->in([TraitTestClassWithSingleTrait::class])
                ->whereNameEquals(TraitTestAuditableTrait::class)
                ->exists();

            expect($exists)->toBeTrue();
        });

        it('counts matching traits', function (): void {
            $count = Introspect::traits()
                ->in([TraitTestClassWithMultipleTraits::class])
                ->whereNameStartsWith('Tests\Fixtures\TraitTest')
                ->count();

            expect($count)->toBe(2);
        });

        it('chains multiple filters with AND logic', function (): void {
            $traits = Introspect::traits()
                ->in([TraitTestClassWithMultipleTraits::class])
                ->whereNameStartsWith('Tests\\Fixtures')
                ->whereNameEndsWith('Trait')
                ->whereNameContains('Auditable')
                ->get();

            expect($traits)->toContain(TraitTestAuditableTrait::class);
            expect($traits)->not->toContain(TraitTestLoggableTrait::class);
        });

        it('searches across multiple classes', function (): void {
            $traits = Introspect::traits()
                ->in([
                    TraitTestClassWithSingleTrait::class,
                    TraitTestClassWithMultipleTraits::class,
                    TraitTestClassWithNestedTraits::class,
                ])
                ->whereNameStartsWith('Tests\Fixtures\TraitTest')
                ->get();

            expect($traits)->toContain(TraitTestAuditableTrait::class);
            expect($traits)->toContain(TraitTestLoggableTrait::class);
            expect($traits)->toContain(TraitTestTimestampableTrait::class);
        });

        it('finds traits from all declared traits when no classes specified', function (): void {
            $traits = Introspect::traits()
                ->in([
                    TraitTestClassWithSingleTrait::class,
                    TraitTestClassWithMultipleTraits::class,
                ])
                ->whereNameStartsWith('Tests\\Fixtures\\TraitTest')
                ->get();

            expect($traits->count())->toBeGreaterThan(0);
        });

        it('handles nested trait usage', function (): void {
            $traits = Introspect::traits()
                ->in([TraitTestClassWithNestedTraits::class])
                ->whereNameEquals(TraitTestComposedTrait::class)
                ->exists();

            expect($traits)->toBeTrue();
        });

        it('filters with complex wildcard patterns', function (): void {
            $traits = Introspect::traits()
                ->in([TraitTestClassWithMultipleTraits::class])
                ->whereNameEquals('*\TraitTest*Trait')
                ->get();

            expect($traits)->toContain(TraitTestAuditableTrait::class);
            expect($traits)->toContain(TraitTestLoggableTrait::class);
        });
    });

    describe('Edge Cases', function (): void {
        it('returns empty collection when no traits match filters', function (): void {
            $traits = Introspect::traits()
                ->in([TraitTestClassWithSingleTrait::class])
                ->whereNameEquals('NonExistentTrait')
                ->get();

            expect($traits)->toBeEmpty();
        });

        it('returns null when first() finds no matches', function (): void {
            $trait = Introspect::traits()
                ->in([TraitTestClassWithSingleTrait::class])
                ->whereNameEquals('NonExistentTrait')
                ->first();

            expect($trait)->toBeNull();
        });

        it('returns false when exists() finds no matches', function (): void {
            $exists = Introspect::traits()
                ->in([TraitTestClassWithSingleTrait::class])
                ->whereNameEquals('NonExistentTrait')
                ->exists();

            expect($exists)->toBeFalse();
        });

        it('returns zero when count() finds no matches', function (): void {
            $count = Introspect::traits()
                ->in([TraitTestClassWithSingleTrait::class])
                ->whereNameEquals('NonExistentTrait')
                ->count();

            expect($count)->toBe(0);
        });

        it('handles class without traits', function (): void {
            $traits = Introspect::traits()
                ->in([TraitTestClassWithoutTraits::class])
                ->get();

            expect($traits)->toBeEmpty();
        });

        it('handles whereUsedBy with class that does not use the trait', function (): void {
            $traits = Introspect::traits()
                ->whereUsedBy(TraitTestClassWithoutTraits::class)
                ->whereNameEquals(TraitTestAuditableTrait::class)
                ->get();

            expect($traits)->toBeEmpty();
        });

        it('handles empty classes array', function (): void {
            $traits = Introspect::traits()
                ->in([])
                ->whereNameStartsWith('Tests\Fixtures\TraitTest')
                ->get();

            expect($traits)->toBeEmpty();
        });

        it('handles whereNameStartsWith with no matches', function (): void {
            $traits = Introspect::traits()
                ->in([TraitTestClassWithSingleTrait::class])
                ->whereNameStartsWith('NonExistent\Namespace')
                ->get();

            expect($traits)->toBeEmpty();
        });

        it('handles whereNameEndsWith with no matches', function (): void {
            $traits = Introspect::traits()
                ->in([TraitTestClassWithSingleTrait::class])
                ->whereNameEndsWith('Interface')
                ->get();

            expect($traits)->toBeEmpty();
        });

        it('handles whereNameContains with no matches', function (): void {
            $traits = Introspect::traits()
                ->in([TraitTestClassWithSingleTrait::class])
                ->whereNameContains('NonExistentSubstring')
                ->get();

            expect($traits)->toBeEmpty();
        });

        it('chains multiple filters that result in no matches', function (): void {
            $traits = Introspect::traits()
                ->in([TraitTestClassWithMultipleTraits::class])
                ->whereNameStartsWith('Tests\Unit')
                ->whereNameEndsWith('Trait')
                ->whereNameContains('NonExistent')
                ->get();

            expect($traits)->toBeEmpty();
        });

        it('handles wildcard pattern with special regex characters', function (): void {
            $traits = Introspect::traits()
                ->in([TraitTestClassWithSingleTrait::class])
                ->whereNameEquals('Tests\Fixtures\*Trait')
                ->get();

            expect($traits)->toContain(TraitTestAuditableTrait::class);
        });

        it('returns unique traits when used by multiple classes', function (): void {
            $traits = Introspect::traits()
                ->in([
                    TraitTestClassWithSingleTrait::class,
                    TraitTestClassWithMultipleTraits::class,
                ])
                ->whereNameEquals(TraitTestAuditableTrait::class)
                ->get();

            expect($traits->count())->toBe(1);
            expect($traits)->toContain(TraitTestAuditableTrait::class);
        });

        it('handles case-sensitive name matching', function (): void {
            $traits = Introspect::traits()
                ->in([TraitTestClassWithSingleTrait::class])
                ->whereNameContains('auditable')
                ->get();

            expect($traits)->toBeEmpty();
        });

        it('handles whereUsedBy with multiple nested trait levels', function (): void {
            $traits = Introspect::traits()
                ->in([TraitTestClassWithNestedTraits::class])
                ->whereUsedBy(TraitTestClassWithNestedTraits::class)
                ->whereNameEquals(TraitTestTimestampableTrait::class)
                ->exists();

            expect($traits)->toBeTrue();
        });
    });
});
