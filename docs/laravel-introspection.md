---
title: Laravel Introspection
description: Query and filter Laravel views, routes, middleware, events, jobs, and service providers at runtime.
---

Introspect provides fluent query builders for Laravel-specific constructs including views, routes, middleware, events, queue jobs, and service providers.

## Views Introspection

Query Laravel Blade views with filtering by name patterns, extends relationships, and include directives.

```php
use Cline\Introspect\Introspect;

// Find all views matching a pattern
$views = Introspect::views()
    ->whereNameEquals('layouts.*')
    ->get();

// Find views that extend a layout
$views = Introspect::views()
    ->whereExtends('layouts.app')
    ->get();

// Find views that include a partial
$views = Introspect::views()
    ->whereUses('partials.header')
    ->get();

// Find views used by another view
$views = Introspect::views()
    ->whereUsedBy('pages.home')
    ->get();

// Complex OR queries
$views = Introspect::views()
    ->whereNameStartsWith('admin.')
    ->or(fn($q) => $q->whereNameStartsWith('dashboard.'))
    ->get();
```

### View Filters

| Method | Description |
|--------|-------------|
| `whereNameEquals($pattern)` | Match view name with wildcards |
| `whereNameStartsWith($prefix)` | Filter by name prefix |
| `whereNameEndsWith($suffix)` | Filter by name suffix |
| `whereNameContains($substring)` | Filter by substring |
| `whereExtends($layout)` | Views that extend a layout |
| `whereDoesntExtend($layout)` | Views that don't extend a layout |
| `whereUses($view)` | Views that include a view |
| `whereDoesntUse($view)` | Views that don't include a view |
| `whereUsedBy($view)` | Views included by another view |
| `whereNotUsedBy($view)` | Views not included by another view |
| `or($callback)` | Add OR logic |

## Routes Introspection

Query Laravel routes with filtering by controller, middleware, name patterns, paths, and HTTP methods.

```php
use Cline\Introspect\Introspect;
use App\Http\Controllers\UserController;

// Find routes using a specific controller
$routes = Introspect::routes()
    ->whereUsesController(UserController::class)
    ->get();

// Find routes with specific middleware
$routes = Introspect::routes()
    ->whereUsesMiddleware('auth')
    ->get();

// Find routes by name pattern
$routes = Introspect::routes()
    ->whereNameEquals('admin.*')
    ->get();

// Find API routes by path
$routes = Introspect::routes()
    ->wherePathStartsWith('/api')
    ->get();

// Find POST routes
$routes = Introspect::routes()
    ->whereUsesMethod('POST')
    ->get();

// Complex queries with OR logic
$routes = Introspect::routes()
    ->whereUsesMiddleware('auth')
    ->or(fn($q) => $q->whereNameStartsWith('public.'))
    ->get();
```

### Route Filters

| Method | Description |
|--------|-------------|
| `whereUsesController($class, $method?)` | Filter by controller and optional method |
| `whereUsesMiddleware($middleware)` | Filter by single middleware |
| `whereUsesMiddlewares($array, $all)` | Filter by multiple middlewares (all or any) |
| `whereDoesntUseMiddleware($middleware)` | Exclude routes with middleware |
| `whereNameEquals($pattern)` | Match route name with wildcards |
| `whereNameStartsWith($prefix)` | Filter by name prefix |
| `whereNameEndsWith($suffix)` | Filter by name suffix |
| `whereNameDoesntEqual($pattern)` | Exclude routes by name pattern |
| `wherePathEquals($pattern)` | Match path with wildcards |
| `wherePathStartsWith($prefix)` | Filter by path prefix |
| `wherePathEndsWith($suffix)` | Filter by path suffix |
| `wherePathContains($substring)` | Filter by path substring |
| `whereUsesMethod($method)` | Filter by HTTP method |
| `or($callback)` | Add OR logic |

## Middleware Introspection

Query Laravel middleware including aliases, groups, and global middleware.

```php
use Cline\Introspect\Introspect;

// Get all middleware
$middleware = Introspect::middleware()->all();

// Get middleware groups
$groups = Introspect::middleware()->groups();

// Get middleware aliases
$aliases = Introspect::middleware()->aliases();

// Get middleware priority
$priority = Introspect::middleware()->priority();

// Filter by pattern
$middleware = Introspect::middleware()
    ->whereNameEquals('auth*')
    ->get();

// Find global middleware
$global = Introspect::middleware()
    ->whereGlobal()
    ->get();

// Find middleware in specific group
$webMiddleware = Introspect::middleware()
    ->whereInGroup('web')
    ->get();

// Get comprehensive middleware info
$info = Introspect::middleware()->toArray();
```

### Middleware Methods

| Method | Description |
|--------|-------------|
| `all()` | Get all registered middleware classes |
| `aliases()` | Get middleware alias mappings |
| `groups()` | Get middleware group definitions |
| `priority()` | Get middleware priority order |
| `whereNameEquals($pattern)` | Filter by name pattern |
| `whereGlobal()` | Filter to global middleware |
| `whereInGroup($group)` | Filter to middleware in a group |
| `toArray()` | Export all middleware info |

