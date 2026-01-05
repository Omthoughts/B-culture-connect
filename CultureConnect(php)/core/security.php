<?php
/**
 * Security Helper Functions
 */

// Autoload SecurityManager if not already loaded
if (!class_exists('Core\Security\SecurityManager')) {
    require_once __DIR__ . '/../SecurityManager.php';
}

/**
 * Get SecurityManager instance
 */
function security(): Core\Security\SecurityManager
{
    return Core\Security\SecurityManager::getInstance();
}

/**
 * Generate CSRF token
 */
function csrf_token(): string
{
    return security()->generateCSRFToken();
}

/**
 * Generate CSRF hidden input field
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * HTML escape helper
 */
function e(?string $string): string
{
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Get client IP address
 */
function get_client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    // Check for forwarded IP (when behind proxy/load balancer)
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (isset($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = $_SERVER['HTTP_X_REAL_IP'];
    }
    
    // Validate IP
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/**
 * Redirect helper
 */
function redirect(string $url, int $statusCode = 302): void
{
    header("Location: $url", true, $statusCode);
    exit;
}

/**
 * Check if request is POST
 */
function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Check if request is GET
 */
function is_get(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'GET';
}

/**
 * Check if request is AJAX
 */
function is_ajax(): bool
{
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Get POST data with default value
 */
function post(string $key, $default = null)
{
    return $_POST[$key] ?? $default;
}

/**
 * Get GET data with default value
 */
function get_param(string $key, $default = null)
{
    return $_GET[$key] ?? $default;
}

/**
 * Flash message helper
 */
function flash(string $key, $value = null)
{
    if ($value === null) {
        // Get flash message
        $message = $_SESSION['_flash'][$key] ?? null;
        if (isset($_SESSION['_flash'][$key])) {
            unset($_SESSION['_flash'][$key]);
        }
        return $message;
    } else {
        // Set flash message
        $_SESSION['_flash'][$key] = $value;
    }
}

/**
 * Old input helper (for form repopulation)
 */
function old(string $key, $default = '')
{
    return $_SESSION['_old'][$key] ?? $default;
}

/**
 * Store old input
 */
function store_old_input(): void
{
    $_SESSION['_old'] = $_POST;
}

/**
 * Clear old input
 */
function clear_old_input(): void
{
    unset($_SESSION['_old']);
}

/**
 * JSON response helper
 */
function json_response(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Sanitize string
 */
function sanitize(string $input): string
{
    return security()->sanitizeInput($input);
}

/**
 * Check if user is authenticated
 */
function is_authenticated(): bool
{
    return security()->isAuthenticated();
}

/**
 * Get authenticated user ID
 */
function auth_user_id(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get authenticated user data
 */
function auth_user(): ?array
{
    return $_SESSION['user_data'] ?? null;
}