<?php

declare(strict_types=1);

namespace Motomedialab\Impersonate\Events;

use Motomedialab\Impersonate\Contracts\CanImpersonate;
use Motomedialab\Impersonate\Contracts\CanBeImpersonated;

final class ImpersonateBegun
{
    public function __construct(
        public CanBeImpersonated $user,
        public CanImpersonate $impersonatedBy,
    ) {
    }
}
