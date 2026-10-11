

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

const backToTop = document.getElementById('welcome-back-to-top');

if (backToTop) {
    const updateBackToTop = () => {
        backToTop.hidden = window.scrollY < 500;
    };

    window.addEventListener('scroll', updateBackToTop, { passive: true });
    updateBackToTop();

    backToTop.addEventListener('click', () => {
        window.scrollTo({
            top: 0,
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
        });
    });
}

const revealItems = document.querySelectorAll('.gt-scroll-reveal');
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

const welcomeHero = document.querySelector('.gt-welcome-hero');
const cursorGlow = welcomeHero?.querySelector('.gt-welcome-cursor-glow');
const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)');

if (cursorGlow && finePointer.matches && !reducedMotion.matches) {
    let glowFrame = 0;
    let cursorX = -240;
    let cursorY = -240;

    welcomeHero.addEventListener('pointermove', event => {
        if (event.pointerType !== 'mouse') return;
        cursorX = event.clientX;
        cursorY = event.clientY;
        cursorGlow.classList.add('is-visible');
        if (glowFrame) return;
        glowFrame = window.requestAnimationFrame(() => {
            const heroBounds = welcomeHero.getBoundingClientRect();
            cursorGlow.style.transform = `translate3d(${cursorX - heroBounds.left - 110}px, ${cursorY - heroBounds.top - 110}px, 0)`;
            glowFrame = 0;
        });
    }, { passive: true });

    welcomeHero.addEventListener('pointerleave', () => cursorGlow.classList.remove('is-visible'));
    window.addEventListener('blur', () => cursorGlow.classList.remove('is-visible'));
    reducedMotion.addEventListener('change', event => {
        if (event.matches) cursorGlow.classList.remove('is-visible');
    });
}

if (revealItems.length && 'IntersectionObserver' in window && !reducedMotion.matches) {
    const revealObserver = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            entry.target.classList.toggle('is-visible', entry.isIntersecting);
        });
    }, { threshold: 0, rootMargin: '0px 0px -10% 0px' });

    revealItems.forEach(item => revealObserver.observe(item));
    document.documentElement.classList.add('gt-reveal-ready');
    reducedMotion.addEventListener('change', event => {
        if (event.matches) {
            document.documentElement.classList.remove('gt-reveal-ready');
            revealObserver.disconnect();
        }
    });
}
