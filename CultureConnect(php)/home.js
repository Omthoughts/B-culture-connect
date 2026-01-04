// home.js - CultureConnect Homepage Interactions
// Where code becomes poetry and interactions become emotions

(function() {
    'use strict';

    // ============================================
    // INITIALIZATION
    // ============================================
    document.addEventListener('DOMContentLoaded', () => {
        initializeParticles();
        initializeAOS();
        initializeNavigation();
        initializeSmoothScroll();
        initializeParallax();
        console.log('🌍 CultureConnect: Soul awakened');
    });

    // ============================================
    // FLOATING PARTICLES
    // ============================================
    function initializeParticles() {
        const container = document.getElementById('particles');
        if (!container) return;

        const particleCount = window.innerWidth > 768 ? 50 : 30;
        
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
        
        particle.style.cssText = `
            position: absolute;
            width: ${size}px;
            height: ${size}px;
            background: radial-gradient(circle, rgba(45, 137, 239, 0.6) 0%, transparent 70%);
            border-radius: 50%;
            left: ${x}%;
            bottom: -20px;
            animation: rise ${duration}s linear ${delay}s infinite;
            pointer-events: none;
        `;
        
        container.appendChild(particle);
    }

    // Add keyframes for particle animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes rise {
            0% {
                transform: translateY(0) translateX(0) scale(1);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100vh) translateX(${Math.random() * 100 - 50}px) scale(0);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);

    // ============================================
    // ANIMATE ON SCROLL (AOS)
    // ============================================
    function initializeAOS() {
        const observerOptions = {
            threshold: 0.15,
            rootMargin: '0px 0px -100px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('aos-animate');
                    // Optional: unobserve after animation
                    // observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        // Observe all elements with data-aos attribute
        document.querySelectorAll('[data-aos]').forEach(el => {
            observer.observe(el);
        });
    }

    // ============================================
    // NAVIGATION SCROLL EFFECT
    // ============================================
    function initializeNavigation() {
        const nav = document.querySelector('.nav-floating');
        const menuToggle = document.getElementById('menuToggle');
        const navLinks = document.querySelector('.nav-links');
        
        // Scroll effect
        let lastScroll = 0;
        window.addEventListener('scroll', () => {
            const currentScroll = window.pageYOffset;
            
            if (currentScroll > 100) {
                nav.style.padding = '1rem 2rem';
                nav.style.boxShadow = '0 4px 20px rgba(45, 137, 239, 0.1)';
            } else {
                nav.style.padding = '1.5rem 2rem';
                nav.style.boxShadow = 'none';
            }
            
            lastScroll = currentScroll;
        });

        // Mobile menu toggle
        if (menuToggle && navLinks) {
            menuToggle.addEventListener('click', () => {
                navLinks.classList.toggle('active');
                menuToggle.classList.toggle('active');
                
                // Animate hamburger
                const spans = menuToggle.querySelectorAll('span');
                if (menuToggle.classList.contains('active')) {
                    spans[0].style.transform = 'rotate(45deg) translate(5px, 5px)';
                    spans[1].style.opacity = '0';
                    spans[2].style.transform = 'rotate(-45deg) translate(7px, -6px)';
                } else {
                    spans[0].style.transform = 'none';
                    spans[1].style.opacity = '1';
                    spans[2].style.transform = 'none';
                }
            });

            // Close menu when clicking outside
            document.addEventListener('click', (e) => {
                if (!nav.contains(e.target) && navLinks.classList.contains('active')) {
                    navLinks.classList.remove('active');
                    menuToggle.classList.remove('active');
                    const spans = menuToggle.querySelectorAll('span');
                    spans[0].style.transform = 'none';
                    spans[1].style.opacity = '1';
                    spans[2].style.transform = 'none';
                }
            });

            // Close menu when clicking a link
            navLinks.querySelectorAll('a').forEach(link => {
                link.addEventListener('click', () => {
                    navLinks.classList.remove('active');
                    menuToggle.classList.remove('active');
                });
            });
        }
    }

    // ============================================
    // SMOOTH SCROLL
    // ============================================
    function initializeSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href === '#') return;
                
                e.preventDefault();
                const target = document.querySelector(href);
                
                if (target) {
                    const offsetTop = target.offsetTop - 80;
                    window.scrollTo({
                        top: offsetTop,
                        behavior: 'smooth'
                    });
                }
            });
        });
    }

    // ============================================
    // PARALLAX EFFECT
    // ============================================
    function initializeParallax() {
        const hero = document.querySelector('.hero');
        if (!hero) return;

        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const heroContent = hero.querySelector('.hero-content');
            const orbs = hero.querySelectorAll('.gradient-orb');
            
            // Parallax for hero content
            if (heroContent && scrolled < window.innerHeight) {
                heroContent.style.transform = `translateY(${scrolled * 0.5}px)`;
                heroContent.style.opacity = 1 - (scrolled / 500);
            }
            
            // Parallax for gradient orbs
            orbs.forEach((orb, index) => {
                const speed = (index + 1) * 0.3;
                orb.style.transform = `translateY(${scrolled * speed}px)`;
            });
        });
    }

    // ============================================
    // BUTTON INTERACTIONS
    // ============================================
    document.querySelectorAll('.btn-hero, .btn-cta').forEach(btn => {
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
                animation: ripple 0.6s ease-out;
            `;
            
            this.appendChild(ripple);
            
            setTimeout(() => ripple.remove(), 600);
        });
    });

    // Add ripple animation
    const rippleStyle = document.createElement('style');
    rippleStyle.textContent = `
        @keyframes ripple {
            to {
                width: 200px;
                height: 200px;
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(rippleStyle);

    // ============================================
    // FEATURE CARDS TILT EFFECT
    // ============================================
    document.querySelectorAll('.feature-card, .vision-card').forEach(card => {
        card.addEventListener('mousemove', function(e) {
            const rect = this.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            const centerX = rect.width / 2;
            const centerY = rect.height / 2;
            
            const rotateX = (y - centerY) / 20;
            const rotateY = (centerX - x) / 20;
            
            this.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-8px)`;
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) translateY(0)';
        });
    });

    // ============================================
    // GRADIENT TEXT ANIMATION
    // ============================================
    const gradientTexts = document.querySelectorAll('.gradient-text');
    gradientTexts.forEach(text => {
        text.style.backgroundSize = '200% auto';
        text.style.animation = 'gradient-flow 3s ease infinite';
    });

    const gradientStyle = document.createElement('style');
    gradientStyle.textContent = `
        @keyframes gradient-flow {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
    `;
    document.head.appendChild(gradientStyle);

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

    // ============================================
    // EASTER EGG - KONAMI CODE
    // ============================================
    const konamiCode = ['ArrowUp', 'ArrowUp', 'ArrowDown', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'ArrowLeft', 'ArrowRight', 'b', 'a'];
    let konamiIndex = 0;

    document.addEventListener('keydown', (e) => {
        if (e.key === konamiCode[konamiIndex]) {
            konamiIndex++;
            if (konamiIndex === konamiCode.length) {
                activateEasterEgg();
                konamiIndex = 0;
            }
        } else {
            konamiIndex = 0;
        }
    });

    function activateEasterEgg() {
        const message = document.createElement('div');
        message.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: linear-gradient(135deg, #2D89EF 0%, #764BA2 100%);
            color: white;
            padding: 3rem 4rem;
            border-radius: 24px;
            font-size: 2rem;
            font-weight: 700;
            text-align: center;
            z-index: 10000;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: celebrate 0.5s ease;
        `;
        message.innerHTML = `
            🌍 ✨<br>
            You discovered the secret!<br>
            <small style="font-size: 1rem; font-weight: 400; margin-top: 1rem; display: block;">
                "The world needs more curiosity like yours."
            </small>
        `;
        
        document.body.appendChild(message);
        
        // Add confetti effect
        for (let i = 0; i < 50; i++) {
            setTimeout(() => createConfetti(), i * 30);
        }
        
        setTimeout(() => message.remove(), 4000);
    }

    function createConfetti() {
        const confetti = document.createElement('div');
        const colors = ['#2D89EF', '#FF6F61', '#764BA2', '#FFD700'];
        const color = colors[Math.floor(Math.random() * colors.length)];
        
        confetti.style.cssText = `
            position: fixed;
            width: 10px;
            height: 10px;
            background: ${color};
            left: ${Math.random() * 100}%;
            top: -20px;
            opacity: 1;
            transform: rotate(${Math.random() * 360}deg);
            animation: confetti-fall ${Math.random() * 3 + 2}s linear forwards;
            z-index: 10001;
        `;
        
        document.body.appendChild(confetti);
        setTimeout(() => confetti.remove(), 5000);
    }

    const confettiStyle = document.createElement('style');
    confettiStyle.textContent = `
        @keyframes confetti-fall {
            to {
                top: 100vh;
                transform: translateX(${Math.random() * 200 - 100}px) rotate(${Math.random() * 720}deg);
                opacity: 0;
            }
        }
        @keyframes celebrate {
            0%, 100% { transform: translate(-50%, -50%) scale(1); }
            50% { transform: translate(-50%, -50%) scale(1.1); }
        }
    `;
    document.head.appendChild(confettiStyle);

    // ============================================
    // CONSOLE MESSAGE
    // ============================================
    console.log(
        '%c🌍 CultureConnect',
        'font-size: 24px; font-weight: bold; background: linear-gradient(135deg, #2D89EF 0%, #764BA2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;'
    );
    console.log(
        '%cBuilt with love for humanity 💙',
        'font-size: 14px; color: #2D89EF;'
    );
    console.log(
        '%cTry the Konami Code for a surprise! ↑↑↓↓←→←→BA',
        'font-size: 12px; color: #718096; font-style: italic;'
    );

})();

    // Logout renewal toast (shows when redirected from logout.php)
    document.addEventListener('DOMContentLoaded', () => {
        try {
            const params = new URLSearchParams(window.location.search);
            if (params.get('energy') === 'renewed') {
                const toast = document.createElement('div');
                toast.style.cssText = `
                    position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%);
                    background: rgba(255,255,255,0.95); padding: 12px 20px; border-radius: 40px;
                    box-shadow: 0 12px 40px rgba(0,0,0,0.12); z-index: 10000; font-weight:600;
                `;
                toast.textContent = '✨ Energy renewed. Safe travels.';
                document.body.appendChild(toast);
                setTimeout(() => { toast.style.opacity = '0'; setTimeout(()=>toast.remove(),300); }, 4200);
                // Clean URL
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        } catch (e) { /* noop */ }
    });