<?php

declare(strict_types=1);

namespace Motomedialab\Impersonate\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Motomedialab\Impersonate\ImpersonationServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ImpersonationServiceProvider::class,
        ];
    }
}
