<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Introspect\Query;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Application as LaravelApplication;
use Illuminate\Support\Collection;

use function array_any;
use function array_keys;
use function array_unique;
use function array_values;
use function collect;
use function in_array;
use function preg_match;
use function str_replace;

/**
 * Fluent query builder for service provider introspection.
 *
 * Allows querying Laravel service providers with wildcard support and filtering
 * by deferred status, provided services, and registration status.
 *
 * @example
 * ```php
 * // Find all registered providers
 * $providers = Introspect::providers()->all();
 *
 * // Find deferred providers
 * $providers = Introspect::providers()
 *     ->whereDeferred()
 *     ->get();
 *
 * // Find providers by namespace pattern
 * $providers = Introspect::providers()
 *     ->whereNameEquals('App\Providers\*')
 *     ->get();
 *
 * // Find providers that provide a specific service
 * $providers = Introspect::providers()
 *     ->whereProvides(SomeService::class)
 *     ->get();
 *
 * // Check if a provider is registered
 * $isRegistered = Introspect::providers()
 *     ->isRegistered(MyProvider::class);
 * ```
 * @author Brian Faust <brian@cline.sh>
 */
final class ServiceProviderIntrospector
{
    /** @var array<string, bool|string> Primary filter conditions (AND logic) */
    private array $filters = [];

    /** @var array<int, self> OR filter groups */
    private array $orFilters = [];

    /**
     * @param Application $app Laravel application instance
     */
    public function __construct(
        private readonly Application $app,
    ) {}

    /**
     * Get all registered service providers.
     *
     * @return Collection<int, string> Collection of provider class names
     *
     * @example
     * ```php
     * $providers = Introspect::providers()->all();
     * // => Collection(['App\Providers\AppServiceProvider', ...])
     * ```
     */
    public function all(): Collection
    {
        /** @var LaravelApplication $app */
        $app = $this->app;
        $loadedProviders = $app->getLoadedProviders();

        return collect(array_keys($loadedProviders));
    }

    /**
     * Filter providers by name pattern (supports wildcards).
     *
     * Matches provider class names using wildcard patterns. Supports both
     * namespace and class name matching.
     *
     * @param  string $pattern Pattern to match (e.g., 'App\Providers\*', '*ServiceProvider')
     * @return static Fluent interface
     *
     * @example
     * ```php
     * // Match all providers in App\Providers namespace
     * ->whereNameEquals('App\Providers\*')
     *
     * // Match all ServiceProvider classes
     * ->whereNameEquals('*ServiceProvider')
     *
     * // Match AppServiceProvider in any namespace
     * ->whereNameEquals('*\AppServiceProvider')
     * ```
     */
    public function whereNameEquals(string $pattern): static
    {
        $this->filters['name'] = $pattern;

        return $this;
    }

    /**
     * Filter to only deferred service providers.
     *
     * Deferred providers are loaded on-demand when their provided services
     * are first accessed.
     *
     * @return static Fluent interface
     *
     * @example
     * ```php
     * $deferred = Introspect::providers()
     *     ->whereDeferred()
     *     ->get();
     * ```
     */
    public function whereDeferred(): static
    {
        $this->filters['deferred'] = true;

        return $this;
    }

    /**
     * Filter to only eager (non-deferred) service providers.
     *
     * Eager providers are loaded immediately during application bootstrap.
     *
     * @return static Fluent interface
     *
     * @example
     * ```php
     * $eager = Introspect::providers()
     *     ->whereEager()
     *     ->get();
     * ```
     */
    public function whereEager(): static
    {
        $this->filters['eager'] = true;

        return $this;
    }

    /**
     * Filter providers that provide a specific service.
     *
     * Only applies to deferred providers. Returns providers that register
     * the specified service binding.
     *
     * @param  string $service Fully-qualified service class name or binding key
     * @return static Fluent interface
     *
     * @example
     * ```php
     * use App\Services\PaymentService;
     *
     * $providers = Introspect::providers()
     *     ->whereProvides(PaymentService::class)
     *     ->get();
     * ```
     */
    public function whereProvides(string $service): static
    {
        $this->filters['provides'] = $service;

        return $this;
    }

