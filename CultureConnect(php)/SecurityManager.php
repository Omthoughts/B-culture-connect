<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 * CULTURECONNECT SECURITY CORE
 * Bank-grade security for a platform that matters
 * ═══════════════════════════════════════════════════════════════════
 */

namespace Core\Security;

class SecurityManager {
    
    private static $instance = null;
    private $redis;
    private $config;
    
    private function __construct() {
        // Redis is optional; provide an in-memory fallback when the extension
        // is not available so the app remains functional locally.
        if (class_exists('Redis')) {
            try {
                $this->redis = new \Redis();
                $this->redis->connect('127.0.0.1', 6379);
            } catch (\Exception $e) {
                // Fall back to array-backed implementation
                $this->redis = $this->getArrayRedisFallback();
            }
        } else {
            $this->redis = $this->getArrayRedisFallback();
        }

        $this->config = require __DIR__ . '/../config/security.php';
        $this->initialize();
    }

    /**
     * Return a simple array-backed fallback that provides the minimal Redis
     * methods used by SecurityManager. This is non-persistent and only for
     * local/dev environments where the Redis extension isn't available.
     */
    private function getArrayRedisFallback() {
        return new class {
            private $store = [];
            private $exp = [];

            public function get($key) {
                if (isset($this->exp[$key]) && time() > $this->exp[$key]) {
                    unset($this->store[$key], $this->exp[$key]);
                    return false;
                }
                return $this->store[$key] ?? false;
            }

            public function setex($key, $ttl, $value) {
                $this->store[$key] = $value;
                $this->exp[$key] = time() + (int)$ttl;
                return true;
            }

            public function incr($key) {
                if (!isset($this->store[$key])) $this->store[$key] = 0;
                return ++$this->store[$key];
            }

            public function ttl($key) {
                if (!isset($this->exp[$key])) return -1;
                return max(0, $this->exp[$key] - time());
            }

            public function lpush($key, $value) {
                if (!isset($this->store[$key]) || !is_array($this->store[$key])) $this->store[$key] = [];
                array_unshift($this->store[$key], $value);
                return count($this->store[$key]);
            }

            public function ltrim($key, $start, $stop) {
                if (!isset($this->store[$key]) || !is_array($this->store[$key])) return true;
                $this->store[$key] = array_slice($this->store[$key], $start, $stop - $start + 1);
                return true;
            }
        };
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize security measures
     */
    private function initialize() {
        // Session security
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 1);
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', 1);
        ini_set('session.use_only_cookies', 1);
        
        // Hide PHP version
        header_remove('X-Powered-By');
        
        // Security headers
        $this->setSecurityHeaders();
        
        // Start secure session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Regenerate session periodically
        $this->regenerateSession();
    }
    
    /**
     * Comprehensive security headers
     */
    private function setSecurityHeaders() {
        $headers = [
            // Prevent clickjacking
            'X-Frame-Options' => 'DENY',
            
            // Prevent MIME sniffing
            'X-Content-Type-Options' => 'nosniff',
            
            // XSS Protection
            'X-XSS-Protection' => '1; mode=block',
            
            // Referrer Policy
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            
            // Content Security Policy
            'Content-Security-Policy' => $this->getCSP(),
            
            // HTTPS Strict Transport Security
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains; preload',
            
            // Permissions Policy
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=(self)',
            
            // Feature Policy (older browsers)
            'Feature-Policy' => "geolocation 'none'; microphone 'none'; camera 'self'"
        ];
        
        foreach ($headers as $name => $value) {
            header("{$name}: {$value}");
        }
    }
    
