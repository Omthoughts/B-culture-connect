<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 * SECURED CultureConnect Forgot Password Flow
 * Secure token generation, rate limiting, and email dispatch
 * ═══════════════════════════════════════════════════════════════════
 * Security Fixes:
 * - CSRF protection on form
 * - Enhanced rate limiting (per IP + per email)
 * - Secure token storage (hashed validator)
 * - Timing attack prevention
 * - Token cleanup for expired entries
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

$response = ['success' => false, 'message' => ''];
$http_method = $_SERVER['REQUEST_METHOD'];

// --- GET: Show form ---
if ($http_method === 'GET') {
    // Time-aware greeting
    $hour = (int)date('H');
    if ($hour < 12) {
        $greeting = "Good morning";
        $emoji = "🌅";
    } elseif ($hour < 18) {
        $greeting = "Good afternoon";
        $emoji = "🌞";
    } else {
        $greeting = "Good evening";
        $emoji = "🌙";
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <!-- SECURITY FIX #3: Add CSRF token meta tag -->
        <meta name="csrf-token" content="<?php echo htmlspecialchars(security()->generateCSRFToken()); ?>">
        <title>Remember Your Path - CultureConnect</title>
        <link rel="stylesheet" href="/assets/css/style_forgot.css">
    </head>
    <body>
        <div class="forgot-container">
            <div class="stars"></div>
            
            <div class="card glass-card">
                <div class="card-header">
                    <h1><?php echo htmlspecialchars($greeting); ?>, Soul Traveler <?php echo htmlspecialchars($emoji); ?></h1>
                    <p class="subtitle">Let's find your way back</p>
                </div>

                <form id="forgotForm" method="POST" class="form-group">
                    <!-- SECURITY FIX #4: Add CSRF field -->
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(security()->generateCSRFToken()); ?>">
                    
                    <div class="form-field">
                        <label for="email">
                            <span class="label-text">Your Email</span>
                            <span class="label-icon">📧</span>
                        </label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            required
                            maxlength="150"
                            placeholder="remembrance@cultureconnect.com"
                            aria-label="Email address"
                            aria-describedby="email-help"
                            autocomplete="email">
                        <small id="email-help" class="form-help">We'll send a memory link to this address</small>
                    </div>

                    <button type="submit" class="btn btn-primary" aria-busy="false">
                        <span class="btn-text">Send Me a Memory Link</span>
                        <span class="btn-loader" style="display:none;">✨ Finding path...</span>
                    </button>
                </form>

                <div class="divider"></div>

                <p class="back-link">
                    <a href="/login.php" aria-label="Return to login">← Return to the circle</a>
                </p>

                <div id="messageBox" class="message-box" role="alert" style="display:none;"></div>
            </div>
        </div>

        <script src="/assets/js/forgot.js"></script>
    </body>
    </html>
    <?php
    exit;
}

// --- POST: Process forgot password request ---
if ($http_method === 'POST') {
    header('Content-Type: application/json');
    
    // SECURITY FIX #5: Validate CSRF token
    if (!security()->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
        exit;
    }
    
    // SECURITY FIX #6: Get client IP safely
    $user_ip = get_client_ip();
    
    $email = isset($_POST['email']) ? trim(strtolower($_POST['email'])) : '';
    
    // SECURITY FIX #7: Enhanced email validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
        exit;
    }
    
    // SECURITY FIX #8: Rate limiting by IP (3 attempts per hour)
    if (!security()->checkRateLimit('forgot_ip:' . $user_ip, 3, 3600)) {
        http_response_code(429);
        echo json_encode([
            'success' => false, 
            'message' => '📬 If your email is part of our circle, a memory link will be sent.'
        ]);
        Logger::log('RATE_LIMIT', "IP exceeded forgot attempts", ['ip' => $user_ip]);
        exit;
    }
    
    // SECURITY FIX #9: Rate limiting by email (5 attempts per day to prevent abuse)
    if (!security()->checkRateLimit('forgot_email:' . $email, 5, 86400)) {
        http_response_code(429);
        echo json_encode([
            'success' => false, 
            'message' => '📬 If your email is part of our circle, a memory link will be sent.'
        ]);
        Logger::log('RATE_LIMIT', "Email exceeded forgot attempts", ['email' => $email]);
        exit;
    }
    
    // SECURITY FIX #10: Add artificial delay to prevent timing attacks (constant time)
    $start_time = microtime(true);
    
    // Lookup user by email (neutral response whether user exists or not)
    try {
        $stmt = $pdo->prepare('SELECT id, email, name FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Always send neutral response to avoid enumeration
        if ($user) {
            // SECURITY FIX #11: Clean up expired tokens first
            $stmt = $pdo->prepare('DELETE FROM password_resets WHERE expires_at < NOW()');
            $stmt->execute();
            
            // SECURITY FIX #12: Check if recent token already exists (prevent spam)
            $stmt = $pdo->prepare('
                SELECT created_at FROM password_resets 
                WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                ORDER BY created_at DESC
                LIMIT 1
            ');
            $stmt->execute([$user['id']]);
            $recent_token = $stmt->fetch();
            
            if (!$recent_token) {
                // Generate selector + validator token
                $selector = bin2hex(random_bytes(9));      // 18 chars
                $validator = bin2hex(random_bytes(32));    // 64 chars (hex)
                $validator_hash = hash('sha256', $validator); // Store hash, not plain
                
                $expires_at = date('Y-m-d H:i:s', time() + 3600); // 1 hour
                
                // SECURITY FIX #13: Store hashed validator
                $stmt = $pdo->prepare('
                    INSERT INTO password_resets (user_id, selector, validator_hash, expires_at, request_ip, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ');
                $stmt->execute([$user['id'], $selector, $validator_hash, $expires_at, $user_ip]);
                
                // Build reset link
                $reset_token = "{$selector}.{$validator}";
                $reset_link = "https://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token={$reset_token}";
                
                // Send email
                $email_sent = Email::sendResetLink(
                    $user['email'],
                    $user['name'],
                    $reset_link,
                    $expires_at
                );
                
                if ($email_sent) {
                    Logger::log('TOKEN_CREATED', "Reset token created", [
                        'user_id' => $user['id'],
                        'ip' => $user_ip,
                        'expires_at' => $expires_at
                    ]);
                } else {
                    Logger::error('EMAIL_SEND_FAILED', "Could not send reset email");
                }
            } else {
                // Token already sent recently, don't send another
                Logger::log('TOKEN_RATE_LIMITED', "Recent token exists for user", ['user_id' => $user['id']]);
            }
        }
        
        // SECURITY FIX #14: Constant-time response (prevent timing attacks)
        $elapsed = microtime(true) - $start_time;
        $target_time = 0.5; // 500ms minimum response time
        if ($elapsed < $target_time) {
            usleep(($target_time - $elapsed) * 1000000);
        }
        
        // Neutral response (success or fail, user won't know)
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => '📬 If your email is part of our circle, a memory link will be sent.'
        ]);
        
    } catch (PDOException $e) {
        Logger::error('DB_ERROR', $e->getMessage());
        
        // SECURITY FIX #15: Still maintain constant time on error
        $elapsed = microtime(true) - $start_time;
        $target_time = 0.5;
        if ($elapsed < $target_time) {
            usleep(($target_time - $elapsed) * 1000000);
        }
        
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'A moment of reflection needed. Please try again.']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
?>