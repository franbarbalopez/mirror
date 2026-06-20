<?php

declare(strict_types=1);

namespace Mirror\Contracts;

interface Impersonatable
{
    public function canImpersonate(): bool;

    public function canBeImpersonated(): bool;
}
