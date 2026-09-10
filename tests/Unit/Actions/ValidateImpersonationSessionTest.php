<?php

declare(strict_types=1);

use Motomedialab\Impersonate\Tests\Fixtures\User;
use Motomedialab\Impersonate\Tests\Fixtures\AdminUser;
use Motomedialab\Impersonate\Tests\Fixtures\CustomerUser;
use Motomedialab\Impersonate\Managers\ImpersonationManager;
use Motomedialab\Impersonate\Actions\ValidateImpersonationSession;

it('returns false when current user is null', function () {
    $action = app(ValidateImpersonationSession::class);

    expect($action(null))->toBeFalse();
});

it('returns false when session target ID is missing', function () {
    $actor = User::create(['email' => 'admin@example.com', 'password' => 'secret']);

    $action = app(ValidateImpersonationSession::class);

    expect($action($actor))->toBeFalse();
});

it('returns false when target user does not exist', function () {
    $actor = User::create(['email' => 'admin@example.com', 'password' => 'secret']);

    $manager = app(ImpersonationManager::class);
    $manager->impersonate(9999, 'web');

    $action = app(ValidateImpersonationSession::class);

    expect($action($actor))->toBeFalse();
});

it('returns false when target user cannot be impersonated by actor', function () {
    config(['auth.providers.users.model' => CustomerUser::class]);

    $actor = AdminUser::create([
        'email' => 'admin@example.com',
        'password' => 'secret',
        'can_impersonate' => true,
    ]);

    $target = CustomerUser::create([
        'email' => 'customer@example.com',
        'password' => 'secret',
        'can_be_impersonated' => false,
    ]);

    $manager = app(ImpersonationManager::class);
    $manager->impersonate($target->id, 'web');

    $action = app(ValidateImpersonationSession::class);

    expect($action($actor))->toBeFalse();
});

it('returns false when actor loses permission to impersonate target', function () {
    config(['auth.providers.users.model' => CustomerUser::class]);

    $actor = AdminUser::create([
        'email' => 'admin@example.com',
        'password' => 'secret',
        'can_impersonate' => false,
    ]);

    $target = CustomerUser::create([
        'email' => 'customer@example.com',
        'password' => 'secret',
        'can_be_impersonated' => true,
    ]);

    $manager = app(ImpersonationManager::class);
    $manager->impersonate($target->id, 'web');

    $action = app(ValidateImpersonationSession::class);

    expect($action($actor))->toBeFalse();
});

it('returns true when both actor and target satisfy permissions', function () {
    config(['auth.providers.users.model' => CustomerUser::class]);

    $actor = AdminUser::create([
        'email' => 'admin@example.com',
        'password' => 'secret',
        'can_impersonate' => true,
    ]);

    $target = CustomerUser::create([
        'email' => 'customer@example.com',
        'password' => 'secret',
        'can_be_impersonated' => true,
    ]);

    $manager = app(ImpersonationManager::class);
    $manager->impersonate($target->id, 'web');

    $action = app(ValidateImpersonationSession::class);

    expect($action($actor))->toBeTrue();
});
