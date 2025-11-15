<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Introspect;

use Cline\Introspect\Query\ClassIntrospector;
use Cline\Introspect\Query\InstanceIntrospector;
use Cline\Introspect\Query\InterfaceIntrospector;
use Cline\Introspect\Query\TraitIntrospector;

/**
 * Fluent introspection facade for inspecting PHP classes, traits, interfaces, and instances.
 *
 * Provides a Laravel-inspired fluent API for querying and inspecting PHP code structures
 * at runtime with chainable methods and wildcard support.
 *
 * ```php
 * // Inspect a class
 * $methods = Introspect::class(User::class)
 *     ->whereUsesTrait(SoftDeletes::class)
 *     ->getPublicMethods();
 *
 * // Inspect an instance
 * $traits = Introspect::instance($user)
 *     ->getAllTraits();
 *
 * // Query traits
 * $traits = Introspect::traits()
 *     ->whereUsedBy(User::class)
 *     ->get();
 *
 * // Query interfaces
 * $interfaces = Introspect::interfaces()
 *     ->whereImplementedBy(User::class)
 *     ->get();
 * ```
 */
class Introspect
{
    /**
     * Create a class introspector for the given class name.
     *
     * @param  string $className Fully-qualified class name
     * @return ClassIntrospector Fluent query builder for class inspection
     */
    public static function class(string $className): ClassIntrospector
    {
        return new ClassIntrospector($className);
    }

    /**
     * Create an instance introspector for the given object.
     *
     * @param  object $instance Object instance to inspect
     * @return InstanceIntrospector Fluent query builder for instance inspection
     */
    public static function instance(object $instance): InstanceIntrospector
    {
        return new InstanceIntrospector($instance);
    }

    /**
     * Create a trait query builder.
     *
     * @return TraitIntrospector Fluent query builder for trait queries
     */
    public static function traits(): TraitIntrospector
    {
        return new TraitIntrospector();
    }

    /**
     * Create an interface query builder.
     *
     * @return InterfaceIntrospector Fluent query builder for interface queries
     */
    public static function interfaces(): InterfaceIntrospector
    {
        return new InterfaceIntrospector();
    }
}
