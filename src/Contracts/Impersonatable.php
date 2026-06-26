<?php

declare(strict_types=1);

namespace Mirror\Contracts;

interface Impersonatable
{
    /**
     * Determine whether this model can impersonate.
     */
    public function canImpersonate(): bool;

    /**
     * Determine whether this model can be impersonated.
     */
    public function canBeImpersonated(): bool;
}
