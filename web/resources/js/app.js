

import Alpine from 'alpinejs';
import { initOwnerMotion } from './owner-motion';
import { initOwnerLive } from './owner-live';

window.Alpine = Alpine;

Alpine.start();

// Make wide portal tables reachable with a keyboard when horizontal scrolling is needed.
if (document.body.matches('.gt-owner-page, .gt-admin-page')) {
    document.querySelectorAll('.overflow-x-auto').forEach(container => {
        if (!container.querySelector('table')) return;
        const update = () => {
            if (container.scrollWidth > container.clientWidth + 1) {
                container.tabIndex = 0;
                container.setAttribute('role', 'region');
                container.setAttribute('aria-label', 'Data table — scroll horizontally to view more columns');
            } else {
                container.removeAttribute('tabindex');
                container.removeAttribute('role');
                container.removeAttribute('aria-label');
            }
        };
        update();
        if ('ResizeObserver' in window) new ResizeObserver(update).observe(container);
    });
}

document.querySelectorAll('form[data-submit-feedback]').forEach(form => {
    const button = form.querySelector('button[type="submit"]');
    const label = button?.querySelector('[data-submit-label]');
    if (!button || !label) return;
    const initialLabel = label.textContent;
    form.addEventListener('submit', event => {
        if (event.defaultPrevented) return;
        if (button.disabled) {
            event.preventDefault();
            return;
        }
        button.disabled = true;
        form.setAttribute('aria-busy', 'true');
        label.textContent = 'Saving…';
    });
    window.addEventListener('pageshow', () => {
        button.disabled = false;
        form.removeAttribute('aria-busy');
        label.textContent = initialLabel;
    });
});

const landingContactForm = document.querySelector('form[data-gt-contact-form]');

if (landingContactForm) {
    const button = landingContactForm.querySelector('button[type="submit"]');
    const status = landingContactForm.querySelector('[data-gt-contact-status]');

    if (button && status) {
        const buttonLabel = button.textContent;
        const showStatus = (message, isError) => {
            status.textContent = message;
            status.hidden = false;
            status.setAttribute('role', isError ? 'alert' : 'status');
            status.classList.toggle('border-red-200', isError);
            status.classList.toggle('bg-red-50', isError);
            status.classList.toggle('text-red-800', isError);
            status.classList.toggle('border-[#B7DAD0]', !isError);
            status.classList.toggle('bg-[#EAF8F2]', !isError);
            status.classList.toggle('text-[#065A49]', !isError);
        };

        landingContactForm.addEventListener('submit', async event => {
            event.preventDefault();
            if (button.disabled) return;

            button.disabled = true;
            button.textContent = 'Sending...';
            status.hidden = true;
            landingContactForm.querySelectorAll('[aria-invalid]').forEach(field => field.setAttribute('aria-invalid', 'false'));

            try {
                const response = await fetch(landingContactForm.action, {
                    method: 'POST',
                    body: new FormData(landingContactForm),
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const result = (await response.json().catch(() => null)) || {};

                if (response.ok) {
                    landingContactForm.reset();
                    showStatus(result.message || 'Your message was sent to the GastoTrack team.', result.acknowledgement_sent === false);
                } else {
                    const errors = result.errors || {};
                    Object.keys(errors).forEach(name => {
                        const field = landingContactForm.elements.namedItem(name);
                        if (field instanceof HTMLElement) field.setAttribute('aria-invalid', 'true');
                    });
                    const firstError = Object.values(errors)[0]?.[0];
                    const message = response.status === 429
                        ? 'Please wait a minute before sending another message.'
                        : response.status === 419
                            ? 'Your session expired. Refresh the page and try again.'
                            : firstError || result.message || 'We could not send your message. Please try again.';
                    showStatus(message, true);
                }
            } catch {
                showStatus('We could not connect to the server. Please try again.', true);
            } finally {
                button.disabled = false;
                button.textContent = buttonLabel;
            }
        });
    }
}

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

const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
const revealItems = [...document.querySelectorAll('.gt-scroll-reveal')]
    .filter(item => !item.closest('.gt-owner-page'));
initOwnerMotion(reducedMotion);
initOwnerLive();

document.querySelectorAll('[data-gt-flip-card]').forEach(card => {
    const product = card.querySelector('[data-gt-product]')?.textContent.trim();
    const description = card.querySelector('[data-gt-description]')?.textContent.trim();
    if (!product || !description) return;

    card.addEventListener('click', () => {
        const flipped = card.classList.toggle('is-flipped');
        card.setAttribute('aria-pressed', String(flipped));
        card.setAttribute('aria-label', flipped
            ? `${product}. ${description} Show photo.`
            : `Show details about ${product.toLowerCase()}`);
    });
});

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
            if (entry.target.dataset.gtRevealOnce === 'true') {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    revealObserver.unobserve(entry.target);
                }
                return;
            }

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
