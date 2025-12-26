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

describe('ConstantIntrospector', function (): void {
    describe('Happy Path', function (): void {
        it('gets all constants as name => value array', function (): void {
            $constants = Introspect::constants(TestClassWithConstants::class)->all();
            expect($constants)->toHaveKeys(['STATUS_ACTIVE', 'STATUS_INACTIVE', 'MAX_ITEMS']);
            expect($constants['STATUS_ACTIVE'])->toBe(1);
            expect($constants['STATUS_INACTIVE'])->toBe(0);
            expect($constants['MAX_ITEMS'])->toBe(100);
        });

        it('gets all constant names', function (): void {
            $names = Introspect::constants(TestClassWithConstants::class)->names();
            expect($names)->toContain('STATUS_ACTIVE');
            expect($names)->toContain('STATUS_INACTIVE');
            expect($names)->toContain('MAX_ITEMS');
        });

        it('gets detailed information about a specific constant', function (): void {
            $constant = Introspect::constants(TestClassWithConstants::class)->get('STATUS_ACTIVE');
            expect($constant)->toBeArray();
            expect($constant['name'])->toBe('STATUS_ACTIVE');
            expect($constant['value'])->toBe(1);
            expect($constant['visibility'])->toBe('public');
            expect($constant['final'])->toBeBool();
        });

        it('filters by public visibility', function (): void {
            $constants = Introspect::constants(TestClassWithVisibilityConstants::class)->wherePublic()->all();
            expect($constants)->toHaveKey('PUBLIC_CONSTANT');
            expect($constants)->not->toHaveKey('PRIVATE_CONSTANT');
            expect($constants)->not->toHaveKey('PROTECTED_CONSTANT');
        });

        it('filters by protected visibility', function (): void {
            $constants = Introspect::constants(TestClassWithVisibilityConstants::class)->whereProtected()->all();
            expect($constants)->toHaveKey('PROTECTED_CONSTANT');
            expect($constants)->not->toHaveKey('PUBLIC_CONSTANT');
            expect($constants)->not->toHaveKey('PRIVATE_CONSTANT');
        });

        it('filters by private visibility', function (): void {
            $constants = Introspect::constants(TestClassWithVisibilityConstants::class)->wherePrivate()->all();
            expect($constants)->toHaveKey('PRIVATE_CONSTANT');
            expect($constants)->not->toHaveKey('PUBLIC_CONSTANT');
            expect($constants)->not->toHaveKey('PROTECTED_CONSTANT');
        });

        it('filters by attribute presence', function (): void {
            $constants = Introspect::constants(TestClassWithAttributedConstants::class)
                ->whereHasAttribute(TestConstantAttribute::class)
                ->all();
            expect($constants)->toHaveKey('ATTRIBUTED_CONSTANT');
            expect($constants)->not->toHaveKey('NON_ATTRIBUTED_CONSTANT');
        });

        it('converts all constants to detailed array', function (): void {
            $constants = Introspect::constants(TestClassWithConstants::class)->toArray();
            expect($constants)->toBeArray();
            expect($constants)->toHaveKey('STATUS_ACTIVE');
            expect($constants['STATUS_ACTIVE'])->toHaveKeys(['name', 'value', 'visibility', 'final', 'type', 'attributes']);
        });

        it('chains multiple filters with AND logic', function (): void {
            $constants = Introspect::constants(TestClassWithMixedConstants::class)
                ->wherePublic()
                ->whereHasAttribute(TestConstantAttribute::class)
                ->all();
            expect($constants)->toHaveKey('PUBLIC_ATTRIBUTED');
            expect($constants)->not->toHaveKey('PUBLIC_NOT_ATTRIBUTED');
            expect($constants)->not->toHaveKey('PRIVATE_ATTRIBUTED');
        });
    });

    describe('Edge Cases', function (): void {
        it('returns empty array when no constants match filters', function (): void {
            $constants = Introspect::constants(TestClassWithConstants::class)
                ->wherePrivate()
                ->all();
            expect($constants)->toBeEmpty();
        });

        it('returns null for non-existent constant', function (): void {
            $constant = Introspect::constants(TestClassWithConstants::class)->get('NON_EXISTENT');
            expect($constant)->toBeNull();
        });

        it('handles class with no constants', function (): void {
            $constants = Introspect::constants(TestClassWithNoConstants::class)->all();
            expect($constants)->toBeEmpty();
        });

        it('returns empty names array when no constants match', function (): void {
            $names = Introspect::constants(TestClassWithConstants::class)
                ->wherePrivate()
                ->names();
            expect($names)->toBeEmpty();
        });

        it('handles constants with null values', function (): void {
            $constant = Introspect::constants(TestClassWithNullConstant::class)->get('NULL_CONSTANT');
            expect($constant)->toBeArray();
            expect($constant['value'])->toBeNull();
        });

        it('handles constants with array values', function (): void {
            $constant = Introspect::constants(TestClassWithArrayConstant::class)->get('ARRAY_CONSTANT');
            expect($constant)->toBeArray();
            expect($constant['value'])->toBeArray();
            expect($constant['value'])->toBe(['key' => 'value']);
        });

        it('filters by final constants', function (): void {
            $constants = Introspect::constants(TestClassWithFinalConstants::class)->whereFinal()->all();
            expect($constants)->toHaveKey('FINAL_CONSTANT');
            expect($constants)->not->toHaveKey('NON_FINAL_CONSTANT');
        });

        it('gets detailed info for protected constant', function (): void {
            $constant = Introspect::constants(TestClassWithVisibilityConstants::class)->get('PROTECTED_CONSTANT');
            expect($constant)->toBeArray();
            expect($constant['visibility'])->toBe('protected');
        });

        it('gets detailed info for private constant', function (): void {
            $constant = Introspect::constants(TestClassWithVisibilityConstants::class)->get('PRIVATE_CONSTANT');
            expect($constant)->toBeArray();
            expect($constant['visibility'])->toBe('private');
        });

        it('converts protected and private constants to array', function (): void {
            $constants = Introspect::constants(TestClassWithVisibilityConstants::class)->toArray();
            expect($constants)->toHaveKey('PROTECTED_CONSTANT');
            expect($constants['PROTECTED_CONSTANT']['visibility'])->toBe('protected');
            expect($constants)->toHaveKey('PRIVATE_CONSTANT');
            expect($constants['PRIVATE_CONSTANT']['visibility'])->toBe('private');
        });

        it('converts attributed constants to array with attributes', function (): void {
            $constants = Introspect::constants(TestClassWithAttributedConstants::class)->toArray();
            expect($constants['ATTRIBUTED_CONSTANT']['attributes'])->toHaveCount(1);
            expect($constants['ATTRIBUTED_CONSTANT']['attributes'][0])->toBeInstanceOf(TestConstantAttribute::class);
        });

        it('returns empty array from toArray when no constants match filter', function (): void {
            $constants = Introspect::constants(TestClassWithConstants::class)
                ->wherePrivate()
                ->toArray();
            expect($constants)->toBeEmpty();
        });
    });
});

