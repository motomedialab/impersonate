<?php

declare(strict_types=1);

namespace Motomedialab\Impersonate\Actions;

use Motomedialab\Impersonate\Contracts\CanImpersonate;
use Motomedialab\Impersonate\Contracts\CanBeImpersonated;
use Motomedialab\Impersonate\Managers\ImpersonationManager;

final readonly class DetermineRedirectUrl
{
    public function __construct(private ImpersonationManager $manager)
    {
        //
    }

    public function __invoke(
        CanBeImpersonated $target,
        CanImpersonate $actor
    ): string {
        if (method_exists($target, 'impersonationRedirectTo')) {
            $destination = $target->impersonationRedirectTo();
            if (is_string($destination) && $destination !== '') {
                return $destination;
            }
        }

        $callback = $this->manager->getRedirectToCallback();
        if ($callback !== null) {
            $destination = $callback($target, $actor);
            if (is_string($destination) && $destination !== '') {
                return $destination;
            }
        }

        $configured = config('impersonate.redirect_to');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return '/';
    }
}
