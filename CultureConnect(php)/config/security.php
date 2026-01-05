<?php
/**
 * Minimal security configuration for local/dev environment.
 * This file is required by SecurityManager.php. It provides safe defaults
 * so the application can boot in a development XAMPP environment.
 */

return [
    // Environment: development|production
    'env' => getenv('APP_ENV') ?: 'development',

    // Redis connection settings (optional)
    'redis' => [
        'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
        'port' => (int)(getenv('REDIS_PORT') ?: 6379),
        'timeout' => 1.5,
    ],

    // Content Security Policy tweaks (not required; SecurityManager has sane defaults)
    'csp' => [
        'allow_inline_scripts' => true,
    ],

    // Session settings
    'session' => [
        'lifetime' => 1800, // seconds
    ],
];
