<?php

declare(strict_types=1);

use Motomedialab\Impersonate\Tests\Fixtures\User;
use Motomedialab\Impersonate\Managers\ImpersonationManager;
use Motomedialab\Impersonate\Exceptions\ImpersonationException;
use Motomedialab\Impersonate\Tests\Fixtures\NonImpersonatableUser;

it('throws an exception if beginImpersonation target integer ID does not exist', function () {
    $actor = User::create([
        'email' => 'actor@example.com',
        'password' => 'secret',
    ]);

    $manager = app(ImpersonationManager::class);

    expect(fn () => $manager->beginImpersonation($actor, 99999))
        ->toThrow(ImpersonationException::class, 'The provided user cannot be impersonated');
});

it('throws an exception if beginImpersonation target integer ID is not impersonatable', function () {
    config(['auth.providers.users.model' => NonImpersonatableUser::class]);

    $nonImpersonatable = NonImpersonatableUser::create([
        'email' => 'non-impersonatable@example.com',
        'password' => 'secret',
    ]);

    $actor = User::create([
        'email' => 'actor@example.com',
        'password' => 'secret',
    ]);

    $manager = app(ImpersonationManager::class);

    expect(fn () => $manager->beginImpersonation($actor, $nonImpersonatable->id))
        ->toThrow(ImpersonationException::class, 'The provided user cannot be impersonated');
});
