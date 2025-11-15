<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Introspect;

use function class_basename as laravelClassBasename;
use function is_object;

/**
 * Retrieves the short name of a class (without namespace).
 *
 * Returns the class name without its namespace, providing just the base
 * class name for easier display or comparison.
 *
 * ```php
 * classBasename(App\Models\User::class); // 'User'
 * classBasename($userInstance); // 'User'
 * classBasename('Illuminate\Database\Eloquent\Model'); // 'Model'
 * ```
 *
 * @param  object|string $class The class name or object instance to inspect
 * @return string        The short class name without namespace
 */
function classBasename(object|string $class): string
{
    $class = is_object($class) ? $class::class : $class;

    return laravelClassBasename($class);
}
