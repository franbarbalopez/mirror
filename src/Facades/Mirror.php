<?php

namespace Mirror\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static ?string start(\Illuminate\Contracts\Auth\Authenticatable $user, ?string $leaveRedirectUrl = null, ?string $startRedirectUrl = null)
 * @method static ?string startByKey(int|string $key, ?string $leaveRedirectUrl = null, ?string $startRedirectUrl = null)
 * @method static ?string startByEmail(string $email, ?string $leaveRedirectUrl = null, ?string $startRedirectUrl = null)
 * @method static void stop()
 * @method static void forceStop()
 * @method static bool isImpersonating()
 * @method static \Illuminate\Contracts\Auth\Authenticatable|null getImpersonator()
 * @method static ?string getLeaveRedirectUrl()
 * @method static int|string|null impersonatorId()
 * @method static ?string as(\Illuminate\Contracts\Auth\Authenticatable $user, ?string $leaveRedirectUrl = null, ?string $startRedirectUrl = null)
 * @method static void leave()
 * @method static bool impersonating()
 * @method static \Illuminate\Contracts\Auth\Authenticatable|null impersonator()
 *
 * @throws \Mirror\Exceptions\ImpersonationException
 *
 * @see \Mirror\Impersonator
 */
class Mirror extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'mirror';
    }
}
