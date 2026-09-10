<?php

declare(strict_types=1);

namespace Motomedialab\Impersonate\Tests\Fixtures;

use Motomedialab\Impersonate\Contracts\CanImpersonate;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Motomedialab\Impersonate\Contracts\CanBeImpersonated;

final class CustomRedirectUser extends Authenticatable implements CanImpersonate, CanBeImpersonated
{
    protected $table = 'users';

    protected $guarded = [];

    public function canImpersonate(CanBeImpersonated $user): bool
    {
        return true;
    }

    public function canBeImpersonatedBy(CanImpersonate $user): bool
    {
        return true;
    }

    public function impersonationRedirectTo(): ?string
    {
        return '/model-redirect';
    }

    public function impersonationReturnTo(): ?string
    {
        return '/model-return';
    }
}
