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

use function array_map;

/**
 * Retrieves all attributes from a class (PHP 8.0+).
 *
 * Returns an array of attribute instances attached to the given class or object.
 * Optionally filter by a specific attribute name. This only returns class-level
 * attributes, not method or property attributes.
 *
 * ```php
 * #[Route('/users')]
 * #[Middleware('auth')]
 * class UserController {}
 *
 * getAttributes(UserController::class);
 * // [Route instance, Middleware instance]
 *
 * getAttributes(UserController::class, Route::class);
 * // [Route instance]
 * ```
 *
 * @param  object|string $class The class name or object instance to inspect
 * @param  null|string   $name  Optional attribute class name to filter by
 * @return array<object> Array of attribute instances
 */
function getAttributes(object|string $class, ?string $name = null): array
{
    $reflection = new ReflectionClass($class);
    $attributes = $reflection->getAttributes($name);

    return array_map(fn (ReflectionAttribute $attribute) => $attribute->newInstance(), $attributes);
}
