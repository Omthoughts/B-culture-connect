<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/security.php';

// Mood adaptation (same logic used in other pages)
$hour = (int)date('H');
function getMoodGradientClass($hour) {
    if ($hour >= 5 && $hour < 12) return 'theme-dawn';
    if ($hour >= 12 && $hour < 17) return 'theme-day';
    if ($hour >= 17 && $hour < 21) return 'theme-dusk';
    return 'theme-night';
}

$post_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$current_user_id = $_SESSION['user_id'] ?? null;

if (!$post_id) { header('Location: /explore.php'); exit; }

try {
    $stmt = $pdo->prepare('SELECT p.*, u.username, u.name, u.avatar, u.country as user_country, u.is_verified,
        (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as likes_count,
        (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comments_count,
        (SELECT 1 FROM post_likes WHERE post_id = p.id AND user_id = ?) as user_liked,
        (SELECT 1 FROM saved_posts WHERE post_id = p.id AND user_id = ?) as user_saved
        FROM posts p JOIN users u ON p.user_id = u.id WHERE p.id = ? AND p.is_published = TRUE LIMIT 1');
    $stmt->execute([$current_user_id, $current_user_id, $post_id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        http_response_code(404);
        echo 'This memory cannot be found.';
        exit;
    }

    // Increment view count (best-effort)
    $update = $pdo->prepare('UPDATE posts SET views_count = COALESCE(views_count,0) + 1 WHERE id = ?');
    $update->execute([$post_id]);

} catch (PDOException $e) {
    http_response_code(500);
    echo 'Database error';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($post['title']) ?> - CultureConnect</title>
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <link rel="stylesheet" href="/style_explore.css">
    <link rel="stylesheet" href="/style_profile.css">
</head>
<body class="<?= getMoodGradientClass($hour) ?>">
    <nav class="nav-floating">
        <div class="nav-content">
            <a href="/explore.php" class="logo">← Return</a>
            <div class="nav-actions">
                <?php if ($current_user_id && (int)$current_user_id === (int)$post['user_id']): ?>
                    <a href="/edit_post.php?id=<?= $post['id'] ?>" class="btn-nav">✏️ Edit</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main class="post-container" style="max-width:900px;margin:120px auto;padding:0 20px;">
        <div class="post-user-info" style="display:flex;gap:12px;align-items:center;margin-bottom:1rem;">
            <img src="<?= e($post['avatar'] ?? '/assets/images/default-avatar.png') ?>" alt="<?= e($post['name']) ?>" style="width:56px;height:56px;border-radius:50%;object-fit:cover;">
            <div>
                <div style="font-weight:600"><?= e($post['name']) ?> <?php if ($post['is_verified']): ?><span style="color:#2D89EF">✓</span><?php endif; ?></div>
                <div style="opacity:0.7;font-size:0.9rem;"><?= e($post['user_country']) ?> • <?= date('M d, Y', strtotime($post['created_at'])) ?></div>
            </div>
        </div>

        <h1 style="font-family:'Playfair Display',serif;font-size:2.8rem;margin-bottom:1rem;"><?= e($post['title']) ?></h1>

        <?php if (!empty($post['media_url'])): ?>
            <img src="<?= e($post['media_url']) ?>" alt="<?= e($post['title']) ?>" style="width:100%;border-radius:16px;box-shadow:0 20px 40px rgba(0,0,0,0.12);margin-bottom:1.5rem;">
        <?php endif; ?>

        <article class="post-body" style="font-size:1.15rem;line-height:1.8;color:var(--text-primary,#2d3748)">
            <?= nl2br(e($post['content'])) ?>
        </article>

        <div style="margin-top:2rem;display:flex;gap:12px;align-items:center;">
            <button onclick="likePost(<?= $post['id'] ?>, this)" data-liked="<?= $post['user_liked'] ? '1' : '0' ?>"><?= $post['user_liked'] ? '❤️' : '🤍' ?> <span id="likes-count"><?= $post['likes_count'] ?></span></button>
            <button onclick="savePost(<?= $post['id'] ?>, this)"><?= $post['user_saved'] ? '✅ Saved' : '🔖 Save' ?></button>
        </div>

        <section class="comments-section" style="margin-top:3rem;">
            <h3>Resonance (<?= $post['comments_count'] ?>)</h3>
            <?php if ($current_user_id): ?>
                <form id="commentForm" method="POST" action="/api/comments.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                    <textarea name="content" rows="3" placeholder="Share your response..." required style="width:100%;padding:12px;border-radius:10px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.06)"></textarea>
                    <div style="text-align:right;margin-top:8px;"><button type="submit" class="btn-primary">✨ Share</button></div>
                </form>
            <?php else: ?>
                <p><a href="/login.php">Sign in</a> to join the circle.</p>
            <?php endif; ?>
        </section>
    </main>

    <script>
    async function postAction(url, body) {
        const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(Object.assign(body, { csrf_token: token })),
                credentials: 'same-origin'
            });
            return await res.json();
        } catch (e) { console.error(e); return { success:false }; }
    }

    async function savePost(id, btn){
        const r = await postAction('/api/save_post.php', { action: 'toggle_save', post_id: id });
        if (r.success) { btn.textContent = r.saved ? '✅ Saved' : '🔖 Save'; }
    }

    async function likePost(id, btn){
        const r = await postAction('/api/like_post.php', { action: 'toggle_like', post_id: id });
        if (r.success) { document.getElementById('likes-count').textContent = r.likes_count; }
    }
    </script>
</body>
</html>
