<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit;

use Cline\Introspect\Introspect;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use function describe;
use function expect;
use function it;

/**
 * Test job fixtures - defined inline.
 * @author Brian Faust <brian@cline.sh>
 */
final class TestEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    public $queue = 'emails';

    public $connection = 'redis';

    public int $tries = 3;

    public array $backoff = [1, 5, 10];

    public function handle(): void
    {
        // Job logic
    }
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class TestNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    public $queue = 'notifications';

    public int $tries = 5;

    public function handle(): void
    {
        // Job logic
    }
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class TestDefaultJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    public function handle(): void
    {
        // Job logic
    }
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class TestUniqueJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    public $queue = 'unique';

    public function handle(): void
    {
        // Job logic
    }

    public function uniqueId(): string
    {
        return 'unique-job-id';
    }
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class TestEncryptedJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    public $queue = 'encrypted';

    public function handle(): void
    {
        // Job logic
    }
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class TestBatchableJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    public $queue = 'batches';

    public function handle(): void
    {
        // Job logic
    }
}

/**
 * Comprehensive test suite for JobsIntrospector.
 *
 * Tests the fluent interface query builder including:
 * - Queue filtering (whereQueue)
 * - Connection filtering (whereConnection)
 * - Pattern matching (whereNameEquals)
 * - Trait filtering (whereHasTrait)
 * - Unique job filtering (whereUnique)
 * - Encrypted job filtering (whereEncrypted)
 * - Middleware filtering (whereHasMiddleware)
 * - Tries filtering (whereTries)
 * - OR logic support
 * - Result methods (get, first, exists, count, toArray)
 * - Edge cases and filter chaining
 */
