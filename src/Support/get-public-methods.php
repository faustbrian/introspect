<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Introspect;

use ReflectionClass;
use ReflectionMethod;

use function array_map;

/**
 * Retrieves all public method names from a class.
 *
 * Returns an array of public method names defined on the given class or object,
 * including inherited public methods from parent classes and traits.
 *
 * ```php
 * getPublicMethods(User::class);
 * // ['save', 'delete', 'update', 'getAttribute', ...]
 *
 * getPublicMethods($userInstance);
 * ```
 *
 * @param  object|string $class The class name or object instance to inspect
 * @return array<string> Array of public method names
 */
function getPublicMethods(object|string $class): array
{
    $reflection = new ReflectionClass($class);
    $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

    return array_map(fn (ReflectionMethod $method) => $method->getName(), $methods);
}
