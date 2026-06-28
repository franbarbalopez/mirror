<?php

declare(strict_types=1);

namespace Mirror\Tests\TestSupport\Models;

use App\Models\User;

class DefaultGuardUser extends User
{
    protected string $guard_name = 'customer';
}
