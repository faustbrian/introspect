## Table of Contents

1. Overview (`docs/README.md`)
2. Attribute Inspection (`docs/attribute-inspection.md`)
3. Callable Introspection (`docs/callable-introspection.md`)
4. Class Hierarchy (`docs/class-hierarchy.md`)
5. Class Utilities (`docs/class-utilities.md`)
6. Constant Introspection (`docs/constant-introspection.md`)
7. Enum Introspection (`docs/enum-introspection.md`)
8. Fluent Api (`docs/fluent-api.md`)
9. Laravel Introspection (`docs/laravel-introspection.md`)
10. Method Inspection (`docs/method-inspection.md`)
11. Model Introspection (`docs/model-introspection.md`)
12. Property Inspection (`docs/property-inspection.md`)
13. Trait Inspection (`docs/trait-inspection.md`)
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

These functions allow you to work with PHP 8.0+ attributes, checking for their presence and retrieving attribute instances from classes.

## Basic Attribute Detection

Use `hasAttribute()` to check if a class has a specific attribute:

```php
use function Cline\Introspect\hasAttribute;

#[Route('/users')]
class UserController
{
    // ...
}

if (hasAttribute(UserController::class, Route::class)) {
    echo "UserController has Route attribute";
}

// Works with instances
$controller = new UserController();
if (hasAttribute($controller, Route::class)) {
    echo "This controller has a route";
}
```

## Retrieving Attributes

Use `getAttributes()` to retrieve attribute instances:

```php
use function Cline\Introspect\getAttributes;

#[Route('/users')]
#[Middleware('auth')]
#[RateLimit(100)]
class UserController {}

// Get all attributes
$attributes = getAttributes(UserController::class);
// Returns: [Route instance, Middleware instance, RateLimit instance]

// Get specific attribute type
$routes = getAttributes(UserController::class, Route::class);
// Returns: [Route instance]

foreach ($routes as $route) {
    echo "Path: {$route->path}\n";
}
```

## Practical Example: Route Registration

```php
use function Cline\Introspect\hasAttribute;
use function Cline\Introspect\getAttributes;

#[Attribute]
class Route
{
    public function __construct(
        public string $path,
        public array $methods = ['GET']
    ) {}
}

class RouteRegistrar
{
    public function register(string $controllerClass): void
    {
        if (!hasAttribute($controllerClass, Route::class)) {
            return;
        }

        $routes = getAttributes($controllerClass, Route::class);

        foreach ($routes as $route) {
            foreach ($route->methods as $method) {
                $this->addRoute($method, $route->path, $controllerClass);
            }
        }
    }

    private function addRoute(string $method, string $path, string $controller): void
    {
        echo "Registered {$method} {$path} -> {$controller}\n";
    }
}

// Usage
$registrar = new RouteRegistrar();
$registrar->register(UserController::class);
// Output: Registered GET /users -> UserController
```

## Example: Middleware Resolver

```php
use function Cline\Introspect\getAttributes;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Middleware
{
    public function __construct(public string $name) {}
}

class MiddlewareResolver
{
    public function resolve(string $controllerClass): array
    {
        $middlewares = [];
        $attributes = getAttributes($controllerClass, Middleware::class);

        foreach ($attributes as $middleware) {
            $middlewares[] = $middleware->name;
        }

        return $middlewares;
    }
}

// Usage
#[Middleware('auth')]
#[Middleware('verified')]
class ProfileController {}

$resolver = new MiddlewareResolver();
$middlewares = $resolver->resolve(ProfileController::class);
// Returns: ['auth', 'verified']
```

## Example: Permission System

```php
use function Cline\Introspect\getAttributes;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class RequiresPermission
{
    public function __construct(public string $permission) {}
}

class PermissionChecker
{
    public function check(object $user, string $controllerClass): bool
    {
        $permissions = getAttributes($controllerClass, RequiresPermission::class);

        foreach ($permissions as $permission) {
            if (!$user->hasPermission($permission->permission)) {
                return false;
            }
        }

        return true;
    }
}

// Usage
#[RequiresPermission('users.manage')]
#[RequiresPermission('admin.access')]
class AdminUserController {}

$checker = new PermissionChecker();
if (!$checker->check($user, AdminUserController::class)) {
    throw new UnauthorizedException();
}
```

