<?php
/**
 * Application-wide constants.
 * Keep simple values here (strings, ints, paths). Prefer environment overrides
 * via .env for deploy-specific values.
 */

// App identity
if (!defined('APP_NAME')) {
    define('APP_NAME', getenv('APP_NAME') ?: 'CultureConnect');
}

// Public base URL (attempt to read from env, fallback to localhost)
if (!defined('APP_URL')) {
    $defaultAppUrl = getenv('APP_URL') ?: 'http://localhost/CultureConnect(php)';
    define('APP_URL', $defaultAppUrl);
}

// Uploads directory (filesystem) and URL path
if (!defined('UPLOAD_DIR')) {
    // Path relative to project root. Adjust if your public webroot differs.
    define('UPLOAD_DIR', __DIR__ . '/../public/uploads/');
}

if (!defined('UPLOAD_URL_PATH')) {
    // URL path used in templates when referencing uploaded files
    define('UPLOAD_URL_PATH', '/uploads/');
}

// Max upload size (bytes) - 5MB
if (!defined('MAX_UPLOAD_SIZE')) {
    define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024);
}

// Asset helpers
if (!defined('ASSETS_URL')) {
    define('ASSETS_URL', rtrim(APP_URL, '\\/') . '/assets');
}
if (!defined('CSS_PATH')) {
    define('CSS_PATH', ASSETS_URL . '/css');
}
if (!defined('JS_PATH')) {
    define('JS_PATH', ASSETS_URL . '/js');
}
if (!defined('IMG_PATH')) {
    define('IMG_PATH', ASSETS_URL . '/img');
}

// Other useful constants
if (!defined('DEFAULT_AVATAR')) {
    define('DEFAULT_AVATAR', ASSETS_URL . '/img/default-avatar.png');
}

?>