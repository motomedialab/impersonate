<?php

declare(strict_types=1);

namespace Motomedialab\Impersonate\Events;

use Motomedialab\Impersonate\Contracts\CanBeImpersonated;

final class ImpersonateEnded
{
    public function __construct(
        public CanBeImpersonated $user,
    ) {
    }
}
