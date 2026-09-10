<?php

declare(strict_types=1);

namespace Motomedialab\Impersonate\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * @method string|null impersonationRedirectTo() Optional redirect destination URL when impersonating this user.
 * @method string|null impersonationReturnTo() Optional return destination URL when ending impersonation.
 *
 * @deprecated Use CanImpersonate and/or CanBeImpersonated contracts instead.
 */
interface ImpersonatableUser extends Authenticatable
{
    public function canImpersonate(ImpersonatableUser $user): bool;

    public function canBeImpersonatedBy(ImpersonatableUser $user): bool;
}
