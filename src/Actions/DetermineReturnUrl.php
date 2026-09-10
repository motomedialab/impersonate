<?php

declare(strict_types=1);

namespace Motomedialab\Impersonate\Actions;

use Motomedialab\Impersonate\Contracts\CanImpersonate;
use Motomedialab\Impersonate\Managers\ImpersonationManager;

final readonly class DetermineReturnUrl
{
    public function __construct(private ImpersonationManager $manager)
    {
        //
    }

    public function __invoke(CanImpersonate $actor): string
    {
        if (method_exists($actor, 'impersonationReturnTo')) {
            $destination = $actor->impersonationReturnTo();
            if (is_string($destination) && $destination !== '') {
                return $destination;
            }
        }

        $callback = $this->manager->getReturnToCallback();
        if ($callback !== null) {
            $destination = $callback($actor);
            if (is_string($destination) && $destination !== '') {
                return $destination;
            }
        }

        $configured = config('impersonate.return_to');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $referrer = $this->manager->getReferrer();
        if (is_string($referrer) && $referrer !== '') {
            return $referrer;
        }

        return '/';
    }
}
