<?php

declare(strict_types=1);

namespace Motomedialab\Impersonate\Events;

use Motomedialab\Impersonate\Contracts\ImpersonatableUser;

final class ImpersonationBegun
{
    public function __construct(public ImpersonatableUser $user, public ImpersonatableUser $impersonatedBy)
    {
        //
    }
}
