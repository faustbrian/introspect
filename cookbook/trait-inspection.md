# Trait Inspection

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
