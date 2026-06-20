<?php

declare(strict_types=1);

namespace Mirror;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Blade;
use Mirror\Contracts\Impersonatable;
use Mirror\Contracts\Mirror;

final readonly class BladeDirectivesRegistrar
{
    public function __construct(
        private Mirror $impersonation,
    ) {}

    public function register(): void
    {
        Blade::if('impersonating', fn (?string $guard = null): bool => $this->impersonating($guard));
        Blade::if('notImpersonating', fn (?string $guard = null): bool => ! $this->impersonating($guard));
        Blade::if('canImpersonate', fn (?string $guard = null): bool => $this->canImpersonate($guard));
        Blade::if('canBeImpersonated', fn (?Authenticatable $user = null, ?string $guard = null): bool => $this->canBeImpersonated($user, $guard));
    }

    private function impersonating(?string $guard): bool
    {
        if ($guard === null) {
            return $this->impersonation->active();
        }

        return $this->impersonation->payload()?->impersonatedGuard === $guard;
    }

    private function canImpersonate(?string $guard): bool
    {
        $user = auth($guard)->user();

        return $user instanceof Impersonatable && $user->canImpersonate();
    }

    private function canBeImpersonated(?Authenticatable $user, ?string $guard): bool
    {
        $user ??= auth($guard)->user();

        return $user instanceof Impersonatable && $user->canBeImpersonated();
    }
}
