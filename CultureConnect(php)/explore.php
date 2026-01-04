<?php
/**
 * ============================================
 * CultureConnect Explore - The Feed That Feels Like Fate
 * Where your soul discovers what it's seeking
 * ============================================
 */

session_start();
// Use the same database/config as your other files
require_once __DIR__ . '/config/database.php'; 
require_once __DIR__ . '/helpers/logger.php';

$current_user_id = $_SESSION['user_id'] ?? null;

// --- Time-of-Day Mood Adaptation ---
$hour = (int)date('H');
function getMoodGradientClass($hour) {
    if ($hour >= 5 && $hour < 12) return 'theme-dawn';
    if ($hour >= 12 && $hour < 17) return 'theme-day';
    if ($hour >= 17 && $hour < 21) return 'theme-dusk';
    return 'theme-night';
}

// --- Dual Feed Mode ---
$feedMode = $_GET['mode'] ?? 'pulse'; // 'pulse' or 'soul'
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// --- Resonance Badge Logic (Simulated) ---
function getResonanceBadge($post) {
    if ($post['likes_count'] > 500) {
        return ['icon' => '🔥', 'text' => 'Trending in your vibe', 'class' => 'badge-viral'];
    }
    if ($post['comments_count'] > 50) {
        return ['icon' => '🌊', 'text' => 'Deep connection', 'class' => 'badge-deep'];
    }
    if ($post['views_count'] > 1000) {
         return ['icon' => '✨', 'text' => 'Soul resonance', 'class' => 'badge-high'];
    }
    return null;
}

// --- Reflection Card Content ---
$reflectionCard = [
    'quote' => '"The world is a book, and those who do not travel read only one page."',
    'author' => 'Saint Augustine',
    'question' => 'What page of humanity are you reading today?'
];

