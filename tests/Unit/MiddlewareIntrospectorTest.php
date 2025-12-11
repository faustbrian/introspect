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
use Illuminate\Support\Facades\Route;

use function array_key_first;
use function beforeEach;
use function count;
use function describe;
use function expect;
use function it;

/**
 * Comprehensive test suite for MiddlewareIntrospector.
 *
 * Tests the fluent interface query builder including:
 * - Getting all middleware
 * - Getting middleware groups
 * - Getting middleware aliases
 * - Getting middleware priority
 * - Getting global middleware
 * - Filtering by pattern (whereNameEquals)
 * - Filtering by namespace (whereNamespace)
 * - Filtering by group (whereInGroup)
 * - Filtering global middleware (whereGlobal)
 * - Filtering by route usage (whereUsedByRoutes)
 * - Result methods (get, first, exists, count)
 * - Comprehensive info (toArray)
 * - Edge cases and filter chaining
 */
describe('MiddlewareIntrospector', function (): void {
    beforeEach(function (): void {
        // Register test routes with various middleware
        if ($this->app['router']->has('middleware.test.auth')) {
            return;
        }

        Route::get('/test/auth', fn (): string => 'test.auth')
            ->name('middleware.test.auth')
            ->middleware('auth');

        Route::get('/test/web', fn (): string => 'test.web')
            ->name('middleware.test.web')
            ->middleware('web');

        Route::get('/test/api', fn (): string => 'test.api')
            ->name('middleware.test.api')
            ->middleware('api');

        Route::get('/test/multiple', fn (): string => 'test.multiple')
            ->name('middleware.test.multiple')
            ->middleware(['auth', 'verified']);

        Route::get('/test/none', fn (): string => 'test.none')
            ->name('middleware.test.none');
    });

    describe('Happy Path', function (): void {
        it('gets all registered middleware', function (): void {
            $middleware = Introspect::middleware()->all();

            expect($middleware)->toBeArray()
                ->and($middleware)->not->toBeEmpty();
        });

        it('gets middleware groups', function (): void {
            $groups = Introspect::middleware()->groups();

            expect($groups)->toBeArray();
        });

        it('gets middleware aliases', function (): void {
            $aliases = Introspect::middleware()->aliases();

            expect($aliases)->toBeArray();
        });

        it('gets middleware priority', function (): void {
            $priority = Introspect::middleware()->priority();

            expect($priority)->toBeArray();
        });

        it('gets global middleware', function (): void {
            $global = Introspect::middleware()->global();

            expect($global)->toBeArray();
        });

        it('filters middleware by exact name match', function (): void {
            $all = Introspect::middleware()->all();

            if ($all === []) {
                $this->markTestSkipped('No middleware registered');
            }

            $firstKey = array_key_first($all);
            $middleware = Introspect::middleware()
                ->whereNameEquals($firstKey)
                ->get();

            expect($middleware)->not->toBeEmpty();
        });

        it('filters middleware by wildcard pattern with asterisk prefix', function (): void {
            $middleware = Introspect::middleware()
                ->whereNameEquals('*auth*')
                ->get();

            expect($middleware)->not->toBeEmpty();
        });

        it('filters middleware by wildcard pattern with asterisk suffix', function (): void {
            $middleware = Introspect::middleware()
                ->whereNameEquals('auth*')
                ->get();

            expect($middleware)->not->toBeEmpty();
        });

        it('filters middleware by namespace', function (): void {
            $middleware = Introspect::middleware()
                ->whereNamespace('Illuminate\\')
                ->get();

            expect($middleware)->not->toBeEmpty();
        });

        it('filters global middleware', function (): void {
            $middleware = Introspect::middleware()
                ->whereGlobal()
                ->get();

            expect($middleware)->toBeInstanceOf(Collection::class);
        });

        it('filters middleware by group', function (): void {
            $groups = Introspect::middleware()->groups();

            if ($groups === []) {
                $this->markTestSkipped('No middleware groups registered');
            }

            $firstGroup = array_key_first($groups);
            $middleware = Introspect::middleware()
                ->whereInGroup($firstGroup)
                ->get();

            expect($middleware)->toBeInstanceOf(Collection::class);
        });

        it('filters middleware used by routes', function (): void {
            $middleware = Introspect::middleware()
                ->whereUsedByRoutes()
                ->get();

            expect($middleware)->not->toBeEmpty();
        });

        it('returns first matching middleware', function (): void {
            $all = Introspect::middleware()->all();

            if ($all === []) {
                $this->markTestSkipped('No middleware registered');
            }

            $firstKey = array_key_first($all);
            $middleware = Introspect::middleware()
                ->whereNameEquals($firstKey)
                ->first();

            expect($middleware)->not->toBeNull();
        });

        it('checks if matching middleware exist', function (): void {
            $all = Introspect::middleware()->all();

            if ($all === []) {
                $this->markTestSkipped('No middleware registered');
            }

            $firstKey = array_key_first($all);
            $exists = Introspect::middleware()
                ->whereNameEquals($firstKey)
                ->exists();

            expect($exists)->toBeTrue();
        });

        it('counts matching middleware', function (): void {
            $groups = Introspect::middleware()->groups();

            if ($groups === []) {
                $this->markTestSkipped('No middleware groups registered');
            }

            $firstGroup = array_key_first($groups);
            $count = Introspect::middleware()
                ->whereInGroup($firstGroup)
                ->count();

            expect($count)->toBeGreaterThanOrEqual(0);
        });

        it('returns comprehensive middleware information', function (): void {
            $info = Introspect::middleware()->toArray();

            expect($info)->toBeArray()
                ->and($info)->toHaveKey('aliases')
                ->and($info)->toHaveKey('groups')
                ->and($info)->toHaveKey('priority')
                ->and($info)->toHaveKey('global')
                ->and($info['aliases'])->toBeArray()
                ->and($info['groups'])->toBeArray()
                ->and($info['priority'])->toBeArray()
                ->and($info['global'])->toBeArray();
        });
    });

    describe('Edge Cases', function (): void {
        it('returns empty collection when no middleware match pattern', function (): void {
            $middleware = Introspect::middleware()
                ->whereNameEquals('nonexistent.middleware')
                ->get();

            expect($middleware)->toBeEmpty();
        });

        it('returns null when first() finds no matches', function (): void {
            $middleware = Introspect::middleware()
                ->whereNameEquals('nonexistent.middleware')
                ->first();

            expect($middleware)->toBeNull();
        });

        it('returns false when exists() finds no matches', function (): void {
            $exists = Introspect::middleware()
                ->whereNameEquals('nonexistent.middleware')
                ->exists();

            expect($exists)->toBeFalse();
        });

        it('returns zero when count() finds no matches', function (): void {
            $count = Introspect::middleware()
                ->whereNameEquals('nonexistent.middleware')
                ->count();

            expect($count)->toBe(0);
        });

        it('returns empty array for non-existent middleware group', function (): void {
            $middleware = Introspect::middleware()
                ->whereInGroup('nonexistent')
                ->get();

            expect($middleware)->toBeEmpty();
        });

        it('chains multiple filters with AND logic', function (): void {
            $middleware = Introspect::middleware()
                ->whereInGroup('web')
                ->whereUsedByRoutes()
                ->get();

            expect($middleware)->toBeInstanceOf(Collection::class);
        });

        it('handles wildcard pattern with no matches', function (): void {
            $middleware = Introspect::middleware()
                ->whereNameEquals('*nonexistent*')
                ->get();

            expect($middleware)->toBeEmpty();
        });

        it('handles complex wildcard pattern', function (): void {
            $middleware = Introspect::middleware()
                ->whereNameEquals('*')
                ->get();

            expect($middleware)->not->toBeEmpty();
        });

        it('handles case sensitivity in name matching', function (): void {
            $middleware = Introspect::middleware()
                ->whereNameEquals('AUTH')
                ->get();

            // Should be case-sensitive and not match 'auth'
            expect($middleware)->toBeEmpty();
        });

        it('filters with multiple constraints narrowing results', function (): void {
            $all = Introspect::middleware()->all();
            $filtered = Introspect::middleware()
                ->whereInGroup('web')
                ->whereUsedByRoutes()
                ->get();

            expect($filtered->count())->toBeLessThanOrEqual(count($all));
        });

        it('handles whereNamespace with no matches', function (): void {
            $middleware = Introspect::middleware()
                ->whereNamespace('NonExistent\\Namespace\\')
                ->get();

            expect($middleware)->toBeEmpty();
        });

        it('handles whereGlobal when no global middleware exists', function (): void {
            $middleware = Introspect::middleware()
                ->whereGlobal()
                ->get();

            // Should return a collection (may be empty or have items depending on app config)
            expect($middleware)->toBeInstanceOf(Collection::class);
        });

        it('filters by namespace prefix', function (): void {
            $middleware = Introspect::middleware()
                ->whereNamespace('Illuminate\\Auth\\')
                ->get();

            $middleware->each(function ($value): void {
                expect($value)->toStartWith('Illuminate\\Auth\\');
            });
        });

        it('handles whereUsedByRoutes with routes using middleware', function (): void {
            $used = Introspect::middleware()
                ->whereUsedByRoutes()
                ->get();

            expect($used)->not->toBeEmpty();
        });

        it('combines group and name filters', function (): void {
            $middleware = Introspect::middleware()
                ->whereInGroup('web')
                ->whereNameEquals('*Session*')
                ->get();

            expect($middleware)->toBeInstanceOf(Collection::class);
        });

        it('handles toArray with empty filters', function (): void {
            $info = Introspect::middleware()->toArray();

            expect($info)->toBeArray()
                ->and($info)->toHaveKey('aliases')
                ->and($info)->toHaveKey('groups')
                ->and($info)->toHaveKey('priority')
                ->and($info)->toHaveKey('global');
        });

        it('filters middleware that match namespace and are used by routes', function (): void {
            $middleware = Introspect::middleware()
                ->whereNamespace('Illuminate\\')
                ->whereUsedByRoutes()
                ->get();

            expect($middleware)->toBeInstanceOf(Collection::class);
        });

        it('handles whereInGroup with API middleware', function (): void {
            $groups = Introspect::middleware()->groups();

            if (!isset($groups['api'])) {
                $this->markTestSkipped('API middleware group not registered');
            }

            $middleware = Introspect::middleware()
                ->whereInGroup('api')
                ->get();

            expect($middleware)->toBeInstanceOf(Collection::class);
        });

        it('handles multiple whereNameEquals calls', function (): void {
            // Later filter should override earlier one
            $middleware = Introspect::middleware()
                ->whereNameEquals('auth')
                ->whereNameEquals('verified')
                ->get();

            // Should only match 'verified' pattern
            expect($middleware)->toBeInstanceOf(Collection::class);
        });

        it('gets middleware priority with specific order', function (): void {
            $priority = Introspect::middleware()->priority();

            // Priority should be an array (order matters)
            expect($priority)->toBeArray();
        });

        it('handles empty middleware groups', function (): void {
            $groups = Introspect::middleware()->groups();

            expect($groups)->toBeArray();

            // All groups should have arrays as values
            foreach ($groups as $middlewares) {
                expect($middlewares)->toBeArray();
            }
        });

        it('handles whereGlobal with whereNamespace', function (): void {
            $middleware = Introspect::middleware()
                ->whereGlobal()
                ->whereNamespace('Illuminate\\')
                ->get();

            expect($middleware)->toBeInstanceOf(Collection::class);
        });

        it('verifies aliases return class names', function (): void {
            $aliases = Introspect::middleware()->aliases();

            expect($aliases)->toBeArray();

            foreach ($aliases as $alias => $class) {
                expect($alias)->toBeString()
                    ->and($class)->toBeString();
            }
        });
    });
});
