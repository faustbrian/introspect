---
title: Attribute Inspection
description: Work with PHP 8.0+ attributes, checking for their presence and retrieving attribute instances from classes.
---

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
