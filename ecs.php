<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\CodingStandard\EasyCodingStandard\Factory;
use PhpCsFixer\Fixer\ClassNotation\FinalClassFixer;
use PhpCsFixer\Fixer\FunctionNotation\VoidReturnFixer;

return Factory::create(
    paths: [__DIR__.'/src', __DIR__.'/tests'],
    skip: [
        // Test files need non-final classes for inheritance testing
        FinalClassFixer::class => [
            __DIR__.'/tests/Unit/HelpersTest.php',
            __DIR__.'/tests/Unit/ClassIntrospectorTest.php',
            __DIR__.'/tests/Unit/InstanceIntrospectorTest.php',
            __DIR__.'/tests/Unit/MethodIntrospectorTest.php',
            __DIR__.'/tests/Fixtures/TestPost.php',
            __DIR__.'/tests/Fixtures/TestUser.php',
            __DIR__.'/tests/Fixtures/TestChildWithInterface.php',
            __DIR__.'/tests/Fixtures/TestProduct.php',
        ],
        // Test fixture needs method without return type for testing
        VoidReturnFixer::class => [
            __DIR__.'/tests/Unit/MethodIntrospectorTest.php',
        ],
    ],
);