    /**
     * Content Security Policy
     */
    private function getCSP() {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' https://cdnjs.cloudflare.com 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com",
            "img-src 'self' data: https: blob:",
            "connect-src 'self'",
            "media-src 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "upgrade-insecure-requests"
        ]);
    }
    
    /**
     * ═══════════════════════════════════════════════════════════════
     * CSRF PROTECTION
     * ═══════════════════════════════════════════════════════════════
     */
    
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_tokens'])) {
            $_SESSION['csrf_tokens'] = [];
        }
        
        $token = bin2hex(random_bytes(32));
        $timestamp = time();
        
        // Store token with timestamp
        $_SESSION['csrf_tokens'][$token] = $timestamp;
        
        // Clean old tokens (older than 2 hours)
        $this->cleanOldCSRFTokens();
        
        return $token;
    }
    
    public function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_tokens'][$token])) {
            $this->logSecurityEvent('CSRF_VALIDATION_FAILED', [
                'ip' => $this->getClientIP(),
                'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
            ]);
            return false;
        }
        
        $timestamp = $_SESSION['csrf_tokens'][$token];
        
        // Token expires after 2 hours
        if (time() - $timestamp > 7200) {
            unset($_SESSION['csrf_tokens'][$token]);
            return false;
        }
        
        // Single use token
        unset($_SESSION['csrf_tokens'][$token]);
        return true;
    }
    
    private function cleanOldCSRFTokens() {
        if (!isset($_SESSION['csrf_tokens'])) return;
        
        $cutoff = time() - 7200;
        $_SESSION['csrf_tokens'] = array_filter(
            $_SESSION['csrf_tokens'],
            fn($timestamp) => $timestamp > $cutoff
        );
    }
    
    public function csrfField() {
        $token = $this->generateCSRFToken();
        return '<input type="hidden" name="csrf_token" value="' . 
               htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
    
    /**
     * ═══════════════════════════════════════════════════════════════
     * RATE LIMITING (Redis-based)
     * ═══════════════════════════════════════════════════════════════
     */
    
    public function checkRateLimit($action, $maxAttempts = 5, $window = 60) {
        $ip = $this->getClientIP();
        $key = "rate_limit:{$action}:{$ip}";
        
        try {
            // Get current count
            $current = $this->redis->get($key);
            
            if ($current === false) {
                // First attempt
                $this->redis->setex($key, $window, 1);
                return true;
            }
            
            if ((int)$current >= $maxAttempts) {
                // Rate limited
                $ttl = $this->redis->ttl($key);
                $this->logSecurityEvent('RATE_LIMIT_EXCEEDED', [
                    'ip' => $ip,
                    'action' => $action,
                    'ttl' => $ttl
                ]);
                return false;
            }
            
            // Increment counter
            $this->redis->incr($key);
            return true;
            
        } catch (\Exception $e) {
            // Fail open (allow request) but log error
            error_log("Rate limit error: " . $e->getMessage());
            return true;
        }
    }
    
    public function getRateLimitInfo($action) {
        $ip = $this->getClientIP();
        $key = "rate_limit:{$action}:{$ip}";
        
        $attempts = (int)$this->redis->get($key);
        $ttl = $this->redis->ttl($key);
        
        return [
            'attempts' => $attempts,
            'reset_in' => max(0, $ttl)
        ];
    }
    
    /**
     * ═══════════════════════════════════════════════════════════════
     * INPUT VALIDATION & SANITIZATION
     * ═══════════════════════════════════════════════════════════════
     */
    
    public function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    public function validateUsername($username) {
        // 3-30 characters, alphanumeric + underscore
        return preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username);
    }
    
    public function validatePassword($password, &$errors = []) {
        $errors = [];
        
        if (strlen($password) < 12) {
            $errors[] = "Password must be at least 12 characters";
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain an uppercase letter";
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain a lowercase letter";
        }
        if (!preg_match('/\d/', $password)) {
            $errors[] = "Password must contain a number";
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = "Password must contain a special character";
        }
        
        // Check against common passwords
        if ($this->isCommonPassword($password)) {
            $errors[] = "Password is too common";
        }
        
        return empty($errors);
    }
    
    private function isCommonPassword($password) {
        $common = [
            'password123', '123456789', 'qwerty123', 'password1!',
            'welcome123', 'admin123', 'letmein123', 'changeme123'
        ];
        return in_array(strtolower($password), $common);
    }
    
    public function sanitizeInput($input, $maxLength = null) {
        // Remove null bytes
        $input = str_replace("\0", '', $input);
        
        // Trim whitespace
        $input = trim($input);
        
        // Strip tags
        $input = strip_tags($input);
        
        // Limit length
        if ($maxLength !== null) {
            $input = mb_substr($input, 0, $maxLength);
        }
        
        return $input;
    }
    
    /**
     * ═══════════════════════════════════════════════════════════════
     * XSS PREVENTION
     * ═══════════════════════════════════════════════════════════════
     */
    
    public function escape($value) {
        if (is_null($value)) return '';
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    public function escapeJS($value) {
        return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
    
    public function escapeURL($value) {
        return rawurlencode($value);
    }
    
    /**
     * ═══════════════════════════════════════════════════════════════
     * FILE UPLOAD SECURITY
     * ═══════════════════════════════════════════════════════════════
     */
    
    public function validateFileUpload($file, $allowedTypes = [], $maxSize = 2097152) {
        // Check upload errors
        if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'File upload failed'];
        }
        
        // Check file size
        if ($file['size'] > $maxSize) {
            return [
                'success' => false, 
                'message' => 'File too large. Max ' . ($maxSize / 1024 / 1024) . 'MB'
            ];
        }
        
        // Verify actual MIME type (not just extension)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            return ['success' => false, 'message' => 'Invalid file type'];
        }
        
        // For images, verify it's actually an image
        if (strpos($mimeType, 'image/') === 0) {
            $imageInfo = @getimagesize($file['tmp_name']);
            if ($imageInfo === false) {
                return ['success' => false, 'message' => 'File is not a valid image'];
            }
        }
        
        return ['success' => true, 'mime_type' => $mimeType];
    }
    
    public function generateSecureFilename($extension) {
        // Generate cryptographically secure random filename
        $filename = bin2hex(random_bytes(16));
        
        // Sanitize extension
        $extension = preg_replace('/[^a-z0-9]/i', '', $extension);
        
        return $filename . '.' . $extension;
    }
    
    /**
     * ═══════════════════════════════════════════════════════════════
     * SESSION MANAGEMENT
     * ═══════════════════════════════════════════════════════════════
     */
    
    private function regenerateSession() {
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
            return;
        }
        
        // Regenerate session ID every 15 minutes
        if (time() - $_SESSION['last_regeneration'] > 900) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
    
    public function validateSession() {
        // Check session timeout (30 minutes of inactivity)
        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > 1800) {
                $this->destroySession();
                return false;
            }
        }
        
        $_SESSION['last_activity'] = time();
        
        // Validate session IP (optional, can be problematic with mobile users)
        if (isset($_SESSION['ip_address'])) {
            if ($_SESSION['ip_address'] !== $this->getClientIP()) {
                $this->logSecurityEvent('SESSION_HIJACK_ATTEMPT', [
                    'original_ip' => $_SESSION['ip_address'],
                    'current_ip' => $this->getClientIP()
                ]);
                $this->destroySession();
                return false;
            }
        } else {
            $_SESSION['ip_address'] = $this->getClientIP();
        }
        
        return true;
    }
    
    public function destroySession() {
        $_SESSION = [];
        
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        
        session_destroy();
    }
    
    /**
     * ═══════════════════════════════════════════════════════════════
     * SQL INJECTION PREVENTION
     * ═══════════════════════════════════════════════════════════════
     */
    
    public function validateInteger($value, $min = null, $max = null) {
        $value = filter_var($value, FILTER_VALIDATE_INT);
        
        if ($value === false) {
            return false;
        }
        
        if ($min !== null && $value < $min) {
            return false;
        }
        
        if ($max !== null && $value > $max) {
            return false;
        }
        
        return $value;
    }
    
    /**
     * ═══════════════════════════════════════════════════════════════
     * PASSWORD HASHING
     * ═══════════════════════════════════════════════════════════════
     */
    
    public function hashPassword($password) {
        // Use Argon2id if available (most secure)
        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($password, PASSWORD_ARGON2ID, [
                'memory_cost' => 65536,
                'time_cost' => 4,
                'threads' => 3
            ]);
        }
        
        // Fallback to bcrypt
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    public function needsRehash($hash) {
        if (defined('PASSWORD_ARGON2ID')) {
            return password_needs_rehash($hash, PASSWORD_ARGON2ID);
        }
        return password_needs_rehash($hash, PASSWORD_BCRYPT);
    }
    
    /**
     * ═══════════════════════════════════════════════════════════════
     * LOGGING & MONITORING
     * ═══════════════════════════════════════════════════════════════
     */
    
    private function logSecurityEvent($eventType, $context = []) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event_type' => $eventType,
            'ip' => $this->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'user_id' => $_SESSION['user_id'] ?? 'guest',
            'context' => $context
        ];
        
        $logFile = __DIR__ . '/../logs/security.log';
        error_log(json_encode($logEntry) . PHP_EOL, 3, $logFile);
        
        // For critical events, also store in Redis for real-time monitoring
        if (in_array($eventType, ['CSRF_VALIDATION_FAILED', 'SESSION_HIJACK_ATTEMPT', 'RATE_LIMIT_EXCEEDED'])) {
            $this->redis->lpush('security_events', json_encode($logEntry));
            $this->redis->ltrim('security_events', 0, 999); // Keep last 1000 events
        }
    }
    
    /**
     * ═══════════════════════════════════════════════════════════════
     * UTILITY METHODS
     * ═══════════════════════════════════════════════════════════════
     */
    
    public function getClientIP() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        // Handle proxies (with caution - can be spoofed)
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        }
        
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
    
    public function requireAuth() {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            header('Location: /login');
            exit;
        }
        
        if (!$this->validateSession()) {
            http_response_code(401);
            header('Location: /login?expired=1');
            exit;
        }
    }
    
    public function requirePermission($resourceOwnerId) {
        $this->requireAuth();
        
        if ((int)$_SESSION['user_id'] !== (int)$resourceOwnerId) {
            $this->logSecurityEvent('UNAUTHORIZED_ACCESS_ATTEMPT', [
                'user_id' => $_SESSION['user_id'],
                'attempted_resource_owner' => $resourceOwnerId
            ]);
            
            http_response_code(403);
            die('Forbidden: You do not have permission to access this resource');
        }
    }
}

/**
 * ═══════════════════════════════════════════════════════════════════
 * GLOBAL HELPER FUNCTIONS
 * ═══════════════════════════════════════════════════════════════════
 */

function security() {
    return \Core\Security\SecurityManager::getInstance();
}

function e($value) {
    return security()->escape($value);
}

function csrf_field() {
    return security()->csrfField();
}

function csrf_token() {
    return security()->generateCSRFToken();
}

/**
 * Convenience helper: return the client IP using the SecurityManager implementation.
 * Use this when you want a consistent source of the client's IP across the app.
 */
function get_client_ip() {
    return security()->getClientIP();
}