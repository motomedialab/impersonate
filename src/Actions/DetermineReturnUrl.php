<?php

declare(strict_types=1);

namespace Motomedialab\Impersonate\Actions;

use Motomedialab\Impersonate\Contracts\CanImpersonate;
use Motomedialab\Impersonate\Contracts\ImpersonatableUser;
use Motomedialab\Impersonate\Managers\ImpersonationManager;

final readonly class DetermineReturnUrl
{
    public function __construct(private ImpersonationManager $manager)
    {
        //
    }

    public function __invoke(CanImpersonate|ImpersonatableUser $actor): string
    {
        // return the actor redirect if defined
        if (method_exists($actor, 'impersonationReturnTo')) {
            $candidate = $actor->impersonationReturnTo();
            if (is_string($candidate) && ! empty($candidate)) {
                return $candidate;
            }
        }

        // return the manager callback if defined
        if (($callback = $this->manager->getReturnToCallback()) !== null) {
            $candidate = $callback($actor);
            if (is_string($candidate) && ! empty($candidate)) {
                return $candidate;
            }
        }

        // default fallback
        return config('impersonate.return_to') ?? $this->manager->getReferrer() ?? '/';
    }
}
