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
