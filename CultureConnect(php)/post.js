/**
 * ===========================================
 * CultureConnect - Single Post View (post.js)
 * High-performance Parallax and Interaction Logic
 * ===========================================
 */

(function() {
    'use strict';
    
    // DOM Elements - Only the essentials for interaction/performance
    const elements = {
        hero: document.querySelector('.hero-image-container'),
        authorCard: document.getElementById('authorCard'), // For second-layer parallax
        // ... (other elements like shareModal, saveBtn) ...
    };

    // --- High-Performance Parallax Effect ---
    // Uses translate3d to force GPU rendering, avoiding jank.
    function handleParallax() {
        const scrolled = window.scrollY;
        
        // Hero: Scrolls very slowly (0.2x) to create deep background effect
        if (elements.hero) {
            elements.hero.style.transform = `translate3d(0, ${scrolled * 0.2}px, 0)`; 
        }
        
        // Author Card: Scrolls slightly slower than content (0.8x of the remaining scroll)
        if (elements.authorCard) {
            // Subtracting the scroll amount by a smaller factor keeps it 'floating'
            const offset = scrolled * 0.8; 
            elements.authorCard.style.transform = `translate3d(0, ${offset}px, 0)`;
        }
    }
    
    // --- Security-Aware Interaction Setup ---
    function setupInteractions() {
        // ... (Modal/UI setup for LIKE/SHARE/SAVE as before) ...
        
        // Comment Button: Must grab CSRF token before sending AJAX request
        document.querySelector('.btn-submit-comment')?.addEventListener('click', (e) => {
            const commentText = e.target.closest('.comment-input-shrine').querySelector('textarea').value;
            const csrfToken = e.target.closest('.comment-input-shrine').querySelector('input[name="csrf_token"]').value;

            // CRITICAL: AJAX request for commenting MUST include the CSRF token
            // and the server MUST validate it.
            if (commentText.length > 0) {
                console.log(`Sending secure comment with CSRF Token: ${csrfToken}`);
                // axios.post('/api/comment', { post_id: POST_ID, content: commentText, csrf_token: csrfToken });
            }
        });
    }

    // --- Main Listener ---
    if (elements.hero || elements.authorCard) {
        window.addEventListener('scroll', () => {
            window.requestAnimationFrame(handleParallax);
        }, { passive: true }); // Use passive listener for smooth scrolling
    }
    
    setupInteractions();
})();