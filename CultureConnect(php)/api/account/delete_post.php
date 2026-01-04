<?php
declare(strict_types=1);

/**
 * ═══════════════════════════════════════════════════════════════════
 * CULTURECONNECT DATA CLEANSE (delete_post.php)
 * Zenith Edition: Anti-fragile & Strict
 * ═══════════════════════════════════════════════════════════════════
 */

// 1. JSON Failsafe: Ensure the app never crashes silently
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Clear any garbage output
        if (ob_get_length()) ob_clean(); 
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'System interruption. Please try again.']);
    }
});

// Buffer output to prevent accidental HTML leakage
ob_start();

session_start();

// 2. Environment Config
$is_production = getenv('APP_ENV') === 'production';
ini_set('display_errors', $is_production ? '0' : '1');
error_reporting(E_ALL);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/security.php';
require_once __DIR__ . '/helpers/logger.php';

// Clear buffer and set headers
ob_clean();
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');

try {
    // 3. Gatekeeper Checks
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed', 405);
    }

    if (empty($_SESSION['user_id'])) {
        throw new Exception('Session expired. Please log in.', 401);
    }

    $user_id = (int)$_SESSION['user_id'];

    if (!security()->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        throw new Exception('Security token invalid. Please refresh.');
    }

    // 4. Rate Limiting (Stricter)
    if (!security()->checkRateLimit('delete_post:' . $user_id, 10, 300)) {
        throw new Exception('Action limit reached. Pause for a moment.');
    }

    // 5. Input Validation
    $post_id = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);
    if (!$post_id) {
        throw new Exception('Invalid memory reference.');
    }

    // 6. Ownership & Integrity Check
    $stmt = $pdo->prepare('SELECT id, user_id, media_url FROM posts WHERE id = ? LIMIT 1');
    $stmt->execute([$post_id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        throw new Exception('Memory not found.');
    }

    if ((int)$post['user_id'] !== $user_id) {
        Logger::warn('SECURITY', 'Unauthorized delete attempt', ['user_id' => $user_id, 'post_id' => $post_id]);
        throw new Exception('You cannot delete what is not yours.', 403);
    }

    // 7. Atomic Destruction
    $pdo->beginTransaction();

    try {
        // Efficient deletion using cascading logic manually for safety
        $tables = ['post_likes', 'comments', 'posts'];
        foreach ($tables as $table) {
            $col = ($table === 'posts') ? 'id' : 'post_id';
            $stmt = $pdo->prepare("DELETE FROM {$table} WHERE {$col} = ?");
            $stmt->execute([$post_id]);
        }
        
        $pdo->commit();
    } catch (Exception $dbEx) {
        $pdo->rollBack();
        throw new Exception('Database lock error. Try again.');
    }

    // 8. Physical Cleanse (File System)
    if (!empty($post['media_url'])) {
        $clean_path = str_replace(['..', "\0"], '', $post['media_url']); // Path traversal protection
        $file_path = __DIR__ . '/public' . $clean_path;
        $real_path = realpath($file_path);
        $safe_root = realpath(__DIR__ . '/public/uploads');

        if ($real_path && $safe_root && strpos($real_path, $safe_root) === 0 && file_exists($real_path)) {
            @unlink($real_path); // @ suppresses warnings if file is already gone
        }
    }

    echo json_encode(['success' => true, 'message' => 'Memory released to the ether. ✨']);

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>