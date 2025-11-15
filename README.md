[![GitHub Workflow Status][ico-tests]][link-tests]
[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Total Downloads][ico-downloads]][link-downloads]

------

# introspect

Runtime introspection helpers for PHP 8.4+ featuring trait detection, class hierarchy inspection, and reflection utilities

## Requirements

> **Requires [PHP 8.4+](https://php.net/releases/)**

## Installation

```bash
composer require cline/introspect
```

## Quick Start

### Fluent API

```php
use Cline\Introspect\Introspect;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;

// Inspect a class
$traits = Introspect::class(User::class)
    ->whereUsesTrait(SoftDeletes::class)
    ->getAllTraits();

// Inspect an instance
$methods = Introspect::instance($user)
    ->getPublicMethods();

// Query traits with wildcards
$traits = Introspect::traits()
    ->whereNameEquals('App\Traits\*')
    ->get();

// Query interfaces
$interfaces = Introspect::interfaces()
    ->whereImplementedBy(User::class)
    ->get();

// Query views (Laravel)
$views = Introspect::views()
    ->whereExtends('layouts.app')
    ->get();

// Query routes (Laravel)
$routes = Introspect::routes()
    ->whereUsesMiddleware('auth')
    ->wherePathStartsWith('/api')
    ->get();

// Query classes
$classes = Introspect::classes()
    ->whereExtends(Model::class)
    ->get();

// Query models
$models = Introspect::models()
    ->whereHasFillable('email')
    ->get();

// Detailed model introspection
$schema = Introspect::model(User::class)
    ->schema();
```

### Standalone Helpers

```php
use function Cline\Introspect\usesTrait;
use function Cline\Introspect\getAllTraits;

if (usesTrait(User::class, SoftDeletes::class)) {
    echo "User uses SoftDeletes";
}

$traits = getAllTraits(User::class);
```

## Features

- 🔍 **Fluent API** - Laravel-inspired chainable query builders
- 🎯 **Wildcard Support** - Pattern matching with `whereNameEquals('App\Traits\*')`
- 🏗️ **Class Introspection** - Inspect traits, interfaces, methods, properties, attributes
- 📦 **Instance Introspection** - Work directly with object instances
- 🔌 **Trait & Interface Queries** - Find and filter across your codebase
- 🎨 **Views & Routes** - Query Laravel views and routes with filters
- 📊 **Model Discovery** - Find and introspect Eloquent models
- 🛠️ **Standalone Helpers** - Use as functions or fluent API
- 📚 **Comprehensive Cookbook** - Real-world examples
- ✅ **100% Test Coverage** - Fully tested with Pest PHP

## Complete API Reference

### Entry Points

```php
Introspect::class($className)      // Single class introspection
Introspect::instance($object)       // Object instance introspection
Introspect::traits()                // Query all traits
Introspect::interfaces()            // Query all interfaces
Introspect::views()                 // Query Laravel views
Introspect::routes()                // Query Laravel routes
Introspect::classes()               // Query declared classes
Introspect::models()                // Query Eloquent models
Introspect::model($modelClass)      // Detailed model introspection
```

### Views Query Builder

```php
Introspect::views()
    ->whereNameEquals('layouts.*')          // Wildcard support
    ->whereNameStartsWith('admin.')
    ->whereNameEndsWith('.index')
    ->whereNameContains('user')
    ->whereExtends('layouts.app')           // Layout inheritance
    ->whereDoesntExtend('layouts.guest')
    ->whereUses('components.button')        // @include detection
    ->whereDoesntUse('components.modal')
    ->whereUsedBy('pages.*')                // Parent view detection
    ->whereNotUsedBy('emails.*')
    ->or(fn($q) => $q->whereNameStartsWith('public.'))
    ->get()                                  // Returns Collection<string>
    ->first()
    ->exists()
    ->count()
```

### Routes Query Builder

```php
Introspect::routes()
    ->whereUsesController(UserController::class, 'index')
    ->whereUsesMiddleware('auth')
    ->whereUsesMiddlewares(['auth', 'verified'], all: true)
    ->whereDoesntUseMiddleware('guest')
    ->whereNameEquals('admin.*')            // Wildcard support
    ->whereNameStartsWith('api.')
    ->whereNameEndsWith('.show')
    ->whereNameDoesntEqual('public.*')
    ->wherePathEquals('/users')
    ->wherePathStartsWith('/api')           // Wildcard support
    ->wherePathEndsWith('/edit')
    ->wherePathContains('admin')
    ->whereUsesMethod('POST')               // HTTP method
    ->or(fn($q) => $q->wherePathStartsWith('/public'))
    ->get()                                  // Returns Collection<Route>
    ->first()
    ->exists()
    ->count()
```

### Classes Query Builder

```php
Introspect::classes()
    ->whereName('App\Models\*')             // Namespace patterns
    ->whereNameStartsWith('App\Services')
    ->whereNameEndsWith('Controller')
    ->whereNameContains('Repository')
    ->whereExtends(Model::class)
    ->whereImplements(ShouldQueue::class)
    ->whereUses(Dispatchable::class)
    ->or(fn($q) => $q->whereImplements(Arrayable::class))
    ->get()                                  // Returns Collection<string>
    ->first()
    ->exists()
    ->count()
```

### Models Query Builder

```php
Introspect::models()
    ->whereHasProperty('email')
    ->whereDoesntHaveProperty('password')
    ->whereHasProperties(['name', 'email'], all: true)
    ->whereHasFillable('email')
    ->whereHasFillableProperties(['name', 'email'])
    ->whereHasHidden('password')
    ->whereHasHiddenProperties(['password', 'remember_token'])
    ->whereHasAppended('full_name')
    ->whereHasAppendedProperties(['avatar_url', 'is_admin'])
    ->whereHasReadable('email')             // Public or has accessor
    ->whereHasReadableProperties(['email', 'name'])
    ->whereHasWritable('name')              // Fillable or public
    ->whereHasWritableProperties(['name', 'email'])
    ->whereHasRelationship('posts')
    ->whereDoesntHaveRelationship('comments')
    ->or(fn($q) => $q->whereHasFillable('title'))
    ->get()                                  // Returns Collection<string>
    ->first()
    ->exists()
    ->count()
```

### Model Introspection (Detailed)

```php
$introspector = Introspect::model(User::class);

$introspector->properties()         // All properties (fillable, hidden, appended, casts)
$introspector->schema()             // JSON schema export
$introspector->fillable()           // Fillable attributes
$introspector->hidden()             // Hidden attributes
$introspector->appended()           // Appended attributes
$introspector->casts()              // Cast definitions
$introspector->relationships()      // Relationship methods
$introspector->table()              // Table name
$introspector->primaryKey()         // Primary key
$introspector->toArray()            // Complete model information
```

## Documentation

See the [cookbook](cookbook/) directory:

- [Fluent API Guide](cookbook/fluent-api.md) - Complete API reference with wildcards
- [Trait Inspection](cookbook/trait-inspection.md)
- [Class Hierarchy](cookbook/class-hierarchy.md)
- [Method Inspection](cookbook/method-inspection.md)
- [Property Inspection](cookbook/property-inspection.md)
- [Class Utilities](cookbook/class-utilities.md)
- [Attribute Inspection](cookbook/attribute-inspection.md)

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CODE_OF_CONDUCT](CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please use the [GitHub security reporting form][link-security] rather than the issue queue.

## Credits

- [Brian Faust][link-maintainer]
- [All Contributors][link-contributors]

## License

The MIT License. Please see [License File](LICENSE.md) for more information.

[ico-tests]: https://github.com/faustbrian/introspect/actions/workflows/quality-assurance.yaml/badge.svg
[ico-version]: https://img.shields.io/packagist/v/cline/introspect.svg
[ico-license]: https://img.shields.io/badge/License-MIT-green.svg
[ico-downloads]: https://img.shields.io/packagist/dt/cline/introspect.svg

[link-tests]: https://github.com/faustbrian/introspect/actions
[link-packagist]: https://packagist.org/packages/cline/introspect
[link-downloads]: https://packagist.org/packages/cline/introspect
[link-security]: https://github.com/faustbrian/introspect/security
[link-maintainer]: https://github.com/faustbrian
[link-contributors]: ../../contributors
