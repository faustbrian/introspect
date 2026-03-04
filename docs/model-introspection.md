Introspect provides comprehensive introspection for Laravel Eloquent models, including properties, relationships, schema analysis, and runtime behavior.

## Discovering Models

Query and filter Eloquent models in your application:

```php
use Cline\Introspect\Introspect;

// Get all Eloquent models
$models = Introspect::models()->get();

// Filter by namespace
$models = Introspect::models()
    ->whereNameStartsWith('App\Models')
    ->get();

// Find models using a specific trait
$models = Introspect::models()
    ->whereUsesTrait(SoftDeletes::class)
    ->get();

// Find models implementing an interface
$models = Introspect::models()
    ->whereImplements(Auditable::class)
    ->get();

// Complex OR queries
$models = Introspect::models()
    ->whereNameStartsWith('App\Models\Admin')
    ->or(fn($q) => $q->whereUsesTrait(HasFactory::class))
    ->get();
```

## Detailed Model Introspection

Inspect a single model in detail:

```php
use Cline\Introspect\Introspect;
use App\Models\User;

$model = Introspect::model(User::class);

// Basic info
$table = $model->table();           // 'users'
$primaryKey = $model->primaryKey(); // 'id'
$connection = $model->connection(); // 'mysql' or null for default

// Timestamps and soft deletes
$hasTimestamps = $model->usesTimestamps();   // true
$hasSoftDeletes = $model->usesSoftDeletes(); // true
```

## Model Properties

Inspect mass assignment and visibility settings:

```php
use Cline\Introspect\Introspect;
use App\Models\User;

$model = Introspect::model(User::class);

// Mass assignment
$fillable = $model->fillable();
// ['name', 'email', 'password']

$guarded = $model->guarded();
// ['id']

// Visibility
$hidden = $model->hidden();
// ['password', 'remember_token']

$appended = $model->appended();
// ['full_name', 'avatar_url']

// All properties at once
$properties = $model->properties();
// ['fillable' => [...], 'hidden' => [...], 'appended' => [...], 'casts' => [...]]
```

## Cast Definitions

Inspect attribute casting configuration:

```php
use Cline\Introspect\Introspect;
use App\Models\User;

$casts = Introspect::model(User::class)->casts();

// [
//     'email_verified_at' => 'datetime',
//     'password' => 'hashed',
//     'is_admin' => 'boolean',
//     'settings' => 'array',
//     'metadata' => AsCollection::class,
// ]
```

## Relationships

Discover all relationship methods on a model:

```php
use Cline\Introspect\Introspect;
use App\Models\User;

$relationships = Introspect::model(User::class)->relationships();

// [
//     'posts' => [
//         'method' => 'posts',
//         'type' => 'HasMany',
//         'related' => 'App\Models\Post',
//     ],
//     'profile' => [
//         'method' => 'profile',
//         'type' => 'HasOne',
//         'related' => 'App\Models\Profile',
//     ],
//     'roles' => [
//         'method' => 'roles',
//         'type' => 'BelongsToMany',
//         'related' => 'App\Models\Role',
//     ],
// ]
```

Supported relationship types:
- `HasOne`, `HasMany`
- `BelongsTo`, `BelongsToMany`
- `HasOneThrough`, `HasManyThrough`
- `MorphOne`, `MorphMany`
- `MorphTo`, `MorphToMany`

## Query Scopes

Discover local scopes defined on the model:

```php
use Cline\Introspect\Introspect;
use App\Models\Post;

// Local scopes (scopeActive, scopePublished, etc.)
$scopes = Introspect::model(Post::class)->scopes();
// ['active', 'published', 'byAuthor', 'recent']

// Global scopes (if registered)
$globalScopes = Introspect::model(Post::class)->globalScopes();
// ['App\Scopes\TenantScope' => 'tenant']
```

## Accessors and Mutators

Discover attribute accessors and mutators:

```php
use Cline\Introspect\Introspect;
use App\Models\User;

$model = Introspect::model(User::class);

// Accessors (getters)
$accessors = $model->accessors();
// ['full_name', 'avatar_url', 'formatted_phone']

// Mutators (setters)
$mutators = $model->mutators();
// ['password', 'phone']
```

