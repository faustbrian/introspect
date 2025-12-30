<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\CodingStandard\Rector\Factory;
use Rector\CodingStyle\Rector\ClassConst\RemoveFinalFromConstRector;
use Rector\DeadCode\Rector\ClassConst\RemoveUnusedPrivateClassConstantRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveEmptyClassMethodRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPrivateMethodRector;
use Rector\DeadCode\Rector\Property\RemoveUnusedPrivatePropertyRector;
use Rector\Privatization\Rector\ClassConst\PrivatizeFinalClassConstantRector;
use Rector\Privatization\Rector\ClassMethod\PrivatizeFinalClassMethodRector;
use Rector\Privatization\Rector\MethodCall\PrivatizeLocalGetterToPropertyRector;
use Rector\Privatization\Rector\Property\PrivatizeFinalClassPropertyRector;
use Rector\TypeDeclaration\Rector\ArrowFunction\AddArrowFunctionReturnTypeRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;

return Factory::create(
    paths: [__DIR__.'/src', __DIR__.'/tests'],
    skip: [
        // Skip these rules in test files - they remove/modify test helper methods/properties
        RemoveEmptyClassMethodRector::class => [__DIR__.'/tests'],
        RemoveUnusedPrivateMethodRector::class => [__DIR__.'/tests'],
        RemoveUnusedPrivatePropertyRector::class => [__DIR__.'/tests'],
        RemoveUnusedPrivateClassConstantRector::class => [__DIR__.'/tests'],
        PrivatizeFinalClassPropertyRector::class => [__DIR__.'/tests'],
        PrivatizeFinalClassMethodRector::class => [__DIR__.'/tests'],
        PrivatizeFinalClassConstantRector::class => [__DIR__.'/tests'],
        RemoveFinalFromConstRector::class => [__DIR__.'/tests'],
        AddVoidReturnTypeWhereNoReturnRector::class => [__DIR__.'/tests'],
        AddArrowFunctionReturnTypeRector::class => [__DIR__.'/tests'],

        // Skip this rule for toArray method - it breaks PHPStan type inference
        PrivatizeLocalGetterToPropertyRector::class => [
            __DIR__.'/src/Query/MiddlewareIntrospector.php',
        ],
    ],
);
