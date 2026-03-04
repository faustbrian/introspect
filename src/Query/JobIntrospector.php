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
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use Throwable;

use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function method_exists;
use function sprintf;
use function throw_unless;

/**
 * Detailed introspector for Laravel queue jobs.
 *
 * Provides comprehensive introspection of a single queue job including
 * queue, connection, tries, backoff, middleware, and other settings.
 *
 * ```php
 * use Cline\Introspect\Introspect;
 *
 * $introspector = Introspect::job(SendEmailJob::class);
 *
 * // Get job properties
 * $queue = $introspector->queue();
 * $connection = $introspector->connection();
 * $tries = $introspector->tries();
 * $backoff = $introspector->backoff();
 * $middleware = $introspector->middleware();
 *
 * // Check traits and interfaces
 * $isUnique = $introspector->isUnique();
 * $isEncrypted = $introspector->isEncrypted();
 *
 * // Export everything
 * $data = $introspector->toArray();
 * ```
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class JobIntrospector
{
    /** @var ReflectionClass<object> */
    private ReflectionClass $reflection;

    private object $instance;

    /**
     * Create a new job introspector.
     *
     * @param class-string $jobClass Fully-qualified job class name
     *
     * @throws InvalidArgumentException If class is not instantiable
     * @throws ReflectionException      If class doesn't exist
     */
    public function __construct(
        private string $jobClass,
    ) {
        /** @var ReflectionClass<object> $reflection */
        $reflection = new ReflectionClass($jobClass);
        $this->reflection = $reflection;

        throw_unless($this->reflection->isInstantiable(), InvalidArgumentException::class, sprintf('Class %s is not instantiable', $jobClass));

        $this->instance = $this->reflection->newInstanceWithoutConstructor();
    }

    /**
     * Get the queue name.
     *
     * @return null|string Queue name or null if using default
     */
    public function queue(): ?string
    {
        $value = $this->getJobProperty('queue');

        return is_string($value) ? $value : null;
    }

    /**
     * Get the connection name.
     *
     * @return null|string Connection name or null if using default
     */
    public function connection(): ?string
    {
        $value = $this->getJobProperty('connection');

        return is_string($value) ? $value : null;
    }

    /**
     * Get the number of tries.
     *
     * @return null|int Number of tries or null if using default
     */
    public function tries(): ?int
    {
        $value = $this->getJobProperty('tries');

        return is_int($value) ? $value : null;
    }

    /**
     * Get the timeout in seconds.
     *
     * @return null|int Timeout in seconds or null if using default
     */
    public function timeout(): ?int
    {
        $value = $this->getJobProperty('timeout');

        return is_int($value) ? $value : null;
    }

    /**
     * Get the backoff delays.
     *
     * @return null|array<int>|int Backoff delays in seconds or null if not configured
     */
    public function backoff(): null|array|int
    {
        $value = $this->getJobProperty('backoff');

        if (is_int($value)) {
            return $value;
        }

        if (is_array($value)) {
            /** @var array<int> $value */
            return $value;
        }

        return null;
    }

    /**
     * Get the maximum number of exceptions allowed.
     *
     * @return null|int Max exceptions or null if not configured
     */
    public function maxExceptions(): ?int
    {
        $value = $this->getJobProperty('maxExceptions');

        return is_int($value) ? $value : null;
    }

    /**
     * Check if job should fail on timeout.
     *
     * @return bool True if fails on timeout, false otherwise
     */
    public function failOnTimeout(): bool
    {
        $value = $this->getJobProperty('failOnTimeout');

        return is_bool($value) && $value;
    }

    /**
     * Check if job should delete when models are missing.
     *
     * @return bool True if deletes when missing models, false otherwise
     */
    public function deleteWhenMissingModels(): bool
    {
        $value = $this->getJobProperty('deleteWhenMissingModels');

        return is_bool($value) && $value;
    }

    /**
     * Get middleware for the job.
     *
     * Note: This method attempts to invoke the middleware() method if it exists.
     * Returns empty array if middleware is dynamic or cannot be determined.
     *
     * @return array<int, mixed> Array of middleware instances or class names
     * @phpstan-return array<int, mixed>
     */
    public function middleware(): array
    {
        if (!method_exists($this->instance, 'middleware')) {
            return [];
        }

        try {
            $middleware = $this->instance->middleware();

            /** @phpstan-ignore-next-line */
            return is_array($middleware) ? $middleware : [$middleware];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Check if job implements ShouldBeUnique.
     */
    public function isUnique(): bool
    {
        return Reflection::implementsInterface($this->jobClass, ShouldBeUnique::class);
    }

    /**
     * Check if job implements ShouldBeEncrypted.
     */
    public function isEncrypted(): bool
    {
        return Reflection::implementsInterface($this->jobClass, ShouldBeEncrypted::class);
    }

    /**
     * Get the unique ID for the job (if ShouldBeUnique).
     *
     * @return null|string Unique ID or null if not unique or cannot determine
     */
    public function uniqueId(): ?string
    {
        if (!$this->isUnique()) {
            return null;
        }

        if (!method_exists($this->instance, 'uniqueId')) {
            return null;
        }

        try {
            $value = $this->instance->uniqueId();

            return is_string($value) ? $value : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Get comprehensive job information as array.
     *
     * Returns all introspected data about the job including properties,
     * configuration, and metadata.
     *
     * @return array{
     *     class: string,
     *     namespace: string,
     *     short_name: string,
     *     queue: null|string,
     *     connection: null|string,
     *     tries: null|int,
     *     timeout: null|int,
     *     backoff: null|array<int>|int,
     *     max_exceptions: null|int,
     *     fail_on_timeout: bool,
     *     delete_when_missing_models: bool,
     *     middleware: array<int, mixed>,
     *     unique: bool,
     *     encrypted: bool,
     *     unique_id: null|string
     * }
     */
    public function toArray(): array
    {
        return [
            'class' => $this->jobClass,
            'namespace' => $this->reflection->getNamespaceName(),
            'short_name' => $this->reflection->getShortName(),
            'queue' => $this->queue(),
            'connection' => $this->connection(),
            'tries' => $this->tries(),
            'timeout' => $this->timeout(),
            'backoff' => $this->backoff(),
            'max_exceptions' => $this->maxExceptions(),
            'fail_on_timeout' => $this->failOnTimeout(),
            'delete_when_missing_models' => $this->deleteWhenMissingModels(),
            'middleware' => $this->middleware(),
            'unique' => $this->isUnique(),
            'encrypted' => $this->isEncrypted(),
            'unique_id' => $this->uniqueId(),
        ];
    }

    /**
     * Get a property value from the job instance.
     *
     * @param string $property Property name
     * @param mixed  $default  Default value if property doesn't exist
     */
    private function getJobProperty(string $property, mixed $default = null): mixed
    {
        if (!$this->reflection->hasProperty($property)) {
            return $default;
        }

        try {
            $prop = $this->reflection->getProperty($property);

            if (!$prop->isPublic() && !$prop->isProtected()) {
                return $default;
            }

            return $prop->getValue($this->instance) ?? $default;
        } catch (Throwable) {
            return $default;
        }
    }
}
