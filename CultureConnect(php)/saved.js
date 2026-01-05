/**
 * ═══════════════════════════════════════════════════════════════════
 * CultureConnect - Saved Collection JS
 * Where interactions shine like gems
 * ═══════════════════════════════════════════════════════════════════
 */

(function() {
    'use strict';

    // ============================================
    // INITIALIZATION
    // ============================================
    document.addEventListener('DOMContentLoaded', () => {
        initializeFilters();
        initializeCardAnimations();
        initializeLazyLoading();
        console.log('💎 Saved Collection: Treasures revealed');
    });

    // ============================================
    // FILTER FUNCTIONALITY
    // ============================================
    function initializeFilters() {
        const filterButtons = document.querySelectorAll('.filter-btn');
        const gemCards = document.querySelectorAll('.gem-card');
        
        if (!filterButtons.length) return;
        
        filterButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const filter = this.dataset.filter;
                
                // Update active state
                filterButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                // Filter cards with animation
                gemCards.forEach((card, index) => {
                    const category = card.dataset.category;
                    
                    if (filter === 'all' || category === filter) {
                        card.style.display = 'block';
                        // Stagger animation
                        setTimeout(() => {
                            card.style.animation = 'fadeInUp 0.6s ease forwards';
                        }, index * 50);
                    } else {
                        card.style.animation = 'fadeOut 0.3s ease forwards';
                        setTimeout(() => {
                            card.style.display = 'none';
                        }, 300);
                    }
                });
                
                // Create filter feedback
                createFilterFeedback(filter);
            });
        });
    }

    function createFilterFeedback(filter) {
        const feedback = document.createElement('div');
        feedback.className = 'filter-feedback';
        feedback.style.cssText = `
            position: fixed;
            top: 100px;
            right: 20px;
            background: linear-gradient(135deg, #FFD700, #FFA500);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            box-shadow: 0 8px 24px rgba(255, 215, 0, 0.3);
            z-index: 10000;
            animation: slideInRight 0.3s ease;
        `;
        feedback.textContent = filter === 'all' 
            ? '✨ Showing all gems' 
            : `🎯 Filtering: ${filter.charAt(0).toUpperCase() + filter.slice(1)}`;
        
        document.body.appendChild(feedback);
        
        setTimeout(() => {
            feedback.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => feedback.remove(), 300);
        }, 2000);
    }

    // ============================================
    // TOGGLE SAVE/UNSAVE
    // ============================================
    window.toggleSave = async function(postId, button) {
        if (!postId || !button) return;
        
        const card = button.closest('.gem-card');
        const isSaved = button.dataset.saved === 'true';
        
        // Get CSRF token
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        if (!csrfToken) {
            showNotification('Security token missing', 'error');
            return;
        }
        
        // Disable button during request
        button.disabled = true;
        button.style.opacity = '0.6';
        
        try {
            const response = await fetch('/saved.php?action=toggle_save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `post_id=${postId}&csrf_token=${encodeURIComponent(csrfToken)}`
            });
            
            const data = await response.json();
            
            if (data.success) {
                if (data.saved) {
                    // Saved (shouldn't happen on this page, but handle it)
                    button.dataset.saved = 'true';
                    button.innerHTML = '<span>💔</span> Remove';
                    showNotification(data.message, 'success');
                } else {
                    // Removed - animate card out
                    card.style.animation = 'fadeOutScale 0.5s ease forwards';
                    
                    setTimeout(() => {
                        card.remove();
                        
                        // Check if any cards left
                        const remainingCards = document.querySelectorAll('.gem-card');
                        if (remainingCards.length === 0) {
                            showEmptyState();
                        }
                    }, 500);
                    
                    showNotification(data.message, 'info');
                    createParticleEffect(card);
                }
            } else {
                showNotification(data.message, 'error');
            }
        } catch (error) {
            console.error('Toggle save error:', error);
            showNotification('Failed to update collection', 'error');
        } finally {
            button.disabled = false;
            button.style.opacity = '1';
        }
    };

    // ============================================
    // CARD ANIMATIONS
    // ============================================
    function initializeCardAnimations() {
        const cards = document.querySelectorAll('.gem-card');
        
        cards.forEach((card, index) => {
            // Stagger initial animation
            card.style.animation = `fadeInUp 0.6s ease forwards`;
            card.style.animationDelay = `${index * 0.05}s`;
            
            // Parallax effect on hover
            card.addEventListener('mousemove', function(e) {
                const rect = this.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                
                const rotateX = (y - centerY) / 30;
                const rotateY = (centerX - x) / 30;
                
                this.style.transform = `
                    perspective(1000px) 
                    rotateX(${rotateX}deg) 
                    rotateY(${rotateY}deg) 
                    translateY(-12px)
                `;
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) translateY(0)';
            });
        });
    }

    // ============================================
    // LAZY LOADING IMAGES
    // ============================================
    function initializeLazyLoading() {
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.removeAttribute('data-src');
                        }
                        imageObserver.unobserve(img);
                    }
                });
            });
            
            document.querySelectorAll('img[data-src]').forEach(img => {
                imageObserver.observe(img);
            });
        }
    }

    // ============================================
    // EMPTY STATE
    // ============================================
    function showEmptyState() {
        const grid = document.getElementById('savedGrid');
        if (!grid) return;
        
        const emptyHTML = `
            <div class="empty-collection" style="grid-column: 1/-1;">
                <div class="empty-icon">💎</div>
                <h2>Collection Empty</h2>
                <p>All your gems have been removed</p>
                <p class="empty-hint">
                    💡 Explore more cultures and save posts to rebuild your collection
                </p>
                <a href="/explore.php" class="btn-explore">
                    ✨ Explore Cultures
                </a>
            </div>
        `;
        
        grid.innerHTML = emptyHTML;
        
        // Animate in
        const emptyState = grid.querySelector('.empty-collection');
        emptyState.style.animation = 'fadeIn 0.6s ease';
        
        // Update header
        const headerTitle = document.querySelector('.collection-title');
        if (headerTitle) {
            headerTitle.innerHTML = 'Start Your <span class="gradient-text">Collection</span>';
        }
        
        // Hide filters
        const filterBar = document.querySelector('.filter-bar');
        if (filterBar) {
            filterBar.style.display = 'none';
        }
        
        // Hide stats
        const headerStats = document.querySelector('.header-stats');
        if (headerStats) {
            headerStats.style.display = 'none';
        }
    }

    // ============================================
    // VISUAL EFFECTS
    // ============================================
    function createParticleEffect(element) {
        const rect = element.getBoundingClientRect();
        const particles = ['✨', '💫', '⭐', '🌟', '💎'];
        
        for (let i = 0; i < 8; i++) {
            const particle = document.createElement('div');
            const emoji = particles[Math.floor(Math.random() * particles.length)];
            const angle = (360 / 8) * i;
            const velocity = {
                x: Math.cos(angle * Math.PI / 180) * 100,
                y: Math.sin(angle * Math.PI / 180) * 100
            };
            
            particle.textContent = emoji;
            particle.style.cssText = `
                position: fixed;
                left: ${rect.left + rect.width / 2}px;
                top: ${rect.top + rect.height / 2}px;
                font-size: 1.5rem;
                pointer-events: none;
                z-index: 10000;
                animation: particleBurst 1s ease-out forwards;
                --tx: ${velocity.x}px;
                --ty: ${velocity.y}px;
            `;
            
            document.body.appendChild(particle);
            
            setTimeout(() => particle.remove(), 1000);
        }
        
        // Add keyframes if not exists
        if (!document.getElementById('particle-burst-style')) {
            const style = document.createElement('style');
            style.id = 'particle-burst-style';
            style.textContent = `
                @keyframes particleBurst {
                    0% {
                        opacity: 1;
                        transform: translate(0, 0) scale(1) rotate(0deg);
                    }
                    100% {
                        opacity: 0;
                        transform: translate(var(--tx), var(--ty)) scale(0.5) rotate(360deg);
                    }
                }
                
                @keyframes fadeOutScale {
                    to {
                        opacity: 0;
                        transform: scale(0.8);
                    }
                }
                
                @keyframes fadeOut {
                    to { opacity: 0; }
                }
                
                @keyframes slideInRight {
                    from {
                        transform: translateX(400px);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }
                
                @keyframes slideOutRight {
                    to {
                        transform: translateX(400px);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);
        }
    }

    // ============================================
    // NOTIFICATION SYSTEM
    // ============================================
    function showNotification(message, type = 'info') {
        // Remove existing notifications
        document.querySelectorAll('.toast-notification').forEach(n => n.remove());
        
        const notification = document.createElement('div');
        notification.className = `toast-notification toast-${type}`;
        
        const colors = {
            success: { bg: '#e6f9ed', border: '#10b981', text: '#1b7f3a' },
            error: { bg: '#ffeaea', border: '#ef4444', text: '#d32f2f' },
            warning: { bg: '#fff3cd', border: '#f59e0b', text: '#9a6e00' },
            info: { bg: '#fff8dc', border: '#FFD700', text: '#8B7500' }
        };
        
        const color = colors[type] || colors.info;
        
        notification.style.cssText = `
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            padding: 1rem 1.5rem;
            background: ${color.bg};
            border: 2px solid ${color.border};
            color: ${color.text};
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            font-weight: 600;
            z-index: 10000;
            animation: slideInUp 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            max-width: 350px;
        `;
        
        notification.textContent = message;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOutDown 0.3s ease forwards';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    // Add notification animations
    if (!document.getElementById('notification-style')) {
        const style = document.createElement('style');
        style.id = 'notification-style';
        style.textContent = `
            @keyframes slideInUp {
                from {
                    opacity: 0;
                    transform: translateY(100px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            @keyframes slideOutDown {
                to {
                    opacity: 0;
                    transform: translateY(100px);
                }
            }
        `;
        document.head.appendChild(style);
    }

    // ============================================
    // KEYBOARD SHORTCUTS
    // ============================================
    document.addEventListener('keydown', (e) => {
        // Press 'e' to go to explore
        if (e.key === 'e' && !e.ctrlKey && !e.metaKey) {
            const activeElement = document.activeElement;
            if (activeElement.tagName !== 'INPUT' && activeElement.tagName !== 'TEXTAREA') {
                window.location.href = '/explore.php';
            }
        }
    });

    // ============================================
    // CONSOLE EASTER EGG
    // ============================================
    console.log(
        '%c💎 CultureConnect Saved Collection',
        'font-size: 20px; font-weight: bold; background: linear-gradient(135deg, #FFD700, #FFA500); -webkit-background-clip: text; -webkit-text-fill-color: transparent;'
    );
    console.log(
        '%cYour cultural treasures shine here ✨',
        'font-size: 14px; color: #FFD700; font-style: italic;'
    );
    console.log(
        '%cKeyboard shortcuts: Press "e" to explore',
        'font-size: 12px; color: #8B7500;'
    );

})();