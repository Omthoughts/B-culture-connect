<?php
/**
 * SECURED login.php - CultureConnect
 * Fixed: SQL Injection, Session Fixation, Display Errors
 */

session_start();
/** */
// SECURITY FIX #1: Disable display_errors in production
if (getenv('APP_ENV') === 'production') {
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/security.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: explore.php');
    exit;
}
 
$message = '';
$messageType = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    
    // SECURITY FIX #2: CSRF Validation
    if (!security()->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Security validation failed. Please try again.';
        $messageType = 'error';
    }
    // SECURITY FIX #3: Rate Limiting
    elseif (!security()->checkRateLimit('login', 5, 300)) {
        $message = 'Too many login attempts. Please try again in 5 minutes.';
        $messageType = 'error';
    }
    else {
        $identifier = trim($_POST['identifier'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);
        
        if (empty($identifier) || empty($password)) {
            $message = 'Please enter your credentials to continue your journey';
            $messageType = 'error';
        } else {
            try {
                // SECURITY FIX #4: Use PDO with prepared statements (NOT mysqli)
                // Check if identifier is email or username
                $stmt = $pdo->prepare("
                    SELECT id, username, name, email, password_hash, avatar, country 
                    FROM users 
                    WHERE email = ? OR username = ? 
                    LIMIT 1
                ");
                $stmt->execute([$identifier, $identifier]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && password_verify($password, $user['password_hash'])) {
                    
                    // SECURITY FIX #5: Regenerate session ID (prevent session fixation)
                    session_regenerate_id(true);
                    
                    // SECURITY FIX #6: Set session security markers
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['last_activity'] = time();
                    $_SESSION['last_regeneration'] = time();
                    // Use server-provided client IP (SecurityManager::getClientIP is private)
                    $_SESSION['ip_address'] = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    
                    // Remember me functionality (if needed)
                    if ($remember) {
                        $token = bin2hex(random_bytes(32));
                        $token_hash = hash('sha256', $token);
                        $expires = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60));
                        
                        // Store in database (you need a remember_tokens table)
                        $stmt = $pdo->prepare("
                            INSERT INTO remember_tokens (user_id, token_hash, expires_at) 
                            VALUES (?, ?, ?)
                        ");
                        $stmt->execute([$user['id'], $token_hash, $expires]);
                        
                        // Set cookie
                        setcookie(
                            'remember_token', 
                            $token, 
                            time() + (30 * 24 * 60 * 60),
                            '/',
                            '',
                            true, // Secure
                            true  // HttpOnly
                        );
                    }
                    
                    // Log successful login
                    $stmt = $pdo->prepare("
                        INSERT INTO user_activity (user_id, activity_type, ip_address, created_at) 
                        VALUES (?, 'login', ?, NOW())
                    ");
                    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                    $stmt->execute([$user['id'], $ip]);
                    
                    // Redirect
                    header('Location: explore.php?welcome_back=1');
                    exit;
                    
                } else {
                    // Generic error message (don't reveal if username exists)
                    $message = 'Invalid credentials. Please try again.';
                    $messageType = 'error';
                    
                    // Log failed attempt
                    Logger::warn('LOGIN_FAILED', 'Failed login attempt', [
                        'identifier' => $identifier,
                        'ip' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
                    ]);
                }
                
            } catch (PDOException $e) {
                error_log("Login error: " . $e->getMessage());
                $message = 'An error occurred. Please try again.';
                $messageType = 'error';
            }
        }
    }
}

// Get time-based greeting
$hour = date('G');
if ($hour < 12) {
    $greeting = "Good Morning";
    $timeEmoji = "🌅";
} elseif ($hour < 17) {
    $greeting = "Good Afternoon";
    $timeEmoji = "☀️";
} elseif ($hour < 21) {
    $greeting = "Good Evening";
    $timeEmoji = "🌙";
} else {
    $greeting = "Good Night";
    $timeEmoji = "✨";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Welcome home to CultureConnect - Your journey continues">
    <!-- SECURITY FIX #7: Add CSRF token meta tag -->
    <meta name="csrf-token" content="<?php echo security()->generateCSRFToken(); ?>">
    <title>Welcome Home - CultureConnect 🌍</title>
    <link rel="stylesheet" href="style_login.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body data-hour="<?= $hour ?>">
    <!-- Canvas and gradients remain the same -->
    <canvas id="glyphCanvas"></canvas>
    <div class="time-gradient">
        <div class="gradient-layer layer-1"></div>
        <div class="gradient-layer layer-2"></div>
        <div class="gradient-layer layer-3"></div>
    </div>
    <div class="parallax-container" id="parallaxContainer">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
        <div class="orb orb-4"></div>
    </div>

    <div class="login-container">
        <!-- Hero Section -->
        <div class="hero-section">
            <a href="index.php" class="logo-link">
                <div class="logo">
                    <span class="logo-icon">🌍</span>
                    <span class="logo-text">CultureConnect</span>
                </div>
            </a>
            
            <div class="hero-content">
                <h1 class="hero-title">
                    Welcome Home,<br>
                    <span class="gradient-text soul-text">Soul Traveler</span>
                </h1>
                <p class="hero-tagline">
                    <?= htmlspecialchars($greeting) ?> <?= htmlspecialchars($timeEmoji) ?><br>
                    <em>Your journey continues. The world awaits your light.</em>
                </p>
                <div class="trust-circle">
                    <div class="circle-item">
                        <span class="circle-number">∞</span>
                        <span class="circle-label">Souls Connected</span>
                    </div>
                    <div class="circle-divider"></div>
                    <div class="circle-item">
                        <span class="circle-number">195+</span>
                        <span class="circle-label">Countries United</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Login Form -->
        <div class="form-section">
            <div class="form-container glass-card">
                <div class="form-header">
                    <h2>Enter Your World</h2>
                    <p class="form-subtitle">The circle is incomplete without you</p>
                </div>

                <?php if ($message): ?>
                <div class="message message-<?= htmlspecialchars($messageType) ?>" id="message">
                    <span class="message-icon"><?= $messageType === 'error' ? '🔒' : '✨' ?></span>
                    <p><?= htmlspecialchars($message) ?></p>
                </div>
                <?php endif; ?>

                <form method="POST" id="loginForm" class="login-form">
                    <!-- SECURITY FIX #8: Add CSRF field -->
                    <?php echo security()->csrfField(); ?>
                    
                    <div class="form-group">
                        <input 
                            type="text" 
                            name="identifier" 
                            id="identifier" 
                            required 
                            autocomplete="username"
                            placeholder=" "
                            class="form-input"
                            value="<?= isset($_POST['identifier']) ? htmlspecialchars($_POST['identifier']) : '' ?>">
                        <label for="identifier" class="floating-label">Email or Username</label>
                        <div class="field-glow"></div>
                        <div class="field-icon">👤</div>
                    </div>

                    <div class="form-group">
                        <input 
                            type="password" 
                            name="password" 
                            id="password" 
                            required 
                            autocomplete="current-password"
                            placeholder=" "
                            class="form-input">
                        <label for="password" class="floating-label">Password</label>
                        <div class="field-glow"></div>
                        <div class="field-icon">🔑</div>
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <span id="toggleIcon">👁️</span>
                        </button>
                    </div>

                    <div class="form-options">
                        <label class="remember-me">
                            <input type="checkbox" name="remember" id="remember">
                            <span class="checkbox-custom"></span>
                            <span class="checkbox-label">Remember my soul</span>
                        </label>
                        <a href="forgot_password.php" class="forgot-link">Lost your key?</a>
                    </div>

                    <button type="submit" name="login" class="btn-login">
                        <span class="btn-text">Enter Your World</span>
                        <div class="btn-heartbeat"></div>
                        <div class="btn-ripple"></div>
                    </button>

                    <div class="social-divider"><span>or continue with</span></div>
                    <div class="social-login">
                        <button type="button" class="social-btn google-btn">
                            <svg viewBox="0 0 24 24" width="20" height="20">
                                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            </svg>
                            Google
                        </button>
                        <button type="button" class="social-btn apple-btn">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                                <path d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.54 4.09l.01-.01z"/>
                            </svg>
                            Apple
                        </button>
                    </div>

                    <div class="form-footer">
                        <p>New to our circle?</p>
                        <a href="register.php" class="register-link">Begin your journey</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="cultural-scroll" id="culturalScroll">
        <div class="scroll-item">🎭</div>
        <div class="scroll-item">🌸</div>
        <div class="scroll-item">🎨</div>
        <div class="scroll-item">🎵</div>
        <div class="scroll-item">🍲</div>
        <div class="scroll-item">💃</div>
        <div class="scroll-item">🏛️</div>
        <div class="scroll-item">📚</div>
    </div>

    <footer class="footer-whisper">
        <p class="whisper-quote"><em>"Connection is the language of the soul"</em></p>
        <div class="footer-links">
            <a href="#">Privacy</a><span>•</span>
            <a href="#">Terms</a><span>•</span>
            <a href="#">Contact</a>
        </div>
        <p class="footer-copyright">
            &copy; <?= date('Y') ?> CultureConnect • Built with ❤️ for humanity
        </p>
    </footer>

    <script src="login.js"></script>
</body>
</html>