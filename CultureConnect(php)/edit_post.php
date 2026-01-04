<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/security.php';

security()->requireAuth();
$user_id = (int)$_SESSION['user_id'];
$post_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$post_id) { header('Location: /profile.php'); exit; }

try {
    $stmt = $pdo->prepare('SELECT * FROM posts WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$post_id, $user_id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$post) { http_response_code(403); echo 'Forbidden'; exit; }
} catch (PDOException $e) { http_response_code(500); echo 'DB error'; exit; }

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_post'])) {
    if (!security()->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Security check failed.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        if (strlen($title) < 5 || strlen($content) < 20) {
            $message = 'Title or content too short.';
        } else {
            $stmt = $pdo->prepare('UPDATE posts SET title = ?, content = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$title, $content, $post_id]);
            header('Location: /post.php?id=' . $post_id . '&updated=1', true, 303);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Edit Story - CultureConnect</title>
    <link rel="stylesheet" href="/create-post.css">
</head>
<body>
    <nav class="nav-floating"><div class="nav-content"><a href="/post.php?id=<?= $post_id ?>">← Cancel</a></div></nav>
    <main style="max-width:900px;margin:120px auto;padding:0 20px;">
        <h1>Refine Your Story</h1>
        <?php if ($message): ?><div class="message message-error"><?= e($message) ?></div><?php endif; ?>
        <form method="POST">
            <?= csrf_field() ?>
            <div><label>Title</label><input type="text" name="title" value="<?= e($post['title']) ?>" required style="width:100%;padding:10px;border-radius:8px;"></div>
            <div style="margin-top:12px;"><label>Content</label><textarea name="content" rows="12" required style="width:100%;padding:12px;border-radius:8px;"><?= e($post['content']) ?></textarea></div>
            <div style="text-align:right;margin-top:12px;"><button type="submit" name="update_post" class="btn-publish">Save Changes</button></div>
        </form>
    </main>
</body>
</html>
