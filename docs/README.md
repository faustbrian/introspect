---
title: Getting Started
description: Runtime introspection helpers for PHP 8.4+ featuring trait detection, class hierarchy inspection, and reflection utilities.
---

Runtime introspection helpers for PHP 8.4+ featuring trait detection, class hierarchy inspection, and reflection utilities.

## Requirements

Introspect requires PHP 8.4+.

## Installation

Install Introspect with composer:

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

## Entry Points

### Core Introspection

```php
Introspect::class($className)       // Single class introspection
Introspect::instance($object)       // Object instance introspection
Introspect::traits()                // Query all traits
Introspect::interfaces()            // Query all interfaces
Introspect::classes()               // Query declared classes
```

### Laravel Introspection

```php
Introspect::views()                 // Query Laravel Blade views
Introspect::routes()                // Query Laravel routes
Introspect::middleware()            // Query Laravel middleware
Introspect::events()                // Query Laravel events and listeners
Introspect::jobs()                  // Query Laravel queue jobs
Introspect::providers()             // Query service providers
Introspect::models()                // Query Eloquent models
Introspect::model($modelClass)      // Detailed model introspection
```

### PHP Type Introspection

```php
Introspect::enum($enumName)         // Inspect PHP 8.1+ enums
Introspect::constants($className)   // Inspect class constants
Introspect::callable($callable)     // Inspect closures, invokables, callable arrays
Introspect::method($class, $name)   // Detailed method introspection
```

## Features

- **Fluent API** - Laravel-inspired chainable query builders
- **Wildcard Support** - Pattern matching with `whereNameEquals('App\Traits\*')`
- **Class Introspection** - Inspect traits, interfaces, methods, properties, attributes
- **Instance Introspection** - Work directly with object instances
- **Trait & Interface Queries** - Find and filter across your codebase
- **Laravel Integration** - Query views, routes, middleware, events, jobs, providers
- **Model Discovery** - Find and introspect Eloquent models with relationships
- **Enum Inspection** - Introspect PHP 8.1+ enums with backing types and cases
- **Callable Inspection** - Analyze closures, invokables, and callable arrays
- **Standalone Helpers** - Use as functions or fluent API
