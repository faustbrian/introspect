<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Introspect\Query;

use Cline\Introspect\Reflection;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use ReflectionClass;
use Throwable;

use function array_all;
use function array_any;
use function array_is_list;
use function collect;
use function get_declared_classes;
use function is_array;
use function is_int;
use function is_string;
use function preg_match;
use function str_contains;
use function str_ends_with;
use function str_replace;

/**
 * Fluent query builder for Laravel queue job introspection.
 *
 * Provides chainable methods for discovering and filtering Laravel queue jobs with support
 * for queue, connection, delay, retry settings, middleware, and trait filters.
 *
 * ```php
 * // Find jobs by queue name
 * $jobs = Introspect::jobs()
 *     ->whereQueue('emails')
 *     ->get();
 *
 * // Find jobs by connection
 * $jobs = Introspect::jobs()
 *     ->whereConnection('redis')
 *     ->get();
 *
 * // Filter by pattern
 * $jobs = Introspect::jobs()
 *     ->whereNameEquals('App\Jobs\*')
 *     ->get();
 *
 * // Find unique jobs
 * $jobs = Introspect::jobs()
 *     ->whereUnique()
 *     ->get();
 *
 * // Use OR logic
 * $jobs = Introspect::jobs()
 *     ->whereQueue('emails')
 *     ->or(fn($query) => $query->whereQueue('notifications'))
 *     ->get();
 * ```
 * @author Brian Faust <brian@cline.sh>
 */
final class JobsIntrospector
{
    /** @var array<int, callable> */
    private array $filters = [];

    /** @var array<int, callable> */
    private array $orFilters = [];

    /** @var null|array<string> */
    private ?array $jobs = null;

    /**
     * Set the jobs to search within.
     *
     * @param array<string> $jobs Array of job class names
     */
    public function in(array $jobs): static
    {
        $this->jobs = $jobs;

        return $this;
    }

    /**
     * Filter jobs by queue name.
     *
     * @param string $queue Queue name to filter by
     */
    public function whereQueue(string $queue): static
    {
        $this->filters[] = fn (string $job): bool => $this->getQueue($job) === $queue;

        return $this;
    }

    /**
     * Filter jobs by connection name.
     *
     * @param string $connection Connection name to filter by
     */
    public function whereConnection(string $connection): static
    {
        $this->filters[] = fn (string $job): bool => $this->getConnection($job) === $connection;

        return $this;
    }

    /**
     * Filter jobs by name pattern (supports wildcards).
     *
     * @param string $pattern Pattern to match (e.g., 'App\Jobs\*', '*EmailJob')
     */
    public function whereNameEquals(string $pattern): static
    {
        $this->filters[] = function (string $job) use ($pattern): bool {
            $regex = '/^'.str_replace(['\\', '*'], ['\\\\', '.*'], $pattern).'$/';

            return (bool) preg_match($regex, $job);
        };

        return $this;
    }

    /**
     * Filter jobs that use a specific trait.
     *
     * @param string $trait Fully-qualified trait name
     */
    public function whereHasTrait(string $trait): static
    {
        $this->filters[] = fn (string $job): bool => Reflection::usesTrait($job, $trait);

        return $this;
    }

    /**
     * Filter jobs that implement ShouldBeUnique.
     */
    public function whereUnique(): static
    {
        $this->filters[] = fn (string $job): bool => Reflection::implementsInterface($job, ShouldBeUnique::class);

        return $this;
    }

    /**
     * Filter jobs that implement ShouldBeEncrypted.
     */
    public function whereEncrypted(): static
    {
        $this->filters[] = fn (string $job): bool => Reflection::implementsInterface($job, ShouldBeEncrypted::class);

        return $this;
    }

    /**
     * Filter jobs that have middleware.
     */
    public function whereHasMiddleware(): static
    {
        $this->filters[] = fn (string $job): bool => $this->getMiddleware($job) !== [];

        return $this;
    }

    /**
     * Filter jobs that have a specific number of tries.
     *
     * @param int $tries Number of tries
     */
    public function whereTries(int $tries): static
    {
        $this->filters[] = fn (string $job): bool => $this->getTries($job) === $tries;

        return $this;
    }

    /**
     * Add OR logic to the query.
     *
     * Jobs will match if they pass either the main filters OR the filters in the callback.
     *
     * @param callable $callback Callback that receives a new query instance
     */
    public function or(callable $callback): static
    {
        $query = new self();
        $callback($query);
        $this->orFilters[] = fn (string $job): bool => $query->matches($job);

        return $this;
    }

    /**
     * Get all jobs matching the filters.
     *
     * @return Collection<int, string> Collection of job class names
     */
    public function get(): Collection
    {
        $jobs = $this->collectJobs();

        return collect($jobs)->filter(fn (string $job): bool => $this->matchesFilters($job))->values();
    }

    /**
     * Get the first matching job.
     *
     * @return null|string Job class name or null if no match
     */
    public function first(): ?string
    {
        return $this->get()->first();
    }

    /**
     * Check if any jobs match the filters.
     */
    public function exists(): bool
    {
        return $this->get()->isNotEmpty();
    }

    /**
     * Count matching jobs.
     */
    public function count(): int
    {
        return $this->get()->count();
    }

