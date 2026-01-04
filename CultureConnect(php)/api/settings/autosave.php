<?php
declare(strict_types=1);

/**
 * ═══════════════════════════════════════════════════════════════════
 * CULTURECONNECT DRAFT MANAGER (autosave.php)
 * Zenith Edition: Unified Save & Load
 * ═══════════════════════════════════════════════════════════════════
 */

// 1. JSON Failsafe
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR])) {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'System interruption.']);
    }
});

ob_start();
session_start();

$is_production = getenv('APP_ENV') === 'production';
ini_set('display_errors', $is_production ? '0' : '1');
error_reporting(E_ALL);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/security.php';
require_once __DIR__ . '/helpers/logger.php';

ob_clean();
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

try {
    // 2. Auth Gate
    if (empty($_SESSION['user_id'])) {
        throw new Exception('Session expired', 401);
    }
    
    $user_id = (int)$_SESSION['user_id'];
    $method = $_SERVER['REQUEST_METHOD'];

    // ═══════════════════════════════════════════════════════════════
    // HANDLE LOAD REQUEST (GET)
    // ═══════════════════════════════════════════════════════════════
    if ($method === 'GET') {
        // Find the most recent UNPUBLISHED post (Draft)
        $stmt = $pdo->prepare('
            SELECT id, title, content, category, country, tags, updated_at 
            FROM posts 
            WHERE user_id = ? AND is_published = 0 
            ORDER BY updated_at DESC 
            LIMIT 1
        ');
        $stmt->execute([$user_id]);
        $draft = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($draft) {
            echo json_encode(['success' => true, 'draft' => $draft]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No draft found']);
        }
        exit;
    }

    // ═══════════════════════════════════════════════════════════════
    // HANDLE SAVE REQUEST (POST)
    // ═══════════════════════════════════════════════════════════════
    if ($method === 'POST') {
        
        if (!security()->validateCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Security token mismatch. Please reload.');
        }

        // Data Hygiene
        $draft_id = filter_input(INPUT_POST, 'draft_id', FILTER_VALIDATE_INT);
        $clean = fn($k) => trim(strip_tags($_POST[$k] ?? ''));
        
        $title    = $clean('title');
        $content  = $clean('content');
        $category = $clean('category');
        $country  = $clean('country');
        
        // Tag Processing
        $tags_json = null;
        if (!empty($_POST['tags'])) {
            $decoded = json_decode($_POST['tags'], true);
            if (is_array($decoded)) {
                $tags = array_slice($decoded, 0, 10);
                $tags = array_filter(array_map(function($t) {
                    return preg_replace('/[\x00-\x1F\x7F]/u', '', substr(trim($t), 0, 50));
                }, $tags));
                $tags_json = !empty($tags) ? json_encode(array_values($tags)) : null;
            }
        }

        if ($draft_id) {
            // Update Existing Draft
            $stmt = $pdo->prepare('SELECT id FROM posts WHERE id = ? AND user_id = ? AND is_published = 0 LIMIT 1');
            $stmt->execute([$draft_id, $user_id]);
            if (!$stmt->fetch()) throw new Exception('Draft context lost or published', 404);

            $stmt = $pdo->prepare('
                UPDATE posts 
                SET title = ?, content = ?, category = ?, country = ?, tags = ?, updated_at = NOW()
                WHERE id = ?
            ');
            $stmt->execute([$title, $content, $category, $country, $tags_json, $draft_id]);
            $msg = 'Saved';

        } else {
            // Create New Draft
            if (empty($title) && empty($content)) {
                echo json_encode(['success' => true, 'message' => 'Empty']);
                exit;
            }

            $stmt = $pdo->prepare('
                INSERT INTO posts (
                    user_id, title, content, category, country, tags, 
                    is_published, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, 0, NOW(), NOW())
            ');
            $stmt->execute([$user_id, $title, $content, $category, $country, $tags_json]);
            $draft_id = (int)$pdo->lastInsertId();
            $msg = 'Draft Created';
        }

        echo json_encode([
            'success'   => true, 
            'draft_id'  => $draft_id, 
            'timestamp' => date('H:i:s'),
            'message'   => '☁️ ' . $msg
        ]);
        exit;
    }

    throw new Exception('Method not allowed', 405);

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>