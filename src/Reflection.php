<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Introspect;

use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;

use function array_all;
use function array_any;
use function array_map;
use function class_basename as laravelClassBasename;
use function class_implements;
use function class_uses_recursive;
use function in_array;
use function is_object;
use function is_subclass_of;
use function method_exists;
use function property_exists;

/**
 * Reflection utility class for introspection operations.
 *
 * Provides static methods for common reflection-based inspection tasks
 * including trait detection, interface checking, method/property inspection,
 * and attribute handling.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Reflection
{
    /**
     * Retrieves the short name of a class (without namespace).
     *
     * Extracts the class name without the namespace portion.
     *
     * @param  class-string|object $class Class name or object instance
     * @return string              Short class name (e.g., 'User' from 'App\Models\User')
     */
    public static function classBasename(object|string $class): string
    {
        $class = is_object($class) ? $class::class : $class;

        return laravelClassBasename($class);
    }

    /**
     * Retrieves the namespace of a class.
     *
     * @param  class-string|object $class Class name or object instance
     * @return string              Namespace (e.g., 'App\Models' from 'App\Models\User')
     */
    public static function classNamespace(object|string $class): string
    {
        $class = is_object($class) ? $class::class : $class;

        return new ReflectionClass($class)->getNamespaceName();
    }

    /**
     * Determines if a class extends a specific parent class.
     *
     * @param  class-string|object $class  Class name or object instance
     * @param  string              $parent Fully-qualified parent class name
     * @return bool                True if class extends parent
     */
    public static function extendsClass(object|string $class, string $parent): bool
    {
        $class = is_object($class) ? $class::class : $class;

        return is_subclass_of($class, $parent);
    }

    /**
     * Retrieves all traits used by a class (recursively).
     *
     * Returns all traits used by a class and its parents, traversing the entire
     * inheritance chain. This includes traits used by parent classes and traits used by other traits.
     *
     * @param  class-string|object $class Class name or object instance
     * @return array<string>       Array of fully-qualified trait names
     */
    public static function allTraits(object|string $class): array
    {
        $class = is_object($class) ? $class::class : $class;

        return class_uses_recursive($class);
    }

    /**
     * Retrieves all attributes from a class.
     *
     * Returns instantiated attribute objects for the given class. Can optionally
     * filter by attribute name.
     *
     * @param  class-string|object $class Class name or object instance
     * @param  null|string         $name  Optional attribute class name to filter by
     * @return array<object>       Array of instantiated attribute objects
     */
    public static function attributes(object|string $class, ?string $name = null): array
    {
        $reflection = new ReflectionClass($class);
        $attributes = $reflection->getAttributes($name);

        return array_map(fn (ReflectionAttribute $attribute): object => $attribute->newInstance(), $attributes);
    }

    /**
     * Retrieves all public method names from a class.
     *
     * Returns the names of all public methods defined on the class,
     * including inherited methods.
     *
     * @param  class-string|object $class Class name or object instance
     * @return array<string>       Array of public method names
     */
    public static function publicMethods(object|string $class): array
    {
        $reflection = new ReflectionClass($class);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        return array_map(fn (ReflectionMethod $method): string => $method->getName(), $methods);
    }

    /**
     * Retrieves all public property names from a class.
     *
     * Returns the names of all public properties defined on the class,
     * including inherited properties.
     *
     * @param  class-string|object $class Class name or object instance
     * @return array<string>       Array of public property names
     */
    public static function publicProperties(object|string $class): array
    {
        $reflection = new ReflectionClass($class);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        return array_map(fn (ReflectionProperty $property): string => $property->getName(), $properties);
    }

    /**
     * Determines if a class has a specific attribute.
     *
     * @param  class-string|object $class     Class name or object instance
     * @param  string              $attribute Fully-qualified attribute class name
     * @return bool                True if attribute is present on the class
     */
    public static function hasAttribute(object|string $class, string $attribute): bool
    {
        $reflection = new ReflectionClass($class);

        return $reflection->getAttributes($attribute) !== [];
    }

    /**
     * Determines if a class has a specific method.
     *
     * @param  class-string|object $class  Class name or object instance
     * @param  string              $method Method name
     * @return bool                True if method exists on the class
     */
    public static function hasMethod(object|string $class, string $method): bool
    {
        return method_exists($class, $method);
    }

    /**
     * Determines if a class has a specific property.
     *
     * @param  class-string|object $class    Class name or object instance
     * @param  string              $property Property name
     * @return bool                True if property exists on the class
     */
    public static function hasProperty(object|string $class, string $property): bool
    {
        return property_exists($class, $property);
    }

    /**
     * Determines if a class implements a specific interface.
     *
     * @param  class-string|object $class     Class name or object instance
     * @param  string              $interface Fully-qualified interface name
     * @return bool                True if class implements the interface
     */
    public static function implementsInterface(object|string $class, string $interface): bool
    {
        $class = is_object($class) ? $class::class : $class;

        return in_array($interface, class_implements($class) ?: [], true);
    }

    /**
     * Determines if a class is concrete (not abstract).
     *
     * A concrete class can be instantiated directly, while abstract classes
     * must be extended by a concrete implementation.
     *
     * @param  class-string $class Fully-qualified class name
     * @return bool         True if class is concrete (not abstract)
     */
    public static function isConcrete(string $class): bool
    {
        return !new ReflectionClass($class)->isAbstract();
    }

    /**
     * Determines if a class can be instantiated.
     *
     * A class is instantiable if it's concrete, has a public constructor,
     * and is not an interface or trait.
     *
     * @param  class-string $class Fully-qualified class name
     * @return bool         True if class can be instantiated
     */
    public static function isInstantiable(string $class): bool
    {
        return new ReflectionClass($class)->isInstantiable();
    }

    /**
     * Determines if a method is public.
     *
     * @param  class-string|object $class  Class name or object instance
     * @param  string              $method Method name
     * @return bool                True if method exists and is public
     */
    public static function methodIsPublic(object|string $class, string $method): bool
    {
        try {
            $reflection = new ReflectionClass($class);

            return $reflection->hasMethod($method) && $reflection->getMethod($method)->isPublic();
        } catch (ReflectionException) {
            return false;
        }
    }

    /**
     * Determines if a class uses a specific trait (recursively).
     *
     * Checks if the class uses the trait directly or through parent classes
     * or other traits in the inheritance chain.
     *
     * @param  class-string|object $class Class name or object instance
     * @param  string              $trait Fully-qualified trait name
     * @return bool                True if class uses the trait
     */
    public static function usesTrait(object|string $class, string $trait): bool
    {
        $class = is_object($class) ? $class::class : $class;

        return in_array($trait, class_uses_recursive($class), true);
    }

    /**
     * Determines if a class uses all specified traits (recursively).
     *
     * Returns true only if the class uses ALL of the provided traits.
     *
     * @param  class-string|object $class     Class name or object instance
     * @param  string              ...$traits Trait names to check for
     * @return bool                True if class uses all specified traits
     */
    public static function usesTraits(object|string $class, string ...$traits): bool
    {
        return array_all($traits, fn (string $trait): bool => self::usesTrait($class, $trait));
    }

    /**
     * Determines if a class uses any of the specified traits (recursively).
     *
     * Returns true if the class uses at least one of the provided traits.
     *
     * @param  class-string|object $class     Class name or object instance
     * @param  string              ...$traits Trait names to check for
     * @return bool                True if class uses any of the specified traits
     */
    public static function usesAnyTrait(object|string $class, string ...$traits): bool
    {
        return array_any($traits, fn (string $trait): bool => self::usesTrait($class, $trait));
    }
}
