# Method Inspection

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

## Example: Hook System

```php
use function Cline\Introspect\hasMethod;
use function Cline\Introspect\methodIsPublic;

class HookManager
{
    public function trigger(object $instance, string $event, array $data = []): void
    {
        $hookMethod = 'on' . ucfirst($event);

        if (hasMethod($instance, $hookMethod) && methodIsPublic($instance, $hookMethod)) {
            $instance->$hookMethod(...$data);
        }
    }
}

// Usage
class OrderProcessor
{
    public function onOrderCreated(Order $order): void
    {
        // Send notification
        // Update inventory
        // Log event
    }
}

$manager = new HookManager();
$processor = new OrderProcessor();
$manager->trigger($processor, 'orderCreated', [$order]); // Calls onOrderCreated()
```
