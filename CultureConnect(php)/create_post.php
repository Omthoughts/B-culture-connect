<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 * SECURED CULTURECONNECT POST CREATION
 * Psychologically optimized to make sharing irresistible
 * ═══════════════════════════════════════════════════════════════════
 * Security Fixes:
 * - Enhanced file upload validation (magic bytes, image verification)
 * - Proper input sanitization
 * - XSS prevention on output
 * - SQL injection protection (already good with PDO)
 * - Rate limiting (already present)
 * - CSRF protection (already present)
 * - Path traversal prevention
 */

session_start();

// SECURITY FIX #1: Disable display_errors in production
if (getenv('APP_ENV') === 'production') {
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
}

require_once __DIR__ . '/core/security.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/image.php';

// SECURITY FIX #2: Add security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

// Security: Require authentication
security()->requireAuth();

$user_id = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Handle post creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_post'])) {
    
    // Validate CSRF token
    if (!security()->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Security validation failed. Please try again.';
        $messageType = 'error';
    } 
    // Rate limiting: 5 posts per hour
    elseif (!security()->checkRateLimit('create_post:' . $user_id, 5, 3600)) {
        $message = 'You\'re sharing too quickly! Take a moment to breathe. 🌸';
        $messageType = 'error';
    }
    else {
        // SECURITY FIX #3: Proper input validation and sanitization
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $country = trim($_POST['country'] ?? '');
        
        // SECURITY FIX #4: Parse tags safely from JSON or array
        $tags = [];
        if (isset($_POST['tags']) && is_string($_POST['tags'])) {
            $decoded = json_decode($_POST['tags'], true);
            if (is_array($decoded)) {
                $tags = array_slice($decoded, 0, 10); // Max 10 tags
                // Sanitize each tag
                $tags = array_map(function($tag) {
                    return substr(trim($tag), 0, 50); // Max 50 chars per tag
                }, $tags);
            }
        }
        
        $errors = [];
        
        // SECURITY FIX #5: Enhanced validation
        if (empty($title)) {
            $errors[] = 'Title is required';
        } elseif (strlen($title) < 10) {
            $errors[] = 'Title must be at least 10 characters';
        } elseif (strlen($title) > 255) {
            $errors[] = 'Title must not exceed 255 characters';
        }
        
        if (empty($content)) {
            $errors[] = 'Story content is required';
        } elseif (strlen($content) < 50) {
            $errors[] = 'Share at least 50 characters of your story';
        } elseif (strlen($content) > 5000) {
            $errors[] = 'Content must not exceed 5000 characters';
        }
        
        $allowed_categories = ['food', 'festival', 'tradition', 'language', 'art', 'music', 'story', 'other'];
        if (!in_array($category, $allowed_categories, true)) {
            $errors[] = 'Invalid category';
        }
        
        // SECURITY FIX #6: Validate country length
        if (strlen($country) > 100) {
            $errors[] = 'Country name too long';
        }
        
        if (empty($errors)) {
            // Handle image upload
            $media_url = null;
            $media_type = null;
            
            if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
                
                // SECURITY FIX #7: Enhanced file validation
                // Verify actual MIME type using magic bytes
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $actualMimeType = finfo_file($finfo, $_FILES['media']['tmp_name']);
                finfo_close($finfo);
                
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                
                if (!in_array($actualMimeType, $allowedTypes, true)) {
                    $errors[] = 'Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.';
                } elseif ($_FILES['media']['size'] > 5242880) { // 5MB
                    $errors[] = 'Image must be less than 5MB';
                } else {
                    // SECURITY FIX #8: Verify it's actually an image
                    $imageInfo = getimagesize($_FILES['media']['tmp_name']);
                    if ($imageInfo === false) {
                        $errors[] = 'File is not a valid image';
                    } else {
                        // SECURITY FIX #9: Generate cryptographically secure filename
                        $ext = match($actualMimeType) {
                            'image/jpeg' => 'jpg',
                            'image/png' => 'png',
                            'image/gif' => 'gif',
                            'image/webp' => 'webp',
                            default => 'jpg'
                        };
                        
                        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
                        
                        // SECURITY FIX #10: Use date-based directory structure
                        $upload_dir = __DIR__ . '/public/uploads/posts/' . date('Y/m/');
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                            
                            // SECURITY FIX #11: Create .htaccess in upload directory
                            $htaccess_path = __DIR__ . '/public/uploads/posts/.htaccess';
                            if (!file_exists($htaccess_path)) {
                                file_put_contents($htaccess_path, 
                                    '<FilesMatch "\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi)$">' . PHP_EOL .
                                    '    Order Deny,Allow' . PHP_EOL .
                                    '    Deny from all' . PHP_EOL .
                                    '</FilesMatch>'
                                );
                            }
                        }
                        
                        $filepath = $upload_dir . $filename;
                        
                        // Optimize and save image
                        if (ImageHelper::optimizeAndSave($_FILES['media']['tmp_name'], $filepath)) {
                            $media_url = '/uploads/posts/' . date('Y/m/') . $filename;
                            $media_type = 'image';
                        } else {
                            $errors[] = 'Failed to process image';
                        }
                    }
                }
            }
            
            if (empty($errors)) {
                try {
                    // Use transaction for atomicity
                    $pdo->beginTransaction();
                    
                    // Insert post
                    $stmt = $pdo->prepare('
                        INSERT INTO posts (
                            user_id, title, content, category, country,
                            media_url, media_type, tags, is_published, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, TRUE, NOW())
                    ');
                    
                    $tags_json = !empty($tags) ? json_encode($tags) : null;
                    
                    $stmt->execute([
                        $user_id,
                        $title,
                        $content,
                        $category,
                        $country,
                        $media_url,
                        $media_type,
                        $tags_json
                    ]);
                    
                    $post_id = $pdo->lastInsertId();
                    
                    // Log activity
                    $stmt = $pdo->prepare('
                        INSERT INTO user_activity (user_id, activity_type, ip_address, created_at)
                        VALUES (?, "post_create", ?, NOW())
                    ');
                    $stmt->execute([$user_id, get_client_ip()]);
                    
                    // Check for achievements
                    $stmt = $pdo->prepare('SELECT COUNT(*) FROM posts WHERE user_id = ?');
                    $stmt->execute([$user_id]);
                    $post_count = $stmt->fetchColumn();
                    
                    // Award achievement for first post
                    if ($post_count === 1) {
                        // SECURITY FIX #12: Check if achievement already exists
                        $stmt = $pdo->prepare('
                            SELECT id FROM achievements 
                            WHERE user_id = ? AND achievement_type = "first_post"
                            LIMIT 1
                        ');
                        $stmt->execute([$user_id]);
                        
                        if (!$stmt->fetch()) {
                            $stmt = $pdo->prepare('
                                INSERT INTO achievements (user_id, achievement_type, title, description, icon, points, unlocked_at)
                                VALUES (?, "first_post", "Soul Awakened", "You shared your first cultural story!", "🌟", 10, NOW())
                            ');
                            $stmt->execute([$user_id]);
                            
                            // Create notification
                            $stmt = $pdo->prepare('
                                INSERT INTO notifications (user_id, type, message, created_at)
                                VALUES (?, "achievement", "🌟 Achievement Unlocked: Soul Awakened!", NOW())
                            ');
                            $stmt->execute([$user_id]);
                        }
                    }
                    
                    $pdo->commit();
                    
                    // SECURITY FIX #13: Clear draft from localStorage via redirect flag
                    header("Location: /post/{$post_id}?created=1");
                    exit;
                    
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    error_log("Post creation error: " . $e->getMessage());
                    $message = 'Could not create post. Please try again.';
                    $messageType = 'error';
                }
            }
        }
        
        if (!empty($errors)) {
            $message = implode('. ', $errors);
            $messageType = 'error';
        }
    }
}

// Get user's country for pre-filling
$stmt = $pdo->prepare('SELECT country FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$user_id]);
$user_country = $stmt->fetchColumn();

// Get user's post count for motivation
$stmt = $pdo->prepare('SELECT COUNT(*) FROM posts WHERE user_id = ?');
$stmt->execute([$user_id]);
$user_post_count = $stmt->fetchColumn();

// Helpers (e(), csrf_field(), csrf_token()) are provided by
// `core/security.php` so they are intentionally not re-declared here to
// avoid redeclaration collisions. This file expects those helpers to be
// available after requiring `core/security.php` near the top.

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title>Share Your Story - CultureConnect 🌍</title>
    <link rel="stylesheet" href="create-post.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
    <!-- Progress Bar (Gamification) -->
    <div class="progress-bar" id="progressBar">
        <div class="progress-fill" style="width: 0%"></div>
    </div>
    
    <!-- Navigation -->
    <nav class="nav-floating">
        <div class="nav-content">
            <a href="/" class="logo">
                <span class="logo-icon">🌍</span>
                <span class="logo-text">CultureConnect</span>
            </a>
            <div class="nav-actions">
                <button type="button" class="btn-preview" id="previewBtn">👁️ Preview</button>
                <button type="submit" form="createPostForm" class="btn-publish" id="publishBtn">
                    <span>✨ Share Story</span>
                </button>
            </div>
        </div>
    </nav>

    <div class="create-container">
        
        <!-- Motivation Header -->
        <header class="create-header">
            <h1 class="create-title">
                <?php if ($user_post_count === 0): ?>
                    Share Your First Story ✨
                <?php elseif ($user_post_count < 5): ?>
                    Story #<?= intval($user_post_count) + 1 ?> 🌱
                <?php else: ?>
                    Add to Your Collection 📚
                <?php endif; ?>
            </h1>
            <p class="create-subtitle">
                <?php if ($user_post_count === 0): ?>
                    Every culture has a story. Yours deserves to be told.
                <?php else: ?>
                    <?= intval($user_post_count) ?> stories shared. The world is listening. 🌏
                <?php endif; ?>
            </p>
        </header>

        <?php if ($message): ?>
        <div class="message message-<?= e($messageType) ?>" id="message">
            <?= e($message) ?>
        </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="createPostForm" class="create-form">
            <?= csrf_field() ?>
            
            <!-- Image Upload (Visual First) -->
            <div class="form-section">
                <label class="section-label">
                    <span class="label-icon">📸</span>
                    <span>Visual Memory (Optional but Powerful)</span>
                </label>
                
                <div class="image-upload-zone" id="imageUploadZone">
                    <input type="file" name="media" id="mediaInput" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
                    
                    <div class="upload-placeholder" id="uploadPlaceholder">
                        <div class="upload-icon">🖼️</div>
                        <h3>Drag & Drop Your Image</h3>
                        <p>or click to browse</p>
                        <span class="upload-hint">JPG, PNG, GIF, WebP • Max 5MB</span>
                    </div>
                    
                    <div class="image-preview" id="imagePreview" style="display: none;">
                        <img id="previewImage" alt="Preview">
                        <button type="button" class="btn-remove-image" id="removeImageBtn">
                            <span>✕</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Title (Emotional Hook) -->
            <div class="form-section">
                <label for="title" class="section-label">
                    <span class="label-icon">✨</span>
                    <span>What's Your Story Called?</span>
                </label>
                
                <input 
                    type="text" 
                    name="title" 
                    id="title" 
                    class="form-input input-large"
                    placeholder="e.g., My Grandmother's Secret Recipe"
                    maxlength="255"
                    required
                    autocomplete="off"
                    value="<?= isset($_POST['title']) ? e($_POST['title']) : '' ?>">
                
                <div class="input-footer">
                    <span class="char-counter"><span id="titleCount">0</span>/255</span>
                    <span class="input-hint">Make it intriguing! 🎭</span>
                </div>
            </div>

            <!-- Category (Context) -->
            <div class="form-section">
                <label for="category" class="section-label">
                    <span class="label-icon">🎯</span>
                    <span>What Kind of Story?</span>
                </label>
                
                <div class="category-grid">
                    <?php
                    $categories = [
                        'food' => ['icon' => '🍲', 'name' => 'Food'],
                        'festival' => ['icon' => '🎉', 'name' => 'Festival'],
                        'tradition' => ['icon' => '🏛️', 'name' => 'Tradition'],
                        'language' => ['icon' => '💬', 'name' => 'Language'],
                        'art' => ['icon' => '🎨', 'name' => 'Art'],
                        'music' => ['icon' => '🎵', 'name' => 'Music'],
                        'story' => ['icon' => '📖', 'name' => 'Story'],
                        'other' => ['icon' => '✨', 'name' => 'Other']
                    ];
                    
                    foreach ($categories as $value => $cat):
                        $checked = (isset($_POST['category']) && $_POST['category'] === $value) ? 'checked' : '';
                    ?>
                    <label class="category-card">
                        <input type="radio" name="category" value="<?= e($value) ?>" <?= $checked ?> required>
                        <div class="category-content">
                            <span class="category-icon"><?= e($cat['icon']) ?></span>
                            <span class="category-name"><?= e($cat['name']) ?></span>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Content (The Heart) -->
            <div class="form-section">
                <label for="content" class="section-label">
                    <span class="label-icon">💭</span>
                    <span>Tell Your Story</span>
                </label>
                
                <textarea 
                    name="content" 
                    id="content" 
                    class="form-textarea"
                    placeholder="Every detail matters... Where does this memory take you? What emotions does it carry? Why does it matter to your culture?"
                    required
                    minlength="50"
                    maxlength="5000"><?= isset($_POST['content']) ? e($_POST['content']) : '' ?></textarea>
                
                <div class="input-footer">
                    <span class="char-counter"><span id="contentCount">0</span>/5000</span>
                    <span class="input-hint" id="contentHint">Minimum 50 characters</span>
                </div>
            </div>

            <!-- Country (Context) -->
            <div class="form-section">
                <label for="country" class="section-label">
                    <span class="label-icon">🌍</span>
                    <span>Where Is This Story From?</span>
                </label>
                
                <input 
                    type="text" 
                    name="country" 
                    id="country" 
                    class="form-input"
                    placeholder="e.g., India, Mexico, Nigeria..."
                    value="<?= e($user_country ?? '') ?>"
                    list="countryList"
                    maxlength="100"
                    autocomplete="off">
                
                <datalist id="countryList">
                    <?php 
                    $countries = ["Afghanistan","Albania","Algeria","Argentina","Australia","Austria","Bangladesh","Belgium","Bolivia","Brazil","Bulgaria","Cambodia","Cameroon","Canada","Chile","China","Colombia","Costa Rica","Croatia","Cuba","Denmark","Egypt","Ethiopia","Finland","France","Georgia","Germany","Ghana","Greece","Guatemala","Honduras","Hungary","Iceland","India","Indonesia","Iran","Iraq","Ireland","Israel","Italy","Jamaica","Japan","Jordan","Kazakhstan","Kenya","South Korea","Lebanon","Libya","Malaysia","Mexico","Morocco","Myanmar","Nepal","Netherlands","New Zealand","Nicaragua","Nigeria","Norway","Pakistan","Palestine","Panama","Paraguay","Peru","Philippines","Poland","Portugal","Qatar","Romania","Russia","Saudi Arabia","Senegal","Serbia","Singapore","South Africa","Spain","Sri Lanka","Sudan","Sweden","Switzerland","Syria","Taiwan","Tanzania","Thailand","Turkey","Uganda","Ukraine","United Arab Emirates","United Kingdom","United States","Uruguay","Venezuela","Vietnam","Yemen","Zimbabwe"];
                    foreach ($countries as $country): ?>
                        <option value="<?= e($country) ?>">
                    <?php endforeach; ?>
                </datalist>
                
                <span class="input-hint">Help others discover stories from your region</span>
            </div>

            <!-- Tags (Discovery) -->
            <div class="form-section">
                <label for="tags" class="section-label">
                    <span class="label-icon">🏷️</span>
                    <span>Add Tags (Optional)</span>
                </label>
                
                <div class="tags-input-wrapper">
                    <input 
                        type="text" 
                        id="tagInput" 
                        class="form-input"
                        placeholder="Type a tag and press Enter..."
                        maxlength="50"
                        autocomplete="off">
                </div>
                
                <div class="tags-display" id="tagsDisplay"></div>
                
                <span class="input-hint">Tags help people discover your story (max 10)</span>
            </div>

            <input type="hidden" name="create_post" value="1">
        </form>
        
        <!-- Publishing Footer -->
        <div class="publish-footer">
            <div class="publish-info">
                <p class="publish-tip">💡 <strong>Tip:</strong> Stories with images get 3× more engagement!</p>
                <p class="publish-privacy">Your story will be visible to everyone. You can edit or delete it anytime.</p>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="modal" id="previewModal" style="display: none;">
        <div class="modal-backdrop" onclick="closePreview()"></div>
        <div class="modal-content">
            <button class="modal-close" onclick="closePreview()">✕</button>
            <h2 class="modal-title">Story Preview 👁️</h2>
            <div id="previewContent"></div>
        </div>
    </div>

    <script src="create-post.js"></script>
</body>
</html>