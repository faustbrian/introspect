<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Introspect;

use ReflectionClass;

use function is_object;

/**
 * Retrieves the namespace of a class.
 *
 * Returns the namespace portion of a fully-qualified class name, excluding
 * the class name itself. Returns empty string for classes in the global namespace.
 *
 * ```php
 * classNamespace(App\Models\User::class); // 'App\Models'
 * classNamespace($userInstance); // 'App\Models'
 * classNamespace('User'); // ''
 * ```
 *
 * @param  object|string $class The class name or object instance to inspect
 * @return string        The namespace portion of the class name, or empty string
 */
function classNamespace(object|string $class): string
{
    $class = is_object($class) ? $class::class : $class;

    return (new ReflectionClass($class))->getNamespaceName();
}
