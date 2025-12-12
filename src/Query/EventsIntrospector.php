<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Introspect\Query;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;

use function array_any;
use function collect;
use function is_array;
use function is_bool;
use function is_object;
use function is_string;
use function preg_match;
use function str_ends_with;
use function str_replace;
use function str_starts_with;

/**
 * Fluent query builder for event introspection.
 *
 * Allows querying Laravel events and listeners with support for filtering by
 * event name patterns, listeners, and namespace patterns. Supports complex OR
 * logic through the or() method.
 *
 * @example
 * ```php
 * // Get all registered events
 * events()->all();
 *
 * // Find events by pattern with wildcards
 * events()->whereNameEquals('App\Events\*')->get();
 *
 * // Find events by namespace
 * events()->whereNameStartsWith('App\Events')->get();
 *
 * // Find events that have a specific listener
 * events()->whereHasListener(SendEmailNotification::class)->get();
 *
 * // Get listeners for a specific event
 * events()->listenersFor(UserCreated::class);
 *
 * // Get all event-to-listener mappings
 * events()->toArray();
 * ```
 * @author Brian Faust <brian@cline.sh>
 */
final class EventsIntrospector
{
    /** @var array<string, mixed> */
    private array $filters = [];

    /** @var array<callable> */
    private array $orFilters = [];

    /**
     * Filter events by name pattern (supports wildcards).
     *
     * @param string $pattern Pattern to match (e.g., 'App\Events\*', 'UserCreated')
     *
     * @example
     * ```php
     * events()->whereNameEquals('App\Events\*')->get();
     * events()->whereNameEquals('UserCreated')->get();
     * ```
     */
    public function whereNameEquals(string $pattern): static
    {
        $this->filters['name'] = $pattern;

        return $this;
    }

    /**
     * Filter events by name prefix.
     *
     * @param string $prefix The prefix to match
     *
     * @example
     * ```php
     * events()->whereNameStartsWith('App\Events')->get();
     * events()->whereNameStartsWith('Illuminate\Auth')->get();
     * ```
     */
    public function whereNameStartsWith(string $prefix): static
    {
        $this->filters['name_starts_with'] = $prefix;

        return $this;
    }

    /**
     * Filter events by name suffix.
     *
     * @param string $suffix The suffix to match
     *
     * @example
     * ```php
     * events()->whereNameEndsWith('Created')->get();
     * events()->whereNameEndsWith('Updated')->get();
     * ```
     */
    public function whereNameEndsWith(string $suffix): static
    {
        $this->filters['name_ends_with'] = $suffix;

        return $this;
    }

    /**
     * Filter events that have a specific listener.
     *
     * @param string $listenerClass The listener class name
     *
     * @example
     * ```php
     * events()->whereHasListener(SendEmailNotification::class)->get();
     * events()->whereHasListener('App\Listeners\LogUserActivity')->get();
     * ```
     */
    public function whereHasListener(string $listenerClass): static
    {
        $this->filters['has_listener'] = $listenerClass;

        return $this;
    }

    /**
     * Filter events that do NOT have any listeners.
     *
     * @example
     * ```php
     * events()->whereHasNoListeners()->get();
     * ```
     */
    public function whereHasNoListeners(): static
    {
        $this->filters['has_no_listeners'] = true;

        return $this;
    }

    /**
     * Filter events that have at least one listener.
     *
     * @example
     * ```php
     * events()->whereHasListeners()->get();
     * ```
     */
    public function whereHasListeners(): static
    {
        $this->filters['has_listeners'] = true;

        return $this;
    }

    /**
     * Add OR logic to the query.
     *
     * The callback receives a new query builder instance. Events matching
     * either the main filters OR the callback filters will be returned.
     *
     * @param callable $callback Receives an EventsIntrospector instance
     *
     * @example
     * ```php
     * // Events starting with App\Events OR Illuminate\Auth
     * events()
     *     ->whereNameStartsWith('App\Events')
     *     ->or(fn($query) => $query->whereNameStartsWith('Illuminate\Auth'))
     *     ->get();
     * ```
     */
    public function or(callable $callback): static
    {
        $query = new self();
        $callback($query);
        $this->orFilters[] = fn (string $event, array $listeners): bool => $query->matchesFilters($event, $listeners);

        return $this;
    }

    /**
     * Get all events matching the filters.
     *
     * @return Collection<int, string>
     *
     * @example
     * ```php
     * $events = events()->whereNameStartsWith('App\Events')->get();
     * ```
     */
    public function get(): Collection
    {
        $allEvents = $this->getAllEvents();

        return collect($allEvents)
            ->filter(fn (array $listeners, string $event): bool => $this->matchesFilters($event, $listeners))
            ->keys();
    }

    /**
     * Get all registered events and their listeners as an associative array.
     *
     * @return array<string, array<string>>
     *
     * @example
     * ```php
     * $mappings = events()->toArray();
     * // ['App\Events\UserCreated' => ['App\Listeners\SendWelcomeEmail', ...]]
     * ```
     */
    public function toArray(): array
    {
        $allEvents = $this->getAllEvents();

        return collect($allEvents)
            ->filter(fn (array $listeners, string $event): bool => $this->matchesFilters($event, $listeners))
            ->map(fn (array $listeners): array => $this->normalizeListeners($listeners))
            ->all();
    }

    /**
     * Get all registered events (just the event names).
     *
     * @return Collection<int, string>
     *
     * @example
     * ```php
     * $allEvents = events()->all();
     * ```
     */
    public function all(): Collection
    {
        return collect($this->getAllEvents())->keys();
    }

