<?php

use Illuminate\Support\Str;

return [
    'driver' => env('SESSION_DRIVER', 'file'),

    'lifetime' => env('SESSION_LIFETIME', 120),

    'expire_on_close' => false,

    'encrypt' => false,

    'files' => storage_path('framework/sessions'),

    'connection' => env('SESSION_CONNECTION'),

    'table' => 'sessions',

    'store' => env('SESSION_STORE'),

    'lottery' => [2, 100],

    'cookie' => env(
        'SESSION_COOKIE',
        Str::slug(env('APP_NAME', 'laravel'), '_').'_session'
    ),

    'path' => env('SESSION_PATH', '/'),

    'domain' => env('SESSION_DOMAIN'),

    // null = "Secure whenever the request came in over HTTPS" (Symfony applies
    // it per response), so production cookies — session and remember-me —
    // carry the Secure flag (scan QID 150122) while plain-HTTP local dev
    // still works. Set SESSION_SECURE_COOKIE=true to force it everywhere.
    'secure' => env('SESSION_SECURE_COOKIE', env('SESSION_SECURE_COOKIES')),

    'http_only' => true,

    'same_site' => env('SESSION_SAME_SITE', 'lax'),
];
