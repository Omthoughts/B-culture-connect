// login.js - Where Souls Return Home
// Sacred Geometry • Divine Timing • Eternal Connection

(function() {
    'use strict';

    // ============================================
    // INITIALIZATION
    // ============================================
    document.addEventListener('DOMContentLoaded', () => {
        initializeGlyphCanvas();
        initializeParallax();
        initializeFormAnimations();
        initializeTimeGreeting();
        initializeCulturalScroll();
        console.log('🌍 CultureConnect Login: The gateway opens');
    });

    // ============================================
    // CULTURAL GLYPHS CANVAS
    // ============================================
    function initializeGlyphCanvas() {
        const canvas = document.getElementById('glyphCanvas');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;

        const glyphs = ['🌍', '🎭', '🌸', '🎨', '🎵', '💫', '✨', '🌟', '🤝', '💖', '🏛️', '📚', '🍲', '💃'];
        const particles = [];

        class Glyph {
            constructor() {
                this.reset();
                this.y = Math.random() * canvas.height;
                this.opacity = Math.random() * 0.5 + 0.3;
            }

            reset() {
                this.x = Math.random() * canvas.width;
                this.y = -50;
                this.symbol = glyphs[Math.floor(Math.random() * glyphs.length)];
                this.speed = Math.random() * 0.5 + 0.2;
                this.size = Math.random() * 20 + 15;
                this.rotation = Math.random() * 360;
                this.rotationSpeed = (Math.random() - 0.5) * 0.5;
            }

            update() {
                this.y += this.speed;
                this.rotation += this.rotationSpeed;

                if (this.y > canvas.height + 50) {
                    this.reset();
                }
            }

            draw() {
                ctx.save();
                ctx.globalAlpha = this.opacity;
                ctx.translate(this.x, this.y);
                ctx.rotate(this.rotation * Math.PI / 180);
                ctx.font = `${this.size}px Arial`;
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(this.symbol, 0, 0);
                ctx.restore();
            }
        }

        // Create particles
        const particleCount = window.innerWidth > 768 ? 30 : 15;
        for (let i = 0; i < particleCount; i++) {
            particles.push(new Glyph());
        }

        function animate() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            particles.forEach(glyph => {
                glyph.update();
                glyph.draw();
            });

            requestAnimationFrame(animate);
        }

        animate();

        // Handle resize
        window.addEventListener('resize', () => {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        });
    }

    // ============================================
    // PARALLAX MOUSE EFFECT
    // ============================================
    function initializeParallax() {
        const container = document.getElementById('parallaxContainer');
        if (!container) return;

        const orbs = container.querySelectorAll('.orb');

        document.addEventListener('mousemove', (e) => {
            const mouseX = e.clientX / window.innerWidth - 0.5;
            const mouseY = e.clientY / window.innerHeight - 0.5;

            orbs.forEach((orb, index) => {
                const speed = (index + 1) * 20;
                const x = mouseX * speed;
                const y = mouseY * speed;

                orb.style.transform = `translate(${x}px, ${y}px)`;
            });
        });
    }

    // ============================================
    // FORM ANIMATIONS
    // ============================================
    function initializeFormAnimations() {
        const inputs = document.querySelectorAll('.form-input');

        inputs.forEach(input => {
            // Focus ripple effect
            input.addEventListener('focus', function() {
                createFocusRipple(this);
            });

            // Real-time validation
            input.addEventListener('input', function() {
                validateField(this);
            });
        });

        // Form submission with mindful delay
        const form = document.getElementById('loginForm');
        if (form) {
            form.addEventListener('submit', handleLogin);
        }
    }

    function createFocusRipple(element) {
        const ripple = document.createElement('div');
        ripple.style.cssText = `
            position: absolute;
            width: 40px;
            height: 40px;
            background: rgba(45, 137, 239, 0.2);
            border-radius: 50%;
            left: 15px;
            top: 50%;
            transform: translate(-50%, -50%);
            animation: focusRipple 0.8s ease-out;
            pointer-events: none;
        `;

        element.parentElement.appendChild(ripple);
        setTimeout(() => ripple.remove(), 800);
    }

    const rippleStyle = document.createElement('style');
    rippleStyle.textContent = `
        @keyframes focusRipple {
            to {
                width: 120px;
                height: 120px;
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(rippleStyle);

    function validateField(field) {
        const value = field.value.trim();
        
        if (field.type === 'email' || field.id === 'identifier') {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const isEmail = emailRegex.test(value);
            const isUsername = value.length >= 3;
            
            if (value && (isEmail || isUsername)) {
                field.style.borderColor = '#4caf50';
            } else if (value) {
                field.style.borderColor = '#FF6F61';
            }
        }

        if (field.type === 'password' && value) {
            if (value.length >= 8) {
                field.style.borderColor = '#4caf50';
            } else {
                field.style.borderColor = '#FF6F61';
            }
        }
    }

    function handleLogin(e) {
        const submitBtn = e.target.querySelector('.btn-login');
        const btnText = submitBtn.querySelector('.btn-text');
        
        // Add loading state
        submitBtn.classList.add('loading');
        btnText.textContent = 'Opening the gateway...';
        
        // Mindful delay - builds emotional resonance
        setTimeout(() => {
            btnText.textContent = 'Welcome home...';
        }, 1000);

        // Form will submit normally after the delay
    }

    // ============================================
    // PASSWORD TOGGLE
    // ============================================
    window.togglePassword = function() {
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('toggleIcon');

        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.textContent = '🙈';
            toggleIcon.style.animation = 'bounce 0.5s ease';
        } else {
            passwordInput.type = 'password';
            toggleIcon.textContent = '👁️';
            toggleIcon.style.animation = 'bounce 0.5s ease';
        }
    };

    const bounceStyle = document.createElement('style');
    bounceStyle.textContent = `
        @keyframes bounce {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.3); }
        }
    `;
    document.head.appendChild(bounceStyle);

    // ============================================
    // TIME-AWARE GREETING
    // ============================================
    function initializeTimeGreeting() {
        const hour = new Date().getHours();
        const heroTagline = document.querySelector('.hero-tagline');
        
        if (heroTagline) {
            // Add personalized time-based message
            const greetings = {
                dawn: "The world awakens with your return",
                morning: "The morning light welcomes you home",
                afternoon: "Your presence brightens the afternoon",
                evening: "The evening embraces your soul",
                night: "The stars witness your return"
            };

            let timeMessage = greetings.morning;
            
            if (hour >= 5 && hour < 8) timeMessage = greetings.dawn;
            else if (hour >= 8 && hour < 12) timeMessage = greetings.morning;
            else if (hour >= 12 && hour < 17) timeMessage = greetings.afternoon;
            else if (hour >= 17 && hour < 21) timeMessage = greetings.evening;
            else timeMessage = greetings.night;

            // Add subtle animation
            setTimeout(() => {
                heroTagline.style.animation = 'fadeIn 1s ease';
            }, 500);
        }
    }

    // ============================================
    // CULTURAL SCROLL INTERACTION
    // ============================================
    function initializeCulturalScroll() {
        const scrollItems = document.querySelectorAll('.scroll-item');
        
        scrollItems.forEach((item, index) => {
            item.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.5) rotate(15deg)';
                this.style.opacity = '1';
                
                // Create sparkle effect
                createSparkle(this);
            });

            item.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1) rotate(0deg)';
                this.style.opacity = '0.6';
            });
        });
    }

    function createSparkle(element) {
        const sparkle = document.createElement('div');
        const rect = element.getBoundingClientRect();
        
        sparkle.textContent = '✨';
        sparkle.style.cssText = `
            position: fixed;
            left: ${rect.left + rect.width / 2}px;
            top: ${rect.top}px;
            font-size: 1.5rem;
            pointer-events: none;
            animation: sparkleFloat 1s ease-out forwards;
            z-index: 10000;
        `;

        document.body.appendChild(sparkle);
        setTimeout(() => sparkle.remove(), 1000);
    }

    const sparkleStyle = document.createElement('style');
    sparkleStyle.textContent = `
        @keyframes sparkleFloat {
            0% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
            100% {
                opacity: 0;
                transform: translateY(-50px) scale(1.5);
            }
        }
    `;
    document.head.appendChild(sparkleStyle);

    // ============================================
    // AUTO-DISMISS MESSAGE
    // ============================================
    const message = document.getElementById('message');
    if (message) {
        setTimeout(() => {
            message.style.animation = 'fadeOut 0.5s ease forwards';
            setTimeout(() => message.remove(), 500);
        }, 5000);
    }

    const fadeStyle = document.createElement('style');
    fadeStyle.textContent = `
        @keyframes fadeOut {
            to {
                opacity: 0;
                transform: translateY(-20px);
            }
        }
    `;
    document.head.appendChild(fadeStyle);

    // ============================================
    // SOCIAL LOGIN ANIMATIONS
    // ============================================
    const socialBtns = document.querySelectorAll('.social-btn');
    socialBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const btnText = this.textContent.trim();
            showNotification(`${btnText} login coming soon! 🌍`, 'info');
        });
    });

    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            background: ${type === 'info' ? '#E3F2FD' : '#ffeaea'};
            color: ${type === 'info' ? '#2D89EF' : '#d32f2f'};
            border: 2px solid ${type === 'info' ? '#2D89EF' : '#d32f2f'};
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            z-index: 10000;
            animation: slideIn 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            font-weight: 500;
        `;
        notification.textContent = message;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.animation = 'slideOut 0.4s ease forwards';
            setTimeout(() => notification.remove(), 400);
        }, 3000);
    }

    const notifStyle = document.createElement('style');
    notifStyle.textContent = `
        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        @keyframes slideOut {
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(notifStyle);

    // ============================================
    // REMEMBER ME PERSISTENCE
    // ============================================
    const rememberCheckbox = document.getElementById('remember');
    const identifierInput = document.getElementById('identifier');

    // Load saved username if exists
    if (rememberCheckbox && identifierInput) {
        const savedUsername = localStorage.getItem('cc_remember_user');
        if (savedUsername) {
            identifierInput.value = savedUsername;
            rememberCheckbox.checked = true;
        }

        rememberCheckbox.addEventListener('change', function() {
            if (!this.checked) {
                localStorage.removeItem('cc_remember_user');
            }
        });

        // Save on successful login (you'd trigger this from PHP success)
        window.saveRememberedUser = function(username) {
            if (rememberCheckbox.checked) {
                localStorage.setItem('cc_remember_user', username);
            }
        };
    }

    // ============================================
    // CONSOLE EASTER EGG
    // ============================================
    console.log(
        '%c🌍 Welcome Home, Soul Traveler',
        'font-size: 24px; font-weight: bold; background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; padding: 10px;'
    );
    console.log(
        '%cThe circle is incomplete without you 💫',
        'font-size: 16px; color: #2D89EF; font-style: italic; padding: 5px;'
    );
    console.log(
        '%c"Connection is the language of the soul"',
        'font-size: 14px; color: #FF6F61; padding: 5px;'
    );

    // ============================================
    // PERFORMANCE OPTIMIZATION
    // ============================================
    let ticking = false;
    window.addEventListener('scroll', () => {
        if (!ticking) {
            window.requestAnimationFrame(() => {
                ticking = false;
            });
            ticking = true;
        }
    });

})();