<?php

declare(strict_types=1);

namespace Motomedialab\Impersonate\Contracts;

/**
 * @deprecated Use CanImpersonate and/or CanBeImpersonated contracts instead.
 */
interface ImpersonatableUser
{
    public function canImpersonate(ImpersonatableUser $user): bool;

    public function canBeImpersonatedBy(ImpersonatableUser $user): bool;
}
