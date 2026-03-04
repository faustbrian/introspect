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
