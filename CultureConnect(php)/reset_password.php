<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 * SECURED CultureConnect Reset Password Flow
 * Token validation, password update, session regeneration
 * ═══════════════════════════════════════════════════════════════════
 * Security Fixes:
 * - Constant-time token comparison (hash_equals)
 * - CSRF protection
 * - Enhanced password validation
 * - Token deletion after use
 * - All sessions invalidated after reset
 * - Security headers
 */

session_start();

// SECURITY FIX #1: Disable display_errors in production
if (getenv('APP_ENV') === 'production') {
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/core/security.php';
require_once __DIR__ . '/helpers/email.php';
require_once __DIR__ . '/helpers/logger.php';

// SECURITY FIX #2: Add security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

// Enforce HTTPS in production
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    // HTTPS is active
} elseif (php_sapi_name() !== 'cli' && getenv('APP_ENV') === 'production') {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
    exit;
}

$http_method = $_SERVER['REQUEST_METHOD'];
$response = ['success' => false, 'message' => '', 'errors' => []];
$token_valid = false;
$reset_data = null;

/**
 * Validate and parse token
 * Format: selector.validator_hex
 * SECURITY: Uses constant-time comparison
 */
function validate_token($token, $pdo) {
    // SECURITY FIX #3: Enhanced token validation
    if (!is_string($token) || strlen($token) < 20 || strlen($token) > 200) {
        return null;
    }
    
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2) {
        return null;
    }
    
    list($selector, $validator_hex) = $parts;
    
    // Selector must be 18 hex chars, validator 64 hex chars
    if (!ctype_xdigit($selector) || strlen($selector) !== 18 || 
        !ctype_xdigit($validator_hex) || strlen($validator_hex) !== 64) {
        return null;
    }
    
    try {
        // SECURITY FIX #4: Clean up expired tokens first
        $stmt = $pdo->prepare('DELETE FROM password_resets WHERE expires_at < NOW()');
        $stmt->execute();
        
        $stmt = $pdo->prepare('
            SELECT id, user_id, selector, validator_hash, expires_at, created_at
            FROM password_resets
            WHERE selector = ? AND expires_at > NOW()
            LIMIT 1
        ');
        $stmt->execute([$selector]);
        $reset_record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$reset_record) {
            return null;
        }
        
        // SECURITY FIX #5: Constant-time comparison using hash_equals
        $validator = hex2bin($validator_hex);
        if ($validator === false) {
            return null;
        }
        
        $incoming_hash = hash('sha256', $validator);
        
        if (!hash_equals($reset_record['validator_hash'], $incoming_hash)) {
            // Log invalid attempt
            Logger::warn('INVALID_TOKEN_ATTEMPT', 'Invalid validator hash', [
                'selector' => $selector,
                'ip' => get_client_ip()
            ]);
            return null;
        }
        
        // Fetch user info
        $stmt = $pdo->prepare('SELECT id, email, name FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$reset_record['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return null;
        }
        
        return [
            'reset_id' => $reset_record['id'],
            'user_id' => $reset_record['user_id'],
            'user' => $user,
            'selector' => $selector
        ];
        
    } catch (PDOException $e) {
        Logger::error('TOKEN_VALIDATION_ERROR', $e->getMessage());
        return null;
    }
}

/**
 * Validate password strength
 * SECURITY FIX #6: Enhanced password requirements
 */
function validate_password($password, &$errors) {
    $min_length = 10;
    $max_length = 128; // Prevent DoS with extremely long passwords
    
    if (strlen($password) < $min_length) {
        $errors[] = "Password must be at least {$min_length} characters.";
    }
    
    if (strlen($password) > $max_length) {
        $errors[] = "Password must not exceed {$max_length} characters.";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must include an uppercase letter.";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must include a lowercase letter.";
    }
    
    if (!preg_match('/\d/', $password)) {
        $errors[] = "Password must include a digit.";
    }
    
    if (!preg_match('/[!@#$%^&*()_\-+=\[\]{};:\'",.<>?\/\\|`~]/', $password)) {
        $errors[] = "Password must include a symbol (!@#$%^&*).";
    }
    
    // SECURITY FIX #7: Check for common weak passwords
    $common_passwords = ['password123', '123456789', 'qwertyuiop', 'admin123'];
    $lower_password = strtolower($password);
    foreach ($common_passwords as $weak) {
        if (strpos($lower_password, $weak) !== false) {
            $errors[] = "Password is too common. Please choose a more unique password.";
            break;
        }
    }
    
    return count($errors) === 0;
}

