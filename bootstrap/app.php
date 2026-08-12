<?php

use Illuminate\Foundation\Application;
use Illuminate\Config\Repository as ConfigRepository;

$app = new Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

// Register error handling first
$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class,
);

// Create config instance
$config = new ConfigRepository(require base_path('config/app.php'));
$app->instance('config', $config);

// Add database config
$config->set('database', require base_path('config/database.php'));
$config->set('cache', require base_path('config/cache.php'));
$config->set('session', require base_path('config/session.php'));

// Register HTTP and Console kernels
$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class,
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class,
);

// Register all service providers
foreach (require base_path('bootstrap/providers.php') as $provider) {
    $app->register($provider);
}

return $app;
