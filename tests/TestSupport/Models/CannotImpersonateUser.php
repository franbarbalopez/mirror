<?php

declare(strict_types=1);

namespace Mirror\Tests\TestSupport\Models;

use App\Models\User;

class CannotImpersonateUser extends User
{
    public function canImpersonate(): bool
    {
        return false;
    }
}
