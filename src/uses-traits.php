<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Introspect;

/**
 * Determines if a class uses all specified traits (recursively).
 *
 * Checks if the given class or object uses every one of the specified traits
 * anywhere in its inheritance chain. All traits must be present for this to
 * return true.
 *
 * ```php
 * usesTraits(User::class, SoftDeletes::class, HasFactory::class); // true only if both present
 * usesTraits($model, Notifiable::class, Authenticatable::class);
 * ```
 *
 * @param  object|string $class  The class name or object instance to inspect
 * @param  string        ...$traits One or more fully-qualified trait names to search for
 * @return bool          true if the class uses all specified traits, false otherwise
 */
function usesTraits(object|string $class, string ...$traits): bool
{
    foreach ($traits as $trait) {
        if (! usesTrait($class, $trait)) {
            return false;
        }
    }

    return true;
}
