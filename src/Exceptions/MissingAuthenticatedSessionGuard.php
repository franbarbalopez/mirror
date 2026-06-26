<?php

declare(strict_types=1);

namespace Mirror\Exceptions;

final class MissingAuthenticatedSessionGuard extends MirrorException implements CannotStartImpersonation
{
    public static function make(): self
    {
        return new self("Could not find an authenticated guard using Laravel's [session] driver for the impersonator. Authenticate the impersonator with a guard that uses the [session] driver before starting impersonation.");
    }
}
