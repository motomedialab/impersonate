<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Motomedialab\Impersonate\Facades\Impersonate;
use Motomedialab\Impersonate\Tests\Fixtures\User;
use Motomedialab\Impersonate\Events\ImpersonateBegun;
use Motomedialab\Impersonate\Tests\Fixtures\AdminUser;
use Motomedialab\Impersonate\Actions\BeginImpersonation;
use Motomedialab\Impersonate\Tests\Fixtures\CustomerUser;
use Motomedialab\Impersonate\Managers\ImpersonationManager;
use Motomedialab\Impersonate\Exceptions\ImpersonationException;

beforeEach(function () {
    Impersonate::flushCallbacks();
});

it('initiates an impersonation session successfully', function () {
    Event::fake();

    $actor = User::create(['email' => 'actor@example.com', 'password' => 'secret']);
    $target = User::create(['email' => 'target@example.com', 'password' => 'secret']);

    $action = app(BeginImpersonation::class);
    $action($actor, $target);

    $manager = app(ImpersonationManager::class);
    expect($manager->isImpersonating())->toBeTrue()
        ->and($manager->getUserId())->toBe($target->id);

    Event::assertDispatched(ImpersonateBegun::class, function (ImpersonateBegun $event) use ($actor, $target) {
        return $event->user->getAuthIdentifier() === $target->id
            && $event->impersonatedBy->getAuthIdentifier() === $actor->id;
    });
});

it('throws an exception if an impersonation session is already active', function () {
    $actor = User::create(['email' => 'actor@example.com', 'password' => 'secret']);
    $target1 = User::create(['email' => 'target1@example.com', 'password' => 'secret']);
    $target2 = User::create(['email' => 'target2@example.com', 'password' => 'secret']);

    $manager = app(ImpersonationManager::class);
    $manager->impersonate($target1->id, 'web');

    $action = app(BeginImpersonation::class);

    expect(fn () => $action($actor, $target2))
        ->toThrow(ImpersonationException::class, 'An active impersonation session is already running');
});

it('throws an exception if the target cannot be impersonated', function () {
    $actor = AdminUser::create([
        'email' => 'admin@example.com',
        'password' => 'secret',
        'can_impersonate' => true,
    ]);

    $target = CustomerUser::create([
        'email' => 'target@example.com',
        'password' => 'secret',
        'can_be_impersonated' => false,
    ]);

    $action = app(BeginImpersonation::class);

    expect(fn () => $action($actor, $target))
        ->toThrow(ImpersonationException::class, 'The provided target cannot be impersonated');
});

it('throws an exception if the actor does not have permission to impersonate target', function () {
    $actor = AdminUser::create([
        'email' => 'admin@example.com',
        'password' => 'secret',
        'can_impersonate' => false,
    ]);

    $target = CustomerUser::create([
        'email' => 'target@example.com',
        'password' => 'secret',
        'can_be_impersonated' => true,
    ]);

    $action = app(BeginImpersonation::class);

    expect(fn () => $action($actor, $target))
        ->toThrow(ImpersonationException::class, 'The actor doesnt have permission to impersonate the target user');
});
