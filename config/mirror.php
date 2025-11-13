<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Impersonation Enabled
    |--------------------------------------------------------------------------
    |
    | This value determines if the impersonation feature is enabled for your
    | application. You may disable this in production or specific environments
    | for security purposes.
    |
    */

    'enabled' => env('MIRROR_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Session Guard
    |--------------------------------------------------------------------------
    |
    | The session guard to use for impersonation. If null, it will use
    | the default guard from your auth configuration.
    |
    */

    'guard' => null,

    /*
    |--------------------------------------------------------------------------
    | Impersonation Time To Live (TTL)
    |--------------------------------------------------------------------------
    |
    | The maximum duration (in seconds) that an impersonation session can last.
    | After this time, the impersonation will automatically expire and the user
    | will be logged out. Set to null for no time limit.
    |
    */

    'ttl' => null,

    /*
    |--------------------------------------------------------------------------
    | Default Redirect URL
    |--------------------------------------------------------------------------
    |
    | The default URL to redirect to when the TTL middleware expires a session
    | and no leave redirect URL was specified. This allows you to customize
    | where users are sent after an automatic session expiration.
    |
    */

    'default_redirect_url' => '/',

];
