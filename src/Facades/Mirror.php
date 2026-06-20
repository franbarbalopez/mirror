<?php

declare(strict_types=1);

namespace Mirror\Facades;

use Illuminate\Support\Facades\Facade;
use Mirror\Data\ImpersonationPayload;
use Mirror\ImpersonationManager;

/**
 * @method static void impersonate(\Illuminate\Contracts\Auth\Authenticatable $target, ?string $guard = null, ?string $leaveUrl = null)
 * @method static void stop()
 * @method static void forceStop()
 * @method static bool active()
 * @method static bool expired()
 * @method static ImpersonationPayload|null payload()
 * @method static \Illuminate\Contracts\Auth\Authenticatable|null impersonator()
 * @method static int|string|null impersonatorId()
 * @method static \Illuminate\Contracts\Auth\Authenticatable|null impersonated()
 * @method static ?string leaveUrl()
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
