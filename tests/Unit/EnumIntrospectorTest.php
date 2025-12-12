<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit;

use Attribute;
use Cline\Introspect\Introspect;

use function describe;
use function expect;
use function it;

describe('EnumIntrospector', function (): void {
    describe('Happy Path', function (): void {
        it('gets all cases from a unit enum', function (): void {
            $cases = Introspect::enum(TestUnitEnum::class)->cases();
            expect($cases)->toBe(['Draft', 'Published', 'Archived']);
        });

        it('gets all cases from a backed string enum', function (): void {
            $cases = Introspect::enum(TestBackedStringEnum::class)->cases();
            expect($cases)->toBe(['Pending', 'Active', 'Completed']);
        });

        it('gets all cases from a backed int enum', function (): void {
            $cases = Introspect::enum(TestBackedIntEnum::class)->cases();
            expect($cases)->toBe(['Low', 'Medium', 'High']);
        });

        it('gets all values from a backed string enum', function (): void {
            $values = Introspect::enum(TestBackedStringEnum::class)->values();
            expect($values)->toBe(['pending', 'active', 'completed']);
        });

        it('gets all values from a backed int enum', function (): void {
            $values = Introspect::enum(TestBackedIntEnum::class)->values();
            expect($values)->toBe([1, 2, 3]);
        });

        it('returns empty array for values on unit enum', function (): void {
            $values = Introspect::enum(TestUnitEnum::class)->values();
            expect($values)->toBe([]);
        });

        it('gets backed type for string enum', function (): void {
            $type = Introspect::enum(TestBackedStringEnum::class)->backedType();
            expect($type)->toBe('string');
        });

        it('gets backed type for int enum', function (): void {
            $type = Introspect::enum(TestBackedIntEnum::class)->backedType();
            expect($type)->toBe('int');
        });

        it('returns null backed type for unit enum', function (): void {
            $type = Introspect::enum(TestUnitEnum::class)->backedType();
            expect($type)->toBeNull();
        });

        it('identifies backed enum correctly', function (): void {
            expect(Introspect::enum(TestBackedStringEnum::class)->isBacked())->toBeTrue();
            expect(Introspect::enum(TestBackedIntEnum::class)->isBacked())->toBeTrue();
        });

        it('identifies unit enum correctly', function (): void {
            expect(Introspect::enum(TestUnitEnum::class)->isBacked())->toBeFalse();
        });

        it('filters by backed enum', function (): void {
            $result = Introspect::enum(TestBackedStringEnum::class)
                ->whereBacked()
                ->passes();
            expect($result)->toBeTrue();
        });

        it('filters by unit enum', function (): void {
            $result = Introspect::enum(TestUnitEnum::class)
                ->whereUnit()
                ->passes();
            expect($result)->toBeTrue();
        });

        it('gets all methods from enum', function (): void {
            $methods = Introspect::enum(TestEnumWithMethods::class)->methods();
            expect($methods)->toContain('label');
            expect($methods)->toContain('color');
        });

        it('filters by method existence', function (): void {
            $result = Introspect::enum(TestEnumWithMethods::class)
                ->whereHasMethod('label')
                ->passes();
            expect($result)->toBeTrue();
        });

        it('filters by public method existence', function (): void {
            $result = Introspect::enum(TestEnumWithMethods::class)
                ->whereHasPublicMethod('label')
                ->passes();
            expect($result)->toBeTrue();
        });

        it('gets all traits from enum', function (): void {
            $traits = Introspect::enum(TestEnumWithTrait::class)->traits();
            expect($traits)->toContain(TestEnumTrait::class);
        });

        it('filters by trait usage', function (): void {
            $result = Introspect::enum(TestEnumWithTrait::class)
                ->whereUsesTrait(TestEnumTrait::class)
                ->passes();
            expect($result)->toBeTrue();
        });

        it('gets all interfaces from enum', function (): void {
            $interfaces = Introspect::enum(TestEnumWithInterface::class)->interfaces();
            expect($interfaces)->toContain(TestEnumInterface::class);
        });

        it('filters by interface implementation', function (): void {
            $result = Introspect::enum(TestEnumWithInterface::class)
                ->whereImplements(TestEnumInterface::class)
                ->passes();
            expect($result)->toBeTrue();
        });

        it('gets all attributes from enum', function (): void {
            $attributes = Introspect::enum(TestEnumWithAttribute::class)->attributes();
            expect($attributes)->toHaveCount(1);
            expect($attributes[0])->toBeInstanceOf(TestEnumAttribute::class);
        });

        it('filters attributes by name', function (): void {
            $attributes = Introspect::enum(TestEnumWithAttribute::class)
                ->attributes(TestEnumAttribute::class);
            expect($attributes)->toHaveCount(1);
        });

        it('filters by attribute existence', function (): void {
            $result = Introspect::enum(TestEnumWithAttribute::class)
                ->whereHasAttribute(TestEnumAttribute::class)
                ->passes();
            expect($result)->toBeTrue();
        });

        it('converts unit enum to array', function (): void {
            $result = Introspect::enum(TestUnitEnum::class)->toArray();
            expect($result)->toHaveKeys(['name', 'namespace', 'short_name', 'is_backed', 'backed_type', 'cases', 'values', 'traits', 'interfaces', 'methods']);
            expect($result['is_backed'])->toBeFalse();
            expect($result['backed_type'])->toBeNull();
            expect($result['cases'])->toBe(['Draft', 'Published', 'Archived']);
            expect($result['values'])->toBe([]);
        });

        it('converts backed enum to array', function (): void {
            $result = Introspect::enum(TestBackedStringEnum::class)->toArray();
            expect($result)->toHaveKeys(['name', 'namespace', 'short_name', 'is_backed', 'backed_type', 'cases', 'values', 'traits', 'interfaces', 'methods']);
            expect($result['is_backed'])->toBeTrue();
            expect($result['backed_type'])->toBe('string');
            expect($result['cases'])->toBe(['Pending', 'Active', 'Completed']);
            expect($result['values'])->toBe(['pending', 'active', 'completed']);
        });

        it('returns enum name when filters pass', function (): void {
            $result = Introspect::enum(TestBackedStringEnum::class)
                ->whereBacked()
                ->get();
            expect($result)->toBe(TestBackedStringEnum::class);
        });
    });

    describe('Edge Cases', function (): void {
        it('returns null when filters do not pass', function (): void {
            $result = Introspect::enum(TestUnitEnum::class)
                ->whereBacked()
                ->get();
            expect($result)->toBeNull();
        });

        it('identifies unit enum when filtering for backed', function (): void {
            $result = Introspect::enum(TestUnitEnum::class)
                ->whereBacked()
                ->passes();
            expect($result)->toBeFalse();
        });

        it('identifies backed enum when filtering for unit', function (): void {
            $result = Introspect::enum(TestBackedStringEnum::class)
                ->whereUnit()
                ->passes();
            expect($result)->toBeFalse();
        });

        it('returns false when enum does not have trait', function (): void {
            $result = Introspect::enum(TestUnitEnum::class)
                ->whereUsesTrait(TestEnumTrait::class)
                ->passes();
            expect($result)->toBeFalse();
        });

        it('returns false when enum does not implement interface', function (): void {
            $result = Introspect::enum(TestUnitEnum::class)
                ->whereImplements(TestEnumInterface::class)
                ->passes();
            expect($result)->toBeFalse();
        });

        it('returns false when enum does not have method', function (): void {
            $result = Introspect::enum(TestUnitEnum::class)
                ->whereHasMethod('nonExistentMethod')
                ->passes();
            expect($result)->toBeFalse();
        });

        it('returns false when enum does not have attribute', function (): void {
            $result = Introspect::enum(TestUnitEnum::class)
                ->whereHasAttribute(TestEnumAttribute::class)
                ->passes();
            expect($result)->toBeFalse();
        });

        it('chains multiple filters with AND logic', function (): void {
            $result = Introspect::enum(TestEnumWithTrait::class)
                ->whereUsesTrait(TestEnumTrait::class)
                ->whereBacked()
                ->whereHasMethod('label')
                ->passes();
            expect($result)->toBeTrue();
        });

        it('fails when any filter in chain fails', function (): void {
            $result = Introspect::enum(TestEnumWithTrait::class)
                ->whereUsesTrait(TestEnumTrait::class)
                ->whereUnit() // This will fail because TestEnumWithTrait is backed
                ->passes();
            expect($result)->toBeFalse();
        });

        it('returns empty array when filtering attributes by non-existent name', function (): void {
            $attributes = Introspect::enum(TestEnumWithAttribute::class)
                ->attributes('NonExistentAttribute');
            expect($attributes)->toBe([]);
        });
    });
});

