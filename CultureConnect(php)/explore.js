/**
 * ============================================
 * CultureConnect Explore - Interaction Spark
 * For saving, sharing, and feeling
 * ============================================
 */

(function() {
    'use strict';

    // This file assumes profile.js (with likePost, sharePost, showNotification)
    // is already loaded.

    /**
     * Handle the 'Save' (Bookmark) button click.
     * This is a UI-only stub. You would add an AJAX call here.
     */
    window.savePost = function(postId, button) {
        if (!button) return;

        const isSaved = button.dataset.saved === 'true';

        if (isSaved) {
            // Un-save
            button.dataset.saved = 'false';
            button.innerHTML = '<span>🔖</span> <span>Save</span>';
            // Use the notification function from profile.js
            if (window.showNotification) {
                window.showNotification('Story removed from your collection', 'info');
            }
        } else {
            // Save
            button.dataset.saved = 'true';
            button.innerHTML = '<span>✅</span> <span>Saved</span>';
            if (window.showNotification) {
                window.showNotification('Story saved to your collection ✨', 'success');
            }
        }

        // TODO: Add your AJAX (fetch) call here to update the database
        // fetch(`/api/save_post.php?action=toggle_save`, {
        //     method: 'POST',
        //     headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        //     body: `post_id=${postId}`
        // }).then(res => res.json()).then(data => {
        //     if (!data.success) {
        //         // Revert on error
        //         button.dataset.saved = isSaved.toString();
        //         window.showNotification('Could not save post', 'error');
        //     }
        // });
    };

    /**
     * Add subtle hover effect to post cards
     */
    document.querySelectorAll('.post-card').forEach(card => {
        const image = card.querySelector('.post-image');
        if (!image) return;

        card.addEventListener('mouseenter', () => {
            // CSS handles the :hover, but we could add JS-driven
            // effects here if we wanted (like the parallax in the mock)
        });

        card.addEventListener('mouseleave', () => {
            // Clear effects
        });
    });

    console.log('🌍 Explore: The mirror of desire is active.');

})();