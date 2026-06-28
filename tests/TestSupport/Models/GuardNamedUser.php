<?php

declare(strict_types=1);

namespace Mirror\Tests\TestSupport\Models;

use App\Models\User;

class GuardNamedUser extends User
{
    public function guardName(): string
    {
        return 'customer';
    }
}
