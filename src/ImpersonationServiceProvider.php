<?php

declare(strict_types=1);

namespace Motomedialab\Impersonate;

use Illuminate\Container\Container;
use Illuminate\Support\ServiceProvider;
use Motomedialab\Impersonate\Managers\ImpersonationManager;
use Motomedialab\Impersonate\Middleware\ImpersonationMiddleware;

final class ImpersonationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // define our routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        // define our driver
        $this->app->singleton(ImpersonationManager::class, function () {
            return new ImpersonationManager(fn () => Container::getInstance());
        });

        $this->app->alias(ImpersonationManager::class, 'impersonation');

        // push our middleware onto web routing...
        $this->app['router']->pushMiddlewareToGroup('web', ImpersonationMiddleware::class);
    }
}
