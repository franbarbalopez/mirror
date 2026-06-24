<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Impersonation Time To Live (TTL)
    |--------------------------------------------------------------------------
    |
    | The maximum number of seconds an impersonation session may remain active.
    | The default is 30 minutes. Set this value to null to disable expiration.
    |
    */

    'ttl' => 1800,

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
