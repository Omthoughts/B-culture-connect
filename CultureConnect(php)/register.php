<?php
/**
 * SECURED register.php - CultureConnect
 * Fixed: SQL Injection, CSRF, File Upload, Session Fixation, Rate Limiting
 */

session_start();

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

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    
    // SECURITY FIX #2: CSRF Validation
    if (!security()->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Security validation failed. Please try again.';
        $messageType = 'error';
    }
    // SECURITY FIX #3: Rate Limiting
    elseif (!security()->checkRateLimit('register', 3, 3600)) { // 3 attempts per hour
        $message = 'Too many registration attempts. Please try again later.';
        $messageType = 'error';
    }
    else {
        $username = trim($_POST['username'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $country = trim($_POST['country'] ?? '');
        $language = trim($_POST['language'] ?? '');
        
        // Validation
        if (empty($username) || empty($name) || empty($email) || empty($password)) {
            $message = 'All fields except avatar are required';
            $messageType = 'error';
        } 
        // SECURITY FIX #4: Validate username format
        elseif (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
            $message = 'Username must be 3-50 characters (letters, numbers, underscore only)';
            $messageType = 'error';
        }
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address';
            $messageType = 'error';
        } 
        elseif (strlen($password) < 8) {
            $message = 'Password must be at least 8 characters';
            $messageType = 'error';
        } 
        // SECURITY FIX #5: Strong password validation
        elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password)) {
            $message = 'Password must contain uppercase, lowercase, and number';
            $messageType = 'error';
        }
        elseif ($password !== $confirm_password) {
            $message = 'Passwords do not match';
            $messageType = 'error';
        } 
        else {
            try {
                // SECURITY FIX #6: Use PDO instead of mysqli
                $checkStmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ? OR email = ? LIMIT 1");
                $checkStmt->execute([$username, $email]);
                
                if ($checkStmt->fetch()) {
                    $message = 'Username or email already exists';
                    $messageType = 'error';
                } else {
                    // SECURITY FIX #7: Use PASSWORD_DEFAULT for compatibility
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    // SECURITY FIX #8: Secure file upload handling
                    $profilePic = null;
                    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                        
                        // Verify actual file type using magic bytes (not browser-reported mime)
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $actualMimeType = finfo_file($finfo, $_FILES['avatar']['tmp_name']);
                        finfo_close($finfo);
                        
                        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                        $maxSize = 2 * 1024 * 1024; // 2MB
                        
                        if (in_array($actualMimeType, $allowedTypes) && $_FILES['avatar']['size'] <= $maxSize) {
                            
                            // Verify it's actually an image by trying to load it
                            $imageInfo = getimagesize($_FILES['avatar']['tmp_name']);
                            if ($imageInfo !== false) {
                                
                                // Generate secure random filename
                                $ext = match($actualMimeType) {
                                    'image/jpeg' => 'jpg',
                                    'image/png' => 'png',
                                    'image/gif' => 'gif',
                                    'image/webp' => 'webp',
                                    default => 'jpg'
                                };
                                
                                $profilePic = 'avatar_' . bin2hex(random_bytes(16)) . '.' . $ext;
                                
                                // Create uploads directory with secure permissions
                                if (!is_dir('uploads')) {
                                    mkdir('uploads', 0755, true);
                                    
                                    // SECURITY FIX #9: Create .htaccess to prevent PHP execution
                                    file_put_contents('uploads/.htaccess', 
                                        '<FilesMatch "\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi)$">' . PHP_EOL .
                                        '    Order Deny,Allow' . PHP_EOL .
                                        '    Deny from all' . PHP_EOL .
                                        '</FilesMatch>'
                                    );
                                }
                                
                                // Move file
                                if (!move_uploaded_file($_FILES['avatar']['tmp_name'], 'uploads/' . $profilePic)) {
                                    $profilePic = null; // Reset if upload failed
                                }
                            }
                        }
                    }
                    
                    // SECURITY FIX #10: Insert with PDO prepared statement (using YOUR column names)
                    $insertStmt = $pdo->prepare("
                        INSERT INTO users (username, name, email, password, country, language, profile_pic, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    
                    if ($insertStmt->execute([$username, $name, $email, $hashedPassword, $country, $language, $profilePic])) {
                        $userId = $pdo->lastInsertId();
                        
                        // SECURITY FIX #11: Regenerate session ID to prevent fixation
                        session_regenerate_id(true);
                        
                        // SECURITY FIX #12: Set session security markers
                        $_SESSION['user_id'] = $userId;
                        $_SESSION['username'] = $username;
                        $_SESSION['name'] = $name;
                        $_SESSION['last_activity'] = time();
                        $_SESSION['last_regeneration'] = time();
                        $_SESSION['ip_address'] = get_client_ip();
                        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
                        
                        // SECURITY FIX #13: Log registration activity
                        $activityStmt = $pdo->prepare("
                            INSERT INTO user_activity (user_id, activity_type, ip_address, created_at) 
                            VALUES (?, 'register', ?, NOW())
                        ");
                        $activityStmt->execute([$userId, get_client_ip()]);
                        
                        // Redirect to explore
                        header('Location: explore.php?welcome=1');
                        exit;
                    } else {
                        $message = 'Registration failed. Please try again.';
                        $messageType = 'error';
                    }
                }
                
            } catch (PDOException $e) {
                error_log("Registration error: " . $e->getMessage());
                $message = 'An error occurred. Please try again.';
                $messageType = 'error';
            }
        }
    }
}

