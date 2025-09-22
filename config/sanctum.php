<?php

use Laravel\Sanctum\Sanctum;

return [

    'stateful' => explode(',', env(
        'SANCTUM_STATEFUL_DOMAINS',
        'localhost,localhost:3000,127..0.1,127.0.0.1:8000,::1'
    )),
   
    'guard' => ['web'], // Use web guard for cookie authentication

    'expiration' => null,

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_cookies' => Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    ],

];