<?php
// scripts/seed.php
require_once __DIR__ . '/../config/database.php';

// Adjust these values if needed
$username = 'tester';
$email = 'tester@example.local';
$password = 'Password123!';
$name = 'Test User';
$avatar = '/assets/images/default-avatar.png';
$country = 'Nowhere';

try {
    $pdo->beginTransaction();

    // Create user if not exists
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $existing = $stmt->fetch();

    if ($existing) {
        $userId = (int)$existing['id'];
        echo "User already exists (id={$userId})\n";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash, name, avatar, country) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$username, $email, $hash, $name, $avatar, $country]);
        $userId = (int)$pdo->lastInsertId();
        echo "Created user id={$userId} (email={$email})\n";
    }

    // Create sample post
    $stmt = $pdo->prepare('SELECT id FROM posts WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $p = $stmt->fetch();
    if ($p) {
        echo "Sample post already exists (id={$p['id']})\n";
    } else {
        $stmt = $pdo->prepare('INSERT INTO posts (user_id, title, content, media_url, is_published, created_at) VALUES (?, ?, ?, ?, 1, NOW())');
        $stmt->execute([
            $userId,
            'Welcome to CultureConnect',
            "This is a seeded post to verify the system works.\n\nEnjoy testing the flow.",
            '/assets/images/sample-hero.jpg'
        ]);
        $postId = (int)$pdo->lastInsertId();
        echo "Created sample post id={$postId}\n";
    }

    $pdo->commit();
    echo "Seeding completed. Login with: {$email} / {$password}\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "Seeder failed: " . $e->getMessage() . "\n";
    exit(1);
}
