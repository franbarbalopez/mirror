<?php

namespace Mirror\Concerns;

/**
 * @phpstan-ignore trait.unused
 */
trait Impersonatable
{
    /**
     * Determine if the user can impersonate other users.
     */
    public function canImpersonate(): bool
    {
        return true;
    }

    /**
     * Determine if the user can be impersonated by other users.
     */
    public function canBeImpersonated(): bool
    {
        return true;
    }
}
