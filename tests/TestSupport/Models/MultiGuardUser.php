<?php

declare(strict_types=1);

namespace Mirror\Tests\TestSupport\Models;

use App\Models\User;

class MultiGuardUser extends User
{
    /**
     * @return list<string>
     */
    public function guardName(): array
    {
        return ['web', 'customer'];
    }
}
