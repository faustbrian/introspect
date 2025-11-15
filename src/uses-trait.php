<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Introspect;

use function class_uses_recursive;
use function in_array;
use function is_object;

/**
 * Determines if a class uses a specific trait (recursively).
 *
 * Checks if the given class or object uses the specified trait anywhere in its
 * inheritance chain. This performs a recursive search through parent classes and
 * all used traits to detect trait usage at any level.
 *
 * ```php
 * use Illuminate\Database\Eloquent\Model;
 * use Illuminate\Database\Eloquent\SoftDeletes;
 *
 * usesTrait(User::class, SoftDeletes::class); // true if User uses SoftDeletes
 * usesTrait($userInstance, SoftDeletes::class); // true
 * ```
 *
 * @param  object|string $class The class name or object instance to inspect
 * @param  string        $trait The fully-qualified trait name to search for
 * @return bool          true if the class uses the trait at any level, false otherwise
 */
function usesTrait(object|string $class, string $trait): bool
{
    $class = is_object($class) ? $class::class : $class;

    return in_array($trait, class_uses_recursive($class), true);
}
