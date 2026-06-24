<?php

declare(strict_types=1);

namespace Mirror\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Queue\SerializesModels;

readonly class ImpersonationStopped
{
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public Authenticatable $impersonator,
        public Authenticatable $impersonated,
        public array $context,
    ) {}
}
