<?php

declare(strict_types=1);

use Motomedialab\Impersonate\ValueObjects\ImpersonationSession;

it('creates an impersonation session from array data', function () {
    $session = ImpersonationSession::fromSession([
        'target_id' => 12,
        'target_guard' => 'web',
        'actor_id' => 1,
        'actor_guard' => 'admin',
    ]);

    expect($session)->not->toBeNull()
        ->targetId->toBe(12)
        ->targetGuard->toBe('web')
        ->actorId->toBe(1)
        ->actorGuard->toBe('admin');
});

it('creates an impersonation session from legacy string format', function () {
    $session = ImpersonationSession::fromSession('web::15');

    expect($session)->not->toBeNull()
        ->targetId->toBe(15)
        ->targetGuard->toBe('web')
        ->actorId->toBeNull()
        ->actorGuard->toBeNull();

    $sessionWithActor = ImpersonationSession::fromSession('dealer::20::admin::5');

    expect($sessionWithActor)->not->toBeNull()
        ->targetId->toBe(20)
        ->targetGuard->toBe('dealer')
        ->actorGuard->toBe('admin')
        ->actorId->toBe(5);
});

it('returns null for invalid session data', function () {
    expect(ImpersonationSession::fromSession(null))->toBeNull()
        ->and(ImpersonationSession::fromSession(''))->toBeNull()
        ->and(ImpersonationSession::fromSession([]))->toBeNull()
        ->and(ImpersonationSession::fromSession('invalid-string'))->toBeNull();
});

it('serialises to array correctly', function () {
    $session = new ImpersonationSession(10, 'web', 2, 'admin');

    expect($session->toArray())->toBe([
        'target_id' => 10,
        'target_guard' => 'web',
        'actor_id' => 2,
        'actor_guard' => 'admin',
    ]);
});
