<?php

declare(strict_types=1);

namespace Mirror\Exceptions;

/** @phpstan-consistent-constructor */
class MissingAuthenticatedSessionGuard extends MirrorException implements CannotStartImpersonation
{
    public static function make(): static
    {
        return new static("Could not find an authenticated guard using Laravel's [session] driver for the impersonator. Authenticate the impersonator with a guard that uses the [session] driver before starting impersonation.");
    }
}
