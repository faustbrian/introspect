<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit;

use Cline\Introspect\Introspect;
use Illuminate\Support\Facades\Route;

use function beforeEach;
use function describe;
use function expect;
use function it;

/**
 * Comprehensive test suite for RoutesIntrospector.
 *
 * Tests the fluent interface query builder including:
 * - Controller filtering (whereUsesController)
 * - Middleware filtering (whereUsesMiddleware, whereDoesntUseMiddleware)
 * - Name filtering (equals, starts with, ends with, contains)
 * - Path filtering (equals, starts with, ends with, contains)
 * - Method filtering (whereUsesMethod)
 * - OR logic support
 * - Result methods (get, first, exists, count)
 * - Edge cases and filter chaining
 */
describe('RoutesIntrospector', function (): void {
    beforeEach(function (): void {
        // Only register test routes if they don't exist
        if ($this->app['router']->has('users.index')) {
            return;
        }

        Route::get('/users', fn (): string => 'users.index')
            ->name('users.index')
            ->middleware('auth');

        Route::post('/users', fn (): string => 'users.store')
            ->name('users.store')
            ->middleware(['auth', 'verified']);

        Route::get('/users/{id}', fn ($id): string => 'users.show: '.$id)
            ->name('users.show')
            ->middleware('auth');

        Route::get('/admin/dashboard', fn (): string => 'admin.dashboard')
            ->name('admin.dashboard')
            ->middleware(['auth', 'admin']);

        Route::get('/public/about', fn (): string => 'public.about')
            ->name('public.about');

        Route::get('/api/v1/users', fn (): string => 'api.v1.users')
            ->name('api.v1.users')
            ->middleware('api');

        // Controller-based routes for testing controller filtering
        Route::get('/products', [RoutesTestProductController::class, 'index'])
            ->name('products.index');

        Route::get('/products/{id}', [RoutesTestProductController::class, 'show'])
            ->name('products.show');

        // Invokable controller route
        Route::get('/health', RoutesTestHealthController::class)
            ->name('health.check');
    });

    describe('Happy Path', function (): void {
        it('gets routes by exact name match', function (): void {
            $routes = Introspect::routes()
                ->whereNameEquals('users.index')
                ->get();

            expect($routes)->toHaveCount(1)
                ->and($routes->first()->getName())->toBe('users.index');
        });

        it('gets routes by wildcard pattern with asterisk prefix', function (): void {
            $routes = Introspect::routes()
                ->whereNameEquals('*.index')
                ->get();

            expect($routes->first()->getName())->toBe('users.index');
        });

        it('gets routes by wildcard pattern with asterisk suffix', function (): void {
            $routes = Introspect::routes()
                ->whereNameEquals('users.*')
                ->get();

            expect($routes)->toHaveCount(3)
                ->and($routes->map(fn ($r) => $r->getName())->all())->toContain('users.index')
                ->and($routes->map(fn ($r) => $r->getName())->all())->toContain('users.store')
                ->and($routes->map(fn ($r) => $r->getName())->all())->toContain('users.show');
        });

        it('gets routes by name prefix', function (): void {
            $routes = Introspect::routes()
                ->whereNameStartsWith('users.')
                ->get();

            expect($routes)->toHaveCount(3);
        });

        it('gets routes by name suffix', function (): void {
            $routes = Introspect::routes()
                ->whereNameEndsWith('.index')
                ->get();

            expect($routes->first()->getName())->toBe('users.index');
        });

        it('filters routes that do not match name pattern', function (): void {
            $routes = Introspect::routes()
                ->whereNameDoesntEqual('users.*')
                ->get();

            $names = $routes->map(fn ($r) => $r->getName())->all();
            expect($names)->not->toContain('users.index')
                ->and($names)->toContain('admin.dashboard')
                ->and($names)->toContain('public.about');
        });

        it('filters routes by middleware', function (): void {
            $routes = Introspect::routes()
                ->whereUsesMiddleware('auth')
                ->get();

            expect($routes)->toHaveCount(4);
        });

        it('filters routes that do not use middleware', function (): void {
            $routes = Introspect::routes()
                ->whereDoesntUseMiddleware('auth')
                ->get();

            expect($routes->map(fn ($r) => $r->getName())->all())->toContain('public.about')
                ->and($routes->map(fn ($r) => $r->getName())->all())->toContain('api.v1.users');
        });

        it('filters routes by multiple middlewares with ALL logic', function (): void {
            $routes = Introspect::routes()
                ->whereUsesMiddlewares(['auth', 'verified'], all: true)
                ->get();

            expect($routes->first()->getName())->toBe('users.store');
        });

        it('filters routes by multiple middlewares with ANY logic', function (): void {
            $routes = Introspect::routes()
                ->whereUsesMiddlewares(['auth', 'api'], all: false)
                ->get();

            expect($routes)->toHaveCount(5);
        });

        it('filters routes by path prefix', function (): void {
            $routes = Introspect::routes()
                ->wherePathStartsWith('/users')
                ->get();

            expect($routes)->toHaveCount(3);
        });

        it('filters routes by path suffix', function (): void {
            $routes = Introspect::routes()
                ->wherePathEndsWith('/about')
                ->get();

            expect($routes->first()->getName())->toBe('public.about');
        });

        it('filters routes by path containing substring', function (): void {
            $routes = Introspect::routes()
                ->wherePathContains('/admin/')
                ->get();

            expect($routes->first()->getName())->toBe('admin.dashboard');
        });

        it('filters routes by exact path', function (): void {
            $routes = Introspect::routes()
                ->wherePathEquals('/users/{id}')
                ->get();

            expect($routes->first()->getName())->toBe('users.show');
        });

        it('filters routes by HTTP method', function (): void {
            $routes = Introspect::routes()
                ->whereUsesMethod('POST')
                ->get();

            expect($routes->first()->getName())->toBe('users.store');
        });

        it('supports OR logic', function (): void {
            $routes = Introspect::routes()
                ->whereNameStartsWith('users.')
                ->or(fn ($q) => $q->whereNameStartsWith('admin.'))
                ->get();

            expect($routes)->toHaveCount(4);
        });

        it('returns first matching route', function (): void {
            $route = Introspect::routes()
                ->whereNameStartsWith('users.')
                ->first();

            expect($route)->not->toBeNull()
                ->and($route->getName())->toContain('users.');
        });

        it('checks if matching routes exist', function (): void {
            $exists = Introspect::routes()
                ->whereNameEquals('users.index')
                ->exists();

            expect($exists)->toBeTrue();
        });

        it('counts matching routes', function (): void {
            $count = Introspect::routes()
                ->whereNameStartsWith('users.')
                ->count();

            expect($count)->toBe(3);
        });
    });

    describe('Edge Cases', function (): void {
        it('returns empty collection when no routes match', function (): void {
            $routes = Introspect::routes()
                ->whereNameEquals('nonexistent.route')
                ->get();

            expect($routes)->toBeEmpty();
        });

        it('returns null when first() finds no matches', function (): void {
            $route = Introspect::routes()
                ->whereNameEquals('nonexistent.route')
                ->first();

            expect($route)->toBeNull();
        });

        it('returns false when exists() finds no matches', function (): void {
            $exists = Introspect::routes()
                ->whereNameEquals('nonexistent.route')
                ->exists();

            expect($exists)->toBeFalse();
        });

        it('returns zero when count() finds no matches', function (): void {
            $count = Introspect::routes()
                ->whereNameEquals('nonexistent.route')
                ->count();

            expect($count)->toBe(0);
        });

        it('chains multiple filters with AND logic', function (): void {
            $routes = Introspect::routes()
                ->whereNameStartsWith('users.')
                ->whereUsesMiddleware('auth')
                ->whereUsesMethod('GET')
                ->get();

            expect($routes)->toHaveCount(2);
        });

        it('handles wildcard pattern with no matches', function (): void {
            $routes = Introspect::routes()
                ->whereNameEquals('*nonexistent*')
                ->get();

            expect($routes)->toBeEmpty();
        });

        it('handles whereUsesMiddleware with non-existent middleware', function (): void {
            $routes = Introspect::routes()
                ->whereUsesMiddleware('nonexistent')
                ->get();

            expect($routes)->toBeEmpty();
        });

        it('handles complex wildcard pattern', function (): void {
            $routes = Introspect::routes()
                ->whereNameEquals('*.*')
                ->get();

            expect($routes)->not->toBeEmpty();
        });

        it('handles case sensitivity in name matching', function (): void {
            $routes = Introspect::routes()
                ->whereNameEquals('USERS.INDEX')
                ->get();

            expect($routes)->toBeEmpty();
        });

        it('filters with multiple constraints narrowing results', function (): void {
            $count = Introspect::routes()
                ->whereNameStartsWith('users.')
                ->whereUsesMiddleware('auth')
                ->wherePathStartsWith('/users')
                ->count();

            expect($count)->toBe(3);
        });

        it('supports multiple OR conditions', function (): void {
            $routes = Introspect::routes()
                ->whereNameStartsWith('users.')
                ->or(fn ($q) => $q->whereNameStartsWith('admin.'))
                ->or(fn ($q) => $q->whereNameStartsWith('public.'))
                ->get();

            expect($routes)->toHaveCount(5);
        });

        it('handles wherePathContains with no matches', function (): void {
            $routes = Introspect::routes()
                ->wherePathContains('/nonexistent/')
                ->get();

            expect($routes)->toBeEmpty();
        });

        it('handles whereUsesMethod case insensitivity', function (): void {
            $routes = Introspect::routes()
                ->whereUsesMethod('post')
                ->get();

            expect($routes)->toHaveCount(1);
        });

        it('handles wherePathStartsWith with wildcard', function (): void {
            $routes = Introspect::routes()
                ->wherePathStartsWith('/api/*')
                ->get();

            expect($routes)->toHaveCount(1);
        });

        it('handles whereDoesntUseMiddleware with routes that have middleware', function (): void {
            $routes = Introspect::routes()
                ->whereDoesntUseMiddleware('auth')
                ->whereNameStartsWith('users.')
                ->get();

            expect($routes)->toBeEmpty();
        });

        it('handles whereUsesMiddlewares with all=true and partial matches', function (): void {
            $routes = Introspect::routes()
                ->whereUsesMiddlewares(['auth', 'nonexistent'], all: true)
                ->get();

            expect($routes)->toBeEmpty();
        });

        it('handles whereUsesMiddlewares with all=false', function (): void {
            $routes = Introspect::routes()
                ->whereUsesMiddlewares(['verified', 'admin'], all: false)
                ->get();

            expect($routes)->toHaveCount(2);
        });

        it('filters by path with parameter patterns', function (): void {
            $routes = Introspect::routes()
                ->wherePathContains('{id}')
                ->get();

            // Multiple routes may have {id} parameter
            expect($routes)->not->toBeEmpty()
                ->and($routes->map(fn ($r) => $r->getName())->all())->toContain('users.show');
        });

        it('handles whereNameDoesntEqual with wildcard', function (): void {
            $routes = Introspect::routes()
                ->whereNameDoesntEqual('*admin*')
                ->get();

            expect($routes->map(fn ($r) => $r->getName())->all())->not->toContain('admin.dashboard')
                ->and($routes->map(fn ($r) => $r->getName())->all())->toContain('users.index');
        });

        it('combines path and name filters', function (): void {
            $routes = Introspect::routes()
                ->wherePathStartsWith('/api')
                ->whereNameStartsWith('api.')
                ->get();

            expect($routes)->toHaveCount(1);
        });

        it('handles middleware with parameters', function (): void {
            Route::get('/throttled', fn (): string => 'throttled')
                ->middleware('throttle:60,1');

            $routes = Introspect::routes()
                ->whereUsesMiddleware('throttle')
                ->get();

            expect($routes)->toHaveCount(1);
        });

        it('filters routes by controller class', function (): void {
            $routes = Introspect::routes()
                ->whereUsesController(RoutesTestProductController::class)
                ->get();

            expect($routes)->toHaveCount(2)
                ->and($routes->map(fn ($r) => $r->getName())->all())->toContain('products.index')
                ->and($routes->map(fn ($r) => $r->getName())->all())->toContain('products.show');
        });

        it('filters routes by controller class and method', function (): void {
            $routes = Introspect::routes()
                ->whereUsesController(RoutesTestProductController::class, 'index')
                ->get();

            expect($routes)->toHaveCount(1)
                ->and($routes->first()->getName())->toBe('products.index');
        });

        it('returns empty when controller method does not match', function (): void {
            $routes = Introspect::routes()
                ->whereUsesController(RoutesTestProductController::class, 'nonexistent')
                ->get();

            expect($routes)->toBeEmpty();
        });

        it('returns empty when controller class does not match', function (): void {
            $routes = Introspect::routes()
                ->whereUsesController('NonExistentController')
                ->get();

            expect($routes)->toBeEmpty();
        });

        it('handles OR logic with only OR filters', function (): void {
            $routes = Introspect::routes()
                ->or(fn ($q) => $q->whereNameEquals('products.index'))
                ->or(fn ($q) => $q->whereNameEquals('health.check'))
                ->get();

            expect($routes)->toHaveCount(2);
        });
    });
});

// Test fixture controllers

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class RoutesTestProductController
{
    public function index(): string
    {
        return 'products.index';
    }

    public function show(int $id): string
    {
        return 'products.show: '.$id;
    }
}

/**
 * Invokable controller for testing.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class RoutesTestHealthController
{
    public function __invoke(): string
    {
        return 'health.ok';
    }
}
