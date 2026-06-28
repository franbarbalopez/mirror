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
     * Start impersonating the given target.
     *
     * @param  array<string, mixed>  $context
     *
     * @throws CannotStartImpersonation
     */
    public function impersonate(Authenticatable $target, ?string $guard = null, array $context = []): void;

    /**
     * Stop the active impersonation and return its signed context.
     *
     * @return array<string, mixed>
     *
     * @throws CannotLeaveImpersonation
     */
    public function leave(): array;

    /**
     * Determine whether an impersonation is currently active.
     *
     * @throws CannotReadImpersonationState
     */
    public function active(): bool;

    /**
     * Determine whether the active impersonation has exceeded the configured TTL.
     *
     * @throws CannotReadImpersonationState
     */
    public function expired(): bool;

    /**
     * Return the original impersonator for the active impersonation.
     *
     * @throws CannotReadImpersonationState
     */
    public function impersonator(): ?Authenticatable;

    /**
     * Return the currently impersonated model.
     *
     * @throws CannotReadImpersonationState
     */
    public function impersonated(): ?Authenticatable;

    /**
     * Return the signed context for the active impersonation.
     *
     * @return array<string, mixed>
     *
     * @throws CannotReadImpersonationState
     */
    public function context(): array;
}
