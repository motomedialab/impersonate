<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Motomedialab\Impersonate\Tests\Fixtures\User;
use Motomedialab\Impersonate\Events\ImpersonateEnded;
use Motomedialab\Impersonate\Actions\EndImpersonation;
use Motomedialab\Impersonate\Managers\ImpersonationManager;

it('clears session and dispatches ImpersonateEnded event when user is impersonated', function () {
    Event::fake();

    $user = User::create(['email' => 'user@example.com', 'password' => 'secret']);

    $manager = app(ImpersonationManager::class);
    $manager->impersonate($user->id, 'web');
    $manager->setReferrer('https://example.com/admin');

    $this->actingAs($user);

    $action = app(EndImpersonation::class);
    $action();

    expect($manager->isImpersonating())->toBeFalse()
        ->and($manager->getReferrer())->toBeNull();

    Event::assertDispatched(ImpersonateEnded::class, function (ImpersonateEnded $event) use ($user) {
        return $event->user->getAuthIdentifier() === $user->id;
    });
});

it('clears session even if no user is authenticated', function () {
    Event::fake();

    $manager = app(ImpersonationManager::class);
    $manager->impersonate(999, 'web');

    $action = app(EndImpersonation::class);
    $action();

    expect($manager->isImpersonating())->toBeFalse();
    Event::assertNotDispatched(ImpersonateEnded::class);
});
