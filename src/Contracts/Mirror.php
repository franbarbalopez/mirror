<?php

declare(strict_types=1);

namespace Mirror\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Mirror\Exceptions\CanNotBeImpersonated;
use Mirror\Exceptions\CanNotImpersonate;
use Mirror\Exceptions\ImpersonationAlreadyActive;
use Mirror\Exceptions\ImpersonationExpired;
use Mirror\Exceptions\ImpersonationNotActive;
use Mirror\Exceptions\TamperedImpersonationState;
use Mirror\Exceptions\UnsupportedGuard;
use Mirror\ImpersonationPayload;

interface Mirror
{
    /**
     * @throws CanNotBeImpersonated
     * @throws CanNotImpersonate
     * @throws ImpersonationAlreadyActive
     * @throws UnsupportedGuard
     */
    public function impersonate(
        Authenticatable $target,
        ?string $guard = null,
        ?string $leaveUrl = null,
    ): void;

    /**
     * @throws ImpersonationExpired
     * @throws ImpersonationNotActive
     */
    public function leave(): void;

    /**
     * @throws ImpersonationNotActive
     */
    public function forceLeave(): void;

    public function active(): bool;

    public function expired(): bool;

    /**
     * @throws TamperedImpersonationState
     */
    public function payload(): ?ImpersonationPayload;

    /**
     * @throws TamperedImpersonationState
     */
    public function impersonator(): ?Authenticatable;

    /**
     * @throws TamperedImpersonationState
     */
    public function impersonatorId(): int|string|null;

    /**
     * @throws TamperedImpersonationState
     */
    public function impersonated(): ?Authenticatable;

    /**
     * @throws TamperedImpersonationState
     */
    public function leaveUrl(): ?string;

    public function expiredRedirectUrl(): string;
}
