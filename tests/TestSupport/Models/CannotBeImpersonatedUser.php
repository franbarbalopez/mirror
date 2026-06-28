<?php

declare(strict_types=1);

namespace Mirror\Tests\TestSupport\Models;

use App\Models\User;

class CannotBeImpersonatedUser extends User
{
    public function canBeImpersonated(): bool
    {
        return false;
    }
}
