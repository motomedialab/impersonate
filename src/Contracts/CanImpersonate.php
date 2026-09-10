<?php

declare(strict_types=1);

namespace Motomedialab\Impersonate\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * @method string|null impersonationReturnTo() Optional return destination URL when ending impersonation.
 */
interface CanImpersonate extends Authenticatable
{
    public function canImpersonate(CanBeImpersonated $user): bool;
}
