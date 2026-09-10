<?php

declare(strict_types=1);

namespace Motomedialab\Impersonate\Contracts;

interface CanBeImpersonated
{
    public function canBeImpersonatedBy(CanImpersonate $user): bool;
}
