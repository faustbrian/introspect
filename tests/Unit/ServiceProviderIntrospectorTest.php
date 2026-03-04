<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit;

use Cline\Introspect\Introspect;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Foundation\Providers\FoundationServiceProvider;
use Illuminate\Support\Collection;
use Illuminate\View\ViewServiceProvider;

use function describe;
use function expect;
use function it;

/**
 * Comprehensive test suite for ServiceProviderIntrospector.
 *
 * Tests the fluent interface query builder including:
 * - Retrieving all registered providers
 * - Name filtering (equals with wildcards)
 * - Deferred vs eager provider filtering
 * - Filtering by provided services
 * - Registration status checks
 * - Getting provider information (toArray)
 * - OR logic support
 * - Result methods (get, first, exists, count)
 * - Edge cases and filter chaining
 */
describe('ServiceProviderIntrospector', function (): void {
    describe('Happy Path', function (): void {
        it('gets all registered service providers', function (): void {
            $providers = Introspect::providers()->all();

            expect($providers)->toBeInstanceOf(Collection::class)
                ->and($providers->count())->toBeGreaterThan(0);
        });

        it('filters providers by exact name match', function (): void {
            $providers = Introspect::providers()
                ->whereNameEquals(ViewServiceProvider::class)
                ->get();

            expect($providers)->toContain(ViewServiceProvider::class)
                ->and($providers)->toHaveCount(1);
        });

        it('filters providers by wildcard pattern with asterisk prefix', function (): void {
            $providers = Introspect::providers()
                ->whereNameEquals('*ServiceProvider')
                ->get();

            expect($providers)->toContain(ViewServiceProvider::class)
                ->and($providers)->toContain(DatabaseServiceProvider::class)
                ->and($providers->count())->toBeGreaterThan(1);
        });

        it('filters providers by wildcard pattern with asterisk suffix', function (): void {
            $providers = Introspect::providers()
                ->whereNameEquals('Illuminate\View\*')
                ->get();

            expect($providers)->toContain(ViewServiceProvider::class);
        });

        it('filters providers by wildcard pattern with asterisk in middle', function (): void {
            $providers = Introspect::providers()
                ->whereNameEquals('Illuminate\*\ViewServiceProvider')
                ->get();

            expect($providers)->toContain(ViewServiceProvider::class);
        });

        it('filters providers by namespace pattern', function (): void {
            $providers = Introspect::providers()
                ->whereNameEquals('Illuminate\Foundation\*')
                ->get();

            expect($providers)->toContain(FoundationServiceProvider::class);
        });

        it('filters to only deferred service providers', function (): void {
            $providers = Introspect::providers()
                ->whereDeferred()
                ->get();

            // Deferred providers are loaded on-demand
            // Common deferred providers: Auth, Broadcasting, Mail, Queue, etc.
            foreach ($providers as $provider) {
                expect(Introspect::providers()->toArray()[$provider]['deferred'])->toBeTrue();
            }
        });

        it('filters to only eager service providers', function (): void {
            $providers = Introspect::providers()
                ->whereEager()
                ->get();

            // Eager providers are loaded immediately
            // Common eager providers: Foundation, Filesystem, etc.
            foreach ($providers as $provider) {
                expect(Introspect::providers()->toArray()[$provider]['deferred'])->toBeFalse();
            }
        });

        it('checks if a specific provider is registered', function (): void {
            $isRegistered = Introspect::providers()
                ->isRegistered(ViewServiceProvider::class);

            expect($isRegistered)->toBeTrue();
        });

        it('checks if a non-existent provider is not registered', function (): void {
            $isRegistered = Introspect::providers()
                ->isRegistered('App\Providers\NonExistentProvider');

            expect($isRegistered)->toBeFalse();
        });

        it('gets all provider information as array', function (): void {
            $info = Introspect::providers()->toArray();

            expect($info)->toBeArray()
                ->and($info)->not->toBeEmpty();

            foreach ($info as $providerClass => $data) {
                expect($data)->toHaveKeys(['class', 'deferred', 'provides'])
                    ->and($data['class'])->toBe($providerClass)
                    ->and($data['deferred'])->toBeIn([true, false])
                    ->and($data['provides'])->toBeArray();
            }
        });

        it('gets deferred services mapping', function (): void {
            $services = Introspect::providers()->getDeferredServices();

            expect($services)->toBeArray();

            // Check structure: service => provider
            foreach ($services as $service => $provider) {
                expect($service)->toBeString()
                    ->and($provider)->toBeString();
            }
        });

        it('returns first matching provider', function (): void {
            $provider = Introspect::providers()
                ->whereNameEquals('Illuminate\View\*')
                ->first();

            expect($provider)->toBeString()
                ->and($provider)->toBe(ViewServiceProvider::class);
        });

        it('checks if matching providers exist', function (): void {
            $exists = Introspect::providers()
                ->whereNameEquals(ViewServiceProvider::class)
                ->exists();

            expect($exists)->toBeTrue();
        });

        it('counts matching providers', function (): void {
            $count = Introspect::providers()
                ->whereNameEquals('Illuminate\*')
                ->count();

            expect($count)->toBeGreaterThan(10);
        });

        it('supports OR logic for filtering', function (): void {
            $providers = Introspect::providers()
                ->whereNameEquals(ViewServiceProvider::class)
                ->or(fn ($q) => $q->whereNameEquals(DatabaseServiceProvider::class))
                ->get();

            expect($providers)->toContain(ViewServiceProvider::class)
                ->and($providers)->toContain(DatabaseServiceProvider::class);
        });

        it('gets providers from specific namespace', function (): void {
            $providers = Introspect::providers()
                ->whereNameEquals('Illuminate\Database\*')
                ->get();

            expect($providers)->toContain(DatabaseServiceProvider::class);
        });

        it('combines multiple filters', function (): void {
            // Get Laravel providers that are eager
            $providers = Introspect::providers()
                ->whereNameEquals('Illuminate\*')
                ->whereEager()
                ->get();

            expect($providers)->not->toBeEmpty();

            foreach ($providers as $provider) {
                expect($provider)->toStartWith('Illuminate\\')
                    ->and(Introspect::providers()->toArray()[$provider]['deferred'])->toBeFalse();
            }
        });
    });

    describe('Edge Cases', function (): void {
        it('returns empty collection when no providers match', function (): void {
            $providers = Introspect::providers()
                ->whereNameEquals('NonExistent\Provider\Class')
                ->get();

            expect($providers)->toBeEmpty();
        });

        it('returns null when first() finds no matches', function (): void {
            $provider = Introspect::providers()
                ->whereNameEquals('NonExistent\Provider\Class')
                ->first();

            expect($provider)->toBeNull();
        });

        it('returns false when exists() finds no matches', function (): void {
            $exists = Introspect::providers()
                ->whereNameEquals('NonExistent\Provider\Class')
                ->exists();

            expect($exists)->toBeFalse();
        });

        it('returns zero when count() finds no matches', function (): void {
            $count = Introspect::providers()
                ->whereNameEquals('NonExistent\Provider\Class')
                ->count();

            expect($count)->toBe(0);
        });

        it('handles wildcard pattern with no matches', function (): void {
            $providers = Introspect::providers()
                ->whereNameEquals('*NonExistent*')
                ->get();

            expect($providers)->toBeEmpty();
        });

        it('handles case sensitivity in name matching', function (): void {
            $providers = Introspect::providers()
                ->whereNameEquals('illuminate\view\viewserviceprovider')
                ->get();

            expect($providers)->toBeEmpty();
        });

        it('handles whereDeferred with no deferred providers in result set', function (): void {
            // Try to find deferred providers in a namespace that only has eager ones
            $providers = Introspect::providers()
                ->whereNameEquals('Illuminate\Foundation\*')
                ->whereDeferred()
                ->get();

            // FoundationServiceProvider is typically eager
            expect($providers)->not->toContain(FoundationServiceProvider::class);
        });

        it('handles whereEager with no eager providers in result set', function (): void {
            // Get a known deferred provider
            $deferredProviders = Introspect::providers()
                ->whereDeferred()
                ->get();

            if (!$deferredProviders->isNotEmpty()) {
                return;
            }

            $firstDeferred = $deferredProviders->first();

            $providers = Introspect::providers()
                ->whereNameEquals($firstDeferred)
                ->whereEager()
                ->get();

            expect($providers)->toBeEmpty();
        });

        it('handles whereProvides for non-existent service', function (): void {
            $providers = Introspect::providers()
                ->whereProvides('NonExistentService')
                ->get();

            expect($providers)->toBeEmpty();
        });

        it('handles complex wildcard pattern', function (): void {
            $providers = Introspect::providers()
                ->whereNameEquals('Illuminate\*\*ServiceProvider')
                ->get();

            expect($providers)->not->toBeEmpty();
        });

        it('handles multiple OR conditions', function (): void {
            $providers = Introspect::providers()
                ->whereNameEquals(ViewServiceProvider::class)
                ->or(fn ($q) => $q->whereNameEquals(DatabaseServiceProvider::class))
                ->or(fn ($q) => $q->whereNameEquals(CacheServiceProvider::class))
                ->get();

            expect($providers)->toContain(ViewServiceProvider::class)
                ->and($providers)->toContain(DatabaseServiceProvider::class)
                ->and($providers)->toContain(CacheServiceProvider::class);
        });

        it('handles OR logic with deferred filter', function (): void {
            $providers = Introspect::providers()
                ->whereNameEquals(ViewServiceProvider::class)
                ->or(fn ($q) => $q->whereDeferred())
                ->get();

            expect($providers)->toContain(ViewServiceProvider::class);
        });

        it('handles OR logic with eager filter', function (): void {
            $providers = Introspect::providers()
                ->whereDeferred()
                ->or(fn ($q) => $q->whereNameEquals(ViewServiceProvider::class))
                ->get();

            expect($providers)->toContain(ViewServiceProvider::class);
        });

        it('filters with multiple constraints narrowing results', function (): void {
            $allLaravelProviders = Introspect::providers()
                ->whereNameEquals('Illuminate\*')
                ->count();

            $eagerLaravelProviders = Introspect::providers()
                ->whereNameEquals('Illuminate\*')
                ->whereEager()
                ->count();

            expect($eagerLaravelProviders)->toBeLessThanOrEqual($allLaravelProviders);
        });

        it('handles empty OR condition', function (): void {
            $providers = Introspect::providers()
                ->whereNameEquals(ViewServiceProvider::class)
                ->or(fn ($q) => $q->whereNameEquals('NonExistent'))
                ->get();

            expect($providers)->toContain(ViewServiceProvider::class);
        });

        it('handles chained deferred and eager filters (mutually exclusive)', function (): void {
            // This should return empty because a provider cannot be both deferred and eager
            $providers = Introspect::providers()
                ->whereDeferred()
                ->whereEager()
                ->get();

            expect($providers)->toBeEmpty();
        });

        it('handles getProvidedServices for non-deferred provider', function (): void {
            $services = Introspect::providers()
                ->getProvidedServices(ViewServiceProvider::class);

            // Non-deferred providers don't register services in deferred services array
            expect($services)->toBeInstanceOf(Collection::class);
        });

        it('handles getProvidedServices for non-existent provider', function (): void {
            $services = Introspect::providers()
                ->getProvidedServices('NonExistent\Provider');

            expect($services)->toBeEmpty();
        });

        it('validates toArray structure for deferred providers', function (): void {
            $info = Introspect::providers()->toArray();

            foreach ($info as $data) {
                if ($data['deferred']) {
                    // Deferred providers should have provides array
                    expect($data['provides'])->toBeArray();
                } else {
                    // Eager providers should have empty provides array
                    expect($data['provides'])->toBeArray()
                        ->and($data['provides'])->toBeEmpty();
                }
            }
        });

        it('handles wildcard matching at start and end', function (): void {
            $providers = Introspect::providers()
                ->whereNameEquals('*View*')
                ->get();

            expect($providers)->toContain(ViewServiceProvider::class);
        });

        it('handles exact match when wildcard would also match', function (): void {
            $providers = Introspect::providers()
                ->whereNameEquals(ViewServiceProvider::class)
                ->get();

            expect($providers)->toHaveCount(1)
                ->and($providers)->toContain(ViewServiceProvider::class);
        });

        it('combines name filter with deferred filter', function (): void {
            $allDeferred = Introspect::providers()
                ->whereDeferred()
                ->count();

            $illuminateDeferred = Introspect::providers()
                ->whereNameEquals('Illuminate\*')
                ->whereDeferred()
                ->count();

            expect($illuminateDeferred)->toBeLessThanOrEqual($allDeferred);
        });

        it('verifies all() returns same count as unfiltered get()', function (): void {
            $allCount = Introspect::providers()->all()->count();
            $getCount = Introspect::providers()->get()->count();

            expect($getCount)->toBe($allCount);
        });
    });
});
