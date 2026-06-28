<?php

declare(strict_types=1);

namespace Mirror\Exceptions;

/** @phpstan-consistent-constructor */
class ImpersonationNotActive extends MirrorException implements CannotLeaveImpersonation
{
    public static function make(): static
    {
        return new static('There is no active impersonation to leave.');
    }
}
