<?php

declare(strict_types=1);

namespace Mirror;

use Illuminate\Contracts\Auth\Authenticatable;

class PendingImpersonation
{
    protected string $targetGuard;

    protected string $impersonatorGuard;

    protected Authenticatable $impersonator;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        protected readonly Authenticatable $target,
        ?string $targetGuard = null,
        protected readonly array $context = [],
    ) {
        if ($targetGuard !== null) {
            $this->targetGuard = $targetGuard;
        }
    }

    /**
     * Return the user model that will be impersonated.
     */
    public function target(): Authenticatable
    {
        return $this->target;
    }

    /**
     * Determine whether a target guard was provided or resolved.
     */
    public function hasTargetGuard(): bool
    {
        return isset($this->targetGuard);
    }

    /**
     * Return the custom context attached to the impersonation attempt.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }

    /**
     * Return the guard that should authenticate the target user.
     */
    public function targetGuard(): string
    {
        return $this->targetGuard;
    }

    /**
     * Set the guard that should authenticate the target user.
     */
    public function setTargetGuard(string $targetGuard): void
    {
        $this->targetGuard = $targetGuard;
    }

    /**
     * Return the guard that authenticated the impersonator.
     */
    public function impersonatorGuard(): string
    {
        return $this->impersonatorGuard;
    }

    /**
     * Set the guard that authenticated the impersonator.
     */
    public function setImpersonatorGuard(string $impersonatorGuard): void
    {
        $this->impersonatorGuard = $impersonatorGuard;
    }

    /**
     * Return the authenticated user that is starting impersonation.
     */
    public function impersonator(): Authenticatable
    {
        return $this->impersonator;
    }

    /**
     * Set the authenticated user that is starting impersonation.
     */
    public function setImpersonator(Authenticatable $impersonator): void
    {
        $this->impersonator = $impersonator;
    }
}