// --- GET: Show form after token validation ---
if ($http_method === 'GET') {
    $token = $_GET['token'] ?? '';
    
    if (!$token) {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Reset Password - CultureConnect</title>
            <link rel="stylesheet" href="/assets/css/style_forgot.css">
        </head>
        <body>
            <div class="forgot-container">
                <div class="stars"></div>
                <div class="card glass-card error-state">
                    <div class="card-header">
                        <h1>🔍 Path Not Found</h1>
                    </div>
                    <p>The memory link is missing or invalid. Please request a new one.</p>
                    <a href="/forgot_password.php" class="btn btn-primary">← Request a New Link</a>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    $reset_data = validate_token($token, $pdo);
    
    if (!$reset_data) {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Reset Password - CultureConnect</title>
            <link rel="stylesheet" href="/assets/css/style_forgot.css">
        </head>
        <body>
            <div class="forgot-container">
                <div class="stars"></div>
                <div class="card glass-card error-state">
                    <div class="card-header">
                        <h1>🕐 Memory Expired</h1>
                    </div>
                    <p>This reset link has expired or is no longer valid. Request a fresh one.</p>
                    <a href="/forgot_password.php" class="btn btn-primary">← Request a New Link</a>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    $token_valid = true;
    
    // SECURITY FIX #8: Generate CSRF token using security helper
    $csrf_token = security()->generateCSRFToken();
    $_SESSION['reset_token_selector'] = $reset_data['selector']; // Store selector for validation
    
    $user_name = htmlspecialchars($reset_data['user']['name'] ?? 'Soul Traveler', ENT_QUOTES, 'UTF-8');
    
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
        <title>Renew Your Key - CultureConnect</title>
        <link rel="stylesheet" href="/assets/css/style_forgot.css">
    </head>
    <body>
        <div class="forgot-container">
            <div class="stars"></div>
            
            <div class="card glass-card">
                <div class="card-header">
                    <h1>🔑 Renew Your Soul Key</h1>
                    <p class="subtitle">Welcome back, <?php echo $user_name; ?></p>
                </div>

                <form id="resetForm" method="POST" class="form-group">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="form-field">
                        <label for="password">
                            <span class="label-text">New Key</span>
                            <span class="label-icon">🔐</span>
                        </label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            required
                            minlength="10"
                            maxlength="128"
                            placeholder="••••••••••"
                            aria-label="New password"
                            aria-describedby="password-requirements"
                            autocomplete="new-password">
                        <small id="password-requirements" class="form-requirements">
                            Min 10 chars • Uppercase • Lowercase • Number • Symbol
                        </small>
                    </div>

                    <div class="form-field">
                        <label for="password_confirm">
                            <span class="label-text">Confirm Key</span>
                            <span class="label-icon">✓</span>
                        </label>
                        <input 
                            type="password" 
                            id="password_confirm" 
                            name="password_confirm" 
                            required
                            minlength="10"
                            maxlength="128"
                            placeholder="••••••••••"
                            aria-label="Confirm password"
                            autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn-primary" aria-busy="false">
                        <span class="btn-text">Renew My Soul Key</span>
                        <span class="btn-loader" style="display:none;">🔄 Renewing...</span>
                    </button>
                </form>

                <div class="divider"></div>

                <p class="back-link">
                    <a href="/login.php" aria-label="Return to login">← Return to the circle</a>
                </p>

                <div id="messageBox" class="message-box" role="alert" style="display:none;"></div>
            </div>
        </div>

        <script src="/assets/js/reset.js"></script>
    </body>
    </html>
    <?php
    exit;
}

// --- POST: Process password reset ---
if ($http_method === 'POST') {
    header('Content-Type: application/json');
    
    $csrf_token = $_POST['csrf_token'] ?? '';
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    // SECURITY FIX #9: Get client IP safely
    $user_ip = get_client_ip();
    
    // SECURITY FIX #10: Validate CSRF token using security helper
    if (!security()->validateCSRFToken($csrf_token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Security check failed. Please try again.']);
        exit;
    }
    
    // SECURITY FIX #11: Rate limiting on password reset attempts
    if (!security()->checkRateLimit('reset_password:' . $user_ip, 5, 3600)) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Too many reset attempts. Please try again later.']);
        exit;
    }
    
    // Validate token
    $reset_data = validate_token($token, $pdo);
    if (!$reset_data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired reset token.']);
        Logger::warn('INVALID_RESET_TOKEN', "Attempted reset with invalid token", ['ip' => $user_ip]);
        exit;
    }
    
    // SECURITY FIX #12: Verify selector matches session (prevent token reuse across sessions)
    if (!isset($_SESSION['reset_token_selector']) || 
        !hash_equals($_SESSION['reset_token_selector'], $reset_data['selector'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Session mismatch. Please start over.']);
        exit;
    }
    
    // Validate passwords match
    if ($password !== $password_confirm) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        exit;
    }
    
    // Validate password strength
    $errors = [];
    if (!validate_password($password, $errors)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Password does not meet requirements.', 'errors' => $errors]);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // SECURITY FIX #13: Use PASSWORD_DEFAULT for forward compatibility
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Update user password (column name: password_hash or password?)
        // Using 'password' based on your schema
        $stmt = $pdo->prepare('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$password_hash, $reset_data['user_id']]);
        
        // SECURITY FIX #14: Delete ALL password reset tokens for this user (not just this one)
        $stmt = $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?');
        $stmt->execute([$reset_data['user_id']]);
        
        // SECURITY FIX #15: Delete all active sessions for this user (force re-login)
        // Only if you have a sessions table
        try {
            $stmt = $pdo->prepare('DELETE FROM sessions WHERE user_id = ?');
            $stmt->execute([$reset_data['user_id']]);
        } catch (PDOException $e) {
            // Sessions table might not exist, continue
        }
        
        $pdo->commit();
        
        // Log the password change
        Logger::log('PASSWORD_CHANGED', "Password reset completed", [
            'user_id' => $reset_data['user_id'],
            'ip' => $user_ip,
            'method' => 'password_reset'
        ]);
        
        // Send confirmation email
        Email::sendResetConfirmation(
            $reset_data['user']['email'],
            $reset_data['user']['name'],
            $user_ip
        );
        
        // SECURITY FIX #16: Clear reset session data
        unset($_SESSION['reset_token_selector']);
        
        // Regenerate session
        session_regenerate_id(true);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => '🔓 Your key has been renewed. Welcome back, Soul Traveler.',
            'redirect' => '/login.php?reset=success'
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        Logger::error('PASSWORD_RESET_ERROR', $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not complete reset. Please try again.']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
?>