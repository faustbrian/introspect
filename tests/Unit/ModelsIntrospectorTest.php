<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit;

use Cline\Introspect\Introspect;
use Tests\Fixtures\TestPost;
use Tests\Fixtures\TestProduct;
use Tests\Fixtures\TestUser;

use function describe;
use function expect;
use function it;

/**
 * Comprehensive test suite for ModelsIntrospector.
 *
 * Tests the fluent interface query builder including:
 * - Property filtering (whereHasProperty, whereDoesntHaveProperty)
 * - Fillable filtering (whereHasFillable, whereDoesntHaveFillable)
 * - Hidden filtering (whereHasHidden, whereDoesntHaveHidden)
 * - Appended filtering (whereHasAppended, whereDoesntHaveAppended)
 * - Readable/Writable filtering
 * - Relationship filtering (whereHasRelationship)
 * - OR logic support
 * - Result methods (get, first, exists, count)
 * - Edge cases and filter chaining
 */
describe('ModelsIntrospector', function (): void {
    describe('Happy Path', function (): void {
        it('filters models by property existence', function (): void {
            $models = Introspect::models()
                ->in([TestUser::class, TestPost::class, TestProduct::class])
                ->whereHasProperty('fillable')
                ->get();

            expect($models)->toContain(TestUser::class)
                ->and($models)->toContain(TestPost::class)
                ->and($models)->toContain(TestProduct::class);
        });

        it('filters models that do not have a property', function (): void {
            $models = Introspect::models()
                ->in([TestUser::class, TestPost::class])
                ->whereDoesntHaveProperty('nonexistent_property')
                ->get();

            expect($models)->toContain(TestUser::class)
                ->and($models)->toContain(TestPost::class);
        });

        it('filters models by fillable property', function (): void {
            $models = Introspect::models()
                ->in([TestUser::class, TestPost::class, TestProduct::class])
                ->whereHasFillable('name')
                ->get();

            expect($models)->toContain(TestUser::class)
                ->and($models)->toContain(TestProduct::class)
                ->and($models)->not->toContain(TestPost::class);
        });

        it('filters models that do not have fillable property', function (): void {
            $models = Introspect::models()
                ->in([TestUser::class, TestPost::class])
                ->whereDoesntHaveFillable('title')
                ->get();

            expect($models)->toContain(TestUser::class)
                ->and($models)->not->toContain(TestPost::class);
        });

        it('filters models by multiple fillable properties with ALL logic', function (): void {
            $models = Introspect::models()
                ->in([TestUser::class, TestPost::class])
                ->whereHasFillableProperties(['name', 'email'], all: true)
                ->get();

            expect($models)->toContain(TestUser::class)
                ->and($models)->not->toContain(TestPost::class);
        });

        it('filters models by multiple fillable properties with ANY logic', function (): void {
            $models = Introspect::models()
                ->in([TestUser::class, TestPost::class])
                ->whereHasFillableProperties(['name', 'title'], all: false)
                ->get();

            expect($models)->toContain(TestUser::class)
                ->and($models)->toContain(TestPost::class);
        });

        it('filters models by hidden property', function (): void {
            $models = Introspect::models()
                ->in([TestUser::class, TestPost::class])
                ->whereHasHidden('password')
                ->get();

            expect($models)->toContain(TestUser::class)
                ->and($models)->not->toContain(TestPost::class);
        });

        it('filters models that do not have hidden property', function (): void {
            $models = Introspect::models()
                ->in([TestUser::class, TestProduct::class])
                ->whereDoesntHaveHidden('password')
                ->get();

            expect($models)->toContain(TestProduct::class)
                ->and($models)->not->toContain(TestUser::class);
        });

        it('filters models by multiple hidden properties with ALL logic', function (): void {
            $models = Introspect::models()
                ->in([TestUser::class, TestPost::class])
                ->whereHasHiddenProperties(['password', 'remember_token'], all: true)
                ->get();

            expect($models)->toContain(TestUser::class)
                ->and($models)->not->toContain(TestPost::class);
        });

        it('filters models by appended attribute', function (): void {
            $models = Introspect::models()
                ->in([TestUser::class, TestPost::class])
                ->whereHasAppended('full_name')
                ->get();

            expect($models)->toContain(TestUser::class)
                ->and($models)->not->toContain(TestPost::class);
        });

        it('filters models that do not have appended attribute', function (): void {
            $models = Introspect::models()
                ->in([TestPost::class, TestProduct::class])
                ->whereDoesntHaveAppended('full_name')
                ->get();

            expect($models)->toContain(TestProduct::class)
                ->and($models)->toContain(TestPost::class);
        });

        it('filters models by relationship', function (): void {
            $models = Introspect::models()
                ->in([TestUser::class, TestPost::class])
                ->whereHasRelationship('posts')
                ->get();

            expect($models)->toContain(TestUser::class)
                ->and($models)->not->toContain(TestPost::class);
        });

        it('filters models that do not have relationship', function (): void {
            $models = Introspect::models()
                ->in([TestUser::class, TestProduct::class])
                ->whereDoesntHaveRelationship('posts')
                ->get();

            expect($models)->toContain(TestProduct::class)
                ->and($models)->not->toContain(TestUser::class);
        });

        it('supports OR logic', function (): void {
            $models = Introspect::models()
                ->in([TestUser::class, TestPost::class, TestProduct::class])
                ->whereHasFillable('email')
                ->or(fn ($q) => $q->whereHasFillable('title'))
                ->get();

            expect($models)->toContain(TestUser::class)
                ->and($models)->toContain(TestPost::class)
                ->and($models)->not->toContain(TestProduct::class);
        });

        it('returns first matching model', function (): void {
            $model = Introspect::models()
                ->in([TestUser::class, TestPost::class])
                ->whereHasFillable('name')
                ->first();

            expect($model)->toBeString()
                ->and($model)->toBe(TestUser::class);
        });

        it('checks if matching models exist', function (): void {
            $exists = Introspect::models()
                ->in([TestUser::class])
                ->whereHasFillable('email')
                ->exists();

            expect($exists)->toBeTrue();
        });

        it('counts matching models', function (): void {
            $count = Introspect::models()
                ->in([TestUser::class, TestPost::class, TestProduct::class])
                ->whereHasFillable('name')
                ->count();

            expect($count)->toBe(2);
        });
    });

    describe('Edge Cases', function (): void {
        it('returns empty collection when no models match', function (): void {
            $models = Introspect::models()
                ->in([TestUser::class])
                ->whereHasFillable('nonexistent_field')
                ->get();

            expect($models)->toBeEmpty();
        });

        it('returns null when first() finds no matches', function (): void {
            $model = Introspect::models()
                ->in([TestUser::class])
                ->whereHasFillable('nonexistent_field')
                ->first();

            expect($model)->toBeNull();
        });

        it('returns false when exists() finds no matches', function (): void {
            $exists = Introspect::models()
                ->in([TestUser::class])
                ->whereHasFillable('nonexistent_field')
                ->exists();

            expect($exists)->toBeFalse();
        });

        it('returns zero when count() finds no matches', function (): void {
            $count = Introspect::models()
                ->in([TestUser::class])
                ->whereHasFillable('nonexistent_field')
                ->count();

            expect($count)->toBe(0);
        });

        it('chains multiple filters with AND logic', function (): void {
            $models = Introspect::models()
                ->in([TestUser::class, TestPost::class])
                ->whereHasFillable('email')
                ->whereHasHidden('password')
                ->whereHasAppended('full_name')
                ->get();

            expect($models)->toContain(TestUser::class)
                ->and($models)->not->toContain(TestPost::class);
        });

        it('handles whereHasProperties with all=true', function (): void {
            $models = Introspect::models()
                ->in([TestUser::class])
                ->whereHasProperties(['fillable', 'hidden', 'appends'], all: true)
                ->get();

            expect($models)->toContain(TestUser::class);
        });

        it('handles whereHasProperties with all=false', function (): void {
            $models = Introspect::models()
                ->in([TestUser::class, TestProduct::class])
                ->whereHasProperties(['fillable', 'nonexistent'], all: false)
                ->get();

            expect($models)->toContain(TestUser::class)
                ->and($models)->toContain(TestProduct::class);
        });

        it('handles whereDoesntHaveProperties with all=true', function (): void {
            $models = Introspect::models()
                ->in([TestUser::class])
                ->whereDoesntHaveProperties(['nonexistent1', 'nonexistent2'], all: true)
                ->get();

            expect($models)->toContain(TestUser::class);
        });

        it('handles whereHasHiddenProperties with ANY logic', function (): void {
            $models = Introspect::models()
                ->in([TestUser::class, TestPost::class])
                ->whereHasHiddenProperties(['password', 'draft_content'], all: false)
                ->get();

            expect($models)->toContain(TestUser::class)
                ->and($models)->toContain(TestPost::class);
        });

        it('handles whereHasAppendedProperties with ALL logic', function (): void {
            $models = Introspect::models()
                ->in([TestUser::class])
                ->whereHasAppendedProperties(['full_name'], all: true)
                ->get();

            expect($models)->toContain(TestUser::class);
        });

        it('supports multiple OR conditions', function (): void {
            $models = Introspect::models()
                ->in([TestUser::class, TestPost::class, TestProduct::class])
                ->whereHasFillable('email')
                ->or(fn ($q) => $q->whereHasFillable('title'))
                ->or(fn ($q) => $q->whereHasFillable('sku'))
                ->get();

            expect($models)->toContain(TestUser::class)
                ->and($models)->toContain(TestPost::class)
                ->and($models)->toContain(TestProduct::class);
        });

        it('handles empty in() array', function (): void {
            $models = Introspect::models()
                ->in([])
                ->get();

            expect($models)->toBeEmpty();
        });

        it('discovers models without in() constraint', function (): void {
            $models = Introspect::models()
                ->whereHasFillable('name')
                ->get();

            expect($models)->toContain(TestUser::class)
                ->and($models)->toContain(TestProduct::class);
        });

        it('filters by readable properties', function (): void {
            $models = Introspect::models()
                ->in([TestUser::class])
                ->whereHasReadable('name')
                ->get();

            expect($models)->toContain(TestUser::class);
        });

        it('filters by writable properties', function (): void {
            $models = Introspect::models()
                ->in([TestUser::class])
                ->whereHasWritable('email')
                ->get();

            expect($models)->toContain(TestUser::class);
        });

        it('filters models that do not have readable property', function (): void {
            $models = Introspect::models()
                ->in([TestUser::class])
                ->whereDoesntHaveReadable('nonexistent_accessor')
                ->get();

            expect($models)->toContain(TestUser::class);
        });

        it('filters models that do not have writable property', function (): void {
            $models = Introspect::models()
                ->in([TestUser::class, TestProduct::class])
                ->whereDoesntHaveWritable('password')
                ->get();

            expect($models)->toContain(TestProduct::class);
        });

        it('handles whereHasReadableProperties with ALL logic', function (): void {
            $models = Introspect::models()
                ->in([TestUser::class])
                ->whereHasReadableProperties(['name', 'email'], all: true)
                ->get();

            expect($models)->toContain(TestUser::class);
        });

        it('handles whereHasWritableProperties with ANY logic', function (): void {
            $models = Introspect::models()
                ->in([TestUser::class, TestPost::class])
                ->whereHasWritableProperties(['email', 'title'], all: false)
                ->get();

            expect($models)->toContain(TestUser::class)
                ->and($models)->toContain(TestPost::class);
        });
    });
});
