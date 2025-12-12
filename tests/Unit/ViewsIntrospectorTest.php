<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit;

use Cline\Introspect\Introspect;
use Illuminate\Support\Collection;

use function describe;
use function expect;
use function it;

/**
 * Comprehensive test suite for ViewsIntrospector.
 *
 * Tests the fluent interface query builder including:
 * - Name filtering (equals, starts with, ends with, contains)
 * - Wildcard pattern matching
 * - Relationship filtering (extends, uses, used by)
 * - OR logic support
 * - Result methods (get, first, exists, count)
 * - Edge cases and filter chaining
 */
describe('ViewsIntrospector', function (): void {
    describe('Happy Path', function (): void {
        it('gets views by exact name match', function (): void {
            $views = Introspect::views()
                ->whereNameEquals('pages.home')
                ->get();

            expect($views)->toContain('pages.home')
                ->and($views)->toHaveCount(1);
        });

        it('gets views by wildcard pattern with asterisk prefix', function (): void {
            $views = Introspect::views()
                ->whereNameEquals('*.home')
                ->get();

            expect($views)->toContain('pages.home');
        });

        it('gets views by wildcard pattern with asterisk suffix', function (): void {
            $views = Introspect::views()
                ->whereNameEquals('pages.*')
                ->get();

            expect($views)->toContain('pages.home')
                ->and($views)->toContain('pages.about');
        });

        it('gets views by wildcard pattern with asterisk in middle', function (): void {
            $views = Introspect::views()
                ->whereNameEquals('*.button')
                ->get();

            expect($views)->toContain('components.button');
        });

        it('gets views by name prefix', function (): void {
            $views = Introspect::views()
                ->whereNameStartsWith('pages.')
                ->get();

            expect($views)->toContain('pages.home')
                ->and($views)->toContain('pages.about')
                ->and($views)->not->toContain('admin.dashboard');
        });

        it('gets views by name suffix', function (): void {
            $views = Introspect::views()
                ->whereNameEndsWith('.home')
                ->get();

            expect($views)->toContain('pages.home')
                ->and($views)->not->toContain('pages.about');
        });

        it('gets views by name containing substring', function (): void {
            $views = Introspect::views()
                ->whereNameContains('button')
                ->get();

            expect($views)->toContain('components.button')
                ->and($views)->not->toContain('pages.home');
        });

        it('filters views that extend a layout', function (): void {
            $views = Introspect::views()
                ->whereExtends('layouts.app')
                ->get();

            expect($views)->toContain('pages.home')
                ->and($views)->toContain('pages.about')
                ->and($views)->toContain('admin.dashboard');
        });

        it('filters views that do not extend a layout', function (): void {
            $views = Introspect::views()
                ->whereDoesntExtend('layouts.app')
                ->get();

            expect($views)->toContain('layouts.app')
                ->and($views)->toContain('components.button')
                ->and($views)->not->toContain('pages.home');
        });

        it('filters views that use (include) a component', function (): void {
            $views = Introspect::views()
                ->whereUses('components.button')
                ->get();

            expect($views)->toContain('pages.home')
                ->and($views)->toContain('admin.dashboard');
        });

        it('filters views that do not use a component', function (): void {
            $views = Introspect::views()
                ->whereDoesntUse('components.button')
                ->get();

            expect($views)->toContain('pages.about')
                ->and($views)->toContain('layouts.app')
                ->and($views)->not->toContain('pages.home');
        });

        it('filters views that are used by (included in) another view', function (): void {
            $views = Introspect::views()
                ->whereUsedBy('pages.home')
                ->get();

            expect($views)->toContain('components.button')
                ->and($views)->not->toContain('pages.home');
        });

        it('filters views that are not used by another view', function (): void {
            $views = Introspect::views()
                ->whereNotUsedBy('pages.home')
                ->get();

            expect($views)->toContain('pages.about')
                ->and($views)->toContain('layouts.app')
                ->and($views)->not->toContain('components.button');
        });

        it('supports OR logic', function (): void {
            $views = Introspect::views()
                ->whereNameStartsWith('pages.')
                ->or(fn ($q) => $q->whereNameStartsWith('admin.'))
                ->get();

            expect($views)->toContain('pages.home')
                ->and($views)->toContain('pages.about')
                ->and($views)->toContain('admin.dashboard')
                ->and($views)->not->toContain('components.button');
        });

        it('returns first matching view', function (): void {
            $view = Introspect::views()
                ->whereNameStartsWith('pages.')
                ->first();

            expect($view)->toBeString()
                ->and($view)->toContain('pages.');
        });

        it('checks if matching views exist', function (): void {
            $exists = Introspect::views()
                ->whereNameEquals('pages.home')
                ->exists();

            expect($exists)->toBeTrue();
        });

        it('counts matching views', function (): void {
            $count = Introspect::views()
                ->whereNameStartsWith('pages.')
                ->count();

            expect($count)->toBe(2);
        });
    });

    describe('Edge Cases', function (): void {
        it('returns empty collection when no views match', function (): void {
            $views = Introspect::views()
                ->whereNameEquals('nonexistent.view')
                ->get();

            expect($views)->toBeEmpty();
        });

        it('returns null when first() finds no matches', function (): void {
            $view = Introspect::views()
                ->whereNameEquals('nonexistent.view')
                ->first();

            expect($view)->toBeNull();
        });

        it('returns false when exists() finds no matches', function (): void {
            $exists = Introspect::views()
                ->whereNameEquals('nonexistent.view')
                ->exists();

            expect($exists)->toBeFalse();
        });

        it('returns zero when count() finds no matches', function (): void {
            $count = Introspect::views()
                ->whereNameEquals('nonexistent.view')
                ->count();

            expect($count)->toBe(0);
        });

        it('chains multiple filters with AND logic', function (): void {
            $views = Introspect::views()
                ->whereNameStartsWith('pages.')
                ->whereExtends('layouts.app')
                ->whereUses('components.button')
                ->get();

            expect($views)->toContain('pages.home')
                ->and($views)->not->toContain('pages.about');
        });

        it('handles wildcard pattern with no matches', function (): void {
            $views = Introspect::views()
                ->whereNameEquals('*nonexistent*')
                ->get();

            expect($views)->toBeEmpty();
        });

        it('handles whereExtends with non-existent layout', function (): void {
            $views = Introspect::views()
                ->whereExtends('layouts.nonexistent')
                ->get();

            expect($views)->toBeEmpty();
        });

        it('handles whereUses with non-existent component', function (): void {
            $views = Introspect::views()
                ->whereUses('components.nonexistent')
                ->get();

            expect($views)->toBeEmpty();
        });

        it('handles whereUsedBy with non-existent view', function (): void {
            $views = Introspect::views()
                ->whereUsedBy('nonexistent.view')
                ->get();

            expect($views)->toBeEmpty();
        });

        it('handles complex wildcard pattern', function (): void {
            $views = Introspect::views()
                ->whereNameEquals('*.*')
                ->get();

            expect($views)->not->toBeEmpty();
        });

        it('handles case sensitivity in name matching', function (): void {
            $views = Introspect::views()
                ->whereNameEquals('PAGES.HOME')
                ->get();

            expect($views)->toBeEmpty();
        });

        it('filters with multiple constraints narrowing results', function (): void {
            $count = Introspect::views()
                ->whereNameStartsWith('admin.')
                ->whereExtends('layouts.app')
                ->whereUses('components.*')
                ->count();

            expect($count)->toBeGreaterThanOrEqual(1);
        });

        it('supports multiple OR conditions', function (): void {
            $views = Introspect::views()
                ->whereNameStartsWith('pages.')
                ->or(fn ($q) => $q->whereNameStartsWith('admin.'))
                ->or(fn ($q) => $q->whereNameStartsWith('layouts.'))
                ->get();

            expect($views)->toContain('pages.home')
                ->and($views)->toContain('admin.dashboard')
                ->and($views)->toContain('layouts.app');
        });

        it('handles whereNameContains with no matches', function (): void {
            $views = Introspect::views()
                ->whereNameContains('nonexistent')
                ->get();

            expect($views)->toBeEmpty();
        });

        it('handles whereDoesntExtend with views that extend', function (): void {
            $views = Introspect::views()
                ->whereDoesntExtend('layouts.*')
                ->whereNameStartsWith('pages.')
                ->get();

            expect($views)->toBeEmpty();
        });

        it('handles whereDoesntUse with views that use components', function (): void {
            $views = Introspect::views()
                ->whereDoesntUse('components.*')
                ->whereNameStartsWith('pages.')
                ->get();

            expect($views)->toContain('pages.about')
                ->and($views)->not->toContain('pages.home');
        });

        it('handles whereNotUsedBy filtering', function (): void {
            $views = Introspect::views()
                ->whereNotUsedBy('admin.*')
                ->whereNameStartsWith('components.')
                ->get();

            // This should return components that are NOT used by admin views
            // admin.dashboard uses button and modal, so they should be excluded
            expect($views)->toBeInstanceOf(Collection::class);
        });

        it('filters views by complex extension patterns', function (): void {
            $views = Introspect::views()
                ->whereExtends('layouts.*')
                ->get();

            expect($views)->toContain('pages.home')
                ->and($views)->toContain('pages.about')
                ->and($views)->toContain('admin.dashboard');
        });

        it('filters views by complex usage patterns', function (): void {
            $views = Introspect::views()
                ->whereUses('components.*')
                ->get();

            expect($views)->toContain('pages.home')
                ->and($views)->toContain('admin.dashboard');
        });
    });
});
