<?php

declare(strict_types=1);

namespace Motomedialab\Impersonate\Actions;

use Motomedialab\Impersonate\Events\ImpersonateBegun;
use Motomedialab\Impersonate\Contracts\CanImpersonate;
use Motomedialab\Impersonate\Contracts\CanBeImpersonated;
use Motomedialab\Impersonate\Contracts\ImpersonatableUser;
use Motomedialab\Impersonate\Managers\ImpersonationManager;
use Motomedialab\Impersonate\Exceptions\ImpersonationException;

final readonly class BeginImpersonation
{
    public function __construct(private ImpersonationManager $manager)
    {
        //
    }

    /**
     * @throws ImpersonationException
     */
    public function __invoke(
        CanImpersonate|ImpersonatableUser $actor,
        CanBeImpersonated|ImpersonatableUser $target,
        ?string $guard = null,
        ?string $actorGuard = null,
    ): void {
        $guard ??= config('auth.defaults.guard');

        if ($this->manager->isImpersonating()) {
            throw new ImpersonationException('An active impersonation session is already running');
        }

        if (! $target->canBeImpersonatedBy($actor)) {
            throw new ImpersonationException('The provided target cannot be impersonated');
        }

        if (! $actor->canImpersonate($target)) {
            throw new ImpersonationException('The actor doesnt have permission to impersonate the target user');
        }

        $this->manager->setReferrer(url()->previous());
        $this->manager->impersonate(
            (int) $target->getAuthIdentifier(),
            $guard,
            (int) $actor->getAuthIdentifier(),
            $actorGuard ?? $this->manager->findActorGuard($actor)
        );

        event(new ImpersonateBegun($target, $actor));
    }
}
