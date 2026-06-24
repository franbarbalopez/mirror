<?php

declare(strict_types=1);

namespace Mirror;

use Illuminate\Contracts\Auth\Authenticatable;

final readonly class EndedImpersonation
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public Authenticatable $impersonator,
        public Authenticatable $impersonated,
        public array $context,
    ) {}
}
