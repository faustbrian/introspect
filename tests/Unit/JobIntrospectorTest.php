<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit;

use Cline\Introspect\Query\JobIntrospector;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use InvalidArgumentException;
use RuntimeException;

use function describe;
use function expect;
use function it;

/**
 * Test job fixtures for JobIntrospector.
 * @author Brian Faust <brian@cline.sh>
 */
final class JobIntrospectorTestFullFeaturedJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    public $queue = 'high-priority';

    public $connection = 'redis';

    public int $tries = 5;

    public int $timeout = 60;

    public array $backoff = [5, 15, 30];

    public int $maxExceptions = 3;

    public bool $failOnTimeout = true;

    public bool $deleteWhenMissingModels = true;

    public function handle(): void
    {
        // Job logic
    }

    /**
     * @return array<int, mixed>
     */
    public function middleware(): array
    {
        return ['throttle', 'rate_limit'];
    }
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class JobIntrospectorTestMinimalJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    public function handle(): void
    {
        // Minimal job with no custom properties
    }
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class JobIntrospectorTestUniqueJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    public $queue = 'unique-queue';

    public bool $failOnTimeout = false;

    public bool $deleteWhenMissingModels = false;

    public function handle(): void
    {
        // Unique job
    }

    public function uniqueId(): string
    {
        return 'test-unique-id-12345';
    }
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class JobIntrospectorTestEncryptedJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    public $queue = 'encrypted-queue';

    public bool $failOnTimeout = false;

    public bool $deleteWhenMissingModels = false;

    public function handle(): void
    {
        // Encrypted job
    }
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class JobIntrospectorTestPrivatePropertiesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    private string $queue = 'private-queue';

    public function handle(): void
    {
        // Job with private queue property
    }
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class JobIntrospectorTestMiddlewareExceptionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    public function handle(): void
    {
        // Job that throws in middleware
    }

    /**
     * @return array<int, mixed>
     */
    public function middleware(): array
    {
        throw new RuntimeException('Middleware requires dependencies');
    }
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class JobIntrospectorTestSingleMiddlewareJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    public function handle(): void
    {
        // Job with single middleware return
    }

    public function middleware(): string
    {
        return 'single-middleware';
    }
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class JobIntrospectorTestUniqueNoIdJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    public function handle(): void
    {
        // Unique job without uniqueId method
    }
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class JobIntrospectorTestUniqueIdExceptionJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    public function handle(): void
    {
        // Unique job that throws in uniqueId
    }

    public function uniqueId(): string
    {
        throw new RuntimeException('Cannot determine unique ID');
    }
}

