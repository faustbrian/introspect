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
- 🛠️ **Standalone Helpers** - Use as functions or fluent API
- 📚 **Comprehensive Cookbook** - Real-world examples

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