// Test fixtures
/**
 * @author Brian Faust <brian@cline.sh>
 */
trait TestEnumTrait
{
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Active => 'Active',
            self::Completed => 'Completed',
        };
    }
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
interface TestEnumInterface
{
    public function label(): string;
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
#[Attribute()]
final class TestEnumAttribute {}

/**
 * @author Brian Faust <brian@cline.sh>
 */
enum TestUnitEnum
{
    case Draft;
    case Published;
    case Archived;
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
enum TestBackedStringEnum: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Completed = 'completed';
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
enum TestBackedIntEnum: int
{
    case Low = 1;
    case Medium = 2;
    case High = 3;
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
enum TestEnumWithMethods: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Active => 'Active',
            self::Completed => 'Completed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'yellow',
            self::Active => 'green',
            self::Completed => 'blue',
        };
    }
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
enum TestEnumWithTrait: string
{
    use TestEnumTrait;

    case Pending = 'pending';
    case Active = 'active';
    case Completed = 'completed';
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
enum TestEnumWithInterface: string implements TestEnumInterface
{
    case Pending = 'pending';
    case Active = 'active';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Active => 'Active',
            self::Completed => 'Completed',
        };
    }
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
#[TestEnumAttribute()]
enum TestEnumWithAttribute: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Completed = 'completed';
}
