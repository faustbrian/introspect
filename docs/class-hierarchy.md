---
title: Class Hierarchy
description: Inspect class inheritance and interface implementation to determine relationships between classes and their contracts.
---

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