Both old-style (`getXAttribute`/`setXAttribute`) and new-style (`Attribute` cast) accessors are detected.

## Events and Observers

Inspect model events and observers:

```php
use Cline\Introspect\Introspect;
use App\Models\User;

$model = Introspect::model(User::class);

// Model events with listeners
$events = $model->events();
// [
//     'creating' => ['creating'],
//     'created' => ['App\Events\UserCreated'],
//     'updating' => ['updating'],
// ]

// Registered observers
$observers = $model->observers();
// ['App\Observers\UserObserver']
```

## Model Schema

Export a comprehensive schema representation:

```php
use Cline\Introspect\Introspect;
use App\Models\User;

$schema = Introspect::model(User::class)->schema();

// [
//     'type' => 'model',
//     'class' => 'App\Models\User',
//     'table' => 'users',
//     'primaryKey' => 'id',
//     'properties' => [
//         'name' => ['type' => 'string', 'fillable' => true, 'hidden' => false],
//         'email' => ['type' => 'string', 'fillable' => true, 'hidden' => false],
//         'password' => ['type' => 'hashed', 'fillable' => true, 'hidden' => true],
//         'full_name' => ['type' => 'computed', 'fillable' => false, 'appended' => true],
//     ],
//     'relationships' => [...],
// ]
```

## Complete Export

Export all model information at once:

```php
use Cline\Introspect\Introspect;
use App\Models\User;

$data = Introspect::model(User::class)->toArray();

// [
//     'class' => 'App\Models\User',
//     'namespace' => 'App\Models',
//     'short_name' => 'User',
//     'table' => 'users',
//     'primary_key' => 'id',
//     'connection' => null,
//     'timestamps' => true,
//     'soft_deletes' => true,
//     'fillable' => ['name', 'email', 'password'],
//     'guarded' => ['id'],
//     'hidden' => ['password', 'remember_token'],
//     'appended' => ['full_name'],
//     'casts' => [...],
//     'relationships' => [...],
//     'scopes' => [...],
//     'accessors' => [...],
//     'mutators' => [...],
//     'events' => [...],
//     'observers' => [...],
//     'schema' => [...],
// ]
```

## Models Discovery Methods

### Filter Methods

| Method | Description |
|--------|-------------|
| `whereNameEquals($pattern)` | Filter by class name pattern |
| `whereNameStartsWith($prefix)` | Filter by namespace prefix |
| `whereNameEndsWith($suffix)` | Filter by class name suffix |
| `whereUsesTrait($trait)` | Filter by trait usage |
| `whereImplements($interface)` | Filter by interface |
| `or($callback)` | Add OR logic |

### Terminal Methods

| Method | Description |
|--------|-------------|
| `get()` | Get matching models as Collection |
| `first()` | Get first matching model |
| `exists()` | Check if any models match |
| `count()` | Count matching models |

## Model Introspector Methods

### Property Methods

| Method | Description |
|--------|-------------|
| `fillable()` | Get fillable attributes |
| `guarded()` | Get guarded attributes |
| `hidden()` | Get hidden attributes |
| `appended()` | Get appended attributes |
| `casts()` | Get cast definitions |
| `properties()` | Get all properties grouped |

### Structure Methods

| Method | Description |
|--------|-------------|
| `table()` | Get table name |
| `primaryKey()` | Get primary key column |
| `connection()` | Get database connection |
| `usesTimestamps()` | Check if timestamps enabled |
| `usesSoftDeletes()` | Check if soft deletes enabled |
| `relationships()` | Get all relationships |

### Behavior Methods

| Method | Description |
|--------|-------------|
| `scopes()` | Get local scope names |
| `globalScopes()` | Get global scopes |
| `accessors()` | Get accessor attribute names |
| `mutators()` | Get mutator attribute names |
| `events()` | Get events with listeners |
| `observers()` | Get registered observers |

### Export Methods

| Method | Description |
|--------|-------------|
| `schema()` | Get JSON schema representation |
| `toArray()` | Export all model information |
