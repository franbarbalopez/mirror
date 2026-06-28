<?php

declare(strict_types=1);

namespace Mirror\Tests\TestSupport\Models;

use App\Models\User;

class ApiGuardUser extends User
{
    public function guardName(): string
    {
        return 'api';
    }
}
