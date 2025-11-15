<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Introspect;

use ReflectionClass;

/**
 * Determines if a class has a specific attribute (PHP 8.0+).
 *
 * Checks whether the given class or object has been decorated with the
 * specified attribute. This only checks class-level attributes, not
 * method or property attributes.
 *
 * ```php
 * #[Route('/users')]
 * class UserController {}
 *
 * hasAttribute(UserController::class, Route::class); // true
 * hasAttribute($controller, Route::class); // true
 * ```
 *
 * @param  object|string $class     The class name or object instance to inspect
 * @param  string        $attribute The fully-qualified attribute class name
 * @return bool          true if the attribute is present, false otherwise
 */
function hasAttribute(object|string $class, string $attribute): bool
{
    $reflection = new ReflectionClass($class);

    return count($reflection->getAttributes($attribute)) > 0;
}
