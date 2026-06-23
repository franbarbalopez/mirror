<?php

declare(strict_types=1);

namespace Mirror;

use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Mirror\Exceptions\UnsupportedGuard;
use ReflectionClass;

final class Guard
{
    public static function from(Authenticatable $user): string
    {
        $matches = self::guardsFor($user);

        if ($matches->isEmpty()) {
            throw UnsupportedGuard::cannotInferFor($user);
        }

        return $matches->first();
    }

    public static function authenticated(): ?string
    {
        return self::sessionGuards()
            ->first(fn (string $guard): bool => Auth::guard($guard)->check());
    }

    public static function ensureSession(string $guard): void
    {
        $instance = auth()->guard($guard);

        if (! $instance instanceof SessionGuard) {
            throw UnsupportedGuard::notSession($guard);
        }
    }

    /**
     * @return Collection<int, string>
     */
    private static function guardsFor(Authenticatable $user): Collection
    {
        $modelGuards = self::modelGuards($user);

        if ($modelGuards->isNotEmpty()) {
            $modelGuards->each(function (string $guard): void {
                self::ensureSession($guard);
            });

            return $modelGuards;
        }

        return self::sessionGuards()
            ->filter(function (string $guard) use ($user): bool {
                $config = config(sprintf('auth.guards.%s', $guard), []);
                $provider = $config['provider'] ?? null;

                if (! is_string($provider)) {
                    return false;
                }

                $model = self::providerModel($provider);

                return is_string($model) && $user instanceof $model;
            })
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    private static function sessionGuards(): Collection
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
    private static function modelGuards(Authenticatable $user): Collection
    {
        if (method_exists($user, 'guardName')) {
            return self::guardNames($user->guardName());
        }

        if (method_exists($user, 'getAttributeValue')) {
            $guard = $user->getAttributeValue('guard_name');

            if ($guard !== null) {
                return self::guardNames($guard);
            }
        }

        $guard = (new ReflectionClass($user))->getDefaultProperties()['guard_name'] ?? null;

        return self::guardNames($guard);
    }

    /**
     * @return Collection<int, string>
     */
    private static function guardNames(mixed $guard): Collection
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

    private static function providerModel(string $provider): ?string
    {
        /** @var ?string $model */
        $model = config(sprintf('auth.providers.%s.model', $provider));

        return $model;
    }
}
