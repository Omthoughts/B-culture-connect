<?php
declare(strict_types=1);
register_shutdown_function(function(){ $err = error_get_last(); if ($err && in_array($err['type'], [E_ERROR,E_PARSE])) { if (ob_get_length()) ob_clean(); header('Content-Type: application/json'); echo json_encode(['success'=>false,'message'=>'System error']); }});
ob_start(); session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/security.php';
header('Content-Type: application/json'); header('X-Content-Type-Options: nosniff');

try {
    if (empty($_SESSION['user_id'])) throw new Exception('Session expired',401);
    $user_id = (int)$_SESSION['user_id'];
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Method not allowed',405);
    $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    if (!security()->validateCSRFToken($body['csrf_token'] ?? '')) throw new Exception('Invalid token');
    if (!security()->checkRateLimit('like_post:'.$user_id, 60, 60)) throw new Exception('Rate limit');

    $post_id = filter_var($body['post_id'] ?? 0, FILTER_VALIDATE_INT);
    if (!$post_id) throw new Exception('Invalid post id');

    // Toggle like
    $stmt = $pdo->prepare('SELECT id FROM post_likes WHERE user_id = ? AND post_id = ?');
    $stmt->execute([$user_id, $post_id]);
    $exists = $stmt->fetch();

    if ($exists) {
        $stmt = $pdo->prepare('DELETE FROM post_likes WHERE user_id = ? AND post_id = ?');
        $stmt->execute([$user_id, $post_id]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO post_likes (user_id, post_id, created_at) VALUES (?, ?, NOW())');
        $stmt->execute([$user_id, $post_id]);
    }

    // Return updated likes count
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM post_likes WHERE post_id = ?');
    $stmt->execute([$post_id]);
    $likes = (int)$stmt->fetchColumn();

    echo json_encode(['success'=>true,'likes_count'=>$likes]);
    exit;

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 400);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
