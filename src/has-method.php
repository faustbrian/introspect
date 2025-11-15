<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Introspect;

use function is_object;
use function method_exists;

/**
 * Determines if a class has a specific method.
 *
 * Checks whether the given class or object has a method with the specified name,
 * including methods from parent classes and traits.
 *
 * ```php
 * hasMethod(User::class, 'save'); // true
 * hasMethod($model, 'getAttribute');
 * ```
 *
 * @param  object|string $class  The class name or object instance to inspect
 * @param  string        $method The method name to search for
 * @return bool          true if the method exists, false otherwise
 */
function hasMethod(object|string $class, string $method): bool
{
    return method_exists($class, $method);
}
