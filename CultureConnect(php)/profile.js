/**
 * ============================================
 * SECURED CultureConnect Soul Mirror - Profile JS
 * Where interactions become emotions
 * ============================================
 * Security Fixes:
 * - CSRF token in all AJAX requests
 * - Input validation and sanitization
 * - XSS prevention
 * - Rate limiting feedback
 */

(function() {
    'use strict';

    // Configuration
    const CONFIG = {
        animationDuration: 300,
        debounceDelay: 500,
        apiEndpoint: '/profile.php'
    };

    // SECURITY FIX #1: Get CSRF token from meta tag
    let csrfToken = null;

    function getCSRFToken() {
        if (!csrfToken) {
            const metaTag = document.querySelector('meta[name="csrf-token"]');
            csrfToken = metaTag ? metaTag.getAttribute('content') : '';
        }
        return csrfToken;
    }

    // ============================================
    // INITIALIZATION
    // ============================================

    document.addEventListener('DOMContentLoaded', () => {
        initializeBioEdit();
        initializeFollowButton();
        initializeLikeButtons();
        initializePostMenu();
        initializeIntersectionObserver();
        initializeCharCounter();
        console.log('🪞 Soul Mirror awakened');
    });

    // ============================================
    // BIO EDITING - The Voice Within
    // ============================================

    function initializeBioEdit() {
        const bioSection = document.getElementById('bio-section');
        const bioDisplay = document.getElementById('bio-display');
        const bioForm = document.getElementById('bio-form');
        const bioInput = document.getElementById('bio-input');
        const charCount = document.getElementById('char-count');

        if (!bioForm) return;

        // Update char count
        bioInput.addEventListener('input', () => {
            charCount.textContent = bioInput.value.length;
        });

        // Handle form submission
        bioForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            // SECURITY FIX #2: Validate and sanitize bio input
            const bio = bioInput.value.trim();
            
            if (bio.length > 250) {
                showNotification('Bio is too long', 'error');
                return;
            }

            // SECURITY FIX #3: Prevent XSS by checking for suspicious patterns
            if (containsSuspiciousContent(bio)) {
                showNotification('Bio contains invalid characters', 'error');
                return;
            }

            try {
                // SECURITY FIX #4: Include CSRF token in request
                const response = await fetch(`${CONFIG.apiEndpoint}?action=update_bio`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `csrf_token=${encodeURIComponent(getCSRFToken())}&bio=${encodeURIComponent(bio)}`
                });

                // SECURITY FIX #5: Handle rate limiting responses
                if (response.status === 429) {
                    showNotification('Too many updates. Please slow down.', 'error');
                    return;
                }

                if (response.status === 403) {
                    showNotification('Security validation failed. Please refresh the page.', 'error');
                    return;
                }

                const data = await response.json();

                if (data.success) {
                    // SECURITY FIX #6: Use textContent to prevent XSS
                    bioDisplay.textContent = bio || 'No story yet...';
                    toggleBioEdit();
                    showNotification(data.message, 'success');
                    createBioBloomAnimation();
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                console.error('Bio update error:', error);
                showNotification('Failed to save bio', 'error');
            }
        });
    }

    // Toggle bio edit mode
    window.toggleBioEdit = function() {
        const bioDisplay = document.getElementById('bio-display');
        const bioForm = document.getElementById('bio-form');

        if (bioForm.style.display === 'none') {
            bioDisplay.style.display = 'none';
            bioForm.style.display = 'flex';
            document.getElementById('bio-input').focus();
        } else {
            bioForm.style.display = 'none';
            bioDisplay.style.display = 'block';
        }
    };

    // Bloom animation on bio save
    function createBioBloomAnimation() {
        const bioSection = document.getElementById('bio-section');
        const style = document.createElement('style');
        style.textContent = `
            @keyframes bio-bloom {
                0% { background: rgba(16, 185, 129, 0.2); }
                100% { background: transparent; }
            }
        `;
        document.head.appendChild(style);
        bioSection.style.animation = 'bio-bloom 1s ease-out';
        
        setTimeout(() => {
            bioSection.style.animation = '';
        }, 1000);
    }

    // ============================================
    // FOLLOW/UNFOLLOW - Connection Flow
    // ============================================

    function initializeFollowButton() {
        const followBtn = document.getElementById('follow-btn');
        if (!followBtn) return;

        followBtn.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target-id') || 
                            this.getAttribute('onclick')?.match(/\d+/)?.[0];
            if (targetId) {
                toggleFollow(parseInt(targetId, 10));
            }
        });
    }

    window.toggleFollow = async function(targetId) {
        // SECURITY FIX #7: Validate targetId
        if (!targetId || !Number.isInteger(targetId) || targetId <= 0) {
            showNotification('Invalid user ID', 'error');
            return;
        }

        const btn = document.getElementById('follow-btn');
        const isFollowing = btn.textContent.includes('Following');
        const action = isFollowing ? 'unfollow' : 'follow';

        // Prevent double-click
        btn.disabled = true;
        btn.style.opacity = '0.7';

        try {
            // SECURITY FIX #8: Include CSRF token
            const response = await fetch(`${CONFIG.apiEndpoint}?action=${action}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `csrf_token=${encodeURIComponent(getCSRFToken())}&target_id=${targetId}`
            });

            // SECURITY FIX #9: Handle rate limiting
            if (response.status === 429) {
                showNotification('Slow down, soul traveler.', 'error');
                btn.disabled = false;
                btn.style.opacity = '1';
                return;
            }

            if (response.status === 403) {
                showNotification('Security validation failed. Please refresh.', 'error');
                btn.disabled = false;
                btn.style.opacity = '1';
                return;
            }

            const data = await response.json();

            if (data.success) {
                if (action === 'follow') {
                    btn.textContent = '✓ Following';
                    btn.classList.add('following');
                    createRippleAnimation(btn);
                } else {
                    btn.textContent = '+ Follow';
                    btn.classList.remove('following');
                }
                showNotification(data.message, 'success');
                playConnectionSound();
            } else {
                showNotification(data.message, 'error');
            }
        } catch (error) {
            console.error('Follow error:', error);
            showNotification('Failed to update follow status', 'error');
        } finally {
            btn.disabled = false;
            btn.style.opacity = '1';
        }
    };

    // Ripple animation
    function createRippleAnimation(element) {
        const ripple = document.createElement('span');
        ripple.style.cssText = `
            position: absolute;
            width: 20px;
            height: 20px;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 50%;
            top: 50%;
            left: 50%;
            pointer-events: none;
            animation: ripple-out 0.6s ease-out;
        `;
        
        if (!element.style.position) element.style.position = 'relative';
        element.appendChild(ripple);

        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple-out {
                to {
                    width: 200px;
                    height: 200px;
                    opacity: 0;
                    transform: translate(-50%, -50%);
                }
            }
        `;
        document.head.appendChild(style);

        setTimeout(() => ripple.remove(), 600);
    }

    // ============================================
    // LIKE POSTS - Joy Expression
    // ============================================

    function initializeLikeButtons() {
        document.querySelectorAll('.btn-action[onclick*="likePost"]').forEach(btn => {
            // Remove inline onclick to prevent XSS
            btn.removeAttribute('onclick');
            
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const postCard = this.closest('.post-card');
                if (postCard) {
                    const postId = parseInt(postCard.dataset.postId, 10);
                    if (postId) {
                        likePost(postId, this);
                    }
                }
            });
        });
    }

    window.likePost = async function(postId, button) {
        // SECURITY FIX #10: Validate postId
        if (!postId || !Number.isInteger(postId) || postId <= 0) {
            showNotification('Invalid post ID', 'error');
            return;
        }

        if (!button) return;

        const isLiked = button.dataset.liked === 'true';

        // Prevent double-click
        button.disabled = true;

        try {
            // SECURITY FIX #11: Include CSRF token
            const response = await fetch(`${CONFIG.apiEndpoint}?action=like_post`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `csrf_token=${encodeURIComponent(getCSRFToken())}&post_id=${postId}`
            });

            // SECURITY FIX #12: Handle rate limiting
            if (response.status === 429) {
                showNotification('Too many actions. Please wait.', 'error');
                button.disabled = false;
                return;
            }

            if (response.status === 403) {
                showNotification('Security validation failed. Please refresh.', 'error');
                button.disabled = false;
                return;
            }

            const data = await response.json();

            if (data.success) {
                if (data.liked) {
                    button.textContent = '❤️ Like';
                    button.dataset.liked = 'true';
                    createParticleBurst(button);
                } else {
                    button.textContent = '🤍 Like';
                    button.dataset.liked = 'false';
                }
                showNotification(data.message, 'success');
            } else {
                showNotification(data.message, 'error');
            }
        } catch (error) {
            console.error('Like error:', error);
            showNotification('Failed to like post', 'error');
        } finally {
            button.disabled = false;
        }
    };

    // Particle burst animation
    function createParticleBurst(element) {
        const rect = element.getBoundingClientRect();
        const particles = 8;

        for (let i = 0; i < particles; i++) {
            const particle = document.createElement('div');
            const angle = (360 / particles) * i;
            const velocity = {
                x: Math.cos(angle * Math.PI / 180) * 5,
                y: Math.sin(angle * Math.PI / 180) * 5
            };

            particle.textContent = '✨';
            particle.style.cssText = `
                position: fixed;
                left: ${rect.left + rect.width / 2}px;
                top: ${rect.top + rect.height / 2}px;
                pointer-events: none;
                font-size: 1.2rem;
                animation: particle-float 0.8s ease-out forwards;
                --tx: ${velocity.x * 30}px;
                --ty: ${velocity.y * 30}px;
            `;

            document.body.appendChild(particle);

            const style = document.createElement('style');
            style.textContent = `
                @keyframes particle-float {
                    0% {
                        opacity: 1;
                        transform: translate(0, 0);
                    }
                    100% {
                        opacity: 0;
                        transform: translate(var(--tx), var(--ty));
                    }
                }
            `;
            document.head.appendChild(style);

            setTimeout(() => particle.remove(), 800);
        }
    }

    // ============================================
    // POST MENU - Owner Controls
    // ============================================

    function initializePostMenu() {
        document.querySelectorAll('.btn-menu').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                togglePostMenu(btn);
            });
        });

        // Close menus when clicking outside
        document.addEventListener('click', () => {
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.style.display = 'none';
            });
        });
    }

    window.togglePostMenu = function(button) {
        const menu = button.nextElementSibling;
        if (menu.style.display === 'none' || menu.style.display === '') {
            menu.style.display = 'block';
        } else {
            menu.style.display = 'none';
        }
    };

    window.deletePost = async function(postId) {
        // SECURITY FIX #13: Validate postId
        postId = parseInt(postId, 10);
        if (!postId || postId <= 0) {
            showNotification('Invalid post ID', 'error');
            return;
        }

        if (!confirm('🗑️ Delete this memory?')) return;

        try {
            // This would be handled by a separate delete endpoint
            // SECURITY FIX #14: Would include CSRF token when implemented
            const response = await fetch(`/delete_post.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `csrf_token=${encodeURIComponent(getCSRFToken())}&post_id=${postId}`
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    // Remove post card from DOM
                    const postCard = document.querySelector(`[data-post-id="${postId}"]`);
                    if (postCard) {
                        postCard.style.animation = 'fadeOut 0.3s ease-out';
                        setTimeout(() => postCard.remove(), 300);
                    }
                    showNotification('Post deleted', 'success');
                } else {
                    showNotification(data.message || 'Failed to delete', 'error');
                }
            }
        } catch (error) {
            console.error('Delete error:', error);
            showNotification('Failed to delete post', 'error');
        }
    };

    // ============================================
    // LAZY LOADING - Performance
    // ============================================

    function initializeIntersectionObserver() {
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src || img.src;
                        observer.unobserve(img);
                    }
                });
            });

            document.querySelectorAll('img[data-src]').forEach(img => {
                observer.observe(img);
            });
        }
    }

    // ============================================
    // CHARACTER COUNTER
    // ============================================

    function initializeCharCounter() {
        const bioInput = document.getElementById('bio-input');
        if (bioInput) {
            bioInput.addEventListener('input', () => {
                const count = bioInput.value.length;
                const charCount = document.getElementById('char-count');
                if (charCount) {
                    charCount.textContent = count;
                    
                    // Warn if approaching limit
                    if (count > 220) {
                        charCount.style.color = '#FF6F61';
                    } else {
                        charCount.style.color = '#718096';
                    }
                }
            });
        }
    }

    // ============================================
    // SECURITY UTILITY FUNCTIONS
    // ============================================

    // SECURITY FIX #15: Check for suspicious content
    function containsSuspiciousContent(text) {
        // Basic XSS pattern detection
        const dangerousPatterns = [
            /<script[^>]*>.*?<\/script>/gi,
            /javascript:/gi,
            /on\w+\s*=/gi,
            /<iframe/gi,
            /<embed/gi,
            /<object/gi
        ];
        
        return dangerousPatterns.some(pattern => pattern.test(text));
    }

    // SECURITY FIX #16: Sanitize text for display (if needed)
    function sanitizeText(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ============================================
    // UTILITY FUNCTIONS
    // ============================================

    // Notification system
    function showNotification(message, type = 'info') {
        // SECURITY FIX #17: Escape message content
        const safeMessage = sanitizeText(message);
        
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.style.cssText = `
            position: fixed;
            top: 100px;
            right: 20px;
            padding: 1rem 1.5rem;
            background: ${type === 'success' ? '#e6f9ed' : type === 'error' ? '#ffeaea' : '#e3f2fd'};
            border: 2px solid ${type === 'success' ? '#10b981' : type === 'error' ? '#FF6F61' : '#2D89EF'};
            border-radius: 12px;
            color: ${type === 'success' ? '#1b7f3a' : type === 'error' ? '#d32f2f' : '#2D89EF'};
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            z-index: 10000;
            animation: notification-slide-in 0.3s ease;
            max-width: 300px;
        `;
        notification.textContent = message; // Use textContent for safety

        document.body.appendChild(notification);

        const style = document.createElement('style');
        style.textContent = `
            @keyframes notification-slide-in {
                from {
                    opacity: 0;
                    transform: translateX(400px);
                }
                to {
                    opacity: 1;
                    transform: translateX(0);
                }
            }
            @keyframes notification-slide-out {
                to {
                    opacity: 0;
                    transform: translateX(400px);
                }
            }
            @keyframes fadeOut {
                to {
                    opacity: 0;
                    transform: scale(0.9);
                }
            }
        `;
        document.head.appendChild(style);

        setTimeout(() => {
            notification.style.animation = 'notification-slide-out 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    // Sound cue for connections (optional)
    function playConnectionSound() {
        try {
            // Simple beep using Web Audio API
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);

            oscillator.frequency.value = 880; // A5 note
            oscillator.type = 'sine';

            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.1);

            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.1);
        } catch (error) {
            // Silently fail if audio not supported
            console.log('Audio not supported');
        }
    }

    // Message function (stub)
    window.sendMessage = function(userId) {
        // SECURITY FIX #18: Validate userId
        userId = parseInt(userId, 10);
        if (!userId || userId <= 0) return;
        
        showNotification('💬 Messaging coming soon', 'info');
    };

    // Share function (stub)
    window.sharePost = function(postId) {
        // SECURITY FIX #19: Validate postId
        postId = parseInt(postId, 10);
        if (!postId || postId <= 0) {
            showNotification('Invalid post', 'error');
            return;
        }

        if (navigator.share) {
            navigator.share({
                title: 'CultureConnect',
                text: 'Check out this amazing cultural story!',
                url: `/post.php?id=${postId}`
            }).catch(() => {
                // User cancelled or share failed
            });
        } else {
            // Fallback: copy to clipboard
            const url = `${window.location.origin}/post.php?id=${postId}`;
            navigator.clipboard.writeText(url).then(() => {
                showNotification('🔗 Share link copied', 'success');
            }).catch(() => {
                showNotification('Failed to copy link', 'error');
            });
        }
    };

    // Edit cover (stub)
    window.editCover = function() {
        showNotification('🖼️ Cover upload coming soon', 'info');
    };

    console.log('%c🌍 CultureConnect Soul Mirror\n%cWhere identity becomes presence', 
        'font-size: 18px; font-weight: bold; color: #2D89EF;',
        'font-size: 12px; color: #718096; font-style: italic;');

})();