    /**
     * Add OR logic for complex filtering.
     *
     * Accepts a callback that receives a fresh query builder instance.
     * Results match if ANY of the OR conditions are satisfied.
     *
     * @param  callable $callback Callback receiving a new query builder instance
     * @return static   Fluent interface
     *
     * @example
     * ```php
     * // Find providers that are EITHER in App\Providers OR are deferred
     * ->whereNameEquals('App\Providers\*')
     * ->or(fn($query) => $query->whereDeferred())
     * ```
     */
    public function or(callable $callback): static
    {
        $orQuery = new self($this->app);
        $callback($orQuery);
        $this->orFilters[] = $orQuery;

        return $this;
    }

    /**
     * Get all service providers matching the filters.
     *
     * @return Collection<int, string> Collection of provider class names
     *
     * @example
     * ```php
     * $providers = Introspect::providers()
     *     ->whereNameEquals('App\Providers\*')
     *     ->get();
     * // => Collection(['App\Providers\AppServiceProvider', ...])
     * ```
     */
    public function get(): Collection
    {
        return $this->all()->filter(fn (string $provider): bool => $this->matchesFilters($provider));
    }

    /**
     * Get the first matching provider.
     *
     * @return null|string First provider class name or null if none found
     *
     * @example
     * ```php
     * $provider = Introspect::providers()
     *     ->whereNameEquals('App\Providers\AppServiceProvider')
     *     ->first();
     * // => 'App\Providers\AppServiceProvider' or null
     * ```
     */
    public function first(): ?string
    {
        return $this->get()->first();
    }

    /**
     * Check if any providers match the filters.
     *
     * @return bool True if at least one provider matches
     *
     * @example
     * ```php
     * $hasDeferred = Introspect::providers()
     *     ->whereDeferred()
     *     ->exists();
     * // => true/false
     * ```
     */
    public function exists(): bool
    {
        return $this->get()->isNotEmpty();
    }

    /**
     * Count matching providers.
     *
     * @return int Number of providers matching filters
     *
     * @example
     * ```php
     * $count = Introspect::providers()
     *     ->whereNameEquals('App\Providers\*')
     *     ->count();
     * // => 5
     * ```
     */
    public function count(): int
    {
        return $this->get()->count();
    }

    /**
     * Check if a specific provider is registered.
     *
     * @param  string $providerClass Fully-qualified provider class name
     * @return bool   True if provider is registered
     *
     * @example
     * ```php
     * $isRegistered = Introspect::providers()
     *     ->isRegistered(MyProvider::class);
     * // => true/false
     * ```
     */
    public function isRegistered(string $providerClass): bool
    {
        return $this->all()->contains($providerClass);
    }

    /**
     * Get all deferred services and their providers.
     *
     * Returns a mapping of service bindings to their provider class names.
     *
     * @return array<string, string> Array mapping service names to provider class names
     *
     * @example
     * ```php
     * $services = Introspect::providers()->getDeferredServices();
     * // => ['App\Services\PaymentService' => 'App\Providers\PaymentServiceProvider', ...]
     * ```
     */
    public function getDeferredServices(): array
    {
        /** @var LaravelApplication $app */
        $app = $this->app;

        /** @var array<string, string> */
        return $app->getDeferredServices();
    }

    /**
     * Get services provided by a specific provider.
     *
     * Only applies to deferred providers. Returns the service bindings
     * that this provider registers.
     *
     * @param  string                  $providerClass Fully-qualified provider class name
     * @return Collection<int, string> Collection of service class names/bindings
     *
     * @example
     * ```php
     * $services = Introspect::providers()
     *     ->getProvidedServices(PaymentServiceProvider::class);
     * // => Collection(['App\Services\PaymentService', 'payment'])
     * ```
     */
    public function getProvidedServices(string $providerClass): Collection
    {
        $deferredServices = $this->getDeferredServices();

        return collect($deferredServices)
            ->filter(fn (string $provider): bool => $provider === $providerClass)
            ->keys();
    }

