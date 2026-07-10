<?php

declare(strict_types=1);

namespace Motomedialab\Impersonate\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

final class NonImpersonatableUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}
