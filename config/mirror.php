<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Impersonation Time To Live (TTL)
    |--------------------------------------------------------------------------
    |
    | The maximum number of seconds an impersonation session may remain active.
    | When this value is null, impersonation sessions do not expire by time.
    |
    */

    'ttl' => null,

    /*
    |--------------------------------------------------------------------------
    | Redirects
    |--------------------------------------------------------------------------
    |
    | Configure the fallback URLs Mirror should use when it needs to redirect
    | after handling impersonation state outside of your controllers.
    |
    */

    'redirects' => [
        'expired' => '/',
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Storage
    |--------------------------------------------------------------------------
    |
    | Mirror stores the signed impersonation payload and its signature using
    | this key as the session namespace.
    |
    */

    'session' => [
        'key' => env('MIRROR_SESSION_KEY', 'mirror.impersonation'),
    ],

];
