<?php

declare(strict_types=1);

namespace Motomedialab\Impersonate\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Motomedialab\Impersonate\Contracts\CanImpersonate;
use Motomedialab\Impersonate\Contracts\CanBeImpersonated;
use Motomedialab\Impersonate\Contracts\ImpersonatableUser;
use Motomedialab\Impersonate\Managers\ImpersonationManager;

final readonly class ValidateImpersonationSession
{
    public function __construct(private ImpersonationManager $manager)
    {
        //
    }

    public function __invoke(?Authenticatable $actor): bool
    {
        if (! $actor instanceof CanImpersonate && ! $actor instanceof ImpersonatableUser) {
            return false;
        }

        if (($targetId = $this->manager->getUserId()) === null) {
            return false;
        }

        $target = $this->manager->findUser($targetId, $this->manager->getAuthGuard());

        if (! $target instanceof CanBeImpersonated && ! $target instanceof ImpersonatableUser) {
            return false;
        }

        return $target->canBeImpersonatedBy($actor) && $actor->canImpersonate($target);
    }
}
