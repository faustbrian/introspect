<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Introspect;

use function class_implements;
use function in_array;
use function is_object;

/**
 * Determines if a class implements a specific interface.
 *
 * Checks if the given class or object implements the specified interface,
 * including interfaces implemented by parent classes.
 *
 * ```php
 * implementsInterface(User::class, Authenticatable::class); // true
 * implementsInterface($model, JsonSerializable::class);
 * ```
 *
 * @param  object|string $class     The class name or object instance to inspect
 * @param  string        $interface The fully-qualified interface name
 * @return bool          true if the class implements the interface, false otherwise
 */
function implementsInterface(object|string $class, string $interface): bool
{
    $class = is_object($class) ? $class::class : $class;

    return in_array($interface, class_implements($class) ?: [], true);
}
