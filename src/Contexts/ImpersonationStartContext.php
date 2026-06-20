<?php

declare(strict_types=1);

namespace Mirror\Contexts;

use Illuminate\Contracts\Auth\Authenticatable;

final class ImpersonationStartContext
{
    private string $targetGuard;

    private string $impersonatorGuard;

    private Authenticatable $impersonator;

    public function __construct(
        private readonly Authenticatable $target,
        private readonly ?string $requestedGuard = null,
        private readonly ?string $leaveUrl = null,
    ) {}

    public function target(): Authenticatable
    {
        return $this->target;
    }

    public function requestedGuard(): ?string
    {
        return $this->requestedGuard;
    }

    public function leaveUrl(): ?string
    {
        return $this->leaveUrl;
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