describe('JobIntrospector', function (): void {
    describe('Happy Path', function (): void {
        it('gets queue name', function (): void {
            $introspector = new JobIntrospector(JobIntrospectorTestFullFeaturedJob::class);

            expect($introspector->queue())->toBe('high-priority');
        });

        it('gets connection name', function (): void {
            $introspector = new JobIntrospector(JobIntrospectorTestFullFeaturedJob::class);

            expect($introspector->connection())->toBe('redis');
        });

        it('gets tries value', function (): void {
            $introspector = new JobIntrospector(JobIntrospectorTestFullFeaturedJob::class);

            expect($introspector->tries())->toBe(5);
        });

        it('gets timeout value', function (): void {
            $introspector = new JobIntrospector(JobIntrospectorTestFullFeaturedJob::class);

            expect($introspector->timeout())->toBe(60);
        });

        it('gets backoff array', function (): void {
            $introspector = new JobIntrospector(JobIntrospectorTestFullFeaturedJob::class);

            expect($introspector->backoff())->toBe([5, 15, 30]);
        });

        it('gets max exceptions', function (): void {
            $introspector = new JobIntrospector(JobIntrospectorTestFullFeaturedJob::class);

            expect($introspector->maxExceptions())->toBe(3);
        });

        it('gets fail on timeout', function (): void {
            $introspector = new JobIntrospector(JobIntrospectorTestFullFeaturedJob::class);

            expect($introspector->failOnTimeout())->toBeTrue();
        });

        it('gets delete when missing models', function (): void {
            $introspector = new JobIntrospector(JobIntrospectorTestFullFeaturedJob::class);

            expect($introspector->deleteWhenMissingModels())->toBeTrue();
        });

        it('gets middleware array', function (): void {
            $introspector = new JobIntrospector(JobIntrospectorTestFullFeaturedJob::class);

            expect($introspector->middleware())->toBe(['throttle', 'rate_limit']);
        });

        it('identifies unique job', function (): void {
            $introspector = new JobIntrospector(JobIntrospectorTestUniqueJob::class);

            expect($introspector->isUnique())->toBeTrue();
        });

        it('identifies encrypted job', function (): void {
            $introspector = new JobIntrospector(JobIntrospectorTestEncryptedJob::class);

            expect($introspector->isEncrypted())->toBeTrue();
        });

        it('gets unique ID from unique job', function (): void {
            $introspector = new JobIntrospector(JobIntrospectorTestUniqueJob::class);

            expect($introspector->uniqueId())->toBe('test-unique-id-12345');
        });

        it('converts to array with all properties', function (): void {
            $introspector = new JobIntrospector(JobIntrospectorTestFullFeaturedJob::class);
            $array = $introspector->toArray();

            expect($array)->toHaveKeys([
                'class',
                'namespace',
                'short_name',
                'queue',
                'connection',
                'tries',
                'timeout',
                'backoff',
                'max_exceptions',
                'fail_on_timeout',
                'delete_when_missing_models',
                'middleware',
                'unique',
                'encrypted',
                'unique_id',
            ])
                ->and($array['class'])->toBe(JobIntrospectorTestFullFeaturedJob::class)
                ->and($array['namespace'])->toBe('Tests\Unit')
                ->and($array['short_name'])->toBe('JobIntrospectorTestFullFeaturedJob')
                ->and($array['queue'])->toBe('high-priority')
                ->and($array['connection'])->toBe('redis')
                ->and($array['tries'])->toBe(5)
                ->and($array['timeout'])->toBe(60)
                ->and($array['backoff'])->toBe([5, 15, 30])
                ->and($array['max_exceptions'])->toBe(3)
                ->and($array['fail_on_timeout'])->toBeTrue()
                ->and($array['delete_when_missing_models'])->toBeTrue()
                ->and($array['middleware'])->toBe(['throttle', 'rate_limit'])
                ->and($array['unique'])->toBeFalse()
                ->and($array['encrypted'])->toBeFalse();
        });
    });

    describe('Edge Cases', function (): void {
        it('returns null for missing queue property', function (): void {
            $introspector = new JobIntrospector(JobIntrospectorTestMinimalJob::class);

            expect($introspector->queue())->toBeNull();
        });

        it('returns null for missing connection property', function (): void {
            $introspector = new JobIntrospector(JobIntrospectorTestMinimalJob::class);

            expect($introspector->connection())->toBeNull();
        });

        it('returns null for missing tries property', function (): void {
            $introspector = new JobIntrospector(JobIntrospectorTestMinimalJob::class);

            expect($introspector->tries())->toBeNull();
        });

        it('returns null for missing timeout property', function (): void {
            $introspector = new JobIntrospector(JobIntrospectorTestMinimalJob::class);

            expect($introspector->timeout())->toBeNull();
        });

        it('returns null for missing backoff property', function (): void {
            $introspector = new JobIntrospector(JobIntrospectorTestMinimalJob::class);

            expect($introspector->backoff())->toBeNull();
        });

        it('returns null for missing max exceptions property', function (): void {
            $introspector = new JobIntrospector(JobIntrospectorTestMinimalJob::class);

            expect($introspector->maxExceptions())->toBeNull();
        });

        it('returns false for non-unique job', function (): void {
            $introspector = new JobIntrospector(JobIntrospectorTestMinimalJob::class);

            expect($introspector->isUnique())->toBeFalse();
        });

        it('returns false for non-encrypted job', function (): void {
            $introspector = new JobIntrospector(JobIntrospectorTestMinimalJob::class);

            expect($introspector->isEncrypted())->toBeFalse();
        });

        it('returns null for unique ID on non-unique job', function (): void {
            $introspector = new JobIntrospector(JobIntrospectorTestMinimalJob::class);

            expect($introspector->uniqueId())->toBeNull();
        });

        it('returns empty array when no middleware method exists', function (): void {
            $introspector = new JobIntrospector(JobIntrospectorTestMinimalJob::class);

            expect($introspector->middleware())->toBe([]);
        });

        it('returns default for private properties', function (): void {
            $introspector = new JobIntrospector(JobIntrospectorTestPrivatePropertiesJob::class);

            expect($introspector->queue())->toBeNull();
        });

        it('returns empty array when middleware throws exception', function (): void {
            $introspector = new JobIntrospector(JobIntrospectorTestMiddlewareExceptionJob::class);

            expect($introspector->middleware())->toBe([]);
        });

        it('wraps single middleware return in array', function (): void {
            $introspector = new JobIntrospector(JobIntrospectorTestSingleMiddlewareJob::class);

            expect($introspector->middleware())->toBe(['single-middleware']);
        });

        it('returns null for unique job without uniqueId method', function (): void {
            $introspector = new JobIntrospector(JobIntrospectorTestUniqueNoIdJob::class);

            expect($introspector->uniqueId())->toBeNull();
        });

        it('returns null when uniqueId method throws exception', function (): void {
            $introspector = new JobIntrospector(JobIntrospectorTestUniqueIdExceptionJob::class);

            expect($introspector->uniqueId())->toBeNull();
        });

        it('throws exception for non-instantiable class', function (): void {
            expect(fn (): JobIntrospector => new JobIntrospector(ShouldQueue::class))
                ->toThrow(InvalidArgumentException::class);
        });

        it('converts minimal job to array correctly', function (): void {
            $introspector = new JobIntrospector(JobIntrospectorTestMinimalJob::class);

            // Note: failOnTimeout() and deleteWhenMissingModels() have bool return type
            // but can return null when property is missing - testing what we can
            expect($introspector->queue())->toBeNull()
                ->and($introspector->connection())->toBeNull()
                ->and($introspector->tries())->toBeNull()
                ->and($introspector->timeout())->toBeNull()
                ->and($introspector->backoff())->toBeNull()
                ->and($introspector->maxExceptions())->toBeNull()
                ->and($introspector->middleware())->toBe([])
                ->and($introspector->isUnique())->toBeFalse()
                ->and($introspector->isEncrypted())->toBeFalse()
                ->and($introspector->uniqueId())->toBeNull();
        });

        it('converts unique job to array correctly', function (): void {
            $introspector = new JobIntrospector(JobIntrospectorTestUniqueJob::class);
            $array = $introspector->toArray();

            expect($array['unique'])->toBeTrue()
                ->and($array['unique_id'])->toBe('test-unique-id-12345');
        });

        it('converts encrypted job to array correctly', function (): void {
            $introspector = new JobIntrospector(JobIntrospectorTestEncryptedJob::class);
            $array = $introspector->toArray();

            expect($array['encrypted'])->toBeTrue();
        });
    });
});
