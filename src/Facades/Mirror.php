<?php

declare(strict_types=1);

namespace Mirror\Facades;

use Illuminate\Support\Facades\Facade;
use Mirror\ImpersonationManager;
use Mirror\ImpersonationPayload;

/**
 * @method static void impersonate(\Illuminate\Contracts\Auth\Authenticatable $target, ?string $guard = null, array<string, mixed> $context = [])
 * @method static ImpersonationPayload leave()
 * @method static bool active()
 * @method static bool expired()
 * @method static ImpersonationPayload|null payload()
 * @method static \Illuminate\Contracts\Auth\Authenticatable|null impersonator()
 * @method static int|string|null impersonatorId()
 * @method static \Illuminate\Contracts\Auth\Authenticatable|null impersonated()
 * @method static array<string, mixed> context()
 * @method static string expiredRedirectUrl()
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
