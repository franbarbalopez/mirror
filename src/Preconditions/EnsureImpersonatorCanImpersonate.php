<?php

declare(strict_types=1);

namespace Mirror\Preconditions;

use Closure;
use Mirror\Contracts\Impersonatable;
use Mirror\Exceptions\CanNotImpersonate;
use Mirror\ImpersonationStartContext;

final class EnsureImpersonatorCanImpersonate
{
    public function handle(ImpersonationStartContext $context, Closure $next): mixed
    {
        if (! $context->impersonator() instanceof Impersonatable || ! $context->impersonator()->canImpersonate()) {
            throw new CanNotImpersonate("You don't have permission to impersonate users.");
        }

        return $next($context);
    }
}
