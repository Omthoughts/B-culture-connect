/**
 * CultureConnect Reset Password - Client-side Logic
 */

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('resetForm');
    const passwordInput = document.getElementById('password');
    const passwordConfirmInput = document.getElementById('password_confirm');
    const submitButton = form.querySelector('button[type="submit"]');
    const messageBox = document.getElementById('messageBox');

    /**
     * Password strength requirements
     */
    const requirements = {
        minLength: {
            regex: /.{10,}/,
            text: 'Min 10 characters',
            element: null
        },
        uppercase: {
            regex: /[A-Z]/,
            text: 'Uppercase letter',
            element: null
        },
        lowercase: {
            regex: /[a-z]/,
            text: 'Lowercase letter',
            element: null
        },
        digit: {
            regex: /\d/,
            text: 'Number',
            element: null
        },
        symbol: {
            regex: /[!@#$%^&*()_\-+=\[\]{};:'"",.<>?\/\\|`~]/,
            text: 'Symbol (!@#$%^&*)',
            element: null
        }
    };

    /**
     * Check password strength
     */
    function checkPasswordStrength(password) {
        const strength = {
            score: 0,
            met: [],
            unmet: []
        };

        Object.entries(requirements).forEach(([key, req]) => {
            if (req.regex.test(password)) {
                strength.score++;
                strength.met.push(key);
            } else {
                strength.unmet.push(key);
            }
        });

        return strength;
    }

    /**
     * Update password strength indicator
     */
    function updateStrengthIndicator(password) {
        const strength = checkPasswordStrength(password);
        const requirements_el = document.getElementById('password-requirements');

        if (!requirements_el) return;

        // Clear and rebuild
        requirements_el.innerHTML = '';

        Object.entries(requirements).forEach(([key, req]) => {
            const item = document.createElement('span');
            item.className = 'requirement-item';

            const isMet = strength.met.includes(key);
            item.setAttribute('data-met', isMet ? 'true' : 'false');
            item.textContent = (isMet ? '✓ ' : '✗ ') + req.text;
            item.style.cssText = `
                display: inline-block;
                font-size: 0.75rem;
                padding: 0.25rem 0.5rem;
                margin-right: 0.5rem;
                margin-bottom: 0.25rem;
                border-radius: 4px;
                background: ${isMet ? 'rgba(16, 185, 129, 0.15)' : 'rgba(239, 68, 68, 0.15)'};
                color: ${isMet ? '#10b981' : '#ef4444'};
                transition: all 0.2s ease;
                border: 1px solid ${isMet ? '#10b981' : '#ef4444'};
            `;

            requirements_el.appendChild(item);
        });

        // Add strength meter
        const meter = document.createElement('div');
        meter.style.cssText = `
            width: 100%;
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            margin-top: 0.5rem;
            overflow: hidden;
        `;

        const meterFill = document.createElement('div');
        const strengthPercent = (strength.score / 5) * 100;
        let color = '#ef4444';

        if (strength.score === 5) {
            color = '#10b981';
        } else if (strength.score >= 3) {
            color = '#f59e0b';
        }

        meterFill.style.cssText = `
            width: ${strengthPercent}%;
            height: 100%;
            background: ${color};
            transition: width 0.3s ease;
        `;

        meter.appendChild(meterFill);
        requirements_el.appendChild(meter);
    }

    /**
     * Validate form
     */
    function validateForm() {
        const password = passwordInput.value;
        const passwordConfirm = passwordConfirmInput.value;
        const strength = checkPasswordStrength(password);
        const errors = [];

        if (!password) {
            errors.push('New key is required.');
        } else if (strength.unmet.length > 0) {
            errors.push('Password does not meet all requirements.');
        }

        if (!passwordConfirm) {
            errors.push('Please confirm your new key.');
        } else if (password !== passwordConfirm) {
            errors.push('Keys do not match.');
        }

        return { valid: errors.length === 0, errors };
    }

    /**
     * Show message
     */
    function showMessage(message, type = 'info', errors = []) {
        if (Array.isArray(message)) {
            errors = message;
            message = 'Please correct the following:';
            type = 'error';
        }

        messageBox.innerHTML = '';

        const messageText = document.createElement('div');
        messageText.textContent = message;
        messageBox.appendChild(messageText);

        if (errors.length > 0) {
            const list = document.createElement('ul');
            errors.forEach(error => {
                const item = document.createElement('li');
                item.textContent = error;
                list.appendChild(item);
            });
            messageBox.appendChild(list);
        }

        messageBox.className = `message-box ${type}`;
        messageBox.style.display = 'block';
        messageBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    /**
     * Handle form submission
     */
    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        // Validate
        const validation = validateForm();
        if (!validation.valid) {
            showMessage('Please correct the following:', 'error', validation.errors);
            return;
        }

        // Disable button and show loading state
        submitButton.setAttribute('aria-busy', 'true');
        submitButton.disabled = true;

        try {
            const formData = new FormData(form);

            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (response.ok && data.success) {
                showMessage(data.message || '🔓 Your key has been renewed. Welcome back, Soul Traveler.', 'success');

                // Redirect after brief delay
                setTimeout(() => {
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    }
                }, 2000);

            } else {
                const errors = data.errors || [];
                showMessage(data.message || 'Could not reset password. Please try again.', 'error', errors);
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
     * Real-time password validation
     */
    passwordInput.addEventListener('input', function() {
        updateStrengthIndicator(this.value);
        messageBox.style.display = 'none';
    });

    passwordConfirmInput.addEventListener('input', function() {
        if (passwordInput.value && this.value) {
            if (passwordInput.value === this.value) {
                this.setAttribute('aria-invalid', 'false');
            } else {
                this.setAttribute('aria-invalid', 'true');
            }
        }
        messageBox.style.display = 'none';
    });

    /**
     * Show/hide password toggle
     */
    function addPasswordToggle(inputElement) {
        const toggleButton = document.createElement('button');
        toggleButton.type = 'button';
        toggleButton.className = 'password-toggle';
        toggleButton.setAttribute('aria-label', 'Toggle password visibility');
        toggleButton.style.cssText = `
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.1rem;
            padding: 0.5rem;
            color: #6b7280;
        `;

        let isVisible = false;

        toggleButton.addEventListener('click', function(e) {
            e.preventDefault();
            isVisible = !isVisible;
            inputElement.type = isVisible ? 'text' : 'password';
            toggleButton.textContent = isVisible ? '👁️' : '👁️‍🗨️';
        });

        toggleButton.textContent = '👁️‍🗨️';

        const wrapper = document.createElement('div');
        wrapper.style.cssText = `
            position: relative;
            display: flex;
            align-items: center;
        `;

        inputElement.parentNode.insertBefore(wrapper, inputElement);
        wrapper.appendChild(inputElement);
        wrapper.appendChild(toggleButton);
    }

    // Initialize password toggles
    addPasswordToggle(passwordInput);
    addPasswordToggle(passwordConfirmInput);

    // Initial strength check
    if (passwordInput.value) {
        updateStrengthIndicator(passwordInput.value);
    }

    // Set initial focus
    passwordInput.focus();
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