---
title: Enum Introspection
description: Inspect PHP 8.1+ enums including cases, backing types, traits, interfaces, and attributes.
---

Introspect provides comprehensive introspection for PHP 8.1+ enums, supporting both unit enums and backed enums.

## Basic Usage

```php
use Cline\Introspect\Introspect;

enum Status: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Inactive = 'inactive';
}

// Create an enum introspector
$enum = Introspect::enum(Status::class);

// Get all case names
$cases = $enum->cases();
// ['Pending', 'Active', 'Inactive']

// Get all backing values (for backed enums)
$values = $enum->values();
// ['pending', 'active', 'inactive']

// Get the backing type
$type = $enum->backedType();
// 'string'

// Check if backed enum
$isBacked = $enum->isBacked();
// true
```

## Filtering Enums

Use fluent filters to check enum characteristics:

```php
use Cline\Introspect\Introspect;

// Check if enum uses a trait
$enum = Introspect::enum(Status::class)
    ->whereUsesTrait(HasDescription::class)
    ->get(); // Returns 'Status' or null

// Check if enum implements an interface
$enum = Introspect::enum(Status::class)
    ->whereImplements(Labelable::class)
    ->passes(); // true/false

// Filter to backed enums only
$enum = Introspect::enum(Status::class)
    ->whereBacked()
    ->get();

// Filter to unit enums only
$enum = Introspect::enum(Priority::class)
    ->whereUnit()
    ->get();

// Check for methods
$enum = Introspect::enum(Status::class)
    ->whereHasMethod('label')
    ->whereHasPublicMethod('color')
    ->get();

// Check for attributes
$enum = Introspect::enum(Status::class)
    ->whereHasAttribute(Description::class)
    ->get();
```

## Enum Information

Extract detailed information about an enum:

```php
use Cline\Introspect\Introspect;

$enum = Introspect::enum(Status::class);

// Get all public methods
$methods = $enum->methods();
// ['cases', 'from', 'tryFrom', 'label', 'color']

// Get all traits
$traits = $enum->traits();
// ['App\Enums\Concerns\HasLabel']

// Get all interfaces
$interfaces = $enum->interfaces();
// ['UnitEnum', 'BackedEnum', 'App\Contracts\Labelable']

// Get attributes
$attributes = $enum->attributes();
// [Description('User account status')]

// Get specific attribute type
$description = $enum->attributes(Description::class);

// Get reflection instance for advanced operations
$reflection = $enum->getReflection();
```

## Exporting Enum Data

Export comprehensive enum information:

```php
use Cline\Introspect\Introspect;

$data = Introspect::enum(Status::class)->toArray();

// Returns:
// [
//     'name' => 'App\Enums\Status',
//     'namespace' => 'App\Enums',
//     'short_name' => 'Status',
//     'is_backed' => true,
//     'backed_type' => 'string',
//     'cases' => ['Pending', 'Active', 'Inactive'],
//     'values' => ['pending', 'active', 'inactive'],
//     'traits' => ['App\Enums\Concerns\HasLabel'],
//     'interfaces' => ['UnitEnum', 'BackedEnum'],
//     'methods' => ['cases', 'from', 'tryFrom', 'label'],
// ]
```

## Unit vs Backed Enums

```php
use Cline\Introspect\Introspect;

// Unit enum (no backing values)
enum Priority
{
    case Low;
    case Medium;
    case High;
}

$enum = Introspect::enum(Priority::class);
$enum->isBacked();     // false
$enum->backedType();   // null
$enum->values();       // [] (empty for unit enums)
$enum->cases();        // ['Low', 'Medium', 'High']

// Backed enum with int
enum Level: int
{
    case Bronze = 1;
    case Silver = 2;
    case Gold = 3;
}

$enum = Introspect::enum(Level::class);
$enum->isBacked();     // true
$enum->backedType();   // 'int'
$enum->values();       // [1, 2, 3]
$enum->cases();        // ['Bronze', 'Silver', 'Gold']
```

## Available Methods

### Filter Methods

| Method | Description |
|--------|-------------|
| `whereUsesTrait($trait)` | Filter by trait usage |
| `whereImplements($interface)` | Filter by interface implementation |
| `whereBacked()` | Filter to backed enums |
| `whereUnit()` | Filter to unit enums |
| `whereHasMethod($method)` | Filter by method existence |
| `whereHasPublicMethod($method)` | Filter by public method existence |
| `whereHasAttribute($attribute)` | Filter by attribute presence |

### Query Methods

| Method | Description |
|--------|-------------|
| `cases()` | Get all case names |
| `values()` | Get all backing values (backed enums only) |
| `backedType()` | Get backing type ('int', 'string', or null) |
| `isBacked()` | Check if enum is backed |
| `methods()` | Get all public methods |
| `traits()` | Get all traits |
| `interfaces()` | Get all interfaces |
| `attributes($name?)` | Get attributes, optionally filtered by type |

### Terminal Methods

| Method | Description |
|--------|-------------|
| `passes()` | Check if all filters pass |
| `get()` | Get enum name if filters pass, null otherwise |
| `getReflection()` | Get underlying ReflectionEnum |
| `toArray()` | Export all enum information |
