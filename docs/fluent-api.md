The Introspect package provides a fluent, chainable API inspired by Laravel's query builders. All introspection starts with the `Introspect` facade.

## Class Introspection

Inspect classes with `Introspect::class()`:

```php
use Cline\Introspect\Introspect;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;

// Get all traits used by a class
$traits = Introspect::class(User::class)
    ->getAllTraits();

// Check if a class meets criteria
$usesTraits = Introspect::class(User::class)
    ->whereUsesTrait(SoftDeletes::class)
    ->passes(); // true/false

// Get class if it passes filters
$class = Introspect::class(User::class)
    ->whereUsesTrait(SoftDeletes::class)
    ->whereImplements(Authenticatable::class)
    ->get(); // Returns 'App\Models\User' or null

// Get comprehensive class information
$info = Introspect::class(User::class)->toArray();
// [
//   'name' => 'App\Models\User',
//   'namespace' => 'App\Models',
//   'short_name' => 'User',
//   'is_abstract' => false,
//   'is_final' => false,
//   'is_instantiable' => true,
//   'traits' => [...],
//   'interfaces' => [...],
//   'parent' => 'Illuminate\Database\Eloquent\Model',
//   'methods' => [...],
//   'properties' => [...],
// ]
```

## Instance Introspection

Inspect object instances with `Introspect::instance()`:

```php
$user = new User();

// Get instance class information
$className = Introspect::instance($user)->getClassName(); // 'App\Models\User'
$basename = Introspect::instance($user)->getBasename(); // 'User'
$namespace = Introspect::instance($user)->getNamespace(); // 'App\Models'

// Filter instances
$instance = Introspect::instance($user)
    ->whereUsesTrait(SoftDeletes::class)
    ->whereHasMethod('save')
    ->get(); // Returns $user or null

// Get all traits
$traits = Introspect::instance($user)->getAllTraits();

// Get public methods
$methods = Introspect::instance($user)->getPublicMethods();

// Get comprehensive instance information
$info = Introspect::instance($user)->toArray();
```

## Trait Queries

Query traits with wildcard support using `Introspect::traits()`:

```php
// Find traits by pattern (wildcards supported)
$traits = Introspect::traits()
    ->whereNameEquals('App\Traits\*')
    ->get(); // Collection of trait names

// Find traits with specific naming
$auditTraits = Introspect::traits()
    ->whereNameEndsWith('Auditable')
    ->get();

// Find traits used by a specific class
$userTraits = Introspect::traits()
    ->whereUsedBy(User::class)
    ->get();

// Combine filters
$traits = Introspect::traits()
    ->whereNameStartsWith('Illuminate\Database')
    ->whereNameContains('Soft')
    ->get();

// Check existence
if (Introspect::traits()->whereNameEquals('App\Traits\Auditable')->exists()) {
    echo "Auditable trait exists";
}

// Count matching traits
$count = Introspect::traits()
    ->whereNameStartsWith('App\Traits')
    ->count();

// Get first match
$first = Introspect::traits()
    ->whereNameEndsWith('able')
    ->first();
```

## Interface Queries

Query interfaces with wildcard support using `Introspect::interfaces()`:

```php
// Find interfaces by pattern
$interfaces = Introspect::interfaces()
    ->whereNameEquals('App\Contracts\*')
    ->get();

// Find interfaces with specific naming
$contracts = Introspect::interfaces()
    ->whereNameEndsWith('Interface')
    ->get();

// Find interfaces implemented by a class
$userInterfaces = Introspect::interfaces()
    ->whereImplementedBy(User::class)
    ->get();

// Combine filters
$interfaces = Introspect::interfaces()
    ->whereNameStartsWith('Illuminate\Contracts')
    ->whereImplementedBy(User::class)
    ->get();

// Check existence
if (Introspect::interfaces()->whereNameEquals('App\Contracts\Auditable')->exists()) {
    echo "Auditable interface exists";
}

// Count and first
$count = Introspect::interfaces()->whereNameStartsWith('App\Contracts')->count();
$first = Introspect::interfaces()->whereImplementedBy(User::class)->first();
```

## Wildcard Patterns

The `whereNameEquals()` method supports wildcard patterns:

```php
// Match any trait in App\Traits namespace
Introspect::traits()
    ->whereNameEquals('App\Traits\*')
    ->get();

// Match traits ending with "able"
Introspect::traits()
    ->whereNameEquals('*able')
    ->get();

// Match traits containing "Audit"
Introspect::traits()
    ->whereNameEquals('*Audit*')
    ->get();

// Complex wildcard pattern
Introspect::traits()
    ->whereNameEquals('App\*\*Auditable')
    ->get();
```

## Chaining Filters

All query builders support method chaining for complex queries:

```php
// Complex class query
$class = Introspect::class(User::class)
    ->whereUsesTrait(SoftDeletes::class)
    ->whereImplements(Authenticatable::class)
    ->whereExtends(Model::class)
    ->whereConcrete()
    ->whereHasMethod('save')
    ->whereHasPublicMethod('getAttribute')
    ->whereHasProperty('fillable')
    ->whereHasAttribute(Route::class)
    ->get();

// Complex trait query
$traits = Introspect::traits()
    ->whereNameStartsWith('App\Traits')
    ->whereNameContains('Audit')
    ->whereNameEndsWith('able')
    ->whereUsedBy(User::class)
    ->get();

// Complex interface query
$interfaces = Introspect::interfaces()
    ->whereNameStartsWith('Illuminate\Contracts')
    ->whereNameContains('Auth')
    ->whereImplementedBy(User::class)
    ->get();
```
