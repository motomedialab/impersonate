<?php

declare(strict_types=1);

namespace Motomedialab\Impersonate\Events;

use Motomedialab\Impersonate\Contracts\CanImpersonate;
use Motomedialab\Impersonate\Contracts\CanBeImpersonated;
use Motomedialab\Impersonate\Contracts\ImpersonatableUser;

final class ImpersonateBegun
{
    public function __construct(
        public CanBeImpersonated|ImpersonatableUser $user,
        public CanImpersonate|ImpersonatableUser $impersonatedBy,
    ) {
        //
    }
}
