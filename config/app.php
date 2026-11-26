<?php

return [
    'name' => env('APP_NAME', 'ATI Inventory'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'asset_url' => env('ASSET_URL'),

    'timezone' => 'UTC',
    'locale' => 'en',
    'fallback_locale' => 'en',
    'faker_locale' => 'en_US',

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'maintenance' => [
        'driver' => 'file',
    ],

    // After-hours mode: lets Engineers return parts outside office hours.
    // Their returns stay 'Pending Return' (not counted as stock) until an
    // Admin or Super Admin approves them. See App\Support\EngineerReturnWindow.
    // Kept here rather than in its own file because bootstrap/app.php only
    // loads the config files it names.
    'engineer_returns' => [
        'enabled' => filter_var(env('ENGINEER_AFTER_HOURS_RETURNS', true), FILTER_VALIDATE_BOOLEAN),
        'timezone' => env('ENGINEER_RETURNS_TIMEZONE', 'Asia/Manila'),
        'weekday_opens_at' => (int) env('ENGINEER_RETURNS_OPENS_AT', 17),   // 5:00 PM
        'weekday_closes_at' => (int) env('ENGINEER_RETURNS_CLOSES_AT', 8),  // open until 7:59 AM
    ],
];