Introspect provides detailed analysis of PHP callables including closures, invokable objects, callable arrays, and callable strings.

## Basic Usage

```php
use Cline\Introspect\Introspect;

// Inspect a closure
$closure = fn(string $name, int $age = 18): string => "$name is $age";
$info = Introspect::callable($closure);

// Get parameters
$params = $info->parameters();
// [
//     ['name' => 'name', 'type' => 'string', 'hasDefault' => false, ...],
//     ['name' => 'age', 'type' => 'int', 'default' => 18, 'hasDefault' => true, ...],
// ]

// Get return type
$returnType = $info->returnType();
// 'string'

// Check if static
$isStatic = $info->isStatic();
// false
```

## Supported Callable Types

### Closures

```php
use Cline\Introspect\Introspect;

$closure = function(string $message): void {
    echo $message;
};

$info = Introspect::callable($closure);
$info->parameters();     // Parameter info
$info->returnType();     // 'void'
$info->sourceFile();     // '/path/to/file.php'
$info->sourceLines();    // [10, 12] (start, end)
```

### Static Closures

```php
$staticClosure = static fn(): string => 'Hello';

$info = Introspect::callable($staticClosure);
$info->isStatic();       // true
$info->scopeClass();     // null (no bound scope)
```

### Invokable Objects

```php
class Greeter
{
    public function __invoke(string $name): string
    {
        return "Hello, {$name}!";
    }
}

$greeter = new Greeter();
$info = Introspect::callable($greeter);

$info->parameters();     // [['name' => 'name', 'type' => 'string', ...]]
$info->returnType();     // 'string'
```

### Callable Arrays

```php
class Calculator
{
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }

    public static function multiply(int $a, int $b): int
    {
        return $a * $b;
    }
}

// Instance method callable
$calculator = new Calculator();
$info = Introspect::callable([$calculator, 'add']);
$info->parameters();     // [['name' => 'a', ...], ['name' => 'b', ...]]
$info->returnType();     // 'int'
$info->isStatic();       // false

// Static method callable
$info = Introspect::callable([Calculator::class, 'multiply']);
$info->isStatic();       // true
```

### First-Class Callables (PHP 8.1+)

```php
class Service
{
    public function process(array $data): array
    {
        return $data;
    }
}

$service = new Service();
$callable = $service->process(...);

$info = Introspect::callable($callable);
$info->parameters();     // [['name' => 'data', 'type' => 'array', ...]]
$info->returnType();     // 'array'
```

## Parameter Details

The `parameters()` method returns comprehensive parameter information:

```php
$closure = fn(
    string $required,
    int $withDefault = 42,
    array &$byReference = [],
    string ...$variadic
): void => null;

$params = Introspect::callable($closure)->parameters();

// Each parameter has:
// [
//     'name' => 'required',
//     'type' => 'string',
//     'default' => null,
//     'hasDefault' => false,
//     'variadic' => false,
//     'byReference' => false,
// ]
```

## Closure-Specific Features

### Bound Variables

Closures can capture variables from their enclosing scope:

```php
$name = 'World';
$greeting = 'Hello';

$closure = function() use ($name, $greeting): string {
    return "$greeting, $name!";
};

$info = Introspect::callable($closure);
$vars = $info->boundVariables();
// ['name' => 'World', 'greeting' => 'Hello']
```

### Scope Class

Closures can be bound to a class scope:

```php
class MyClass
{
    private string $secret = 'hidden';

    public function getClosure(): Closure
    {
        return fn() => $this->secret;
    }
}

$obj = new MyClass();
$closure = $obj->getClosure();

$info = Introspect::callable($closure);
$info->scopeClass();     // 'MyClass'
```

## Source Location

Get the file and line numbers where a callable is defined:

```php
$closure = fn() => 'test';

$info = Introspect::callable($closure);
$file = $info->sourceFile();      // '/path/to/file.php'
$lines = $info->sourceLines();    // [42, 42] (single-line closure)

// For multi-line closures
$multiLine = function() {
    $a = 1;
    $b = 2;
    return $a + $b;
};

$info = Introspect::callable($multiLine);
$info->sourceLines();    // [50, 54]
```

## Exporting Callable Data

Export all information about a callable:

