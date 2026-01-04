<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 * CULTURECONNECT SINGLE POST VIEW
 * A Portal to Memory - Where Stories Live Forever
 * ═══════════════════════════════════════════════════════════════════
 */

session_start();
// Correct relative includes: this file lives in helpers/helpers/
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../helpers/logger.php';

$current_user_id = $_SESSION['user_id'] ?? null;
$post_id = (int)($_GET['id'] ?? 0);

// Validate post ID
if ($post_id === 0) {
    http_response_code(400);
    die('🔍 Memory ID required');
}

// Handle AJAX requests (comments, likes, etc.)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    if (!$current_user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Please sign in to interact']);
        exit;
    }
    
    $action = $_GET['action'];
    
    // Validate CSRF
    if (!security()->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit;
    }
    
    try {
        switch ($action) {
            case 'add_comment':
                $content = security()->sanitizeInput($_POST['content'] ?? '', 1000);
                $parent_id = (int)($_POST['parent_id'] ?? 0) ?: null;
                
                if (strlen($content) < 2) {
                    echo json_encode(['success' => false, 'message' => 'Comment too short']);
                    exit;
                }
                
                // Insert comment
                $stmt = $pdo->prepare('
                    INSERT INTO comments (post_id, user_id, content, parent_id, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ');
                $stmt->execute([$post_id, $current_user_id, $content, $parent_id]);
                $comment_id = $pdo->lastInsertId();
                
                // Update comment count
                $stmt = $pdo->prepare('UPDATE posts SET comments_count = comments_count + 1 WHERE id = ?');
                $stmt->execute([$post_id]);
                
                // Get post author for notification
                $stmt = $pdo->prepare('SELECT user_id FROM posts WHERE id = ?');
                $stmt->execute([$post_id]);
                $post_owner = $stmt->fetchColumn();
                
                // Create notification if not own post
                if ($post_owner !== $current_user_id) {
                    $stmt = $pdo->prepare('
                        INSERT INTO notifications (user_id, type, actor_id, post_id, message)
                        VALUES (?, "comment", ?, ?, "Someone resonated with your story")
                    ');
                    $stmt->execute([$post_owner, $current_user_id, $post_id]);
                }
                
                // Fetch the new comment with user data
                $stmt = $pdo->prepare('
                    SELECT c.*, u.name, u.avatar, u.is_verified
                    FROM comments c
                    JOIN users u ON c.user_id = u.id
                    WHERE c.id = ?
                ');
                $stmt->execute([$comment_id]);
                $comment = $stmt->fetch(PDO::FETCH_ASSOC);
                
                Logger::log('COMMENT_ADDED', "User {$current_user_id} commented on post {$post_id}");
                
                echo json_encode([
                    'success' => true,
                    'message' => '💬 Your resonance was felt',
                    'comment' => $comment
                ]);
                break;
                
            case 'delete_comment':
                $comment_id = (int)($_POST['comment_id'] ?? 0);
                
                // Verify ownership
                $stmt = $pdo->prepare('SELECT user_id FROM comments WHERE id = ?');
                $stmt->execute([$comment_id]);
                $comment_owner = $stmt->fetchColumn();
                
                if ($comment_owner !== $current_user_id) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Not authorized']);
                    exit;
                }
                
                // Delete comment
                $stmt = $pdo->prepare('DELETE FROM comments WHERE id = ?');
                $stmt->execute([$comment_id]);
                
                // Update count
                $stmt = $pdo->prepare('UPDATE posts SET comments_count = comments_count - 1 WHERE id = ?');
                $stmt->execute([$post_id]);
                
                echo json_encode(['success' => true, 'message' => 'Comment removed']);
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Unknown action']);
        }
    } catch (PDOException $e) {
        Logger::error('POST_ACTION_ERROR', $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Action failed']);
    }
    exit;
}

// Fetch post with author details
try {
    $stmt = $pdo->prepare('
        SELECT 
            p.*,
            u.username,
            u.name AS author_name,
            u.avatar,
            u.country AS author_country,
            u.is_verified,
            COUNT(DISTINCT pl.id) AS likes_count,
            COUNT(DISTINCT c.id) AS comments_count,
            (SELECT 1 FROM post_likes WHERE post_id = p.id AND user_id = ? LIMIT 1) AS user_liked,
            (SELECT 1 FROM saved_posts WHERE post_id = p.id AND user_id = ? LIMIT 1) AS user_saved
        FROM posts p
        JOIN users u ON p.user_id = u.id
        LEFT JOIN post_likes pl ON p.id = pl.post_id
        LEFT JOIN comments c ON p.id = c.post_id
        WHERE p.id = ? AND p.is_published = TRUE
        GROUP BY p.id
    ');
    $stmt->execute([$current_user_id, $current_user_id, $post_id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$post) {
        http_response_code(404);
        die('🔍 This memory has faded...');
    }
    
    // Increment view count (only once per session)
    if (!isset($_SESSION['viewed_posts'][$post_id])) {
        $stmt = $pdo->prepare('UPDATE posts SET views_count = views_count + 1 WHERE id = ?');
        $stmt->execute([$post_id]);
        $_SESSION['viewed_posts'][$post_id] = true;
    }
    
    // Fetch comments
    $stmt = $pdo->prepare('
        SELECT 
            c.*,
            u.name,
            u.avatar,
            u.is_verified
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.post_id = ? AND c.parent_id IS NULL
        ORDER BY c.created_at DESC
    ');
    $stmt->execute([$post_id]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch replies for each comment
    foreach ($comments as &$comment) {
        $stmt = $pdo->prepare('
            SELECT 
                c.*,
                u.name,
                u.avatar,
                u.is_verified
            FROM comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.parent_id = ?
            ORDER BY c.created_at ASC
        ');
        $stmt->execute([$comment['id']]);
        $comment['replies'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Parse tags from JSON
    $tags = $post['tags'] ? json_decode($post['tags'], true) : [];
    
} catch (PDOException $e) {
    Logger::error('POST_FETCH_ERROR', $e->getMessage());
    die('💔 Could not load this memory');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <meta property="og:title" content="<?= e($post['title']) ?>">
    <meta property="og:description" content="<?= e(substr($post['content'], 0, 160)) ?>">
    <meta property="og:image" content="<?= e($post['media_url']) ?>">
    <title><?= e($post['title']) ?> - CultureConnect 🌍</title>
    <link rel="stylesheet" href="/assets/css/post.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;700;900&display=swap" rel="stylesheet">
</head>

<body data-post-id="<?= $post_id ?>" data-user-id="<?= $current_user_id ?? '' ?>">

    <!-- Hero Image with Parallax -->
    <header class="hero-image-container" style="background-image: url('<?= $post['media_url'] ? e($post['media_url']) : '/assets/images/default-post.jpg' ?>');">
        <div class="hero-image-overlay"></div>
        
        <!-- Back Button -->
        <a href="/explore.php" class="btn-back">
            <span>←</span> Back to Souls
        </a>
    </header>

    <!-- Floating Author Card -->
    <a href="/profile.php?id=<?= $post['user_id'] ?>" class="author-card-portal" id="authorCard">
        <img src="<?= e($post['avatar'] ?? '/assets/images/default-avatar.png') ?>" 
             alt="<?= e($post['author_name']) ?>" 
             class="author-avatar">
        <div class="author-info-mini">
            <span class="author-name">
                <?= e($post['author_name']) ?>
                <?php if ($post['is_verified']): ?>
                    <span class="verified-badge">✓</span>
                <?php endif; ?>
            </span>
            <span class="author-country">🌍 <?= e($post['country'] ?? $post['author_country']) ?></span>
        </div>
    </a>

    <!-- Main Content -->
    <main class="post-content-container">
        <article class="post-article">
            
            <!-- Category Badge -->
            <div class="post-category-tag">
                <?= getCategoryIcon($post['category']) ?> <?= e(ucfirst($post['category'])) ?>
            </div>
            
            <!-- Title -->
            <h1 class="post-title"><?= e($post['title']) ?></h1>
            
            <!-- Post Meta -->
            <div class="post-meta">
                <span>👁️ <?= number_format($post['views_count']) ?> views</span>
                <span>•</span>
                <span>📅 <?= formatDate($post['created_at']) ?></span>
            </div>
            
            <!-- Post Body -->
            <div class="post-body">
                <?php 
                // Split content into paragraphs and format
                $paragraphs = explode("\n", $post['content']);
                foreach ($paragraphs as $p) {
                    $p = trim($p);
                    if (!empty($p)) {
                        echo '<p>' . nl2br(e($p)) . '</p>';
                    }
                }
                ?>
            </div>
            
            <!-- Tags Garden -->
            <?php if (!empty($tags)): ?>
            <div class="post-tags-garden">
                <h3>🏷️ Tagged As</h3>
                <div class="tags-list">
                    <?php foreach ($tags as $tag): ?>
                        <span class="tag-beacon"><?= e($tag) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Post Stats -->
            <div class="post-stats-detailed">
                <div class="stat-item">
                    <span class="stat-icon">❤️</span>
                    <span class="stat-value"><?= $post['likes_count'] ?></span>
                    <span class="stat-label">Loves</span>
                </div>
                <div class="stat-item">
                    <span class="stat-icon">💬</span>
                    <span class="stat-value"><?= count($comments) ?></span>
                    <span class="stat-label">Resonances</span>
                </div>
                <div class="stat-item">
                    <span class="stat-icon">👁️</span>
                    <span class="stat-value"><?= number_format($post['views_count']) ?></span>
                    <span class="stat-label">Souls Witnessed</span>
                </div>
            </div>
            
        </article>

        <!-- Comments Section -->
        <section class="comments-section" id="comments">
            <h2 class="comments-title">
                💬 Resonances (<?= count($comments) ?>)
            </h2>
            
            <?php if ($current_user_id): ?>
            <!-- Comment Input -->
            <div class="comment-input-shrine glass-shrine" id="commentForm">
                <textarea 
                    id="commentInput"
                    placeholder="Share your resonance... What does this story mean to you?"
                    maxlength="1000"
                    rows="3"></textarea>
                <div class="comment-footer">
                    <span class="char-count"><span id="charCount">0</span>/1000</span>
                    <button class="btn-submit-comment" onclick="submitComment()">
                        ✨ Send Resonance
                    </button>
                </div>
            </div>
            <?php else: ?>
            <div class="signin-prompt glass-shrine">
                <p>🔐 <a href="/login.php">Sign in</a> to share your resonance</p>
            </div>
            <?php endif; ?>
            
            <!-- Comments List -->
            <div class="comments-list" id="commentsList">
                <?php if (empty($comments)): ?>
                    <div class="empty-comments">
                        <p>🌱 Be the first to resonate with this story</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($comments as $comment): ?>
                        <?php renderComment($comment, $current_user_id); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <!-- Sticky Interaction Bar -->
    <div class="sticky-interaction-bar">
        <button class="action-btn btn-like" 
                onclick="toggleLike(<?= $post_id ?>)" 
                data-liked="<?= $post['user_liked'] ? 'true' : 'false' ?>">
            <span class="icon"><?= $post['user_liked'] ? '❤️' : '🤍' ?></span>
            <span class="text">Love</span>
        </button>
        
        <button class="action-btn btn-comment" onclick="scrollToComments()">
            <span class="icon">💬</span>
            <span class="text">Resonate</span>
        </button>
        
        <button class="action-btn btn-save" 
                onclick="toggleSave(<?= $post_id ?>)"
                data-saved="<?= $post['user_saved'] ? 'true' : 'false' ?>">
            <span class="icon"><?= $post['user_saved'] ? '✅' : '📖' ?></span>
            <span class="text">Save</span>
        </button>
        
        <button class="action-btn btn-share" onclick="openShareModal()">
            <span class="icon">🔗</span>
            <span class="text">Share</span>
        </button>
    </div>

    <!-- Share Modal -->
    <div class="modal share-modal" id="shareModal" style="display: none;">
        <div class="modal-backdrop" onclick="closeShareModal()"></div>
        <div class="modal-content glass-shrine">
            <button class="modal-close" onclick="closeShareModal()">×</button>
            <h3>Share This Memory</h3>
            <div class="share-options">
                <button class="share-btn" onclick="shareVia('twitter')">
                    🐦 Twitter
                </button>
                <button class="share-btn" onclick="shareVia('facebook')">
                    👥 Facebook
                </button>
                <button class="share-btn" onclick="shareVia('whatsapp')">
                    💚 WhatsApp
                </button>
                <button class="share-btn" onclick="copyLink()">
                    🔗 Copy Link
                </button>
            </div>
        </div>
    </div>

    <script src="/assets/js/post.js"></script>
</body>
</html>

<?php
// Helper Functions

function getCategoryIcon($category) {
    $icons = [
        'food' => '🍲',
        'festival' => '🎉',
        'tradition' => '🏛️',
        'language' => '💬',
        'art' => '🎨',
        'music' => '🎵',
        'story' => '📖',
        'other' => '✨'
    ];
    return $icons[$category] ?? '✨';
}

function formatDate($datetime) {
    $date = new DateTime($datetime);
    return $date->format('F j, Y');
}

function renderComment($comment, $current_user_id) {
    ?>
    <div class="comment-shrine glass-shrine" data-comment-id="<?= $comment['id'] ?>">
        <div class="comment-header">
            <img src="<?= e($comment['avatar'] ?? '/assets/images/default-avatar.png') ?>" 
                 alt="<?= e($comment['name']) ?>" 
                 class="comment-avatar">
            <div class="comment-meta">
                <span class="comment-author">
                    <?= e($comment['name']) ?>
                    <?php if ($comment['is_verified']): ?>
                        <span class="verified-badge">✓</span>
                    <?php endif; ?>
                </span>
                <span class="comment-date"><?= formatTimeAgo($comment['created_at']) ?></span>
            </div>
            <?php if ($current_user_id === $comment['user_id']): ?>
            <button class="btn-delete-comment" onclick="deleteComment(<?= $comment['id'] ?>)">
                🗑️
            </button>
            <?php endif; ?>
        </div>
        <div class="comment-content">
            <?= nl2br(e($comment['content'])) ?>
        </div>
        
        <!-- Replies -->
        <?php if (!empty($comment['replies'])): ?>
        <div class="comment-replies">
            <?php foreach ($comment['replies'] as $reply): ?>
                <div class="comment-reply glass-shrine">
                    <div class="comment-header">
                        <img src="<?= e($reply['avatar'] ?? '/assets/images/default-avatar.png') ?>" 
                             alt="<?= e($reply['name']) ?>" 
                             class="comment-avatar comment-avatar-small">
                        <div class="comment-meta">
                            <span class="comment-author"><?= e($reply['name']) ?></span>
                            <span class="comment-date"><?= formatTimeAgo($reply['created_at']) ?></span>
                        </div>
                    </div>
                    <div class="comment-content">
                        <?= nl2br(e($reply['content'])) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php
}

function formatTimeAgo($datetime) {
    $now = new DateTime();
    $then = new DateTime($datetime);
    $interval = $now->diff($then);
    
    if ($interval->y > 0) return $interval->y . 'y ago';
    if ($interval->m > 0) return $interval->m . 'mo ago';
    if ($interval->d > 6) return floor($interval->d / 7) . 'w ago';
    if ($interval->d > 0) return $interval->d . 'd ago';
    if ($interval->h > 0) return $interval->h . 'h ago';
    if ($interval->i > 0) return $interval->i . 'm ago';
    return 'Just now';
}
?>