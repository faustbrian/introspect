<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Introspect;

use ReflectionClass;
use ReflectionProperty;

use function array_map;

/**
 * Retrieves all public property names from a class.
 *
 * Returns an array of public property names defined on the given class or object,
 * including inherited public properties from parent classes and traits.
 *
 * ```php
 * getPublicProperties(User::class);
 * // ['timestamps', 'incrementing', ...]
 *
 * getPublicProperties($userInstance);
 * ```
 *
 * @param  object|string $class The class name or object instance to inspect
 * @return array<string> Array of public property names
 */
function getPublicProperties(object|string $class): array
{
    $reflection = new ReflectionClass($class);
    $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

    return array_map(fn (ReflectionProperty $property) => $property->getName(), $properties);
}
