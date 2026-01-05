<?php
// index.php - The Soul of CultureConnect
session_start();
include 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="CultureConnect - Where souls meet across borders. Share your world, discover theirs.">
    <title>CultureConnect - Unite Humanity Through Culture 🌍</title>
    <link rel="stylesheet" href="style_home.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Floating Particles Background -->
    <div class="particles-container" id="particles"></div>
    
    <!-- Navigation -->
    <nav class="nav-floating">
        <div class="nav-content">
            <a href="index.php" class="logo">
                <span class="logo-icon">🌍</span>
                <span class="logo-text">CultureConnect</span>
            </a>
            <div class="nav-links">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="explore.php" class="nav-link">Explore</a>
                    <a href="profile.php" class="nav-link">Profile</a>
                    <a href="create_post.php" class="nav-link">Create</a>
                    <a href="logout.php" class="btn-nav btn-nav-secondary">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="nav-link">Sign In</a>
                    <a href="register.php" class="btn-nav btn-nav-primary">Join Us</a>
                <?php endif; ?>
            </div>
            <button class="menu-toggle" id="menuToggle">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </nav>

    <!-- Hero Section - The First Breath -->
    <section class="hero">
        <div class="hero-background">
            <div class="gradient-orb orb-1"></div>
            <div class="gradient-orb orb-2"></div>
            <div class="gradient-orb orb-3"></div>
        </div>
        
        <div class="hero-content">
            <div class="hero-badge" data-aos="fade-down">
                <span class="badge-pulse"></span>
                Connecting 195+ Countries
            </div>
            
            <h1 class="hero-title" data-aos="fade-up" data-aos-delay="200">
                Where <span class="gradient-text">Souls</span> Meet<br>
                Across <span class="gradient-text">Borders</span>
            </h1>
            
            <p class="hero-subtitle" data-aos="fade-up" data-aos-delay="400">
                A sanctuary where cultures dance, stories breathe, and humanity remembers—<br>
                <em>we are one, yet beautifully different.</em>
            </p>
            
            <div class="hero-cta" data-aos="fade-up" data-aos-delay="600">
                <a href="register.php" class="btn-hero btn-hero-primary">
                    <span>Begin Your Journey</span>
                    <div class="btn-glow"></div>
                </a>
                <a href="explore.php" class="btn-hero btn-hero-secondary">
                    <span>Explore Cultures</span>
                </a>
            </div>
            
            <div class="hero-stats" data-aos="fade-up" data-aos-delay="800">
                <div class="stat-item">
                    <span class="stat-number">50K+</span>
                    <span class="stat-label">Cultural Stories</span>
                </div>
                <div class="stat-divider"></div>
                <div class="stat-item">
                    <span class="stat-number">195+</span>
                    <span class="stat-label">Countries United</span>
                </div>
                <div class="stat-divider"></div>
                <div class="stat-item">
                    <span class="stat-number">∞</span>
                    <span class="stat-label">Connections Made</span>
                </div>
            </div>
        </div>
        
        <div class="scroll-indicator">
            <div class="mouse">
                <div class="wheel"></div>
            </div>
            <p>Scroll to discover</p>
        </div>
    </section>

    <!-- Section 1: What Is CultureConnect -->
    <section class="section section-about">
        <div class="wave-divider wave-top"></div>
        
        <div class="container">
            <div class="section-header" data-aos="fade-up">
                <span class="section-tag">What We Are</span>
                <h2 class="section-title">A Bridge Between<br><span class="gradient-text">Hearts & Horizons</span></h2>
                <p class="section-description">
                    CultureConnect is not just a platform—it's a movement. A digital canvas where every tradition,<br>
                    every recipe, every dance, and every story finds a home.
                </p>
            </div>
            
            <div class="features-grid">
                <div class="feature-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-icon">🎭</div>
                    <h3>Share Your Heritage</h3>
                    <p>From ancient rituals to modern celebrations, your culture deserves to be seen, felt, and honored.</p>
                </div>
                
                <div class="feature-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-icon">🌏</div>
                    <h3>Discover New Worlds</h3>
                    <p>Travel through traditions without leaving home. Experience festivals, foods, and philosophies from every corner of Earth.</p>
                </div>
                
                <div class="feature-card" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-icon">💫</div>
                    <h3>Connect Deeply</h3>
                    <p>Beyond likes and shares—forge genuine friendships, learn languages, and build bridges of understanding.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Section 2: Emotional Journey -->
    <section class="section section-emotion">
        <div class="container">
            <div class="emotion-content">
                <div class="emotion-visual" data-aos="fade-right">
                    <div class="emotion-image">
                        <div class="image-placeholder">
                            <span style="font-size: 8rem;">🤝</span>
                        </div>
                    </div>
                </div>
                
                <div class="emotion-text" data-aos="fade-left">
                    <span class="section-tag">The Feeling</span>
                    <h2 class="section-title">
                        When You Share,<br>
                        <span class="gradient-text">The World Listens</span>
                    </h2>
                    <p class="emotion-description">
                        Imagine waking up to a message from Japan, thanking you for sharing your grandmother's recipe.<br><br>
                        
                        Picture a student in Brazil learning your language through your posts.<br><br>
                        
                        Feel the warmth of thousands celebrating your traditions with you, even from miles away.
                    </p>
                    <blockquote class="emotion-quote">
                        "In a world that tries to divide us, CultureConnect reminds us:<br>
                        <strong>We are different flowers from the same garden.</strong>"
                    </blockquote>
                </div>
            </div>
        </div>
    </section>

    <!-- Section 3: Global Vision -->
    <section class="section section-vision">
        <div class="wave-divider wave-top wave-light"></div>
        
        <div class="container">
            <div class="vision-header" data-aos="fade-up">
                <span class="section-tag">Our Dream</span>
                <h2 class="section-title">
                    One World,<br>
                    <span class="gradient-text">Infinite Stories</span>
                </h2>
            </div>
            
            <div class="vision-grid">
                <div class="vision-card" data-aos="zoom-in" data-aos-delay="100">
                    <div class="vision-number">01</div>
                    <h3>Preserve Heritage</h3>
                    <p>Every culture is a library. When a tradition fades, we lose chapters of human history. Let's write them together.</p>
                </div>
                
                <div class="vision-card" data-aos="zoom-in" data-aos-delay="200">
                    <div class="vision-number">02</div>
                    <h3>Break Barriers</h3>
                    <p>Language, distance, and prejudice dissolve when we see each other's humanity through culture.</p>
                </div>
                
                <div class="vision-card" data-aos="zoom-in" data-aos-delay="300">
                    <div class="vision-number">03</div>
                    <h3>Inspire Future</h3>
                    <p>Children grow up in a world where "different" means "beautiful"—not "foreign." That's the legacy we're building.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Section 4: Call to Action -->
    <section class="section section-cta">
        <div class="cta-background">
            <div class="cta-glow"></div>
        </div>
        
        <div class="container">
            <div class="cta-content" data-aos="fade-up">
                <h2 class="cta-title">
                    Ready to Make<br>
                    <span class="gradient-text">History Together?</span>
                </h2>
                <p class="cta-description">
                    Your story matters. Your culture matters. Your voice matters.<br>
                    <strong>Join thousands who are rewriting the narrative of human connection.</strong>
                </p>
                
                <div class="cta-buttons">
                    <a href="register.php" class="btn-cta btn-cta-primary">
                        <span>Start Sharing Now</span>
                        <div class="btn-glow"></div>
                    </a>
                    <a href="explore.php" class="btn-cta btn-cta-secondary">
                        <span>Explore First</span>
                    </a>
                </div>
                
                <p class="cta-footnote">
                    ✨ Free forever. No ads. Just humanity.
                </p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-brand">
                    <div class="footer-logo">
                        <span class="logo-icon">🌍</span>
                        <span class="logo-text">CultureConnect</span>
                    </div>
                    <p class="footer-tagline">
                        Where every culture has a voice,<br>
                        and every voice finds a home.
                    </p>
                </div>
                
                <div class="footer-links">
                    <div class="footer-column">
                        <h4>Explore</h4>
                        <a href="explore.php">Discover Posts</a>
                        <a href="register.php">Join Community</a>
                        <a href="create_post.php">Share Culture</a>
                    </div>
                    
                    <div class="footer-column">
                        <h4>Connect</h4>
                        <a href="#">About Us</a>
                        <a href="#">Contact</a>
                        <a href="#">Support</a>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> CultureConnect. Built with ❤️ for humanity.</p>
                <p class="footer-whisper">
                    <em>"Share your world. Discover theirs. 🌍✨"</em>
                </p>
            </div>
        </div>
    </footer>

    <script src="home.js"></script>
</body>
</html>