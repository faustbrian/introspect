---
title: Callable Introspection
description: Inspect closures, invokable objects, callable arrays, and callable strings.
---

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
