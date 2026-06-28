<?php

declare(strict_types=1);

namespace Mirror\Exceptions;

/** @phpstan-consistent-constructor */
class ImpersonationAlreadyActive extends MirrorException implements CannotStartImpersonation
{
    public static function make(): static
    {
        return new static('An impersonation is already active. Leave the current impersonation before starting a new one.');
    }
}
