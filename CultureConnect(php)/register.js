// register.js - Where Interactions Become Emotion
// CultureConnect Registration Page - Living Magic

(function() {
    'use strict';

    // ============================================
    // INITIALIZATION
    // ============================================
    document.addEventListener('DOMContentLoaded', () => {
        initializeParticles();
        initializeAOS();
        initializeFormAnimations();
        initializeAvatarUpload();
        initializeConnectionSparks();
        initializeFormValidation();
        console.log('🌍 CultureConnect Registration: Soul awakened');
    });

    // ============================================
    // FLOATING PARTICLES
    // ============================================
    function initializeParticles() {
        const container = document.getElementById('particles');
        if (!container) return;

        const particleCount = window.innerWidth > 768 ? 40 : 20;
        
        for (let i = 0; i < particleCount; i++) {
            createParticle(container);
        }
    }

    function createParticle(container) {
        const particle = document.createElement('div');
        const size = Math.random() * 4 + 2;
        const x = Math.random() * 100;
        const duration = Math.random() * 20 + 15;
        const delay = Math.random() * 5;
        const colors = [
            'rgba(45, 137, 239, 0.6)',
            'rgba(255, 111, 97, 0.5)',
            'rgba(252, 228, 236, 0.7)',
            'rgba(227, 242, 253, 0.6)'
        ];
        const color = colors[Math.floor(Math.random() * colors.length)];
        
        particle.style.cssText = `
            position: absolute;
            width: ${size}px;
            height: ${size}px;
            background: radial-gradient(circle, ${color} 0%, transparent 70%);
            border-radius: 50%;
            left: ${x}%;
            bottom: -20px;
            animation: rise ${duration}s linear ${delay}s infinite;
            pointer-events: none;
        `;
        
        container.appendChild(particle);
    }

    // Add keyframe for particle rise
    const particleStyle = document.createElement('style');
    particleStyle.textContent = `
        @keyframes rise {
            0% {
                transform: translateY(0) translateX(0) scale(1) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100vh) translateX(${Math.random() * 100 - 50}px) scale(0.3) rotate(360deg);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(particleStyle);

    // ============================================
    // ANIMATE ON SCROLL (AOS)
    // ============================================
    function initializeAOS() {
        const observerOptions = {
            threshold: 0.15,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('aos-animate');
                }
            });
        }, observerOptions);

        document.querySelectorAll('[data-aos]').forEach(el => {
            observer.observe(el);
        });
    }

    // ============================================
    // FORM ANIMATIONS
    // ============================================
    function initializeFormAnimations() {
        const inputs = document.querySelectorAll('.form-group input, .form-group select');
        
        inputs.forEach(input => {
            // Focus animations
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
                createFocusRipple(this);
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('focused');
            });
            
            // Real-time validation feedback
            input.addEventListener('input', function() {
                validateField(this);
            });
        });
    }

    function createFocusRipple(element) {
        const ripple = document.createElement('div');
        const rect = element.getBoundingClientRect();
        
        ripple.style.cssText = `
            position: absolute;
            width: 20px;
            height: 20px;
            background: rgba(45, 137, 239, 0.3);
            border-radius: 50%;
            left: 10px;
            top: 50%;
            transform: translate(-50%, -50%);
            animation: focus-ripple 0.6s ease-out;
            pointer-events: none;
        `;
        
        element.parentElement.appendChild(ripple);
        setTimeout(() => ripple.remove(), 600);
    }

    const focusRippleStyle = document.createElement('style');
    focusRippleStyle.textContent = `
        @keyframes focus-ripple {
            to {
                width: 100px;
                height: 100px;
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(focusRippleStyle);

    // ============================================
    // AVATAR UPLOAD
    // ============================================
    function initializeAvatarUpload() {
        const avatarPreview = document.getElementById('avatarPreview');
        const avatarInput = document.getElementById('avatar');
        
        if (!avatarPreview || !avatarInput) return;
        
        avatarPreview.addEventListener('click', () => {
            avatarInput.click();
        });
        
        avatarInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            // Validate file
            if (!file.type.startsWith('image/')) {
                showNotification('Please select an image file', 'error');
                return;
            }
            
            if (file.size > 2 * 1024 * 1024) {
                showNotification('Image must be less than 2MB', 'error');
                return;
            }
            
            // Preview image
            const reader = new FileReader();
            reader.onload = function(e) {
                avatarPreview.innerHTML = `<img src="${e.target.result}" alt="Avatar preview">`;
                avatarPreview.style.animation = 'avatarPop 0.4s ease';
            };
            reader.readAsDataURL(file);
        });
    }

    const avatarStyle = document.createElement('style');
    avatarStyle.textContent = `
        @keyframes avatarPop {
            0% { transform: scale(0.8); opacity: 0; }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); opacity: 1; }
        }
    `;
    document.head.appendChild(avatarStyle);

    // ============================================
    // CONNECTION SPARKS
    // ============================================
    function initializeConnectionSparks() {
        const sparksContainer = document.getElementById('sparks');
        if (!sparksContainer) return;
        
        setInterval(() => {
            if (Math.random() > 0.7) {
                createConnectionSpark(sparksContainer);
            }
        }, 3000);
    }

    function createConnectionSpark(container) {
        const spark = document.createElement('div');
        const x = Math.random() * 100;
        const y = Math.random() * 100;
        const size = Math.random() * 8 + 4;
        const symbols = ['✨', '💫', '⭐', '🌟', '💖', '🌍', '🤝'];
        const symbol = symbols[Math.floor(Math.random() * symbols.length)];
        
        spark.textContent = symbol;
        spark.style.cssText = `
            position: absolute;
            left: ${x}%;
            top: ${y}%;
            font-size: ${size}px;
            animation: sparkle 2s ease-out forwards;
            pointer-events: none;
        `;
        
        container.appendChild(spark);
        setTimeout(() => spark.remove(), 2000);
    }

    const sparkleStyle = document.createElement('style');
    sparkleStyle.textContent = `
        @keyframes sparkle {
            0% {
                opacity: 0;
                transform: scale(0) rotate(0deg);
            }
            50% {
                opacity: 1;
                transform: scale(1.2) rotate(180deg);
            }
            100% {
                opacity: 0;
                transform: scale(0.8) rotate(360deg) translateY(-50px);
            }
        }
    `;
    document.head.appendChild(sparkleStyle);

    // ============================================
    // FORM VALIDATION
    // ============================================
    function initializeFormValidation() {
        const form = document.getElementById('registerForm');
        if (!form) return;
        
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('.btn-submit');
            
            // Validate all fields
            const inputs = this.querySelectorAll('input[required], select[required]');
            let isValid = true;
            
            inputs.forEach(input => {
                if (!validateField(input)) {
                    isValid = false;
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                showNotification('Please fill all required fields correctly', 'error');
                return;
            }
            
            // Check password match
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                showNotification('Passwords do not match', 'error');
                return;
            }
            
            // Add loading state
            submitBtn.classList.add('loading');
            submitBtn.querySelector('.btn-text').textContent = 'Creating your story...';
        });
    }

    function validateField(field) {
        if (!field) return true;
        
        let isValid = true;
        const value = field.value.trim();
        
        // Required check
        if (field.hasAttribute('required') && !value) {
            isValid = false;
        }
        
        // Email validation
        if (field.type === 'email' && value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            isValid = emailRegex.test(value);
        }
        
        // Password length
        if (field.type === 'password' && value && value.length < 8) {
            isValid = false;
        }
        
        // Visual feedback
        if (isValid) {
            field.style.borderColor = '#4caf50';
        } else if (value) {
            field.style.borderColor = '#FF6F61';
        }
        
        return isValid;
    }

    // ============================================
    // PASSWORD TOGGLE
    // ============================================
    window.togglePassword = function(fieldId) {
        const field = document.getElementById(fieldId);
        const toggle = field.nextElementSibling.nextElementSibling;
        
        if (field.type === 'password') {
            field.type = 'text';
            toggle.textContent = '🙈';
        } else {
            field.type = 'password';
            toggle.textContent = '👁️';
        }
    };

    // ============================================
    // NOTIFICATIONS
    // ============================================
    function showNotification(message, type = 'info') {
        // Remove existing notifications
        document.querySelectorAll('.toast-notification').forEach(n => n.remove());
        
        const notification = document.createElement('div');
        notification.className = `toast-notification toast-${type}`;
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 16px;
            background: ${type === 'error' ? '#ffeaea' : '#e6f9ed'};
            color: ${type === 'error' ? '#d32f2f' : '#1b7f3a'};
            border: 1.5px solid ${type === 'error' ? '#d32f2f' : '#1b7f3a'};
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 10000;
            animation: slideInRight 0.3s ease, fadeOut 0.3s ease 2.7s;
            font-weight: 500;
            max-width: 300px;
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    const toastStyle = document.createElement('style');
    toastStyle.textContent = `
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
        
        @keyframes fadeOut {
            to { opacity: 0; }
        }
    `;
    document.head.appendChild(toastStyle);

    // ============================================
    // BUTTON INTERACTIONS
    // ============================================
    document.querySelectorAll('.btn-submit').forEach(btn => {
        btn.addEventListener('mouseenter', function(e) {
            const rect = this.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            const ripple = document.createElement('span');
            ripple.style.cssText = `
                position: absolute;
                left: ${x}px;
                top: ${y}px;
                width: 0;
                height: 0;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.5);
                transform: translate(-50%, -50%);
                animation: button-ripple 0.6s ease-out;
                pointer-events: none;
            `;
            
            this.appendChild(ripple);
            setTimeout(() => ripple.remove(), 600);
        });
    });

    const buttonRippleStyle = document.createElement('style');
    buttonRippleStyle.textContent = `
        @keyframes button-ripple {
            to {
                width: 200px;
                height: 200px;
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(buttonRippleStyle);

    // ============================================
    // AUTO-DISMISS MESSAGE
    // ============================================
    const message = document.getElementById('message');
    if (message) {
        setTimeout(() => {
            message.style.animation = 'slideUp 0.3s ease forwards';
            setTimeout(() => message.remove(), 300);
        }, 5000);
    }

    const slideUpStyle = document.createElement('style');
    slideUpStyle.textContent = `
        @keyframes slideUp {
            to {
                opacity: 0;
                transform: translateY(-20px);
            }
        }
    `;
    document.head.appendChild(slideUpStyle);

    // ============================================
    // CONSOLE EASTER EGG
    // ============================================
    console.log(
        '%c🌍 Welcome to CultureConnect',
        'font-size: 24px; font-weight: bold; background: linear-gradient(135deg, #2D89EF 0%, #764BA2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;'
    );
    console.log(
        '%cYour story is about to meet the world 💫',
        'font-size: 14px; color: #2D89EF; font-style: italic;'
    );

})();