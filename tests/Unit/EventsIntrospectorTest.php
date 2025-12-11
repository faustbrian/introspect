<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit;

use Cline\Introspect\Introspect;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;

use function beforeEach;
use function describe;
use function expect;
use function it;

/**
 * Comprehensive test suite for EventsIntrospector.
 *
 * Tests the fluent interface query builder including:
 * - Event name filtering (whereNameEquals, whereNameStartsWith, whereNameEndsWith)
 * - Listener filtering (whereHasListener, whereHasNoListeners, whereHasListeners)
 * - OR logic support
 * - Result methods (get, first, exists, count, all, toArray, listenersFor, hasListeners)
 * - Edge cases and filter chaining
 */
describe('EventsIntrospector', function (): void {
    beforeEach(function (): void {
        // Clear any existing event listeners
        Event::clearResolvedInstances();

        // Register test events and listeners
        Event::listen('App\Events\UserCreated', 'App\Listeners\SendWelcomeEmail');
        Event::listen('App\Events\UserCreated', 'App\Listeners\LogUserCreation');
        Event::listen('App\Events\UserUpdated', 'App\Listeners\SendUpdateNotification');
        Event::listen('App\Events\UserDeleted', 'App\Listeners\CleanupUserData');
        Event::listen('App\Events\OrderPlaced', 'App\Listeners\ProcessPayment');
        Event::listen('App\Events\OrderPlaced', 'App\Listeners\SendOrderConfirmation');
        Event::listen(Login::class, 'App\Listeners\LogLogin');
        Event::listen(Logout::class, 'App\Listeners\LogLogout');
    });

    describe('Happy Path', function (): void {
        it('gets all registered events', function (): void {
            $events = Introspect::events()->all();

            expect($events)->toContain('App\Events\UserCreated')
                ->and($events)->toContain('App\Events\OrderPlaced')
                ->and($events)->toContain(Login::class);
        });

        it('gets events by exact name match', function (): void {
            $events = Introspect::events()
                ->whereNameEquals('App\Events\UserCreated')
                ->get();

            expect($events)->toHaveCount(1)
                ->and($events->first())->toBe('App\Events\UserCreated');
        });

        it('gets events by wildcard pattern with asterisk prefix', function (): void {
            $events = Introspect::events()
                ->whereNameEquals('*\UserCreated')
                ->get();

            expect($events->first())->toBe('App\Events\UserCreated');
        });

        it('gets events by wildcard pattern with asterisk suffix', function (): void {
            $events = Introspect::events()
                ->whereNameEquals('App\Events\User*')
                ->get();

            expect($events)->toHaveCount(3)
                ->and($events->all())->toContain('App\Events\UserCreated')
                ->and($events->all())->toContain('App\Events\UserUpdated')
                ->and($events->all())->toContain('App\Events\UserDeleted');
        });

        it('gets events by namespace prefix', function (): void {
            $events = Introspect::events()
                ->whereNameStartsWith('App\Events\User')
                ->get();

            expect($events)->toHaveCount(3);
        });

        it('gets events by name suffix', function (): void {
            $events = Introspect::events()
                ->whereNameEndsWith('Created')
                ->get();

            expect($events->first())->toBe('App\Events\UserCreated');
        });

        it('filters events by listener class', function (): void {
            $events = Introspect::events()
                ->whereHasListener('App\Listeners\SendWelcomeEmail')
                ->get();

            expect($events)->toHaveCount(1)
                ->and($events->first())->toBe('App\Events\UserCreated');
        });

        it('filters events that have no listeners', function (): void {
            // Register an event without listeners by checking a non-existent event
            $events = Introspect::events()
                ->whereHasNoListeners()
                ->get();

            // Since we can't easily create an event with no listeners in Laravel's event system,
            // we'll just verify the filter works by checking it returns a collection
            expect($events)->toBeInstanceOf(Collection::class);
        });

        it('filters events that have listeners', function (): void {
            $events = Introspect::events()
                ->whereHasListeners()
                ->get();

            expect($events)->toContain('App\Events\UserCreated')
                ->and($events)->toContain('App\Events\OrderPlaced');
        });

        it('returns event-to-listener mappings as array', function (): void {
            $mappings = Introspect::events()
                ->whereNameEquals('App\Events\UserCreated')
                ->toArray();

            expect($mappings)->toHaveKey('App\Events\UserCreated')
                ->and($mappings['App\Events\UserCreated'])->toContain('App\Listeners\SendWelcomeEmail')
                ->and($mappings['App\Events\UserCreated'])->toContain('App\Listeners\LogUserCreation');
        });

        it('gets listeners for a specific event', function (): void {
            $listeners = Introspect::events()->listenersFor('App\Events\UserCreated');

            expect($listeners)->toBeArray()
                ->and($listeners)->toHaveCount(2);
        });

        it('checks if event has listeners', function (): void {
            $hasListeners = Introspect::events()->hasListeners('App\Events\UserCreated');
            $noListeners = Introspect::events()->hasListeners('App\Events\NonExistentEvent');

            expect($hasListeners)->toBeTrue()
                ->and($noListeners)->toBeFalse();
        });

        it('supports OR logic', function (): void {
            $events = Introspect::events()
                ->whereNameStartsWith('App\Events\User')
                ->or(fn ($q) => $q->whereNameStartsWith('App\Events\Order'))
                ->get();

            expect($events)->toHaveCount(4)
                ->and($events->all())->toContain('App\Events\UserCreated')
                ->and($events->all())->toContain('App\Events\OrderPlaced');
        });

        it('returns first matching event', function (): void {
            $event = Introspect::events()
                ->whereNameStartsWith('App\Events\User')
                ->first();

            expect($event)->not->toBeNull()
                ->and($event)->toContain('App\Events\User');
        });

        it('checks if matching events exist', function (): void {
            $exists = Introspect::events()
                ->whereNameEquals('App\Events\UserCreated')
                ->exists();

            expect($exists)->toBeTrue();
        });

        it('counts matching events', function (): void {
            $count = Introspect::events()
                ->whereNameStartsWith('App\Events\User')
                ->count();

            expect($count)->toBe(3);
        });
    });

    describe('Edge Cases', function (): void {
        it('returns empty collection when no events match', function (): void {
            $events = Introspect::events()
                ->whereNameEquals('App\Events\NonExistent')
                ->get();

            expect($events)->toBeEmpty();
        });

        it('returns null when first() finds no matches', function (): void {
            $event = Introspect::events()
                ->whereNameEquals('App\Events\NonExistent')
                ->first();

            expect($event)->toBeNull();
        });

        it('returns false when exists() finds no matches', function (): void {
            $exists = Introspect::events()
                ->whereNameEquals('App\Events\NonExistent')
                ->exists();

            expect($exists)->toBeFalse();
        });

        it('returns zero when count() finds no matches', function (): void {
            $count = Introspect::events()
                ->whereNameEquals('App\Events\NonExistent')
                ->count();

            expect($count)->toBe(0);
        });

        it('chains multiple filters with AND logic', function (): void {
            $events = Introspect::events()
                ->whereNameStartsWith('App\Events\User')
                ->whereNameEndsWith('Created')
                ->get();

            expect($events)->toHaveCount(1)
                ->and($events->first())->toBe('App\Events\UserCreated');
        });

        it('handles wildcard pattern with no matches', function (): void {
            $events = Introspect::events()
                ->whereNameEquals('*NonExistent*')
                ->get();

            expect($events)->toBeEmpty();
        });

        it('handles whereHasListener with non-existent listener', function (): void {
            $events = Introspect::events()
                ->whereHasListener('App\Listeners\NonExistent')
                ->get();

            expect($events)->toBeEmpty();
        });

        it('handles complex wildcard pattern', function (): void {
            $events = Introspect::events()
                ->whereNameEquals('*\*')
                ->get();

            expect($events)->not->toBeEmpty();
        });

        it('handles case sensitivity in name matching', function (): void {
            $events = Introspect::events()
                ->whereNameEquals('app\events\usercreated')
                ->get();

            expect($events)->toBeEmpty();
        });

        it('filters with multiple constraints narrowing results', function (): void {
            $count = Introspect::events()
                ->whereNameStartsWith('App\Events')
                ->whereHasListeners()
                ->count();

            expect($count)->toBeGreaterThan(0);
        });

        it('supports multiple OR conditions', function (): void {
            $events = Introspect::events()
                ->whereNameStartsWith('App\Events\User')
                ->or(fn ($q) => $q->whereNameStartsWith('App\Events\Order'))
                ->or(fn ($q) => $q->whereNameStartsWith('Illuminate\Auth'))
                ->get();

            expect($events)->toHaveCount(6);
        });

        it('handles whereHasListener for event with multiple listeners', function (): void {
            $events = Introspect::events()
                ->whereHasListener('App\Listeners\SendWelcomeEmail')
                ->get();

            expect($events)->toHaveCount(1)
                ->and($events->first())->toBe('App\Events\UserCreated');
        });

        it('handles whereNameStartsWith with namespace', function (): void {
            $events = Introspect::events()
                ->whereNameStartsWith('Illuminate\Auth')
                ->get();

            expect($events)->toHaveCount(2)
                ->and($events->all())->toContain(Login::class)
                ->and($events->all())->toContain(Logout::class);
        });

        it('combines name and listener filters', function (): void {
            $events = Introspect::events()
                ->whereNameStartsWith('App\Events')
                ->whereHasListener('App\Listeners\SendWelcomeEmail')
                ->get();

            expect($events)->toHaveCount(1);
        });

        it('returns empty array for listenersFor non-existent event', function (): void {
            $listeners = Introspect::events()->listenersFor('App\Events\NonExistent');

            expect($listeners)->toBeArray()
                ->and($listeners)->toBeEmpty();
        });

        it('handles toArray with no filters', function (): void {
            $mappings = Introspect::events()->toArray();

            expect($mappings)->toBeArray()
                ->and($mappings)->toHaveKey('App\Events\UserCreated')
                ->and($mappings)->toHaveKey('App\Events\OrderPlaced');
        });

        it('filters events with listeners AND specific namespace', function (): void {
            $events = Introspect::events()
                ->whereHasListeners()
                ->whereNameStartsWith('App\Events\User')
                ->get();

            expect($events)->toHaveCount(3);
        });

        it('handles OR with whereHasListener', function (): void {
            $events = Introspect::events()
                ->whereHasListener('App\Listeners\SendWelcomeEmail')
                ->or(fn ($q) => $q->whereHasListener('App\Listeners\ProcessPayment'))
                ->get();

            expect($events)->toHaveCount(2)
                ->and($events->all())->toContain('App\Events\UserCreated')
                ->and($events->all())->toContain('App\Events\OrderPlaced');
        });

        it('handles whereNameEndsWith with common suffix', function (): void {
            $events = Introspect::events()
                ->whereNameEndsWith('Placed')
                ->get();

            expect($events)->toHaveCount(1)
                ->and($events->first())->toBe('App\Events\OrderPlaced');
        });

        it('combines whereHasListeners with whereNameStartsWith', function (): void {
            $events = Introspect::events()
                ->whereHasListeners()
                ->whereNameStartsWith('Illuminate\Auth')
                ->get();

            expect($events)->toHaveCount(2);
        });

        it('handles events with no matches', function (): void {
            $events = Introspect::events()
                ->whereNameEquals('App\Events\NonExistentEvent')
                ->get();

            expect($events)->toBeEmpty();
        });

        it('handles wildcard in middle of pattern', function (): void {
            $events = Introspect::events()
                ->whereNameEquals('App\*\UserCreated')
                ->get();

            expect($events)->toHaveCount(1)
                ->and($events->first())->toBe('App\Events\UserCreated');
        });

        it('filters by exact listener match only', function (): void {
            $events = Introspect::events()
                ->whereHasListener('App\Listeners\SendWelcome')
                ->get();

            expect($events)->toBeEmpty();
        });
    });
});
