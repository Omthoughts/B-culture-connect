<?php
/**
 * ============================================
 * SECURED CultureConnect Soul Mirror - Profile Page
 * Where identity becomes presence
 * ============================================
 */

// Load the central configuration (Database, Security, Constants)
require_once __DIR__ . '/config.php';

// Load Logger helper (not included in config by default)
require_once __DIR__ . '/helpers/logger.php';

// Disable display_errors in production
if (getenv('APP_ENV') === 'production') {
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
}

// Add security headers (Double check, though SecurityManager usually handles this)
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

// Handle API requests (AJAX)
$request_method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// If AJAX request
if (!empty($action) && $request_method === 'POST') {
    header('Content-Type: application/json');
    handleAjaxRequest($action, $pdo);
    exit;
}

// --- Main Page Load ---
// Validate profile user ID as integer
$profile_user_id = filter_var($_GET['id'] ?? ($_SESSION['user_id'] ?? null), FILTER_VALIDATE_INT);
$current_user_id = $_SESSION['user_id'] ?? null;
$is_own_profile = ($current_user_id === $profile_user_id);

if (!$profile_user_id) {
    header('Location: login.php'); // Fixed relative path
    exit;
}

// Fetch profile user
try {
    $stmt = $pdo->prepare('
        SELECT id, username, email, name, avatar, bio, country, language, 
               cover_image, theme_color, created_at, is_verified, last_active
        FROM users
        WHERE id = ? AND profile_visibility != "private"
        LIMIT 1
    ');
    $stmt->execute([$profile_user_id]);
    $profile_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$profile_user) {
        http_response_code(404);
        die('🪞 This soul\'s profile is not visible');
    }
    
    // Get stats
    $stats = getProfileStats($profile_user_id, $pdo);
    $connection_state = getConnectionState($current_user_id, $profile_user_id, $pdo);
    $preferences = getUserPreferences($profile_user_id, $pdo);
    
    // Validate pagination inputs
    $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
    if ($page === false || $page < 1) {
        $page = 1;
    }
    if ($page > 1000) { // Reasonable upper limit
        $page = 1000;
    }
    
    $per_page = 10;
    $offset = ($page - 1) * $per_page;
    
    $stmt = $pdo->prepare('
        SELECT p.*, 
               COUNT(DISTINCT pl.id) as likes,
               COUNT(DISTINCT c.id) as comments,
               (SELECT 1 FROM post_likes WHERE post_id = p.id AND user_id = ?) as user_liked
        FROM posts p
        LEFT JOIN post_likes pl ON p.id = pl.post_id
        LEFT JOIN comments c ON p.id = c.post_id
        WHERE p.user_id = ? AND p.is_published = TRUE
        GROUP BY p.id
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?
    ');
    $stmt->execute([$current_user_id, $profile_user_id, $per_page, $offset]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get achievements
    $stmt = $pdo->prepare('
        SELECT achievement_type, title, description, icon, unlocked_at
        FROM achievements
        WHERE user_id = ?
        ORDER BY unlocked_at DESC
    ');
    $stmt->execute([$profile_user_id]);
    $achievements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    Logger::error('PROFILE_ERROR', $e->getMessage());
    die('🪞 A moment of reflection needed...');
}

// --- Helper Functions ---

function getProfileStats($user_id, $pdo) {
    $stmt = $pdo->prepare('SELECT COUNT(*) as posts FROM posts WHERE user_id = ? AND is_published = TRUE');
    $stmt->execute([$user_id]);
    $posts_count = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare('SELECT COUNT(*) as followers FROM follows WHERE following_id = ?');
    $stmt->execute([$user_id]);
    $followers_count = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare('SELECT COUNT(*) as following FROM follows WHERE follower_id = ?');
    $stmt->execute([$user_id]);
    $following_count = $stmt->fetchColumn();
    
    return compact('posts_count', 'followers_count', 'following_count');
}

function getConnectionState($current_user_id, $profile_user_id, $pdo) {
    if (!$current_user_id) {
        return ['is_following' => false, 'is_follower' => false];
    }
    
    $stmt = $pdo->prepare('
        SELECT 1 FROM follows
        WHERE follower_id = ? AND following_id = ?
        LIMIT 1
    ');
    $stmt->execute([$current_user_id, $profile_user_id]);
    $is_following = $stmt->fetch() !== false;
    
    $stmt = $pdo->prepare('
        SELECT 1 FROM follows
        WHERE follower_id = ? AND following_id = ?
        LIMIT 1
    ');
    $stmt->execute([$profile_user_id, $current_user_id]);
    $is_follower = $stmt->fetch() !== false;
    
    return compact('is_following', 'is_follower');
}

function getUserPreferences($user_id, $pdo) {
    $stmt = $pdo->prepare('
        SELECT energy_mode, show_presence
        FROM user_preferences
        WHERE user_id = ?
    ');
    $stmt->execute([$user_id]);
    $prefs = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $prefs ?? ['energy_mode' => 'cosmic', 'show_presence' => true];
}

function handleAjaxRequest($action, $pdo) {
    $user_id = $_SESSION['user_id'] ?? null;
    
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        return;
    }
    
    // CSRF validation on ALL actions
    if (!security()->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Security validation failed']);
        return;
    }
    
    switch ($action) {
        case 'update_bio':
            // Rate limiting
            if (!security()->checkRateLimit('update_bio:' . $user_id, 5, 300)) {
                http_response_code(429);
                echo json_encode(['success' => false, 'message' => 'Too many updates. Please wait.']);
                return;
            }
            
            // Validate input
            $bio = trim($_POST['bio'] ?? '');
            if (strlen($bio) > 250) {
                echo json_encode(['success' => false, 'message' => 'Bio too long']);
                return;
            }
            
            $stmt = $pdo->prepare('UPDATE users SET bio = ? WHERE id = ?');
            $result = $stmt->execute([$bio, $user_id]);
            
            if ($result) {
                Logger::log('BIO_UPDATED', "User updated bio", ['user_id' => $user_id]);
                echo json_encode(['success' => true, 'message' => '✨ Saved. You are evolving.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to save']);
            }
            break;
        
        case 'follow':
            if (!security()->checkRateLimit('follow:' . $user_id, 20, 3600)) {
                http_response_code(429);
                echo json_encode(['success' => false, 'message' => 'Slow down, soul traveler.']);
                return;
            }
            
            $target_id = filter_var($_POST['target_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$target_id || $target_id === $user_id) {
                echo json_encode(['success' => false, 'message' => 'Invalid target']);
                return;
            }
            
            $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$target_id]);
            if (!$stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'User not found']);
                return;
            }
            
            $stmt = $pdo->prepare('SELECT id FROM follows WHERE follower_id = ? AND following_id = ? LIMIT 1');
            $stmt->execute([$user_id, $target_id]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Already following']);
                return;
            }
            
            $stmt = $pdo->prepare('INSERT INTO follows (follower_id, following_id, created_at) VALUES (?, ?, NOW())');
            $result = $stmt->execute([$user_id, $target_id]);
            
            if ($result) {
                $stmt = $pdo->prepare('INSERT INTO notifications (user_id, type, actor_id, message, created_at) VALUES (?, "follow", ?, "You now share light with this soul.", NOW())');
                $stmt->execute([$target_id, $user_id]);
                Logger::log('FOLLOW', "User followed another", ['follower' => $user_id, 'following' => $target_id]);
                echo json_encode(['success' => true, 'message' => '💫 Connection formed']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to follow']);
            }
            break;
        
        case 'unfollow':
            $target_id = filter_var($_POST['target_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$target_id) {
                echo json_encode(['success' => false, 'message' => 'Invalid target']);
                return;
            }
            
            $stmt = $pdo->prepare('DELETE FROM follows WHERE follower_id = ? AND following_id = ? LIMIT 1');
            $result = $stmt->execute([$user_id, $target_id]);
            
            if ($result && $stmt->rowCount() > 0) {
                Logger::log('UNFOLLOW', "User unfollowed another", ['follower' => $user_id, 'unfollowed' => $target_id]);
                echo json_encode(['success' => true, 'message' => '🌊 Path diverged']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Not following']);
            }
            break;
        
        case 'like_post':
            if (!security()->checkRateLimit('like:' . $user_id, 60, 60)) {
                http_response_code(429);
                echo json_encode(['success' => false, 'message' => 'Too many actions']);
                return;
            }
            
            $post_id = filter_var($_POST['post_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$post_id) {
                echo json_encode(['success' => false, 'message' => 'Invalid post']);
                return;
            }
            
            $stmt = $pdo->prepare('SELECT user_id FROM posts WHERE id = ? AND is_published = TRUE LIMIT 1');
            $stmt->execute([$post_id]);
            $post_owner = $stmt->fetchColumn();
            
            if (!$post_owner) {
                echo json_encode(['success' => false, 'message' => 'Post not found']);
                return;
            }
            
            $stmt = $pdo->prepare('SELECT id FROM post_likes WHERE post_id = ? AND user_id = ? LIMIT 1');
            $stmt->execute([$post_id, $user_id]);
            $already_liked = $stmt->fetch() !== false;
            
            if ($already_liked) {
                $stmt = $pdo->prepare('DELETE FROM post_likes WHERE post_id = ? AND user_id = ? LIMIT 1');
                $stmt->execute([$post_id, $user_id]);
                $stmt = $pdo->prepare('UPDATE posts SET likes_count = GREATEST(0, likes_count - 1) WHERE id = ?');
                $stmt->execute([$post_id]);
                echo json_encode(['success' => true, 'liked' => false, 'message' => 'Unliked']);
            } else {
                $stmt = $pdo->prepare('INSERT INTO post_likes (post_id, user_id, created_at) VALUES (?, ?, NOW())');
                $stmt->execute([$post_id, $user_id]);
                $stmt = $pdo->prepare('UPDATE posts SET likes_count = likes_count + 1 WHERE id = ?');
                $stmt->execute([$post_id]);
                
                if ($post_owner !== $user_id) {
                    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, type, actor_id, post_id, message, created_at) VALUES (?, "like", ?, ?, "Someone loves your story.", NOW())');
                    $stmt->execute([$post_owner, $user_id, $post_id]);
                }
                echo json_encode(['success' => true, 'liked' => true, 'message' => '❤️ Loved']);
            }
            break;
        
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo htmlspecialchars($profile_user['name']); ?>'s story on CultureConnect">
    <meta name="csrf-token" content="<?php echo security()->generateCSRFToken(); ?>">
    <title><?php echo htmlspecialchars($profile_user['name']); ?> - CultureConnect 🌍</title>
    <link rel="stylesheet" href="style_profile.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
</head>

<body data-theme="<?php echo htmlspecialchars($preferences['energy_mode']); ?>" data-user-id="<?php echo $current_user_id ?? ''; ?>">
    
    <nav class="nav-floating">
        <div class="nav-content">
            <a href="index.php" class="logo">
                <span class="logo-icon">🌍</span>
                <span class="logo-text">CultureConnect</span>
            </a>
            <div class="nav-links">
                <?php if ($current_user_id): ?>
                    <a href="explore.php" class="nav-link">Explore</a>
                    <a href="profile.php?id=<?php echo $current_user_id; ?>" class="nav-link">My Profile</a>
                    <a href="create_post.php" class="nav-link">Create</a>
                    <a href="logout.php" class="btn-nav btn-nav-secondary">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="nav-link">Sign In</a>
                    <a href="register.php" class="btn-nav btn-nav-primary">Join Us</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="profile-container">
        
        <div class="profile-cover" style="background: linear-gradient(135deg, <?php echo htmlspecialchars($profile_user['theme_color'] ?? '#667EEA'); ?> 0%, #764BA2 100%);">
            <div class="cover-glow"></div>
            <?php if ($is_own_profile): ?>
                <button class="btn-edit-cover" onclick="editCover()">📸 Change Cover</button>
            <?php endif; ?>
        </div>

        <div class="profile-header">
            <div class="header-content">
                <div class="avatar-section">
                    <div class="avatar-aura" style="border-color: <?php echo htmlspecialchars($profile_user['theme_color'] ?? '#667EEA'); ?>;">
                        <img src="<?php echo htmlspecialchars($profile_user['avatar'] ?? DEFAULT_AVATAR); ?>" 
                             alt="<?php echo htmlspecialchars($profile_user['name']); ?>"
                             class="profile-avatar">
                        <?php if ($preferences['show_presence']): ?>
                            <div class="presence-indicator"></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="user-info">
                    <div class="name-section">
                        <h1 class="user-name">
                            <?php echo htmlspecialchars($profile_user['name']); ?>
                            <?php if ($profile_user['is_verified']): ?>
                                <span class="verified-badge">✓</span>
                            <?php endif; ?>
                        </h1>
                        <p class="user-handle">@<?php echo htmlspecialchars($profile_user['username']); ?></p>
                    </div>

                    <div class="bio-section" id="bio-section">
                        <p class="user-bio" id="bio-display">
                            <?php echo htmlspecialchars($profile_user['bio'] ?? 'No story yet...'); ?>
                        </p>
                        <?php if ($is_own_profile): ?>
                            <form id="bio-form" class="bio-form" style="display: none;">
                                <textarea id="bio-input" maxlength="250" placeholder="Tell us about yourself..."><?php echo htmlspecialchars($profile_user['bio'] ?? ''); ?></textarea>
                                <div class="bio-footer">
                                    <span class="char-count"><span id="char-count">0</span>/250</span>
                                    <button type="submit" class="btn-save">Save</button>
                                    <button type="button" class="btn-cancel" onclick="toggleBioEdit()">Cancel</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>

                    <div class="meta-info">
                        <span class="meta-item">🌍 <?php echo htmlspecialchars($profile_user['country'] ?? 'Earth'); ?></span>
                        <span class="meta-item">🗣️ <?php echo htmlspecialchars($profile_user['language'] ?? 'Language'); ?></span>
                        <span class="meta-item">📅 <?php echo date('M Y', strtotime($profile_user['created_at'])); ?></span>
                    </div>
                </div>

                <div class="stats-section">
                    <div class="stat-card">
                        <span class="stat-number"><?php echo $stats['posts_count']; ?></span>
                        <span class="stat-label">Stories</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number"><?php echo $stats['followers_count']; ?></span>
                        <span class="stat-label">Followers</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number"><?php echo $stats['following_count']; ?></span>
                        <span class="stat-label">Following</span>
                    </div>
                </div>
            </div>

            <div class="action-buttons">
                <?php if ($is_own_profile): ?>
                    <button class="btn-primary" onclick="toggleBioEdit()">✏️ Edit Profile</button>
                    <a href="settings.php" class="btn-secondary">⚙️ Settings</a>
                <?php else: ?>
                    <button class="btn-primary" onclick="toggleFollow(<?php echo $profile_user_id; ?>)" id="follow-btn">
                        <?php echo $connection_state['is_following'] ? '✓ Following' : '+ Follow'; ?>
                    </button>
                    <button class="btn-secondary" onclick="sendMessage(<?php echo $profile_user_id; ?>)">💬 Message</button>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($achievements)): ?>
        <div class="achievements-section">
            <h2 class="section-title">🌸 Gentle Glows</h2>
            <div class="achievements-grid">
                <?php foreach ($achievements as $achievement): ?>
                <div class="achievement-card" title="<?php echo htmlspecialchars($achievement['description']); ?>">
                    <span class="achievement-icon"><?php echo htmlspecialchars($achievement['icon']); ?></span>
                    <p class="achievement-title"><?php echo htmlspecialchars($achievement['title']); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="posts-section">
            <h2 class="section-title">📚 Cultural Stories</h2>
            
            <?php if (empty($posts)): ?>
                <div class="empty-state">
                    <p class="empty-icon">🌱</p>
                    <p class="empty-text">No stories yet. The garden is waiting to bloom.</p>
                    <?php if ($is_own_profile): ?>
                        <a href="create_post.php" class="btn-primary">✨ Start Sharing</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="posts-grid">
                    <?php foreach ($posts as $post): ?>
                    <div class="post-card" data-post-id="<?php echo $post['id']; ?>">
                        <div class="post-header">
                            <div class="post-meta">
                                <time class="post-date" datetime="<?php echo htmlspecialchars($post['created_at']); ?>">
                                    <?php echo formatTimeAgo($post['created_at']); ?>
                                </time>
                                <span class="post-category"><?php echo htmlspecialchars(ucfirst($post['category'])); ?></span>
                            </div>
                            <?php if ($is_own_profile): ?>
                            <div class="post-menu">
                                <button class="btn-menu" onclick="togglePostMenu(this)">⋮</button>
                                <div class="dropdown-menu" style="display: none;">
                                    <a href="edit_post.php?id=<?php echo $post['id']; ?>">✏️ Edit</a>
                                    <button onclick="deletePost(<?php echo $post['id']; ?>)">🗑️ Delete</button>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <h3 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h3>
                        <p class="post-excerpt"><?php echo htmlspecialchars(substr($post['content'], 0, 150)); ?>...</p>

                        <?php if (!empty($post['media_url'])): ?>
                        <div class="post-media">
                            <img src="<?php echo htmlspecialchars($post['media_url']); ?>" alt="Post image" loading="lazy">
                        </div>
                        <?php endif; ?>

                        <div class="post-stats">
                            <span>❤️ <?php echo intval($post['likes']); ?></span>
                            <span>💬 <?php echo intval($post['comments']); ?></span>
                            <span>👁️ <?php echo intval($post['views_count']); ?></span>
                        </div>

                        <div class="post-actions">
                            <button class="btn-action" onclick="likePost(<?php echo $post['id']; ?>, this)" 
                                    <?php echo $post['user_liked'] ? 'data-liked="true"' : ''; ?>>
                                <?php echo $post['user_liked'] ? '❤️' : '🤍'; ?> Like
                            </button>
                            <a href="post.php?id=<?php echo $post['id']; ?>" class="btn-action">💬 Comment</a>
                            <button class="btn-action" onclick="sharePost(<?php echo $post['id']; ?>)">🔗 Share</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?id=<?php echo $profile_user_id; ?>&page=<?php echo $page - 1; ?>" class="btn-page">← Previous</a>
                    <?php endif; ?>
                    <span class="page-indicator">Page <?php echo $page; ?></span>
                    <?php if (count($posts) === $per_page): ?>
                        <a href="?id=<?php echo $profile_user_id; ?>&page=<?php echo $page + 1; ?>" class="btn-page">Next →</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="profile.js"></script>
</body>
</html>

<?php
// Helper function: Format time ago
function formatTimeAgo($datetime) {
    $now = new DateTime();
    $then = new DateTime($datetime);
    $interval = $now->diff($then);
    
    if ($interval->d === 0 && $interval->h === 0) {
        return $interval->i . 'm ago';
    } elseif ($interval->d === 0) {
        return $interval->h . 'h ago';
    } elseif ($interval->d < 7) {
        return $interval->d . 'd ago';
    } else {
        return $then->format('M d, Y');
    }
}
?>