    /**
     * Get all jobs with detailed information.
     *
     * @return Collection<int, array{class: string, queue: null|string, connection: null|string, tries: null|int, backoff: null|array<int>|int, middleware: array<int, string>, unique: bool, encrypted: bool}>
     */
    public function toArray(): Collection
    {
        return $this->get()->map(fn (string $job): array => [
            'class' => $job,
            'queue' => $this->getQueue($job),
            'connection' => $this->getConnection($job),
            'tries' => $this->getTries($job),
            'backoff' => $this->getBackoff($job),
            'middleware' => $this->getMiddleware($job),
            'unique' => Reflection::implementsInterface($job, ShouldBeUnique::class),
            'encrypted' => Reflection::implementsInterface($job, ShouldBeEncrypted::class),
        ]);
    }

    /**
     * Collect all queue job classes.
     *
     * @return array<string>
     */
    private function collectJobs(): array
    {
        if ($this->jobs !== null) {
            return $this->jobs;
        }

        // Discover jobs by finding classes that implement ShouldQueue
        return $this->discoverJobs();
    }

    /**
     * Discover all queue job classes from declared classes.
     *
     * @return array<string>
     */
    private function discoverJobs(): array
    {
        $jobs = [];

        foreach (get_declared_classes() as $class) {
            if (!$this->isQueueJob($class)) {
                continue;
            }

            $jobs[] = $class;
        }

        return $jobs;
    }

    /**
     * Check if a class is a queue job.
     */
    private function isQueueJob(string $class): bool
    {
        // Check if implements ShouldQueue interface
        if (Reflection::implementsInterface($class, ShouldQueue::class)) {
            return true;
        }

        // Check if class name contains 'Job' and is in a Jobs namespace
        return str_contains($class, 'Jobs\\') || str_ends_with($class, 'Job');
    }

    /**
     * Check if job matches all filters.
     */
    private function matchesFilters(string $job): bool
    {
        // If no filters at all, match everything
        if ($this->filters === [] && $this->orFilters === []) {
            return true;
        }

        // Check main filters (AND logic)
        $mainMatches = $this->matches($job);

        // If no OR filters, return main filter result
        if ($this->orFilters === []) {
            return $mainMatches;
        }

        // With OR filters, match if main filters pass OR any OR filter passes
        if ($mainMatches) {
            return true;
        }

        return array_any($this->orFilters, fn (callable $orFilter): bool => (bool) $orFilter($job));
    }

    /**
     * Check if job matches all main filters.
     */
    private function matches(string $job): bool
    {
        return array_all($this->filters, fn (callable $filter): bool => (bool) $filter($job));
    }

    /**
     * Get queue name for a job.
     */
    private function getQueue(string $job): ?string
    {
        try {
            /** @var class-string $job */
            $reflection = new ReflectionClass($job);

            // Check for public $queue property using default values (no instantiation needed)
            if ($reflection->hasProperty('queue')) {
                $defaults = $reflection->getDefaultProperties();
                $queue = $defaults['queue'] ?? null;

                return is_string($queue) ? $queue : null;
            }

            // Check for viaQueue() method
            if ($reflection->hasMethod('viaQueue')) {
                return null; // Dynamic queue, can't determine statically
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * Get connection name for a job.
     */
    private function getConnection(string $job): ?string
    {
        try {
            /** @var class-string $job */
            $reflection = new ReflectionClass($job);

            // Check for public $connection property using default values (no instantiation needed)
            if ($reflection->hasProperty('connection')) {
                $defaults = $reflection->getDefaultProperties();
                $connection = $defaults['connection'] ?? null;

                return is_string($connection) ? $connection : null;
            }

            // Check for viaConnection() method
            if ($reflection->hasMethod('viaConnection')) {
                return null; // Dynamic connection, can't determine statically
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * Get tries setting for a job.
     */
    private function getTries(string $job): ?int
    {
        try {
            /** @var class-string $job */
            $reflection = new ReflectionClass($job);

            // Check for public $tries property using default values (no instantiation needed)
            if ($reflection->hasProperty('tries')) {
                $defaults = $reflection->getDefaultProperties();
                $tries = $defaults['tries'] ?? null;

                return is_int($tries) ? $tries : null;
            }

            // Check for tries() method
            if ($reflection->hasMethod('tries')) {
                return null; // Dynamic tries, can't determine statically
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * Get backoff setting for a job.
     *
     * @return null|array<int>|int
     */
    private function getBackoff(string $job): null|array|int
    {
        try {
            /** @var class-string $job */
            $reflection = new ReflectionClass($job);

            // Check for public $backoff property using default values (no instantiation needed)
            if ($reflection->hasProperty('backoff')) {
                $defaults = $reflection->getDefaultProperties();
                $backoff = $defaults['backoff'] ?? null;

                if (is_int($backoff)) {
                    return $backoff;
                }

                if (is_array($backoff) && array_is_list($backoff)) {
                    /** @var array<int> $backoff */
                    return $backoff;
                }

                return null;
            }

            // Check for backoff() method
            if ($reflection->hasMethod('backoff')) {
                return null; // Dynamic backoff, can't determine statically
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * Get middleware for a job.
     *
     * @return array<int, string>
     */
    private function getMiddleware(string $job): array
    {
        try {
            /** @var class-string $job */
            $reflection = new ReflectionClass($job);

            // Check for middleware() method
            if ($reflection->hasMethod('middleware')) {
                // Can't invoke without constructor, return empty
                // In real usage, middleware is typically dynamic
                return [];
            }
        } catch (Throwable) {
            return [];
        }

        return [];
    }
}
