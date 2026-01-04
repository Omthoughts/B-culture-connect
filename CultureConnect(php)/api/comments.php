<?php
declare(strict_types=1);
// api/comments.php - handle comment creation (JSON)
register_shutdown_function(function(){ $err = error_get_last(); if ($err && in_array($err['type'], [E_ERROR,E_PARSE])) { if (ob_get_length()) ob_clean(); header('Content-Type: application/json'); echo json_encode(['success'=>false,'message'=>'System error']); }});
ob_start(); session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/security.php';
header('Content-Type: application/json'); header('X-Content-Type-Options: nosniff');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Method not allowed', 405);
    if (empty($_SESSION['user_id'])) throw new Exception('Session expired', 401);
    $user_id = (int)$_SESSION['user_id'];

    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    // CSRF
    if (!security()->validateCSRFToken($data['csrf_token'] ?? '')) throw new Exception('Invalid CSRF token');

    // Rate limit comments
    if (!security()->checkRateLimit('comment:' . $user_id, 30, 60)) {
        throw new Exception('Rate limit exceeded. Slow down.');
    }

    $post_id = filter_var($data['post_id'] ?? 0, FILTER_VALIDATE_INT);
    $content = trim($data['content'] ?? '');

    if (!$post_id || mb_strlen($content) < 2 || mb_strlen($content) > 5000) {
        throw new Exception('Invalid input');
    }

    // Ensure post exists and is published
    $stmt = $pdo->prepare('SELECT id, is_published FROM posts WHERE id = ? LIMIT 1');
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();
    if (!$post || !$post['is_published']) throw new Exception('Cannot comment on this post', 404);

    // Insert comment
    $stmt = $pdo->prepare('INSERT INTO comments (post_id, user_id, content, created_at) VALUES (?, ?, ?, NOW())');
    $stmt->execute([$post_id, $user_id, $content]);
    $comment_id = (int)$pdo->lastInsertId();

    // Return created comment info (escaped by client where needed)
    echo json_encode([
        'success' => true,
        'comment' => [
            'id' => $comment_id,
            'post_id' => $post_id,
            'user_id' => $user_id,
            'content' => $content,
            'created_at' => date('c')
        ]
    ]);
    exit;

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