    /**
     * Get all provider information as array.
     *
     * Returns detailed information about all registered providers including
     * their deferred status and provided services.
     *
     * @return array<string, array{class: string, deferred: bool, provides: array<int, string>}> Provider information
     *
     * @example
     * ```php
     * $info = Introspect::providers()->toArray();
     * // => [
     * //   'App\Providers\AppServiceProvider' => [
     * //     'class' => 'App\Providers\AppServiceProvider',
     * //     'deferred' => false,
     * //     'provides' => []
     * //   ],
     * //   ...
     * // ]
     * ```
     */
    public function toArray(): array
    {
        $deferredServices = $this->getDeferredServices();
        $deferredProviders = array_unique(array_values($deferredServices));

        return $this->all()
            ->mapWithKeys(function (string $provider) use ($deferredProviders): array {
                $isDeferred = in_array($provider, $deferredProviders, true);
                $provides = $isDeferred
                    ? $this->getProvidedServices($provider)->all()
                    : [];

                return [
                    $provider => [
                        'class' => $provider,
                        'deferred' => $isDeferred,
                        'provides' => $provides,
                    ],
                ];
            })
            ->all();
    }

    /**
     * Check if a provider matches all filter conditions.
     *
     * Evaluates primary filters (AND logic) and OR filters separately.
     * Returns true if primary filters pass OR at least one OR filter passes.
     *
     * @param  string $provider Fully-qualified provider class name
     * @return bool   True if provider matches all conditions
     */
    private function matchesFilters(string $provider): bool
    {
        // If there are OR filters, match if primary OR any OR filter matches
        if ($this->orFilters !== []) {
            $primaryMatches = $this->matchesPrimaryFilters($provider);
            $orMatches = array_any(
                $this->orFilters,
                fn (ServiceProviderIntrospector $orQuery): bool => $orQuery->matchesPrimaryFilters($provider),
            );

            return $primaryMatches || $orMatches;
        }

        // Otherwise just check primary filters
        return $this->matchesPrimaryFilters($provider);
    }

    /**
     * Check if a provider matches the primary filter conditions.
     *
     * All primary filters must pass (AND logic).
     *
     * @param  string $provider Fully-qualified provider class name
     * @return bool   True if provider matches all primary conditions
     */
    private function matchesPrimaryFilters(string $provider): bool
    {
        if (isset($this->filters['name'])) {
            /** @var string $nameFilter */
            $nameFilter = $this->filters['name'];

            if (!$this->matchesPattern($provider, $nameFilter)) {
                return false;
            }
        }

        if (isset($this->filters['deferred']) && !$this->isDeferred($provider)) {
            return false;
        }

        if (isset($this->filters['eager']) && $this->isDeferred($provider)) {
            return false;
        }

        if (isset($this->filters['provides'])) {
            /** @var string $serviceFilter */
            $serviceFilter = $this->filters['provides'];

            if (!$this->providesService($provider, $serviceFilter)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a provider is deferred.
     *
     * @param  string $provider Fully-qualified provider class name
     * @return bool   True if provider is deferred
     */
    private function isDeferred(string $provider): bool
    {
        $deferredServices = $this->getDeferredServices();

        return in_array($provider, $deferredServices, true);
    }

    /**
     * Check if a provider provides a specific service.
     *
     * @param  string $provider Fully-qualified provider class name
     * @param  string $service  Service class name or binding key
     * @return bool   True if provider provides the service
     */
    private function providesService(string $provider, string $service): bool
    {
        $deferredServices = $this->getDeferredServices();

        return isset($deferredServices[$service]) && $deferredServices[$service] === $provider;
    }

    /**
     * Check if a value matches a wildcard pattern.
     *
     * Converts wildcard pattern to regex for matching.
     * Supports * as wildcard character.
     *
     * @param  string $value   Value to test
     * @param  string $pattern Wildcard pattern (e.g., 'App\Providers\*', '*ServiceProvider')
     * @return bool   True if value matches pattern
     */
    private function matchesPattern(string $value, string $pattern): bool
    {
        // Convert wildcard pattern to regex
        $regex = '/^'.str_replace(['\\', '*'], ['\\\\', '.*'], $pattern).'$/';

        return (bool) preg_match($regex, $value);
    }
}
