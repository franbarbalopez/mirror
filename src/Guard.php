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
     * Infer the session-backed guard that owns the given user model.
     *
     * @throws CannotInferTargetGuard
     */
    public static function from(Authenticatable $user): string
    {
        $matches = static::guardsFor($user);

        if ($matches->isEmpty()) {
            throw CannotInferTargetGuard::make($user);
        }

        return $matches->first();
    }

    /**
     * Return the currently authenticated session-backed guard name, if any.
     */
    public static function authenticated(): ?string
    {
        return static::sessionDriverGuards()
            ->first(fn (string $guard): bool => Auth::guard($guard)->check());
    }

    /**
     * Ensure the named guard is backed by Laravel's session guard implementation.
     *
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
     * Return all session-backed guards that can authenticate the given user model.
     *
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
     * Return the configured guard names that use Laravel's session driver.
     *
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
     * Resolve guard names explicitly declared by the model, if any.
     *
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
     * Normalize a guard declaration into a list of string guard names.
     *
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

    /**
     * Return the configured model class for the given auth provider.
     */
    protected static function providerModel(string $provider): ?string
    {
        /** @var ?string $model */
        $model = config(sprintf('auth.providers.%s.model', $provider));

        return $model;
    }
}