```php
$closure = fn(string $name, int $age = 18): string => "$name is $age";

$data = Introspect::callable($closure)->toArray();

// [
//     'parameters' => [
//         ['name' => 'name', 'type' => 'string', 'hasDefault' => false, ...],
//         ['name' => 'age', 'type' => 'int', 'default' => 18, 'hasDefault' => true, ...],
//     ],
//     'returnType' => 'string',
//     'boundVariables' => [],
//     'scopeClass' => null,
//     'isStatic' => false,
//     'sourceFile' => '/path/to/file.php',
//     'sourceLines' => [10, 10],
// ]
```

## Advanced Reflection

Access the underlying reflection for advanced operations:

```php
$info = Introspect::callable($callable);

// Get ReflectionFunction or ReflectionMethod
$reflection = $info->getReflection();

// Use for advanced operations
$attributes = $reflection->getAttributes();
$docComment = $reflection->getDocComment();
```

## Available Methods

| Method | Description |
|--------|-------------|
| `parameters()` | Get all parameters with details |
| `returnType()` | Get the return type |
| `boundVariables()` | Get closure-bound variables (use) |
| `scopeClass()` | Get closure scope class |
| `isStatic()` | Check if callable is static |
| `sourceFile()` | Get source file path |
| `sourceLines()` | Get [start, end] line numbers |
| `getReflection()` | Get underlying reflection |
| `toArray()` | Export all information |

These functions help you inspect class inheritance and interface implementation, allowing you to determine relationships between classes and their contracts.

## Interface Implementation

Use `implementsInterface()` to check if a class implements a specific interface:

```php
use function Cline\Introspect\implementsInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use App\Models\User;

if (implementsInterface(User::class, Authenticatable::class)) {
    echo "User can be authenticated";
}

// Works with objects too
$user = new User();
if (implementsInterface($user, Authenticatable::class)) {
    echo "This user instance can be authenticated";
}
```

## Class Extension

Use `extendsClass()` to verify parent class relationships:

```php
use function Cline\Introspect\extendsClass;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

if (extendsClass(User::class, Model::class)) {
    echo "User is an Eloquent model";
}

// Check controller inheritance
use Illuminate\Routing\Controller;
use App\Http\Controllers\UserController;

if (extendsClass(UserController::class, Controller::class)) {
    echo "UserController extends base Controller";
}
```

## Concrete vs Abstract Classes

Use `isConcrete()` to determine if a class can be instantiated:

```php
use function Cline\Introspect\isConcrete;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

if (isConcrete(User::class)) {
    echo "User is a concrete class that can be instantiated";
}

if (!isConcrete(Model::class)) {
    echo "Model is abstract and cannot be directly instantiated";
}
```

Use `isInstantiable()` for a broader check that also excludes interfaces and traits:

```php
use function Cline\Introspect\isInstantiable;

if (isInstantiable(User::class)) {
    $user = new User(); // Safe to instantiate
}

// This returns false for interfaces
interface UserInterface {}
isInstantiable(UserInterface::class); // false

// And false for traits
trait Auditable {}
isInstantiable(Auditable::class); // false
```

## Practical Example: Factory Pattern

```php
use function Cline\Introspect\implementsInterface;
use function Cline\Introspect\isInstantiable;

class ServiceFactory
{
    public function create(string $className): object
    {
        if (!isInstantiable($className)) {
            throw new InvalidArgumentException(
                "Cannot instantiate {$className} - it may be abstract, interface, or trait"
            );
        }

        $instance = new $className();

        if (implementsInterface($instance, InitializableInterface::class)) {
            $instance->initialize();
        }

        return $instance;
    }
}
```

## Example: Repository Pattern

```php
use function Cline\Introspect\extendsClass;
use function Cline\Introspect\implementsInterface;
use Illuminate\Database\Eloquent\Model;

class RepositoryResolver
{
    public function resolve(string $modelClass): RepositoryInterface
    {
        if (!extendsClass($modelClass, Model::class)) {
            throw new InvalidArgumentException("{$modelClass} is not an Eloquent model");
        }

        $repositoryClass = str_replace('Models', 'Repositories', $modelClass) . 'Repository';

        if (!implementsInterface($repositoryClass, RepositoryInterface::class)) {
            throw new InvalidArgumentException(
                "{$repositoryClass} does not implement RepositoryInterface"
            );
        }

        return new $repositoryClass();
    }
}
```

