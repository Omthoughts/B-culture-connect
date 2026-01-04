<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 * CULTURECONNECT SAVED COLLECTION
 * Where your heart's treasures live
 * ═══════════════════════════════════════════════════════════════════
 */

session_start();
// Fix relative includes: helpers/ is one level under project root
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/logger.php';

// Security: Require authentication
security()->requireAuth();

$user_id = $_SESSION['user_id'];
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Handle AJAX requests for save/unsave
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    $action = $_GET['action'];
    $post_id = (int)($_POST['post_id'] ?? 0);
    
    // Validate CSRF
    if (!security()->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit;
    }
    
    if ($action === 'toggle_save') {
        try {
            // Check if already saved
            $stmt = $pdo->prepare('SELECT id FROM saved_posts WHERE user_id = ? AND post_id = ?');
            $stmt->execute([$user_id, $post_id]);
            $exists = $stmt->fetch();
            
            if ($exists) {
                // Remove from saved
                $stmt = $pdo->prepare('DELETE FROM saved_posts WHERE user_id = ? AND post_id = ?');
                $stmt->execute([$user_id, $post_id]);
                
                Logger::log('UNSAVE_POST', "User {$user_id} unsaved post {$post_id}");
                echo json_encode([
                    'success' => true,
                    'saved' => false,
                    'message' => '💔 Removed from your collection'
                ]);
            } else {
                // Add to saved
                $stmt = $pdo->prepare('INSERT INTO saved_posts (user_id, post_id) VALUES (?, ?)');
                $stmt->execute([$user_id, $post_id]);
                
                Logger::log('SAVE_POST', "User {$user_id} saved post {$post_id}");
                echo json_encode([
                    'success' => true,
                    'saved' => true,
                    'message' => '✨ Added to your collection'
                ]);
            }
        } catch (PDOException $e) {
            Logger::error('SAVE_ERROR', $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update collection']);
        }
    }
    exit;
}