try {
    // --- Database Query ---
    // This is the core logic. We join posts and users, count likes/comments,
    // and check if the *current user* has liked each post.
    $sql = "
        SELECT 
            p.*, 
            u.username, u.name, u.avatar, u.country, u.is_verified,
            COUNT(DISTINCT pl.id) as likes_count,
            COUNT(DISTINCT c.id) as comments_count,
            (SELECT 1 FROM post_likes WHERE post_id = p.id AND user_id = :current_user_id LIMIT 1) as user_liked
        FROM posts p
        JOIN users u ON p.user_id = u.id
        LEFT JOIN post_likes pl ON p.id = pl.post_id
        LEFT JOIN comments c ON p.id = c.post_id
        WHERE p.is_published = TRUE
        GROUP BY p.id
    ";

    // Add different sorting for Pulse vs. Soul mode
    if ($feedMode === 'pulse') {
        // Pulse Mode: Fast, trending, dopamine-rich. Sort by likes + comments.
        $sql .= " ORDER BY (p.views_count + (COUNT(DISTINCT pl.id) * 5) + (COUNT(DISTINCT c.id) * 10)) DESC, p.created_at DESC";
    } else {
        // Soul Mode: Slow, reflective, emotionally resonant. Sort by newest.
        $sql .= " ORDER BY p.created_at DESC";
    }

    $sql .= " LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':current_user_id', $current_user_id, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    Logger::error('EXPLORE_ERROR', $e->getMessage());
    die('🪞 A moment of reflection needed... The universe is busy.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Discover what your soul is seeking. Stories from 195+ countries.">
    <title>Explore Cultures - CultureConnect 🌍</title>
    <link rel="stylesheet" href="style_explore.css"> 
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
</head>

<body class="<?php echo getMoodGradientClass($hour); ?>">
    
    <nav class="nav-floating">
        <div class="nav-content">
            <a href="index.php" class="logo">
                <span class="logo-icon">🌍</span>
                <span class="logo-text">CultureConnect</span>
            </a>
            <div class="nav-links">
                <?php if($current_user_id): ?>
                    <a href="explore.php" class="nav-link active">Explore</a>
                    <a href="profile.php" class="nav-link">Profile</a>
                    <a href="create_post.php" class="nav-link">Create</a>
                    <a href="logout.php" class="btn-nav btn-nav-secondary">Logout</a>
                <?php else: ?>
                    <a href="explore.php" class="nav-link active">Explore</a>
                    <a href="login.php" class="nav-link">Sign In</a>
                    <a href="register.php" class="btn-nav btn-nav-primary">Join Us</a>
                <?php endif; ?>
            </div>
            </div>
    </nav>

    <div class="feed-container">
        
        <header class="feed-header">
            <h1 class="feed-title">
                <?php echo ($feedMode === 'pulse') ? "What's Sparking Now" : "For Your Soul"; ?>
            </h1>
            <p class="feed-subtitle">
                <?php echo ($feedMode === 'pulse') ? "The world is alive. Feel its heartbeat." : "Stories that resonate at the frequency of your being."; ?>
            </p>

            <div class="feed-toggle">
                <a href="explore.php?mode=pulse" class="toggle-btn <?php echo ($feedMode === 'pulse') ? 'active' : ''; ?>">
                    <span>⚡️</span> Pulse
                </a>
                <a href="explore.php?mode=soul" class="toggle-btn <?php echo ($feedMode === 'soul') ? 'active' : ''; ?>">
                    <span>🌙</span> Soul
                </a>
            </div>
        </header>

        <div class="feed-grid">
            <?php if (empty($posts)): ?>
                <div class="feed-empty-state">
                    <div class="empty-icon">🌱</div>
                    <p>The garden is quiet for now.</p>
                    <p>Why not <a href="create_post.php">plant the first seed</a>?</p>
                </div>
            <?php else: ?>
                <?php foreach ($posts as $index => $post): ?>
                    
                    <?php 
                    // Mock tags based on category
                    $tags = ['For the soul', htmlspecialchars(ucfirst($post['category']))];
                    // Get simulated resonance badge
                    $badge = getResonanceBadge($post);
                    ?>

                    <?php if ($feedMode === 'soul' && $index === 2): ?>
                        <div class="reflection-card">
                            <div class="reflection-icon">🧘</div>
                            <p class="reflection-quote"><?php echo $reflectionCard['quote']; ?></p>
                            <p class="reflection-author">— <?php echo $reflectionCard['author']; ?></p>
                            <p class="reflection-question"><?php echo $reflectionCard['question']; ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="post-card" data-post-id="<?php echo $post['id']; ?>">
                        
                        <div class="post-image-wrapper">
                            <?php if ($badge): ?>
                                <div class="resonance-badge <?php echo $badge['class']; ?>">
                                    <span><?php echo $badge['icon']; ?></span>
                                    <span><?php echo $badge['text']; ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($post['media_url']): ?>
                                <img src="<?php echo htmlspecialchars($post['media_url']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" class="post-image" loading="lazy">
                            <?php else: ?>
                                <div class="post-image-placeholder"></div>
                            <?php endif; ?>
                            <div class="post-image-gradient"></div>
                        </div>

                        <div class="post-content">
                            <div class="post-user-info">
                                <a href="profile.php?id=<?php echo $post['user_id']; ?>" class="user-avatar-link">
                                    <img src="<?php echo htmlspecialchars($post['avatar'] ?? 'assets/images/default-avatar.png'); ?>" alt="<?php echo htmlspecialchars($post['name']); ?>" class="user-avatar">
                                </a>
                                <div class="user-meta">
                                    <a href="profile.php?id=<?php echo $post['user_id']; ?>" class="user-name">
                                        <?php echo htmlspecialchars($post['name']); ?>
                                        <?php if ($post['is_verified']): ?><span class="verified-badge">✓</span><?php endif; ?>
                                    </a>
                                    <div class="post-meta">
                                        <span>🌍 <?php echo htmlspecialchars($post['country']); ?></span>
                                        <span>•</span>
                                        <span><?php echo formatTimeAgo($post['created_at']); ?></span>
                                    </div>
                                </div>
                                <button class="post-options-btn" aria-label="Post options">⋮</button>
                            </div>

                            <h3 class="post-title"><a href="post.php?id=<?php echo $post['id']; ?>"><?php echo htmlspecialchars($post['title']); ?></a></h3>
                            <p class="post-excerpt">
                                <?php echo htmlspecialchars(substr($post['content'], 0, 180)); ?>...
                            </p>

                            <div class="post-tags">
                                <?php foreach ($tags as $tag): ?>
                                    <span class="post-tag"><?php echo $tag; ?></span>
                                <?php endforeach; ?>
                            </div>

                            <div class="post-stats">
                                <span title="<?php echo $post['views_count']; ?> views">👁️ <?php echo $post['views_count']; ?></span>
                                <span title="<?php echo $post['likes_count']; ?> likes">❤️ <?php echo $post['likes_count']; ?></span>
                                <span title="<?php echo $post['comments_count']; ?> comments">💬 <?php echo $post['comments_count']; ?></span>
                            </div>

                            <div class="post-actions">
                                <button 
                                    class="btn-action btn-like" 
                                    onclick="likePost(<?php echo $post['id']; ?>, this)" 
                                    <?php echo $post['user_liked'] ? 'data-liked="true"' : ''; ?>>
                                    <span><?php echo $post['user_liked'] ? '❤️' : '🤍'; ?></span>
                                    <span>Love</span>
                                </button>
                                <a href="post.php?id=<?php echo $post['id']; ?>#comments" class="btn-action btn-comment">
                                    <span>💬</span>
                                    <span>Feel</span>
                                </a>
                                <button class="btn-action btn-save" onclick="savePost(<?php echo $post['id']; ?>, this)">
                                    <span>🔖</span>
                                    <span>Save</span>
                                </button>
                                <button class="btn-action btn-share" onclick="sharePost(<?php echo $post['id']; ?>)">
                                    <span>🔗</span>
                                    <span>Share</span>
                                </button>
                            </div>
                        </div>
                    </div>

                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?mode=<?php echo $feedMode; ?>&page=<?php echo $page - 1; ?>" class="btn-page">← Previous</a>
            <?php endif; ?>
            <span class="page-indicator">Page <?php echo $page; ?></span>
            <?php if (count($posts) === $per_page): ?>
                <a href="?mode=<?php echo $feedMode; ?>&page=<?php echo $page + 1; ?>" class="btn-page">Next →</a>
            <?php endif; ?>
        </div>

        <div class="feed-end-message">
            <div class="feed-end-icon">✨</div>
            <p>You've reached this moment of the journey.</p>
            <p>More souls await tomorrow. Rest now. 🌙</p>
        </div>

    </div>

    <a href="create_post.php" class="fab" title="Create a new post">
        <span>+</span>
    </a>

    <script src="assets/js/profile.js"></script>
    <script src="assets/js/explore.js"></script>
</body>
</html>

<?php
// Helper function: Format time ago (Copied from your profile.php)
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