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
 * Determines if a class is concrete (not abstract).
 *
 * Checks whether the given class is a concrete implementation that can be
 * instantiated, as opposed to an abstract class that requires subclassing.
 *
 * ```php
 * isConcrete(User::class); // true
 * isConcrete(Model::class); // false if Model is abstract
 * ```
 *
 * @param  string $class The fully-qualified class name to inspect
 * @return bool   true if the class is concrete, false if abstract
 */
function isConcrete(string $class): bool
{
    return ! (new ReflectionClass($class))->isAbstract();
}