## Example: Plugin System

```php
use function Cline\Introspect\implementsInterface;
use function Cline\Introspect\isConcrete;

class PluginLoader
{
    public function loadPlugin(string $className): void
    {
        if (!isConcrete($className)) {
            throw new InvalidArgumentException("Plugin {$className} must be concrete");
        }

        if (!implementsInterface($className, PluginInterface::class)) {
            throw new InvalidArgumentException(
                "Plugin {$className} must implement PluginInterface"
            );
        }

        $plugin = new $className();
        $plugin->register();
    }
}
```

These utility functions provide convenient ways to work with class names and namespaces, making it easier to manipulate and display class information.

## Class Basename

Use `classBasename()` to get the short class name without its namespace:

```php
use function Cline\Introspect\classBasename;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

echo classBasename(User::class); // 'User'
echo classBasename(Model::class); // 'Model'

// Works with instances
$user = new User();
echo classBasename($user); // 'User'

// Useful for display purposes
$className = classBasename($user);
echo "Creating new {$className}..."; // "Creating new User..."
```

## Class Namespace

Use `classNamespace()` to extract the namespace portion of a fully-qualified class name:

```php
use function Cline\Introspect\classNamespace;
use App\Models\User;

echo classNamespace(User::class); // 'App\Models'
echo classNamespace($user); // 'App\Models'

// Global namespace classes return empty string
class GlobalClass {}
echo classNamespace(GlobalClass::class); // ''
```

## Practical Example: Auto-Discovery

```php
use function Cline\Introspect\classBasename;
use function Cline\Introspect\classNamespace;

class ServiceLocator
{
    public function findService(string $modelClass): string
    {
        $namespace = classNamespace($modelClass);
        $basename = classBasename($modelClass);

        // Convert App\Models\User -> App\Services\UserService
        $serviceNamespace = str_replace('Models', 'Services', $namespace);
        $serviceClass = "{$serviceNamespace}\\{$basename}Service";

        if (!class_exists($serviceClass)) {
            throw new RuntimeException("Service {$serviceClass} not found");
        }

        return $serviceClass;
    }
}

// Usage
$locator = new ServiceLocator();
$serviceClass = $locator->findService(User::class); // App\Services\UserService
```

## Example: Logger with Class Context

```php
use function Cline\Introspect\classBasename;

class ContextLogger
{
    public function log(object $instance, string $message): void
    {
        $className = classBasename($instance);
        $timestamp = now()->toDateTimeString();

        echo "[{$timestamp}] {$className}: {$message}\n";
    }
}

// Usage
class OrderProcessor
{
    public function __construct(private ContextLogger $logger) {}

    public function process(Order $order): void
    {
        $this->logger->log($this, "Processing order {$order->id}");
        // Output: [2025-01-15 10:30:00] OrderProcessor: Processing order 123
    }
}
```

## Example: Repository Auto-Resolver

```php
use function Cline\Introspect\classBasename;
use function Cline\Introspect\classNamespace;

class RepositoryResolver
{
    private array $cache = [];

    public function resolve(string $modelClass): object
    {
        if (isset($this->cache[$modelClass])) {
            return $this->cache[$modelClass];
        }

        $namespace = str_replace('Models', 'Repositories', classNamespace($modelClass));
        $basename = classBasename($modelClass);
        $repositoryClass = "{$namespace}\\{$basename}Repository";

        if (!class_exists($repositoryClass)) {
            throw new RuntimeException("Repository {$repositoryClass} not found");
        }

        return $this->cache[$modelClass] = new $repositoryClass();
    }
}
```

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

These functions allow you to introspect class methods, check for their existence, verify visibility, and retrieve lists of available methods.

## Basic Method Detection

Use `hasMethod()` to check if a class has a specific method:

```php
use function Cline\Introspect\hasMethod;
use App\Models\User;

if (hasMethod(User::class, 'save')) {
    echo "User has a save method";
}

// Works with instances
$user = new User();
if (hasMethod($user, 'getAttribute')) {
    $value = $user->getAttribute('name');
}
```

## Checking Method Visibility

Use `methodIsPublic()` to verify a method is publicly accessible:

