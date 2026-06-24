<?php

declare(strict_types=1);

namespace Mirror;

use Illuminate\Contracts\Auth\Authenticatable;

final class PendingImpersonation
{
    private string $targetGuard;

    private string $impersonatorGuard;

    private Authenticatable $impersonator;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        private readonly Authenticatable $target,
        ?string $targetGuard = null,
        private readonly array $context = [],
    ) {
        if ($targetGuard !== null) {
            $this->targetGuard = $targetGuard;
        }
    }

    public function target(): Authenticatable
    {
        return $this->target;
    }

    public function hasTargetGuard(): bool
    {
        return isset($this->targetGuard);
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }

    public function targetGuard(): string
    {
        return $this->targetGuard;
    }

    public function setTargetGuard(string $targetGuard): void
    {
        $this->targetGuard = $targetGuard;
    }

    public function impersonatorGuard(): string
    {
        return $this->impersonatorGuard;
    }

    public function setImpersonatorGuard(string $impersonatorGuard): void
    {
        $this->impersonatorGuard = $impersonatorGuard;
    }

    public function impersonator(): Authenticatable
    {
        return $this->impersonator;
    }

    public function setImpersonator(Authenticatable $impersonator): void
    {
        $this->impersonator = $impersonator;
    }
}