    /**
     * Get listeners for a specific event.
     *
     * @param string $eventClass The event class name
     *
     * @return array<string>
     *
     * @example
     * ```php
     * $listeners = events()->listenersFor(UserCreated::class);
     * // ['SendWelcomeEmail', 'LogUserCreation']
     * ```
     */
    public function listenersFor(string $eventClass): array
    {
        $listeners = Event::getListeners($eventClass);

        return $this->normalizeListeners($listeners);
    }

    /**
     * Check if an event has listeners.
     *
     * @param string $eventClass The event class name
     *
     * @example
     * ```php
     * if (events()->hasListeners(UserCreated::class)) {
     *     // Event has listeners
     * }
     * ```
     */
    public function hasListeners(string $eventClass): bool
    {
        return Event::hasListeners($eventClass);
    }

    /**
     * Get the first matching event.
     *
     * @example
     * ```php
     * $event = events()->whereNameStartsWith('App\Events')->first();
     * ```
     */
    public function first(): ?string
    {
        return $this->get()->first();
    }

    /**
     * Check if any events match the filters.
     *
     * @example
     * ```php
     * if (events()->whereNameStartsWith('App\Events')->exists()) {
     *     // Events exist
     * }
     * ```
     */
    public function exists(): bool
    {
        return $this->get()->isNotEmpty();
    }

    /**
     * Count matching events.
     *
     * @example
     * ```php
     * $count = events()->whereNameStartsWith('App\Events')->count();
     * ```
     */
    public function count(): int
    {
        return $this->get()->count();
    }

    /**
     * Get all registered events and their raw listeners from Laravel.
     *
     * @return array<string, array<mixed>>
     */
    private function getAllEvents(): array
    {
        $rawListeners = Event::getRawListeners();

        /** @var array<string, array<mixed>> */
        return collect($rawListeners)
            ->filter(fn (mixed $listeners, mixed $event): bool => is_string($event) && is_array($listeners))
            ->toArray();
    }

    /**
     * Normalize listeners to an array of class names or closure strings.
     *
     * @param array<mixed> $listeners
     *
     * @return array<string>
     */
    private function normalizeListeners(array $listeners): array
    {
        return collect($listeners)
            ->map(function (mixed $listener): string {
                if (is_string($listener)) {
                    return $listener;
                }

                if (is_array($listener) && isset($listener[0])) {
                    $firstElement = $listener[0];

                    if (is_string($firstElement)) {
                        return $firstElement;
                    }

                    if (is_object($firstElement)) {
                        return $firstElement::class;
                    }
                }

                if (is_object($listener)) {
                    return $listener::class;
                }

                return 'Closure';
            })
            ->values()
            ->all();
    }

    /**
     * Check if an event matches all filters.
     *
     * @param string       $event     The event name
     * @param array<mixed> $listeners The event's listeners
     */
    private function matchesFilters(string $event, array $listeners): bool
    {
        // Check OR filters first - if any OR filter matches, the event passes
        if ($this->orFilters !== []) {
            $matchesOr = array_any($this->orFilters, function (callable $orFilter) use ($event, $listeners): bool {
                $result = $orFilter($event, $listeners);

                return is_bool($result) && $result;
            });

            // If we have main filters AND OR filters, check if main filters match OR any OR filter matches
            if ($this->filters !== []) {
                $matchesMain = $this->matchesMainFilters($event, $listeners);

                return $matchesMain || $matchesOr;
            }

            // If we only have OR filters, at least one must match
            return $matchesOr;
        }

        // No OR filters, just check main filters
        return $this->matchesMainFilters($event, $listeners);
    }

    /**
     * Check if an event matches the main filters (non-OR filters).
     *
     * @param string       $event     The event name
     * @param array<mixed> $listeners The event's listeners
     */
    private function matchesMainFilters(string $event, array $listeners): bool
    {
        if (isset($this->filters['name'])) {
            $pattern = $this->filters['name'];

            if (!is_string($pattern) || !$this->matchesPattern($event, $pattern)) {
                return false;
            }
        }

        if (isset($this->filters['name_starts_with'])) {
            $prefix = $this->filters['name_starts_with'];

            if (!is_string($prefix) || !str_starts_with($event, $prefix)) {
                return false;
            }
        }

        if (isset($this->filters['name_ends_with'])) {
            $suffix = $this->filters['name_ends_with'];

            if (!is_string($suffix) || !str_ends_with($event, $suffix)) {
                return false;
            }
        }

        if (isset($this->filters['has_listener'])) {
            $listenerClass = $this->filters['has_listener'];

            if (!is_string($listenerClass) || !$this->hasListener($listeners, $listenerClass)) {
                return false;
            }
        }

        if (isset($this->filters['has_no_listeners']) && $listeners !== []) {
            return false;
        }

        return !(isset($this->filters['has_listeners']) && $listeners === []);
    }

    /**
     * Check if the listeners array contains a specific listener.
     *
     * @param array<mixed> $listeners
     */
    private function hasListener(array $listeners, string $listenerClass): bool
    {
        $normalized = $this->normalizeListeners($listeners);

        return array_any($normalized, fn (string $listener): bool => $listener === $listenerClass);
    }

    /**
     * Check if a value matches a wildcard pattern.
     */
    private function matchesPattern(string $value, string $pattern): bool
    {
        // Convert wildcard pattern to regex
        $regex = '/^'.str_replace(['\\', '*'], ['\\\\', '.*'], $pattern).'$/';

        return (bool) preg_match($regex, $value);
    }
}
