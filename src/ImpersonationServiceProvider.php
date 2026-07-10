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
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/impersonate.php', 'impersonate'
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/impersonate.php' => config_path('impersonate.php'),
            ], 'impersonate-config');
        }

        // define our routes
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // define our driver
        $this->app->alias(ImpersonationManager::class, 'impersonate');
        $this->app->singleton(ImpersonationManager::class, fn (): ImpersonationManager => new ImpersonationManager(fn () => Container::getInstance()));

        // push our middleware onto routing groups...
        $router = $this->app->make(Router::class);
        $groups = (array) config('impersonate.middleware_groups', ['web', 'api']);

        foreach ($groups as $group) {
            $router->pushMiddlewareToGroup($group, ImpersonationMiddleware::class);
        }
    }
}
