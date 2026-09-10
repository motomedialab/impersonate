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

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => 'file:impersonation_test?mode=memory&cache=shared',
            'prefix' => '',
        ]);

        $app['config']->set('session.driver', 'array');
        $app['config']->set('auth.providers.users.model', Fixtures\User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        \Illuminate\Support\Facades\Schema::connection('sqlite')->dropIfExists('users');
        \Illuminate\Support\Facades\Schema::connection('sqlite')->create('users', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('can_impersonate')->default(true);
            $table->boolean('can_be_impersonated')->default(true);
            $table->timestamps();
        });
    }
}
