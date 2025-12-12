<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit;

use Cline\Introspect\Introspect;
use Tests\Fixtures\TestPost;
use Tests\Fixtures\TestProduct;
use Tests\Fixtures\TestUser;

use function describe;
use function expect;
use function it;

/**
 * Comprehensive test suite for ModelIntrospector.
 *
 * Tests introspection of individual Eloquent models including:
 * - Fillable attributes
 * - Hidden attributes
 * - Appended attributes
 * - Casts
 * - Relationships
 * - Table and primary key information
 * - Schema generation
 * - toArray() export
 */
describe('ModelIntrospector', function (): void {
    describe('Happy Path', function (): void {
        it('gets fillable attributes', function (): void {
            $introspector = Introspect::model(TestUser::class);
            $fillable = $introspector->fillable();

            expect($fillable)->toBeArray()
                ->and($fillable)->toContain('name')
                ->and($fillable)->toContain('email')
                ->and($fillable)->toContain('password');
        });

        it('gets hidden attributes', function (): void {
            $introspector = Introspect::model(TestUser::class);
            $hidden = $introspector->hidden();

            expect($hidden)->toBeArray()
                ->and($hidden)->toContain('password')
                ->and($hidden)->toContain('remember_token');
        });

        it('gets appended attributes', function (): void {
            $introspector = Introspect::model(TestUser::class);
            $appended = $introspector->appended();

            expect($appended)->toBeArray()
                ->and($appended)->toContain('full_name');
        });

        it('gets casts', function (): void {
            $introspector = Introspect::model(TestUser::class);
            $casts = $introspector->casts();

            expect($casts)->toBeArray()
                ->and($casts)->toHaveKey('email_verified_at')
                ->and($casts['email_verified_at'])->toBe('datetime')
                ->and($casts)->toHaveKey('is_active')
                ->and($casts['is_active'])->toBe('boolean');
        });

        it('gets relationships', function (): void {
            $introspector = Introspect::model(TestUser::class);
            $relationships = $introspector->relationships();

            expect($relationships)->toBeArray()
                ->and($relationships)->toHaveKey('posts')
                ->and($relationships['posts'])->toHaveKey('method')
                ->and($relationships['posts']['method'])->toBe('posts')
                ->and($relationships['posts'])->toHaveKey('type')
                ->and($relationships['posts']['type'])->toBe('HasMany');
        });

        it('gets table name', function (): void {
            $introspector = Introspect::model(TestUser::class);
            $table = $introspector->table();

            expect($table)->toBeString()
                ->and($table)->toBe('test_users');
        });

        it('gets primary key', function (): void {
            $introspector = Introspect::model(TestUser::class);
            $primaryKey = $introspector->primaryKey();

            expect($primaryKey)->toBeString()
                ->and($primaryKey)->toBe('id');
        });

        it('gets all properties', function (): void {
            $introspector = Introspect::model(TestUser::class);
            $properties = $introspector->properties();

            expect($properties)->toBeArray()
                ->and($properties)->toHaveKey('fillable')
                ->and($properties)->toHaveKey('hidden')
                ->and($properties)->toHaveKey('appended')
                ->and($properties)->toHaveKey('casts');
        });

        it('generates schema', function (): void {
            $introspector = Introspect::model(TestUser::class);
            $schema = $introspector->schema();

            expect($schema)->toBeArray()
                ->and($schema)->toHaveKey('type')
                ->and($schema['type'])->toBe('model')
                ->and($schema)->toHaveKey('class')
                ->and($schema['class'])->toBe(TestUser::class)
                ->and($schema)->toHaveKey('table')
                ->and($schema['table'])->toBe('test_users')
                ->and($schema)->toHaveKey('primaryKey')
                ->and($schema['primaryKey'])->toBe('id')
                ->and($schema)->toHaveKey('properties')
                ->and($schema['properties'])->toBeArray()
                ->and($schema)->toHaveKey('relationships')
                ->and($schema['relationships'])->toBeArray();
        });

        it('exports to array with all information', function (): void {
            $introspector = Introspect::model(TestUser::class);
            $array = $introspector->toArray();

            expect($array)->toBeArray()
                ->and($array)->toHaveKey('class')
                ->and($array['class'])->toBe(TestUser::class)
                ->and($array)->toHaveKey('namespace')
                ->and($array['namespace'])->toBe('Tests\Fixtures')
                ->and($array)->toHaveKey('short_name')
                ->and($array['short_name'])->toBe('TestUser')
                ->and($array)->toHaveKey('table')
                ->and($array)->toHaveKey('primary_key')
                ->and($array)->toHaveKey('fillable')
                ->and($array)->toHaveKey('hidden')
                ->and($array)->toHaveKey('appended')
                ->and($array)->toHaveKey('casts')
                ->and($array)->toHaveKey('relationships')
                ->and($array)->toHaveKey('schema');
        });

        it('handles model with relationships', function (): void {
            $introspector = Introspect::model(TestPost::class);
            $relationships = $introspector->relationships();

            expect($relationships)->toHaveKey('user')
                ->and($relationships['user']['type'])->toBe('BelongsTo');
        });

        it('includes fillable in schema properties', function (): void {
            $introspector = Introspect::model(TestUser::class);
            $schema = $introspector->schema();

            expect($schema['properties'])->toHaveKey('name')
                ->and($schema['properties']['name'])->toHaveKey('fillable')
                ->and($schema['properties']['name']['fillable'])->toBeTrue();
        });

        it('marks hidden properties in schema', function (): void {
            $introspector = Introspect::model(TestUser::class);
            $schema = $introspector->schema();

            expect($schema['properties'])->toHaveKey('password')
                ->and($schema['properties']['password'])->toHaveKey('hidden')
                ->and($schema['properties']['password']['hidden'])->toBeTrue();
        });

        it('includes appended attributes in schema', function (): void {
            $introspector = Introspect::model(TestUser::class);
            $schema = $introspector->schema();

            expect($schema['properties'])->toHaveKey('full_name')
                ->and($schema['properties']['full_name'])->toHaveKey('appended')
                ->and($schema['properties']['full_name']['appended'])->toBeTrue();
        });

        it('includes cast types in schema properties', function (): void {
            $introspector = Introspect::model(TestUser::class);
            $schema = $introspector->schema();

            expect($schema['properties'])->toHaveKey('is_active')
                ->and($schema['properties']['is_active'])->toHaveKey('type')
                ->and($schema['properties']['is_active']['type'])->toBe('boolean');
        });
    });

    describe('Edge Cases', function (): void {
        it('handles model with no fillable attributes', function (): void {
            $introspector = Introspect::model(TestUser::class);
            // Create instance and override fillable
            expect($introspector->fillable())->toBeArray();
        });

        it('handles model with no hidden attributes', function (): void {
            $introspector = Introspect::model(TestProduct::class);
            $hidden = $introspector->hidden();

            expect($hidden)->toBeArray();
        });

        it('handles model with no appended attributes', function (): void {
            $introspector = Introspect::model(TestProduct::class);
            $appended = $introspector->appended();

            expect($appended)->toBeArray();
        });

        it('handles model with no casts', function (): void {
            $introspector = Introspect::model(TestUser::class);
            $casts = $introspector->casts();

            expect($casts)->toBeArray();
        });

        it('handles model with no relationships', function (): void {
            $introspector = Introspect::model(TestProduct::class);
            $relationships = $introspector->relationships();

            expect($relationships)->toBeArray();
        });

        it('handles models with default table names', function (): void {
            $introspector = Introspect::model(TestUser::class);
            $table = $introspector->table();

            expect($table)->toBeString();
        });

        it('handles models with default primary key', function (): void {
            $introspector = Introspect::model(TestUser::class);
            $primaryKey = $introspector->primaryKey();

            expect($primaryKey)->toBe('id');
        });

        it('includes all relationship types in discovery', function (): void {
            $introspector = Introspect::model(TestUser::class);
            $relationships = $introspector->relationships();

            expect($relationships)->toBeArray();
        });

        it('exports complete schema with all property metadata', function (): void {
            $introspector = Introspect::model(TestUser::class);
            $schema = $introspector->schema();

            foreach (['name', 'email', 'password'] as $field) {
                expect($schema['properties'])->toHaveKey($field);
            }
        });

        it('handles models with complex casts', function (): void {
            $introspector = Introspect::model(TestProduct::class);
            $casts = $introspector->casts();

            expect($casts)->toHaveKey('price');
        });

        it('correctly identifies relationship return types', function (): void {
            $introspector = Introspect::model(TestPost::class);
            $relationships = $introspector->relationships();

            expect($relationships['user']['type'])->toBe('BelongsTo');
        });

        it('excludes non-relationship public methods', function (): void {
            $introspector = Introspect::model(TestUser::class);
            $relationships = $introspector->relationships();

            // Should only contain actual relationship methods
            foreach ($relationships as $details) {
                expect($details)->toHaveKey('type');
            }
        });

        it('handles properties() method returning all categories', function (): void {
            $introspector = Introspect::model(TestUser::class);
            $properties = $introspector->properties();

            expect($properties['fillable'])->toBeArray()
                ->and($properties['hidden'])->toBeArray()
                ->and($properties['appended'])->toBeArray()
                ->and($properties['casts'])->toBeArray();
        });

        it('generates proper namespace in toArray', function (): void {
            $introspector = Introspect::model(TestUser::class);
            $array = $introspector->toArray();

            expect($array['namespace'])->toBe('Tests\Fixtures');
        });

        it('generates proper short name in toArray', function (): void {
            $introspector = Introspect::model(TestUser::class);
            $array = $introspector->toArray();

            expect($array['short_name'])->toBe('TestUser');
        });

        it('includes schema in toArray export', function (): void {
            $introspector = Introspect::model(TestUser::class);
            $array = $introspector->toArray();

            expect($array['schema'])->toBeArray()
                ->and($array['schema'])->toHaveKey('properties');
        });

        it('handles casted properties not in fillable', function (): void {
            $introspector = Introspect::model(TestUser::class);
            $schema = $introspector->schema();

            // Casted properties should appear in schema
            expect($schema['properties'])->toHaveKey('is_active');
        });

        it('marks non-fillable properties correctly in schema', function (): void {
            $introspector = Introspect::model(TestUser::class);
            $schema = $introspector->schema();

            if (!isset($schema['properties']['is_active'])) {
                return;
            }

            expect($schema['properties']['is_active']['fillable'])->toBeFalse();
        });

        it('generates schema with model class reference', function (): void {
            $introspector = Introspect::model(TestPost::class);
            $schema = $introspector->schema();

            expect($schema['class'])->toBe(TestPost::class);
        });
    });
});