// Test fixtures
/**
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
#[Attribute()]
final readonly class TestConstantAttribute
{
    public function __construct(
        public string $description = '',
    ) {}
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class TestClassWithConstants
{
    public const int STATUS_ACTIVE = 1;

    public const int STATUS_INACTIVE = 0;

    public const int MAX_ITEMS = 100;
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class TestClassWithVisibilityConstants
{
    public const string PUBLIC_CONSTANT = 'public';

    protected const string PROTECTED_CONSTANT = 'protected';

    private const string PRIVATE_CONSTANT = 'private';
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class TestClassWithAttributedConstants
{
    #[TestConstantAttribute('This is attributed')]
    public const string ATTRIBUTED_CONSTANT = 'attributed';

    public const string NON_ATTRIBUTED_CONSTANT = 'not attributed';
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class TestClassWithMixedConstants
{
    #[TestConstantAttribute()]
    public const string PUBLIC_ATTRIBUTED = 'public attributed';

    public const string PUBLIC_NOT_ATTRIBUTED = 'public not attributed';
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class TestClassWithNoConstants {}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class TestClassWithNullConstant
{
    public const null NULL_CONSTANT = null;
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class TestClassWithArrayConstant
{
    public const array ARRAY_CONSTANT = ['key' => 'value'];
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class TestClassWithFinalConstants
{
    final public const string FINAL_CONSTANT = 'final';

    public const string NON_FINAL_CONSTANT = 'not final';
}
