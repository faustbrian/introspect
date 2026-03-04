Introspect provides fluent querying of class constants with support for filtering by visibility, final status, and PHP 8 attributes.

## Basic Usage

```php
use Cline\Introspect\Introspect;

class Config
{
    public const VERSION = '1.0.0';
    protected const API_URL = 'https://api.example.com';
    private const SECRET_KEY = 'secret';
    public final const MAX_RETRIES = 3;
}

// Create a constants introspector
$constants = Introspect::constants(Config::class);

// Get all constants as name => value
$all = $constants->all();
// ['VERSION' => '1.0.0', 'API_URL' => 'https://api.example.com', ...]

// Get constant names only
$names = $constants->names();
// ['VERSION', 'API_URL', 'SECRET_KEY', 'MAX_RETRIES']

// Get detailed info about a specific constant
$info = $constants->get('VERSION');
// ['name' => 'VERSION', 'value' => '1.0.0', 'visibility' => 'public', ...]
```

## Filtering by Visibility

```php
use Cline\Introspect\Introspect;

// Get only public constants
$public = Introspect::constants(Config::class)
    ->wherePublic()
    ->all();

// Get only protected constants
$protected = Introspect::constants(Config::class)
    ->whereProtected()
    ->all();

// Get only private constants
$private = Introspect::constants(Config::class)
    ->wherePrivate()
    ->all();
```

## Filtering Final Constants

PHP 8.1+ allows constants to be declared as `final`:

```php
use Cline\Introspect\Introspect;

class Settings
{
    public const TIMEOUT = 30;
    public final const MAX_CONNECTIONS = 100;
}

// Get only final constants
$final = Introspect::constants(Settings::class)
    ->whereFinal()
    ->all();
// ['MAX_CONNECTIONS' => 100]
```

## Filtering by Attributes

PHP 8 attributes can be applied to constants:

```php
use Cline\Introspect\Introspect;

#[Attribute]
class Deprecated {}

#[Attribute]
class Description {
    public function __construct(public string $text) {}
}

class ApiEndpoints
{
    #[Description('User listing')]
    public const USERS = '/api/users';

    #[Deprecated]
    public const LEGACY_USERS = '/api/v1/users';

    #[Description('Order listing')]
    public const ORDERS = '/api/orders';
}

// Find constants with a specific attribute
$deprecated = Introspect::constants(ApiEndpoints::class)
    ->whereHasAttribute(Deprecated::class)
    ->all();
// ['LEGACY_USERS' => '/api/v1/users']

$described = Introspect::constants(ApiEndpoints::class)
    ->whereHasAttribute(Description::class)
    ->all();
// ['USERS' => '/api/users', 'ORDERS' => '/api/orders']
```

## Combining Filters

Filters can be chained to create complex queries:

```php
use Cline\Introspect\Introspect;

// Get public, non-final constants
$constants = Introspect::constants(Config::class)
    ->wherePublic()
    ->all();

// Get final public constants with a specific attribute
$constants = Introspect::constants(ApiEndpoints::class)
    ->wherePublic()
    ->whereFinal()
    ->whereHasAttribute(Description::class)
    ->all();
```

## Detailed Constant Information

Get comprehensive information about constants:

```php
use Cline\Introspect\Introspect;

// Get info about a specific constant
$info = Introspect::constants(Config::class)->get('VERSION');
// [
//     'name' => 'VERSION',
//     'value' => '1.0.0',
//     'visibility' => 'public',
//     'final' => false,
//     'type' => null,          // PHP 8.3+ typed constants
//     'attributes' => [],
// ]

// Get detailed info for all matching constants
$detailed = Introspect::constants(ApiEndpoints::class)
    ->wherePublic()
    ->toArray();
// [
//     'USERS' => [
//         'name' => 'USERS',
//         'value' => '/api/users',
//         'visibility' => 'public',
//         'final' => false,
//         'type' => 'string',
//         'attributes' => [Description('User listing')],
//     ],
//     ...
// ]
```

## PHP 8.3 Typed Constants

PHP 8.3 introduced typed class constants:

```php
class TypedConfig
{
    public const string APP_NAME = 'MyApp';
    public const int MAX_ITEMS = 100;
    public const array ALLOWED_HOSTS = ['localhost', '127.0.0.1'];
}

$info = Introspect::constants(TypedConfig::class)->get('APP_NAME');
// ['name' => 'APP_NAME', 'value' => 'MyApp', 'type' => 'string', ...]
```

## Available Methods

### Filter Methods

| Method | Description |
|--------|-------------|
| `wherePublic()` | Filter to public constants |
| `whereProtected()` | Filter to protected constants |
| `wherePrivate()` | Filter to private constants |
| `whereFinal()` | Filter to final constants (PHP 8.1+) |
| `whereHasAttribute($attribute)` | Filter by attribute presence |

### Query Methods

| Method | Description |
|--------|-------------|
| `all()` | Get constants as name => value array |
| `names()` | Get constant names only |
| `get($name)` | Get detailed info about a specific constant |
| `toArray()` | Get detailed info for all matching constants |