// Fetch saved posts
try {
    $stmt = $pdo->prepare('
        SELECT 
            p.*,
            u.username,
            u.name,
            u.avatar,
            u.country AS user_country,
            u.is_verified,
            sp.saved_at,
            COUNT(DISTINCT pl.id) AS likes_count,
            COUNT(DISTINCT c.id) AS comments_count
        FROM saved_posts sp
        JOIN posts p ON sp.post_id = p.id
        JOIN users u ON p.user_id = u.id
        LEFT JOIN post_likes pl ON p.id = pl.post_id
        LEFT JOIN comments c ON p.id = c.post_id
        WHERE sp.user_id = ? AND p.is_published = TRUE
        GROUP BY p.id
        ORDER BY sp.saved_at DESC
        LIMIT ? OFFSET ?
    ');
    $stmt->execute([$user_id, $per_page, $offset]);
    $saved_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM saved_posts WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $total_saved = (int)$stmt->fetchColumn();
    
    // Get statistics
    $stmt = $pdo->prepare('
        SELECT 
            COUNT(DISTINCT p.country) AS unique_countries,
            COUNT(DISTINCT p.category) AS unique_categories
        FROM saved_posts sp
        JOIN posts p ON sp.post_id = p.id
        WHERE sp.user_id = ?
    ');
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get category breakdown
    $stmt = $pdo->prepare('
        SELECT 
            p.category,
            COUNT(*) AS count
        FROM saved_posts sp
        JOIN posts p ON sp.post_id = p.id
        WHERE sp.user_id = ?
        GROUP BY p.category
        ORDER BY count DESC
    ');
    $stmt->execute([$user_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    Logger::error('SAVED_FETCH_ERROR', $e->getMessage());
    die('💔 Could not load your collection. Please try again.');
}

$total_pages = ceil($total_saved / $per_page);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title>Your Cultural Gems 💎 - CultureConnect</title>
    <link rel="stylesheet" href="/assets/css/saved.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
</head>

<body>
    <!-- Navigation -->
    <nav class="nav-floating">
        <div class="nav-content">
            <a href="/" class="logo">
                <span class="logo-icon">🌍</span>
                <span class="logo-text">CultureConnect</span>
            </a>
            <div class="nav-links">
                <a href="/explore.php" class="nav-link">Explore</a>
                <a href="/profile.php?id=<?= $user_id ?>" class="nav-link">Profile</a>
                <a href="/saved.php" class="nav-link active">Saved</a>
                <a href="/create_post.php" class="nav-link">Create</a>
                <a href="/logout.php" class="btn-nav btn-nav-secondary">Logout</a>
            </div>
        </div>
    </nav>

    <main class="collection-main">
        
        <!-- Collection Header -->
        <header class="collection-header">
            <div class="header-content">
                <div class="header-text">
                    <h1 class="collection-title">
                        <?php if ($total_saved > 0): ?>
                            Your Cultural <span class="gradient-text">Treasury</span>
                        <?php else: ?>
                            Start Your <span class="gradient-text">Collection</span>
                        <?php endif; ?>
                    </h1>
                    <p class="collection-subtitle">
                        <?php if ($total_saved > 0): ?>
                            <strong><?= $total_saved ?></strong> treasures from 
                            <strong><?= $stats['unique_countries'] ?></strong> countries
                        <?php else: ?>
                            Save posts that resonate with your soul
                        <?php endif; ?>
                    </p>
                </div>
                
                <?php if ($total_saved > 0): ?>
                <div class="header-stats">
                    <div class="stat-gem">
                        <span class="stat-icon">💎</span>
                        <span class="stat-value"><?= $total_saved ?></span>
                        <span class="stat-label">Gems</span>
                    </div>
                    <div class="stat-gem">
                        <span class="stat-icon">🌍</span>
                        <span class="stat-value"><?= $stats['unique_countries'] ?></span>
                        <span class="stat-label">Countries</span>
                    </div>
                    <div class="stat-gem">
                        <span class="stat-icon">🎨</span>
                        <span class="stat-value"><?= $stats['unique_categories'] ?></span>
                        <span class="stat-label">Categories</span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($total_saved > 0): ?>
            <!-- Filter Buttons -->
            <div class="filter-bar">
                <button class="filter-btn active" data-filter="all">All Gems</button>
                <?php foreach ($categories as $cat): ?>
                    <button class="filter-btn" data-filter="<?= e($cat['category']) ?>">
                        <?= e(ucfirst($cat['category'])) ?> (<?= $cat['count'] ?>)
                    </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </header>

        <?php if ($total_saved > 0): ?>
            <!-- Saved Posts Grid -->
            <div class="collection-grid" id="savedGrid">
                <?php foreach ($saved_posts as $post): ?>
                    <article class="gem-card" 
                             data-id="<?= $post['id'] ?>"
                             data-category="<?= e($post['category']) ?>">
                        
                        <!-- Post Image -->
                        <a href="/post.php?id=<?= $post['id'] ?>" class="gem-image-wrapper">
                            <?php if ($post['media_url']): ?>
                                <img src="<?= e($post['media_url']) ?>" 
                                     alt="<?= e($post['title']) ?>" 
                                     class="gem-image"
                                     loading="lazy">
                            <?php else: ?>
                                <div class="gem-image-placeholder">
                                    <span class="placeholder-icon">📖</span>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Category Badge -->
                            <span class="category-badge">
                                <?= getCategoryIcon($post['category']) ?> <?= e(ucfirst($post['category'])) ?>
                            </span>
                        </a>
                        
                        <!-- Post Info -->
                        <div class="gem-content">
                            <h3 class="gem-title">
                                <a href="/post.php?id=<?= $post['id'] ?>">
                                    <?= e($post['title']) ?>
                                </a>
                            </h3>
                            
                            <p class="gem-excerpt">
                                <?= e(substr($post['content'], 0, 100)) ?>...
                            </p>
                            
                            <!-- Author Info -->
                            <div class="gem-author">
                                <img src="<?= e($post['avatar'] ?? '/assets/images/default-avatar.png') ?>" 
                                     alt="<?= e($post['name']) ?>" 
                                     class="author-avatar">
                                <div class="author-info">
                                    <a href="/profile.php?id=<?= $post['user_id'] ?>" class="author-name">
                                        <?= e($post['name']) ?>
                                        <?php if ($post['is_verified']): ?>
                                            <span class="verified-badge">✓</span>
                                        <?php endif; ?>
                                    </a>
                                    <span class="author-meta">
                                        🌍 <?= e($post['country'] ?? $post['user_country']) ?>
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Stats -->
                            <div class="gem-stats">
                                <span>❤️ <?= $post['likes_count'] ?></span>
                                <span>💬 <?= $post['comments_count'] ?></span>
                                <span>👁️ <?= $post['views_count'] ?></span>
                            </div>
                            
                            <!-- Actions -->
                            <div class="gem-actions">
                                <a href="/post.php?id=<?= $post['id'] ?>" class="btn-action btn-view">
                                    <span>👁️</span> View
                                </a>
                                <button class="btn-action btn-remove" 
                                        onclick="toggleSave(<?= $post['id'] ?>, this)"
                                        data-saved="true">
                                    <span>💔</span> Remove
                                </button>
                            </div>
                        </div>
                        
                        <!-- Saved Date -->
                        <div class="saved-timestamp">
                            Saved <?= formatTimeAgo($post['saved_at']) ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>" class="btn-page">← Previous</a>
                <?php endif; ?>
                
                <span class="page-info">Page <?= $page ?> of <?= $total_pages ?></span>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>" class="btn-page">Next →</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
        <?php else: ?>
            <!-- Empty State -->
            <div class="empty-collection">
                <div class="empty-icon">💎</div>
                <h2>Your Collection Awaits</h2>
                <p>Start saving posts that speak to your soul</p>
                <p class="empty-hint">
                    💡 Click the <strong>📖 Save</strong> button on any post to add it here
                </p>
                <a href="/explore.php" class="btn-explore">
                    ✨ Explore Cultures
                </a>
            </div>
        <?php endif; ?>
        
    </main>

    <script src="/assets/js/saved.js"></script>
</body>
</html>

<?php
// Helper functions
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