<?php

declare(strict_types=1);

namespace Mirror\Preconditions;

use Closure;
use Mirror\Contracts\Impersonatable;
use Mirror\Exceptions\CanNotImpersonate;
use Mirror\PendingImpersonation;

final class EnsureImpersonatorCanImpersonate
{
    public function handle(PendingImpersonation $pending, Closure $next): mixed
    {
        if (! $pending->impersonator() instanceof Impersonatable || ! $pending->impersonator()->canImpersonate()) {
            throw CanNotImpersonate::make($pending->impersonator());
        }

        return $next($pending);
    }
}
