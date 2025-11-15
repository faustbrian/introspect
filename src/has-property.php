<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Introspect;

use function is_object;
use function property_exists;

/**
 * Determines if a class has a specific property.
 *
 * Checks whether the given class or object has a property with the specified name,
 * including properties from parent classes and traits.
 *
 * ```php
 * hasProperty(User::class, 'name'); // true
 * hasProperty($model, 'attributes');
 * ```
 *
 * @param  object|string $class    The class name or object instance to inspect
 * @param  string        $property The property name to search for
 * @return bool          true if the property exists, false otherwise
 */
function hasProperty(object|string $class, string $property): bool
{
    return property_exists($class, $property);
}
