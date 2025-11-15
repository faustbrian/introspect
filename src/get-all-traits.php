<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Introspect;

use function class_uses_recursive;
use function is_object;

/**
 * Retrieves all traits used by a class (recursively).
 *
 * Returns an array of all trait names used by the given class or object,
 * including traits from parent classes and traits used by other traits.
 * This performs a complete recursive traversal of the trait hierarchy.
 *
 * ```php
 * getAllTraits(User::class);
 * // ['Illuminate\Database\Eloquent\SoftDeletes', 'Illuminate\Notifications\Notifiable', ...]
 *
 * getAllTraits($userInstance);
 * ```
 *
 * @param  object|string $class The class name or object instance to inspect
 * @return array<string> Array of fully-qualified trait names used by the class
 */
function getAllTraits(object|string $class): array
{
    $class = is_object($class) ? $class::class : $class;

    return class_uses_recursive($class);
}