// Get countries list
$countries = ["Afghanistan","Albania","Algeria","Andorra","Angola","Argentina","Armenia","Australia","Austria","Azerbaijan","Bahamas","Bahrain","Bangladesh","Barbados","Belarus","Belgium","Belize","Benin","Bhutan","Bolivia","Bosnia and Herzegovina","Botswana","Brazil","Brunei","Bulgaria","Burkina Faso","Burundi","Cambodia","Cameroon","Canada","Chad","Chile","China","Colombia","Congo","Costa Rica","Croatia","Cuba","Cyprus","Czech Republic","Denmark","Dominican Republic","Ecuador","Egypt","El Salvador","Estonia","Ethiopia","Fiji","Finland","France","Gabon","Gambia","Georgia","Germany","Ghana","Greece","Guatemala","Guinea","Guyana","Haiti","Honduras","Hungary","Iceland","India","Indonesia","Iran","Iraq","Ireland","Israel","Italy","Jamaica","Japan","Jordan","Kazakhstan","Kenya","Kuwait","Kyrgyzstan","Laos","Latvia","Lebanon","Liberia","Libya","Lithuania","Luxembourg","Madagascar","Malawi","Malaysia","Maldives","Mali","Malta","Mexico","Moldova","Monaco","Mongolia","Morocco","Mozambique","Myanmar","Namibia","Nepal","Netherlands","New Zealand","Nicaragua","Niger","Nigeria","North Macedonia","Norway","Oman","Pakistan","Palestine","Panama","Paraguay","Peru","Philippines","Poland","Portugal","Qatar","Romania","Russia","Rwanda","Saudi Arabia","Senegal","Serbia","Singapore","Slovakia","Slovenia","Somalia","South Africa","South Korea","Spain","Sri Lanka","Sudan","Sweden","Switzerland","Syria","Taiwan","Tajikistan","Tanzania","Thailand","Togo","Trinidad and Tobago","Tunisia","Turkey","Uganda","Ukraine","United Arab Emirates","United Kingdom","United States","Uruguay","Uzbekistan","Venezuela","Vietnam","Yemen","Zambia","Zimbabwe"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Join CultureConnect - Share your soul with the world">
    <!-- SECURITY FIX #14: Add CSRF token meta tag -->
    <meta name="csrf-token" content="<?php echo security()->generateCSRFToken(); ?>">
    <title>Join the World - CultureConnect 🌍</title>
    <link rel="stylesheet" href="style_register.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Floating Particles -->
    <div class="particles-container" id="particles"></div>
    
    <!-- Breathing Background -->
    <div class="background-gradient">
        <div class="gradient-orb orb-1"></div>
        <div class="gradient-orb orb-2"></div>
        <div class="gradient-orb orb-3"></div>
    </div>

    <!-- Main Container -->
    <div class="register-container">
        <!-- Left Side - Emotional Story -->
        <div class="story-side" data-aos="fade-right">
            <div class="story-content">
                <a href="index.php" class="logo-link">
                    <div class="logo">
                        <span class="logo-icon">🌍</span>
                        <span class="logo-text">CultureConnect</span>
                    </div>
                </a>
                
                <h1 class="story-title">
                    Join the World.<br>
                    <span class="gradient-text">Share Your Soul.</span>
                </h1>
                
                <p class="story-subtitle">
                    <em>Your story deserves to meet theirs 🌍✨</em>
                </p>
                
                <div class="story-features">
                    <div class="feature-item" data-aos="fade-up" data-aos-delay="100">
                        <div class="feature-icon">💫</div>
                        <div class="feature-text">
                            <h3>Your Culture Matters</h3>
                            <p>Every tradition, every recipe, every story—worthy of being celebrated</p>
                        </div>
                    </div>
                    
                    <div class="feature-item" data-aos="fade-up" data-aos-delay="200">
                        <div class="feature-icon">🤝</div>
                        <div class="feature-text">
                            <h3>Connect Deeply</h3>
                            <p>Beyond borders, beyond language—human to human, soul to soul</p>
                        </div>
                    </div>
                    
                    <div class="feature-item" data-aos="fade-up" data-aos-delay="300">
                        <div class="feature-icon">🌟</div>
                        <div class="feature-text">
                            <h3>Belong Here</h3>
                            <p>A global family waiting to hear your voice, see your world</p>
                        </div>
                    </div>
                </div>
                
                <div class="trust-indicators">
                    <div class="trust-item">
                        <span class="trust-number">50K+</span>
                        <span class="trust-label">Stories Shared</span>
                    </div>
                    <div class="trust-item">
                        <span class="trust-number">195+</span>
                        <span class="trust-label">Countries United</span>
                    </div>
                    <div class="trust-item">
                        <span class="trust-number">∞</span>
                        <span class="trust-label">Connections Made</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side - Registration Form -->
        <div class="form-side" data-aos="fade-left">
            <div class="form-container">
                <div class="form-header">
                    <h2>Create Your Story</h2>
                    <p>Join thousands who chose to connect</p>
                </div>

                <?php if ($message): ?>
                <div class="message message-<?= htmlspecialchars($messageType) ?>" id="message">
                    <?= htmlspecialchars($message) ?>
                </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" id="registerForm" class="register-form">
                    <!-- SECURITY FIX #15: Add CSRF field -->
                    <?php echo security()->csrfField(); ?>
                    
                    <!-- Avatar Upload -->
                    <div class="avatar-upload-container">
                        <div class="avatar-preview" id="avatarPreview">
                            <span class="avatar-icon">👤</span>
                            <div class="avatar-overlay">
                                <span>Choose Avatar</span>
                            </div>
                        </div>
                        <input type="file" name="avatar" id="avatar" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
                        <p class="avatar-hint">Optional • Max 2MB</p>
                    </div>

                    <!-- Form Fields -->
                    <div class="form-grid">
                        <div class="form-group">
                            <input type="text" name="username" id="username" required maxlength="50" autocomplete="off" 
                                   pattern="[a-zA-Z0-9_]{3,50}" 
                                   value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
                            <label for="username" class="floating-label">Username</label>
                            <div class="field-glow"></div>
                        </div>

                        <div class="form-group">
                            <input type="text" name="name" id="name" required maxlength="100" autocomplete="name"
                                   value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>">
                            <label for="name" class="floating-label">Full Name</label>
                            <div class="field-glow"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <input type="email" name="email" id="email" required maxlength="150" autocomplete="email"
                               value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                        <label for="email" class="floating-label">Email Address</label>
                        <div class="field-glow"></div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <input type="password" name="password" id="password" required minlength="8" autocomplete="new-password">
                            <label for="password" class="floating-label">Password</label>
                            <div class="field-glow"></div>
                            <span class="password-toggle" onclick="togglePassword('password')">👁️</span>
                        </div>

                        <div class="form-group">
                            <input type="password" name="confirm_password" id="confirm_password" required minlength="8" autocomplete="new-password">
                            <label for="confirm_password" class="floating-label">Confirm Password</label>
                            <div class="field-glow"></div>
                            <span class="password-toggle" onclick="togglePassword('confirm_password')">👁️</span>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <select name="country" id="country" required>
                                <option value="" disabled selected></option>
                                <?php foreach ($countries as $c): ?>
                                <option value="<?= htmlspecialchars($c) ?>" 
                                        <?= (isset($_POST['country']) && $_POST['country'] === $c) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <label for="country" class="floating-label">Country</label>
                            <div class="field-glow"></div>
                        </div>

                        <div class="form-group">
                            <input type="text" name="language" id="language" required maxlength="50" autocomplete="language"
                                   value="<?= isset($_POST['language']) ? htmlspecialchars($_POST['language']) : '' ?>">
                            <label for="language" class="floating-label">Primary Language</label>
                            <div class="field-glow"></div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" name="register" class="btn-submit">
                        <span class="btn-text">Begin Your Journey</span>
                        <div class="btn-glow"></div>
                        <div class="btn-ripple"></div>
                    </button>

                    <div class="form-footer">
                        <p>Already part of the family? <a href="login.php" class="link-primary">Sign in here</a></p>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Floating Connection Sparks -->
    <div class="connection-sparks" id="sparks"></div>

    <script src="register.js"></script>
</body>
</html>