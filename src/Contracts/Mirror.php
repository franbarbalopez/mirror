<?php

declare(strict_types=1);

namespace Mirror\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Mirror\Exceptions\CanNotBeImpersonated;
use Mirror\Exceptions\CanNotImpersonate;
use Mirror\Exceptions\ImpersonationAlreadyActive;
use Mirror\Exceptions\ImpersonationNotActive;
use Mirror\Exceptions\TamperedImpersonationState;
use Mirror\Exceptions\UnsupportedGuard;
use Mirror\ImpersonationPayload;

interface Mirror
{
    /**
     * @param  array<string, mixed>  $context
     *
     * @throws CanNotBeImpersonated
     * @throws CanNotImpersonate
     * @throws ImpersonationAlreadyActive
     * @throws UnsupportedGuard
     */
    public function impersonate(
        Authenticatable $target,
        ?string $guard = null,
        array $context = [],
    ): void;

    /**
     * @throws ImpersonationNotActive
     * @throws TamperedImpersonationState
     */
    public function leave(): ImpersonationPayload;

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
     * @return array<string, mixed>
     *
     * @throws TamperedImpersonationState
     */
    public function context(): array;

    public function expiredRedirectUrl(): string;
}
