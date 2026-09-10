<?php

declare(strict_types=1);

namespace Motomedialab\Impersonate\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * @method string|null impersonationRedirectTo() Optional redirect destination URL when impersonating this user.
 */
interface CanBeImpersonated extends Authenticatable
{
    public function canBeImpersonatedBy(CanImpersonate $user): bool;
}
