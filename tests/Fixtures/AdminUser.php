<?php

declare(strict_types=1);

namespace Motomedialab\Impersonate\Tests\Fixtures;

use Motomedialab\Impersonate\Contracts\CanImpersonate;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Motomedialab\Impersonate\Contracts\CanBeImpersonated;

final class AdminUser extends Authenticatable implements CanImpersonate
{
    protected $table = 'users';

    protected $guarded = [];

    protected $casts = [
        'can_impersonate' => 'boolean',
    ];

    public function canImpersonate(CanBeImpersonated $user): bool
    {
        return (bool) ($this->can_impersonate ?? true);
    }
}
