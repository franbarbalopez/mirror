<?php

namespace Mirror\Exceptions;

class TamperedSessionException extends ImpersonationException
{
    public static function detected(): self
    {
        return new self('Impersonation session data has been tampered with. For security reasons, the session has been cleared.');
    }
}