## Events Introspection

Query Laravel events and their listeners with filtering by name patterns and listener classes.

```php
use Cline\Introspect\Introspect;
use App\Listeners\SendEmailNotification;

// Get all registered events
$events = Introspect::events()->all();

// Find events by pattern
$events = Introspect::events()
    ->whereNameEquals('App\Events\*')
    ->get();

// Find events by namespace
$events = Introspect::events()
    ->whereNameStartsWith('App\Events')
    ->get();

// Find events with a specific listener
$events = Introspect::events()
    ->whereHasListener(SendEmailNotification::class)
    ->get();

// Get listeners for a specific event
$listeners = Introspect::events()
    ->listenersFor(UserCreated::class);

// Get all event-to-listener mappings
$mappings = Introspect::events()->toArray();

// Complex OR queries
$events = Introspect::events()
    ->whereNameStartsWith('App\Events')
    ->or(fn($q) => $q->whereNameStartsWith('Illuminate\Auth'))
    ->get();
```

### Event Filters

| Method | Description |
|--------|-------------|
| `all()` | Get all registered events |
| `whereNameEquals($pattern)` | Filter by name pattern |
| `whereNameStartsWith($prefix)` | Filter by namespace prefix |
| `whereNameEndsWith($suffix)` | Filter by name suffix |
| `whereHasListener($listener)` | Filter by listener class |
| `listenersFor($event)` | Get listeners for an event |
| `or($callback)` | Add OR logic |
| `toArray()` | Export event-listener mappings |

## Jobs Introspection

Query Laravel queue jobs with filtering by queue, connection, and job characteristics.

```php
use Cline\Introspect\Introspect;

// Find jobs by queue name
$jobs = Introspect::jobs()
    ->whereQueue('emails')
    ->get();

// Find jobs by connection
$jobs = Introspect::jobs()
    ->whereConnection('redis')
    ->get();

// Filter by name pattern
$jobs = Introspect::jobs()
    ->whereNameEquals('App\Jobs\*')
    ->get();

// Find unique jobs (implements ShouldBeUnique)
$jobs = Introspect::jobs()
    ->whereUnique()
    ->get();

// Find encrypted jobs
$jobs = Introspect::jobs()
    ->whereEncrypted()
    ->get();

// Use OR logic
$jobs = Introspect::jobs()
    ->whereQueue('emails')
    ->or(fn($q) => $q->whereQueue('notifications'))
    ->get();

// Search within specific job classes
$jobs = Introspect::jobs()
    ->in([SendWelcomeEmail::class, SendOrderConfirmation::class])
    ->whereQueue('emails')
    ->get();
```

### Job Filters

| Method | Description |
|--------|-------------|
| `in($jobs)` | Limit search to specific job classes |
| `whereQueue($queue)` | Filter by queue name |
| `whereConnection($connection)` | Filter by connection |
| `whereNameEquals($pattern)` | Filter by class name pattern |
| `whereUnique()` | Filter to ShouldBeUnique jobs |
| `whereEncrypted()` | Filter to ShouldBeEncrypted jobs |
| `or($callback)` | Add OR logic |

## Service Providers Introspection

Query Laravel service providers with filtering by deferred status and provided services.

```php
use Cline\Introspect\Introspect;

// Get all registered providers
$providers = Introspect::providers()->all();

// Find deferred providers
$providers = Introspect::providers()
    ->whereDeferred()
    ->get();

// Find eager-loaded providers
$providers = Introspect::providers()
    ->whereNotDeferred()
    ->get();

// Find providers by namespace pattern
$providers = Introspect::providers()
    ->whereNameEquals('App\Providers\*')
    ->get();

// Find providers that provide a specific service
$providers = Introspect::providers()
    ->whereProvides(SomeService::class)
    ->get();

// Check if a provider is registered
$isRegistered = Introspect::providers()
    ->isRegistered(MyProvider::class);

// Use OR logic
$providers = Introspect::providers()
    ->whereNameStartsWith('App\Providers')
    ->or(fn($q) => $q->whereDeferred())
    ->get();
```

### Provider Filters

| Method | Description |
|--------|-------------|
| `all()` | Get all registered providers |
| `whereNameEquals($pattern)` | Filter by name pattern |
| `whereNameStartsWith($prefix)` | Filter by namespace prefix |
| `whereDeferred()` | Filter to deferred providers |
| `whereNotDeferred()` | Filter to eager providers |
| `whereProvides($service)` | Filter by provided service |
| `isRegistered($provider)` | Check if provider is registered |
| `or($callback)` | Add OR logic |

## Terminal Methods

All Laravel introspectors support these terminal methods:

| Method | Description |
|--------|-------------|
| `get()` | Get matching items as Collection |
| `first()` | Get first matching item |
| `exists()` | Check if any items match |
| `count()` | Count matching items |
