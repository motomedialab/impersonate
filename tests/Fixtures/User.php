<?php

declare(strict_types=1);

namespace Motomedialab\Impersonate\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Motomedialab\Impersonate\Contracts\ImpersonatableUser;

final class User extends Authenticatable implements ImpersonatableUser
{
    protected $table = 'users';

    protected $guarded = [];

    protected $casts = [
        'can_impersonate' => 'boolean',
        'can_be_impersonated' => 'boolean',
    ];

    public function canImpersonate(ImpersonatableUser $user): bool
    {
        return (bool) ($this->can_impersonate ?? true);
    }

    public function canBeImpersonatedBy(ImpersonatableUser $user): bool
    {
        return (bool) ($this->can_be_impersonated ?? true);
    }
}
