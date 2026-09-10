<?php

declare(strict_types=1);

namespace Motomedialab\Impersonate\Contracts;

interface CanImpersonate
{
    public function canImpersonate(CanBeImpersonated $user): bool;
}
