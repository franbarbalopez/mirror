<?php

declare(strict_types=1);

namespace Mirror;

use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Mirror\Exceptions\CannotInferTargetGuard;
use Mirror\Exceptions\CannotStartImpersonation;
use Mirror\Exceptions\GuardDoesNotUseSessionDriver;
use ReflectionClass;

class Guard
{
    /**
     * @throws CannotStartImpersonation
     */
    public static function from(Authenticatable $user): string
    {
        $matches = static::guardsFor($user);

        if ($matches->isEmpty()) {
            throw CannotInferTargetGuard::make($user);
        }

        return $matches->first();
    }

    public static function authenticated(): ?string
    {
        return static::sessionDriverGuards()
            ->first(fn (string $guard): bool => Auth::guard($guard)->check());
    }

    /**
     * @throws GuardDoesNotUseSessionDriver
     */
    public static function ensureUsesSessionDriver(string $guard): void
    {
        $instance = auth()->guard($guard);

        if (! $instance instanceof SessionGuard) {
            throw GuardDoesNotUseSessionDriver::make($guard);
        }
    }

    /**
     * @return Collection<int, string>
     */
    protected static function guardsFor(Authenticatable $user): Collection
    {
        $modelGuards = static::modelGuards($user);

        if ($modelGuards->isNotEmpty()) {
            $modelGuards->each(function (string $guard): void {
                static::ensureUsesSessionDriver($guard);
            });

            return $modelGuards;
        }

        return static::sessionDriverGuards()
            ->filter(function (string $guard) use ($user): bool {
                $config = config(sprintf('auth.guards.%s', $guard), []);
                $provider = $config['provider'] ?? null;

                if (! is_string($provider)) {
                    return false;
                }

                $model = static::providerModel($provider);

                return is_string($model) && $user instanceof $model;
            })
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    protected static function sessionDriverGuards(): Collection
    {
        /** @var array<string, mixed> $guards */
        $guards = config('auth.guards', []);

        return collect($guards)
            ->filter(fn (mixed $config): bool => is_array($config) && ($config['driver'] ?? null) === 'session')
            ->keys()
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    protected static function modelGuards(Authenticatable $user): Collection
    {
        if (method_exists($user, 'guardName')) {
            return static::guardNames($user->guardName());
        }

        if (method_exists($user, 'getAttributeValue')) {
            $guard = $user->getAttributeValue('guard_name');

            if ($guard !== null) {
                return static::guardNames($guard);
            }
        }

        $guard = (new ReflectionClass($user))->getDefaultProperties()['guard_name'] ?? null;

        return static::guardNames($guard);
    }

    /**
     * @return Collection<int, string>
     */
    protected static function guardNames(mixed $guard): Collection
    {
        if (is_string($guard)) {
            return collect([$guard]);
        }

        if (! is_array($guard)) {
            return collect();
        }

        return collect($guard)
            ->filter(fn (mixed $guard): bool => is_string($guard))
            ->values();
    }

    protected static function providerModel(string $provider): ?string
    {
        /** @var ?string $model */
        $model = config(sprintf('auth.providers.%s.model', $provider));

        return $model;
    }
}
