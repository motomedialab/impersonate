<?php

declare(strict_types=1);

namespace Motomedialab\Impersonate\Actions;

use Motomedialab\Impersonate\Contracts\CanImpersonate;
use Motomedialab\Impersonate\Contracts\CanBeImpersonated;
use Motomedialab\Impersonate\Contracts\ImpersonatableUser;
use Motomedialab\Impersonate\Managers\ImpersonationManager;

final readonly class DetermineRedirectUrl
{
    public function __construct(private ImpersonationManager $manager)
    {
    }

    public function __invoke(
        CanBeImpersonated|ImpersonatableUser $target,
        CanImpersonate|ImpersonatableUser $actor
    ): string {
        // return the target redirect if defined
        if (method_exists($target, 'impersonationRedirectTo')) {
            $destination = $target->impersonationRedirectTo();

            if (is_string($destination) && ! empty($destination)) {
                return $destination;
            }
        }

        // return the manager callback if defined
        if (($callback = $this->manager->getRedirectToCallback()) !== null) {
            $destination = $callback($target, $actor);

            if (is_string($destination) && ! empty($destination)) {
                return $destination;
            }
        }

        // default fallback
        return config('impersonate.redirect_to') ?? '/';
    }
}
