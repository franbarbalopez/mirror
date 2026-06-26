<?php

declare(strict_types=1);

namespace Mirror\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Mirror\Exceptions\CannotLeaveImpersonation;
use Mirror\Exceptions\CannotReadImpersonationState;
use Mirror\Exceptions\CannotStartImpersonation;

interface Mirror
{
    /**
     * @param  array<string, mixed>  $context
     *
     * @throws CannotStartImpersonation
     */
    public function impersonate(Authenticatable $target, ?string $guard = null, array $context = []): void;

    /**
     * @return array<string, mixed>
     *
     * @throws CannotLeaveImpersonation
     */
    public function leave(): array;

    /**
     * @throws CannotReadImpersonationState
     */
    public function active(): bool;

    /**
     * @throws CannotReadImpersonationState
     */
    public function expired(): bool;

    /**
     * @throws CannotReadImpersonationState
     */
    public function impersonator(): ?Authenticatable;

    /**
     * @throws CannotReadImpersonationState
     */
    public function impersonated(): ?Authenticatable;

    /**
     * @return array<string, mixed>
     *
     * @throws CannotReadImpersonationState
     */
    public function context(): array;
}
