<?php

declare(strict_types=1);

namespace Motomedialab\Impersonate\Actions;

use Motomedialab\Impersonate\Events\ImpersonateEnded;
use Motomedialab\Impersonate\Contracts\CanBeImpersonated;
use Motomedialab\Impersonate\Managers\ImpersonationManager;

final readonly class EndImpersonation
{
    public function __construct(private ImpersonationManager $manager)
    {
        //
    }

    public function __invoke(): void
    {
        $userId = $this->manager->getUserId();
        $guard = $this->manager->getAuthGuard();

        if ($userId !== null && $guard !== null) {
            $user = $this->manager->findUser($userId, $guard);

            if ($user instanceof CanBeImpersonated) {
                event(new ImpersonateEnded($user));
            }
        }

        $this->manager->clearSession();
    }
}