describe('JobsIntrospector', function (): void {
    describe('Happy Path', function (): void {
        it('filters jobs by queue name', function (): void {
            $jobs = Introspect::jobs()
                ->in([TestEmailJob::class, TestNotificationJob::class, TestDefaultJob::class])
                ->whereQueue('emails')
                ->get();

            expect($jobs)->toContain(TestEmailJob::class)
                ->and($jobs)->not->toContain(TestNotificationJob::class)
                ->and($jobs)->not->toContain(TestDefaultJob::class);
        });

        it('filters jobs by connection name', function (): void {
            $jobs = Introspect::jobs()
                ->in([TestEmailJob::class, TestNotificationJob::class])
                ->whereConnection('redis')
                ->get();

            expect($jobs)->toContain(TestEmailJob::class)
                ->and($jobs)->not->toContain(TestNotificationJob::class);
        });

        it('filters jobs by name pattern with wildcard', function (): void {
            $jobs = Introspect::jobs()
                ->in([TestEmailJob::class, TestNotificationJob::class, TestDefaultJob::class])
                ->whereNameEquals('*EmailJob')
                ->get();

            expect($jobs)->toContain(TestEmailJob::class)
                ->and($jobs)->not->toContain(TestNotificationJob::class);
        });

        it('filters jobs by name pattern with namespace wildcard', function (): void {
            $jobs = Introspect::jobs()
                ->in([TestEmailJob::class, TestNotificationJob::class])
                ->whereNameEquals('Tests\Unit\Test*Job')
                ->get();

            expect($jobs)->toContain(TestEmailJob::class)
                ->and($jobs)->toContain(TestNotificationJob::class);
        });

        it('filters jobs by trait', function (): void {
            $jobs = Introspect::jobs()
                ->in([TestBatchableJob::class, TestEmailJob::class])
                ->whereHasTrait(Batchable::class)
                ->get();

            expect($jobs)->toContain(TestBatchableJob::class)
                ->and($jobs)->not->toContain(TestEmailJob::class);
        });

        it('filters unique jobs', function (): void {
            $jobs = Introspect::jobs()
                ->in([TestUniqueJob::class, TestEmailJob::class, TestDefaultJob::class])
                ->whereUnique()
                ->get();

            expect($jobs)->toContain(TestUniqueJob::class)
                ->and($jobs)->not->toContain(TestEmailJob::class);
        });

        it('filters encrypted jobs', function (): void {
            $jobs = Introspect::jobs()
                ->in([TestEncryptedJob::class, TestEmailJob::class])
                ->whereEncrypted()
                ->get();

            expect($jobs)->toContain(TestEncryptedJob::class)
                ->and($jobs)->not->toContain(TestEmailJob::class);
        });

        it('filters jobs by tries setting', function (): void {
            $jobs = Introspect::jobs()
                ->in([TestEmailJob::class, TestNotificationJob::class])
                ->whereTries(3)
                ->get();

            expect($jobs)->toContain(TestEmailJob::class)
                ->and($jobs)->not->toContain(TestNotificationJob::class);
        });

        it('supports OR logic', function (): void {
            $jobs = Introspect::jobs()
                ->in([TestEmailJob::class, TestNotificationJob::class, TestDefaultJob::class])
                ->whereQueue('emails')
                ->or(fn ($q) => $q->whereQueue('notifications'))
                ->get();

            expect($jobs)->toContain(TestEmailJob::class)
                ->and($jobs)->toContain(TestNotificationJob::class)
                ->and($jobs)->not->toContain(TestDefaultJob::class);
        });

        it('returns first matching job', function (): void {
            $job = Introspect::jobs()
                ->in([TestEmailJob::class, TestNotificationJob::class])
                ->whereQueue('emails')
                ->first();

            expect($job)->toBeString()
                ->and($job)->toBe(TestEmailJob::class);
        });

        it('checks if matching jobs exist', function (): void {
            $exists = Introspect::jobs()
                ->in([TestEmailJob::class])
                ->whereQueue('emails')
                ->exists();

            expect($exists)->toBeTrue();
        });

        it('counts matching jobs', function (): void {
            $count = Introspect::jobs()
                ->in([TestEmailJob::class, TestNotificationJob::class, TestDefaultJob::class])
                ->whereQueue('emails')
                ->count();

            expect($count)->toBe(1);
        });

        it('returns jobs as array with detailed information', function (): void {
            $jobs = Introspect::jobs()
                ->in([TestEmailJob::class])
                ->toArray();

            expect($jobs)->toHaveCount(1)
                ->and($jobs->first())->toHaveKeys([
                    'class',
                    'queue',
                    'connection',
                    'tries',
                    'backoff',
                    'middleware',
                    'unique',
                    'encrypted',
                ])
                ->and($jobs->first()['class'])->toBe(TestEmailJob::class)
                ->and($jobs->first()['queue'])->toBe('emails')
                ->and($jobs->first()['connection'])->toBe('redis')
                ->and($jobs->first()['tries'])->toBe(3)
                ->and($jobs->first()['backoff'])->toBe([1, 5, 10])
                ->and($jobs->first()['unique'])->toBeFalse()
                ->and($jobs->first()['encrypted'])->toBeFalse();
        });
    });

    describe('Edge Cases', function (): void {
        it('returns empty collection when no jobs match', function (): void {
            $jobs = Introspect::jobs()
                ->in([TestEmailJob::class])
                ->whereQueue('nonexistent')
                ->get();

            expect($jobs)->toBeEmpty();
        });

        it('returns null when first() finds no matches', function (): void {
            $job = Introspect::jobs()
                ->in([TestEmailJob::class])
                ->whereQueue('nonexistent')
                ->first();

            expect($job)->toBeNull();
        });

        it('returns false when exists() finds no matches', function (): void {
            $exists = Introspect::jobs()
                ->in([TestEmailJob::class])
                ->whereQueue('nonexistent')
                ->exists();

            expect($exists)->toBeFalse();
        });

        it('returns zero when count() finds no matches', function (): void {
            $count = Introspect::jobs()
                ->in([TestEmailJob::class])
                ->whereQueue('nonexistent')
                ->count();

            expect($count)->toBe(0);
        });

        it('chains multiple filters with AND logic', function (): void {
            $jobs = Introspect::jobs()
                ->in([TestEmailJob::class, TestNotificationJob::class])
                ->whereQueue('emails')
                ->whereConnection('redis')
                ->whereTries(3)
                ->get();

            expect($jobs)->toContain(TestEmailJob::class)
                ->and($jobs)->not->toContain(TestNotificationJob::class);
        });

        it('supports multiple OR conditions', function (): void {
            $jobs = Introspect::jobs()
                ->in([TestEmailJob::class, TestNotificationJob::class, TestUniqueJob::class])
                ->whereQueue('emails')
                ->or(fn ($q) => $q->whereQueue('notifications'))
                ->or(fn ($q) => $q->whereQueue('unique'))
                ->get();

            expect($jobs)->toContain(TestEmailJob::class)
                ->and($jobs)->toContain(TestNotificationJob::class)
                ->and($jobs)->toContain(TestUniqueJob::class);
        });

        it('handles empty in() array', function (): void {
            $jobs = Introspect::jobs()
                ->in([])
                ->get();

            expect($jobs)->toBeEmpty();
        });

        it('discovers jobs without in() constraint', function (): void {
            $jobs = Introspect::jobs()
                ->whereQueue('emails')
                ->get();

            expect($jobs)->toContain(TestEmailJob::class);
        });

        it('handles jobs with no queue specified', function (): void {
            $jobs = Introspect::jobs()
                ->in([TestDefaultJob::class, TestEmailJob::class])
                ->whereQueue('emails')
                ->get();

            expect($jobs)->toContain(TestEmailJob::class)
                ->and($jobs)->not->toContain(TestDefaultJob::class);
        });

        it('handles jobs with no connection specified', function (): void {
            $jobs = Introspect::jobs()
                ->in([TestNotificationJob::class, TestEmailJob::class])
                ->whereConnection('redis')
                ->get();

            expect($jobs)->toContain(TestEmailJob::class)
                ->and($jobs)->not->toContain(TestNotificationJob::class);
        });

        it('handles toArray() with unique job', function (): void {
            $jobs = Introspect::jobs()
                ->in([TestUniqueJob::class])
                ->toArray();

            expect($jobs)->toHaveCount(1)
                ->and($jobs->first()['unique'])->toBeTrue()
                ->and($jobs->first()['encrypted'])->toBeFalse();
        });

        it('handles toArray() with encrypted job', function (): void {
            $jobs = Introspect::jobs()
                ->in([TestEncryptedJob::class])
                ->toArray();

            expect($jobs)->toHaveCount(1)
                ->and($jobs->first()['unique'])->toBeFalse()
                ->and($jobs->first()['encrypted'])->toBeTrue();
        });

        it('handles pattern matching with full class name', function (): void {
            $jobs = Introspect::jobs()
                ->in([TestEmailJob::class, TestNotificationJob::class])
                ->whereNameEquals(TestEmailJob::class)
                ->get();

            expect($jobs)->toContain(TestEmailJob::class)
                ->and($jobs)->not->toContain(TestNotificationJob::class);
        });

        it('filters jobs without any filters returns all', function (): void {
            $jobs = Introspect::jobs()
                ->in([TestEmailJob::class, TestNotificationJob::class])
                ->get();

            expect($jobs)->toContain(TestEmailJob::class)
                ->and($jobs)->toContain(TestNotificationJob::class);
        });

        it('handles combination of trait and queue filters', function (): void {
            $jobs = Introspect::jobs()
                ->in([TestBatchableJob::class, TestEmailJob::class])
                ->whereHasTrait(Batchable::class)
                ->whereQueue('batches')
                ->get();

            expect($jobs)->toContain(TestBatchableJob::class)
                ->and($jobs)->not->toContain(TestEmailJob::class);
        });

        it('handles tries filter with jobs that have no tries set', function (): void {
            $jobs = Introspect::jobs()
                ->in([TestDefaultJob::class, TestEmailJob::class])
                ->whereTries(3)
                ->get();

            expect($jobs)->toContain(TestEmailJob::class)
                ->and($jobs)->not->toContain(TestDefaultJob::class);
        });

        it('handles toArray() with job having backoff setting', function (): void {
            $jobs = Introspect::jobs()
                ->in([TestEmailJob::class])
                ->toArray();

            expect($jobs->first()['backoff'])->toBeArray()
                ->and($jobs->first()['backoff'])->toBe([1, 5, 10]);
        });
    });
});
