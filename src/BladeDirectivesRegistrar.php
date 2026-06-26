<?php

declare(strict_types=1);

namespace Mirror;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Blade;
use Mirror\Contracts\Impersonatable;

class BladeDirectivesRegistrar
{
    public function __construct(
        protected SessionImpersonationStore $store,
    ) {}

    /**
     * Register Mirror's Blade condition directives.
     */
    public function register(): void
    {
        Blade::if('impersonating', fn (?string $guard = null): bool => $this->impersonating($guard));
        Blade::if('notImpersonating', fn (?string $guard = null): bool => ! $this->impersonating($guard));
        Blade::if('canImpersonate', fn (?string $guard = null): bool => $this->canImpersonate($guard));
        Blade::if('canBeImpersonated', fn (?Authenticatable $user = null, ?string $guard = null): bool => $this->canBeImpersonated($user, $guard));
    }

    protected function impersonating(?string $guard): bool
    {
        $payload = $this->store->get();

        if (! $payload instanceof ImpersonationPayload) {
            return false;
        }

        return $guard === null || $payload->impersonatedGuard === $guard;
    }

    protected function canImpersonate(?string $guard): bool
    {
        $user = auth($guard)->user();

        return $user instanceof Impersonatable && $user->canImpersonate();
    }

    protected function canBeImpersonated(?Authenticatable $user, ?string $guard): bool
    {
        $user ??= auth($guard)->user();

        return $user instanceof Impersonatable && $user->canBeImpersonated();
    }
}
