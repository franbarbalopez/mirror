<?php

declare(strict_types=1);

namespace Mirror\Facades;

use Illuminate\Support\Facades\Facade;
use Mirror\ImpersonationManager;

/**
 * @method static void impersonate(\Illuminate\Contracts\Auth\Authenticatable $target, ?string $guard = null, array<string, mixed> $context = [])
 * @method static array<string, mixed> leave()
 * @method static bool active()
 * @method static bool expired()
 * @method static \Illuminate\Contracts\Auth\Authenticatable|null impersonator()
 * @method static \Illuminate\Contracts\Auth\Authenticatable|null impersonated()
 * @method static array<string, mixed> context()
 *
 * @see ImpersonationManager
 */
final class Mirror extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'mirror';
    }
}