```php
use function Cline\Introspect\methodIsPublic;
use App\Models\User;

if (methodIsPublic(User::class, 'save')) {
    // Safe to call from outside the class
    $user = new User();
    $user->save();
}

// Returns false for protected/private methods
if (!methodIsPublic(User::class, 'bootTraits')) {
    echo "bootTraits is not public";
}
```

## Retrieving All Public Methods

Use `getPublicMethods()` to get an array of all public method names:

```php
use function Cline\Introspect\getPublicMethods;
use App\Models\User;

$methods = getPublicMethods(User::class);
// Returns: ['save', 'delete', 'update', 'getAttribute', 'setAttribute', ...]

foreach ($methods as $method) {
    echo "Public method: {$method}\n";
}
```

## Practical Example: Dynamic Method Calling

```php
use function Cline\Introspect\hasMethod;
use function Cline\Introspect\methodIsPublic;

class MethodCaller
{
    public function callIfExists(object $instance, string $method, array $args = []): mixed
    {
        if (!hasMethod($instance, $method)) {
            throw new BadMethodCallException("Method {$method} does not exist");
        }

        if (!methodIsPublic($instance, $method)) {
            throw new BadMethodCallException("Method {$method} is not public");
        }

        return $instance->$method(...$args);
    }
}

$caller = new MethodCaller();
$user = new User();

// Safe dynamic call
$caller->callIfExists($user, 'save'); // Works
$caller->callIfExists($user, 'bootTraits'); // Throws exception (not public)
```

## Example: API Resource Generator

```php
use function Cline\Introspect\getPublicMethods;
use function Cline\Introspect\methodIsPublic;

class ApiDocumentationGenerator
{
    public function generateEndpoints(string $controllerClass): array
    {
        $endpoints = [];
        $methods = getPublicMethods($controllerClass);

        foreach ($methods as $method) {
            // Skip magic methods and constructor
            if (str_starts_with($method, '__')) {
                continue;
            }

            if (methodIsPublic($controllerClass, $method)) {
                $endpoints[] = [
                    'method' => $method,
                    'endpoint' => strtolower($method),
                ];
            }
        }

        return $endpoints;
    }
}
```

## Example: Method-Based Permissions

```php
use function Cline\Introspect\hasMethod;

class PermissionChecker
{
    public function canPerform(object $user, string $action, object $resource): bool
    {
        $permissionMethod = 'can' . ucfirst($action);

        // Check if resource defines custom permission logic
        if (hasMethod($resource, $permissionMethod)) {
            return $resource->$permissionMethod($user);
        }

        // Fall back to default permission check
        return $user->hasPermission($action, get_class($resource));
    }
}

// Usage
class Post
{
    public function canPublish(User $user): bool
    {
        return $user->isAdmin() || $user->id === $this->author_id;
    }
}

$checker = new PermissionChecker();
$checker->canPerform($user, 'publish', $post); // Calls Post::canPublish()
```

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

These functions help you inspect class properties, check for their existence, and retrieve lists of public properties available on a class.

## Basic Property Detection

Use `hasProperty()` to check if a class has a specific property:

```php
use function Cline\Introspect\hasProperty;
use App\Models\User;

if (hasProperty(User::class, 'name')) {
    echo "User has a name property";
}

// Works with instances
$user = new User();
if (hasProperty($user, 'email')) {
    echo $user->email;
}
```

## Retrieving Public Properties

Use `getPublicProperties()` to get an array of all public property names:

```php
use function Cline\Introspect\getPublicProperties;
use App\Models\User;

$properties = getPublicProperties(User::class);
// Returns: ['timestamps', 'incrementing', ...]

foreach ($properties as $property) {
    echo "Public property: {$property}\n";
}
```

## Practical Example: Property Validator

```php
use function Cline\Introspect\hasProperty;

class PropertyValidator
{
    public function validate(object $instance, array $rules): array
    {
        $errors = [];

        foreach ($rules as $property => $validators) {
            if (!hasProperty($instance, $property)) {
                $errors[$property] = "Property {$property} does not exist";
                continue;
            }

            foreach ($validators as $validator) {
                if (!$validator($instance->$property)) {
                    $errors[$property] = "Validation failed for {$property}";
                }
            }
        }

        return $errors;
    }
}

// Usage
$validator = new PropertyValidator();
$user = new User();

$errors = $validator->validate($user, [
    'email' => [fn($v) => filter_var($v, FILTER_VALIDATE_EMAIL)],
    'age' => [fn($v) => is_int($v) && $v >= 18],
]);
```

