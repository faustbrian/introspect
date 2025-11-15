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
 * Determines if a class can be instantiated.
 *
 * Checks whether the given class can be instantiated with the new operator.
 * Returns false for interfaces, abstract classes, and traits.
 *
 * ```php
 * isInstantiable(User::class); // true
 * isInstantiable(UserInterface::class); // false
 * isInstantiable(AbstractModel::class); // false
 * ```
 *
 * @param  string $class The fully-qualified class name to inspect
 * @return bool   true if the class can be instantiated, false otherwise
 */
function isInstantiable(string $class): bool
{
    return (new ReflectionClass($class))->isInstantiable();
}
