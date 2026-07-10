<?php

declare(strict_types=1);

namespace Motomedialab\Impersonate;

use Illuminate\Routing\Router;
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
        $this->app->alias(ImpersonationManager::class, 'impersonation');
        $this->app->singleton(ImpersonationManager::class, fn(): ImpersonationManager => new ImpersonationManager(fn () => Container::getInstance()));


        // push our middleware onto web routing...
        $this->app->make(Router::class)->pushMiddlewareToGroup('web', ImpersonationMiddleware::class);
    }
}