## Example: DTO Mapper

```php
use function Cline\Introspect\hasProperty;
use function Cline\Introspect\getPublicProperties;

class DtoMapper
{
    public function map(object $source, object $destination): object
    {
        $sourceProperties = getPublicProperties($source);

        foreach ($sourceProperties as $property) {
            if (hasProperty($destination, $property)) {
                $destination->$property = $source->$property;
            }
        }

        return $destination;
    }
}

// Usage
class UserDto
{
    public string $name;
    public string $email;
}

class UserEntity
{
    public string $name;
    public string $email;
    public int $id;
}

$mapper = new DtoMapper();
$dto = new UserDto();
$entity = new UserEntity();

$mapper->map($dto, $entity); // Maps name and email
```

## Example: Serializer

```php
use function Cline\Introspect\getPublicProperties;

class SimpleSerializer
{
    public function serialize(object $instance): array
    {
        $data = [];
        $properties = getPublicProperties($instance);

        foreach ($properties as $property) {
            $value = $instance->$property;

            // Recursively serialize nested objects
            if (is_object($value)) {
                $data[$property] = $this->serialize($value);
            } else {
                $data[$property] = $value;
            }
        }

        return $data;
    }
}
```

The trait inspection functions allow you to determine which traits a class uses, either checking for specific traits or retrieving all traits used by a class.

## Basic Trait Detection

Use `usesTrait()` to check if a class uses a specific trait:

```php
use function Cline\Introspect\usesTrait;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

if (usesTrait(User::class, SoftDeletes::class)) {
    echo "User model implements soft deletes";
}

// Also works with instances
$user = new User();
if (usesTrait($user, SoftDeletes::class)) {
    echo "This user instance uses soft deletes";
}
```

## Checking Multiple Traits

Use `usesTraits()` when you need to verify that a class uses ALL specified traits:

```php
use function Cline\Introspect\usesTraits;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

// Returns true only if User uses BOTH traits
if (usesTraits(User::class, SoftDeletes::class, HasFactory::class)) {
    echo "User model has both soft deletes and factory support";
}
```

Use `usesAnyTrait()` when you need to check if a class uses AT LEAST ONE of the specified traits:

```php
use function Cline\Introspect\usesAnyTrait;
use Illuminate\Notifications\Notifiable;
use Illuminate\Auth\Authenticatable;
use App\Models\User;

// Returns true if User uses either Notifiable OR Authenticatable (or both)
if (usesAnyTrait(User::class, Notifiable::class, Authenticatable::class)) {
    echo "User model has notification or authentication capabilities";
}
```

## Retrieving All Traits

Use `getAllTraits()` to get a complete list of all traits used by a class:

```php
use function Cline\Introspect\getAllTraits;
use App\Models\User;

$traits = getAllTraits(User::class);
// Returns: [
//   'Illuminate\Database\Eloquent\SoftDeletes',
//   'Illuminate\Notifications\Notifiable',
//   'Illuminate\Database\Eloquent\Factories\HasFactory',
//   ...
// ]

foreach ($traits as $trait) {
    echo "Uses: " . $trait . "\n";
}
```

## Practical Example: Conditional Behavior

```php
use function Cline\Introspect\usesTrait;
use Illuminate\Database\Eloquent\SoftDeletes;

class ModelExporter
{
    public function export($model): array
    {
        $data = $model->toArray();

        // Include soft delete status if the model uses SoftDeletes
        if (usesTrait($model, SoftDeletes::class)) {
            $data['is_deleted'] = $model->trashed();
            $data['deleted_at'] = $model->deleted_at;
        }

        return $data;
    }
}
```

## Example: Trait-Based Factory

```php
use function Cline\Introspect\usesTraits;
use App\Traits\Auditable;
use App\Traits\Publishable;

class ContentManager
{
    public function canPublish($model): bool
    {
        // Only models with both Auditable and Publishable can be published
        return usesTraits($model, Auditable::class, Publishable::class);
    }
}
```
