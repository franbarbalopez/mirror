<?php

namespace Mirror\Exceptions;

use Exception;

class ImpersonationException extends Exception
{
    public static function notEnabled(): self
    {
        return new self('Impersonation is not enabled.');
    }

    public static function alreadyImpersonating(): self
    {
        return new self('You are already impersonating a user. Please stop the current impersonation before starting a new one.');
    }

    public static function notImpersonating(): self
    {
        return new self('You are not impersonating any user.');
    }

    public static function cannotImpersonate(): self
    {
        return new self('You do not have permission to impersonate users.');
    }

    public static function cannotBeImpersonated(): self
    {
        return new self('This user cannot be impersonated.');
    }

    public static function expired(): self
    {
        return new self('The impersonation session has expired.');
    }
}
