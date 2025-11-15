<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Introspect;

use Cline\Introspect\Query\ClassIntrospector;
use Cline\Introspect\Query\ClassesIntrospector;
use Cline\Introspect\Query\InstanceIntrospector;
use Cline\Introspect\Query\InterfaceIntrospector;
use Cline\Introspect\Query\ModelIntrospector;
use Cline\Introspect\Query\ModelsIntrospector;
use Cline\Introspect\Query\RoutesIntrospector;
use Cline\Introspect\Query\TraitIntrospector;
use Cline\Introspect\Query\ViewsIntrospector;

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

    /**
     * Create a views query builder.
     *
     * @return ViewsIntrospector Fluent query builder for view queries
     */
    public static function views(): ViewsIntrospector
    {
        return new ViewsIntrospector();
    }

    /**
     * Create a routes query builder.
     *
     * @return RoutesIntrospector Fluent query builder for route queries
     */
    public static function routes(): RoutesIntrospector
    {
        return new RoutesIntrospector();
    }

    /**
     * Create a classes query builder.
     *
     * @return ClassesIntrospector Fluent query builder for discovering classes
     */
    public static function classes(): ClassesIntrospector
    {
        return new ClassesIntrospector();
    }

    /**
     * Create a models query builder.
     *
     * @return ModelsIntrospector Fluent query builder for Eloquent model queries
     */
    public static function models(): ModelsIntrospector
    {
        return new ModelsIntrospector();
    }

    /**
     * Get detailed model introspection.
     *
     * @param  string $modelClass Fully-qualified model class name
     * @return ModelIntrospector Detailed model introspector
     */
    public static function model(string $modelClass): ModelIntrospector
    {
        return new ModelIntrospector($modelClass);
    }
}
