

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
