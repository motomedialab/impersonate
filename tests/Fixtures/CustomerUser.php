<?php

declare(strict_types=1);

namespace Motomedialab\Impersonate\Tests\Fixtures;

use Motomedialab\Impersonate\Contracts\CanImpersonate;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Motomedialab\Impersonate\Contracts\CanBeImpersonated;

final class CustomerUser extends Authenticatable implements CanBeImpersonated
{
    protected $table = 'users';

    protected $guarded = [];

    protected $casts = [
        'can_be_impersonated' => 'boolean',
    ];

    public function canBeImpersonatedBy(CanImpersonate $user): bool
    {
        return (bool) ($this->can_be_impersonated ?? true);
    }
}
