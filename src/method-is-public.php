<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Introspect;

use ReflectionClass;
use ReflectionException;

/**
 * Determines if a method is public.
 *
 * Checks whether the specified method on a class or object has public visibility.
 * Returns false if the method doesn't exist.
 *
 * ```php
 * methodIsPublic(User::class, 'save'); // true
 * methodIsPublic($model, 'getAttribute'); // true
 * methodIsPublic(User::class, 'bootTraits'); // false (protected)
 * ```
 *
 * @param  object|string $class  The class name or object instance to inspect
 * @param  string        $method The method name to check
 * @return bool          true if the method is public, false otherwise or if method doesn't exist
 */
function methodIsPublic(object|string $class, string $method): bool
{
    try {
        $reflection = new ReflectionClass($class);

        return $reflection->hasMethod($method) && $reflection->getMethod($method)->isPublic();
    } catch (ReflectionException) {
        return false;
    }
}
