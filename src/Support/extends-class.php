<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Introspect;

use function is_object;
use function is_subclass_of;

/**
 * Determines if a class extends a specific parent class.
 *
 * Checks if the given class or object extends the specified parent class
 * anywhere in its inheritance chain.
 *
 * ```php
 * extendsClass(User::class, Model::class); // true if User extends Model
 * extendsClass($instance, BaseController::class);
 * ```
 *
 * @param  object|string $class  The class name or object instance to inspect
 * @param  string        $parent The fully-qualified parent class name
 * @return bool          true if the class extends the parent, false otherwise
 */
function extendsClass(object|string $class, string $parent): bool
{
    $class = is_object($class) ? $class::class : $class;

    return is_subclass_of($class, $parent);
}
