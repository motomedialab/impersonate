<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Motomedialab\Impersonate\Tests\Fixtures\User;
use Motomedialab\Impersonate\Services\ActorResolver;
use Motomedialab\Impersonate\Tests\Fixtures\NonImpersonatableUser;

it('resolves the preferred guard when authenticated', function () {
    config(['auth.guards.admin' => [
        'driver' => 'session',
        'provider' => 'users',
    ]]);

    $admin = User::create([
        'email' => 'admin-resolver@example.com',
        'password' => 'password',
    ]);

    auth('admin')->login($admin);

    $request = Request::create('/');
    $request->setUserResolver(fn ($guard = null) => auth($guard)->user());

    $resolver = new ActorResolver();
    $resolved = $resolver->resolve($request, 'admin');

    expect($resolved)->not->toBeNull()
        ->getAuthIdentifier()->toBe($admin->id);
});

it('resolves an active guard user if default user cannot impersonate', function () {
    config([
        'auth.providers.non_impersonatable' => [
            'driver' => 'eloquent',
            'model' => NonImpersonatableUser::class,
        ],
        'auth.guards.web.provider' => 'non_impersonatable',
        'auth.guards.admin' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ]);

    $regular = NonImpersonatableUser::create([
        'email' => 'regular@example.com',
        'password' => 'password',
    ]);

    $admin = User::create([
        'email' => 'admin-can@example.com',
        'password' => 'password',
    ]);

    auth('web')->login($regular);
    auth('admin')->login($admin);

    $request = Request::create('/');
    $request->setUserResolver(fn ($guard = null) => auth($guard)->user());

    $resolver = new ActorResolver();
    $resolved = $resolver->resolve($request);

    expect($resolved)->not->toBeNull()
        ->getAuthIdentifier()->toBe($admin->id);
});

it('resolves actor guard correctly', function () {
    config(['auth.guards.admin' => [
        'driver' => 'session',
        'provider' => 'users',
    ]]);

    $admin = User::create([
        'email' => 'admin-guard-check@example.com',
        'password' => 'password',
    ]);

    auth('admin')->login($admin);

    $resolver = new ActorResolver();
    expect($resolver->resolveGuard($admin))->toBe('admin');
});
