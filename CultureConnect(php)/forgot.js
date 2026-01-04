/**
 * CultureConnect Forgot Password - Client-side Logic
 */

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('forgotForm');
    const emailInput = document.getElementById('email');
    const submitButton = form.querySelector('button[type="submit"]');
    const messageBox = document.getElementById('messageBox');

    /**
     * Validate email format
     */
    function isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    /**
     * Show message
     */
    function showMessage(message, type = 'info') {
        messageBox.textContent = message;
        messageBox.className = `message-box ${type}`;
        messageBox.style.display = 'block';
        messageBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    /**
     * Handle form submission
     */
    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const email = emailInput.value.trim().toLowerCase();

        // Client-side validation
        if (!email) {
            showMessage('Please enter your email address.', 'error');
            emailInput.focus();
            return;
        }

        if (!isValidEmail(email)) {
            showMessage('Please enter a valid email address.', 'error');
            emailInput.focus();
            return;
        }

        // Disable button and show loading state
        submitButton.setAttribute('aria-busy', 'true');
        submitButton.disabled = true;

        try {
            const formData = new FormData();
            formData.append('email', email);

            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (response.ok) {
                showMessage(data.message || '📬 If your email is part of our circle, a memory link will be sent.', 'success');
                form.reset();
                emailInput.focus();
            } else {
                showMessage(data.message || 'Something went wrong. Please try again.', 'error');
            }

        } catch (error) {
            console.error('Error:', error);
            showMessage('Network error. Please check your connection and try again.', 'error');
        } finally {
            // Re-enable button
            submitButton.setAttribute('aria-busy', 'false');
            submitButton.disabled = false;
        }
    });

    /**
     * Real-time email validation feedback
     */
    emailInput.addEventListener('blur', function() {
        const email = this.value.trim();
        if (email && !isValidEmail(email)) {
            this.setAttribute('aria-invalid', 'true');
        } else {
            this.removeAttribute('aria-invalid');
        }
    });

    emailInput.addEventListener('focus', function() {
        messageBox.style.display = 'none';
    });

    /**
     * Clear message when user starts typing
     */
    emailInput.addEventListener('input', function() {
        if (messageBox.classList.contains('error')) {
            messageBox.style.display = 'none';
        }
    });

    // Set initial focus
    emailInput.focus();
});

/**
 * Particles background effect (optional, lightweight)
 */
(function() {
    // Only initialize on larger screens for performance
    if (window.innerWidth < 768) return;

    const canvas = document.createElement('canvas');
    canvas.id = 'particles-canvas';
    canvas.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        opacity: 0.4;
        z-index: 1;
    `;

    // Don't add if already exists
    if (!document.getElementById('particles-canvas')) {
        document.body.insertBefore(canvas, document.body.firstChild);

        const ctx = canvas.getContext('2d');
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;

        const particles = [];
        const particleCount = 30;

        class Particle {
            constructor() {
                this.x = Math.random() * canvas.width;
                this.y = Math.random() * canvas.height;
                this.size = Math.random() * 2 + 1;
                this.speedX = (Math.random() - 0.5) * 0.5;
                this.speedY = (Math.random() - 0.5) * 0.5;
                this.opacity = Math.random() * 0.5 + 0.2;
            }

            update() {
                this.x += this.speedX;
                this.y += this.speedY;

                // Wrap around screen
                if (this.x > canvas.width) this.x = 0;
                if (this.x < 0) this.x = canvas.width;
                if (this.y > canvas.height) this.y = 0;
                if (this.y < 0) this.y = canvas.height;
            }

            draw() {
                ctx.fillStyle = `rgba(255, 255, 255, ${this.opacity})`;
                ctx.beginPath();
                ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                ctx.fill();
            }
        }

        // Initialize particles
        for (let i = 0; i < particleCount; i++) {
            particles.push(new Particle());
        }

        function animate() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            particles.forEach(particle => {
                particle.update();
                particle.draw();
            });

            requestAnimationFrame(animate);
        }

        animate();

        // Handle window resize
        window.addEventListener('resize', function() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        });
    }
})();