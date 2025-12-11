<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Illuminate\Contracts\Config\Repository;
use Orchestra\Testbench\TestCase as BaseTestCase;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
abstract class TestCase extends BaseTestCase
{
    protected function defineEnvironment($app): void
    {
        // Configure view paths for ViewsIntrospector tests
        $app->make(Repository::class)->set('view.paths', [
            __DIR__.'/Fixtures/views',
        ]);
    }
}
