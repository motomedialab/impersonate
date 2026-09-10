<?php

declare(strict_types=1);

namespace Motomedialab\Impersonate\Actions;

use Motomedialab\Impersonate\Events\ImpersonateEnded;
use Motomedialab\Impersonate\Contracts\CanBeImpersonated;
use Motomedialab\Impersonate\Contracts\ImpersonatableUser;
use Motomedialab\Impersonate\Managers\ImpersonationManager;

final readonly class EndImpersonation
{
    public function __construct(private ImpersonationManager $manager)
    {
        //
    }

    public function __invoke(): void
    {
        $targetId = $this->manager->getUserId();
        $guard = $this->manager->getAuthGuard();

        $user = $targetId !== null ? $this->manager->findUser($targetId, $guard) : null;

        if ($user instanceof CanBeImpersonated || $user instanceof ImpersonatableUser) {
            event(new ImpersonateEnded($user));
        }

        $this->manager->clearSession();
    }
}
