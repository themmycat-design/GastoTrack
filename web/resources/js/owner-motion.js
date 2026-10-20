// Animate visible content surfaces once, including replacements loaded by filters.
// Content stays readable if animation APIs are unavailable or motion is reduced.
export function initOwnerMotion(motionPreference) {
    const main = document.querySelector('.gt-owner-page main');
    if (!main || !('IntersectionObserver' in window) || !Element.prototype.animate) return;

    const surfaceSelector = '.gt-card, article, section.bg-white, [data-owner-motion]';
    const candidateSelector = `${surfaceSelector}, section, header, .gt-enter, .gt-scroll-reveal, [aria-live="polite"]`;
    const topbar = main.querySelector(':scope > header');
    const registered = new WeakSet();
    const running = new Set();
    let nextRevealAt = 0;
    const play = (target, updated = false) => {
        if (motionPreference.matches || target.contains(document.activeElement)) return;
        // Share a sequence across initial content and scroll observer batches so
        // sections near the viewport edge do not start alongside the first card.
        const now = performance.now();
        const delay = updated ? 0 : Math.max(0, nextRevealAt - now);
        if (!updated) nextRevealAt = now + delay + 110;
        const animation = target.animate([
            { opacity: updated ? .55 : 0, translate: updated ? '0 0' : '0 6px' },
            { opacity: 1, translate: '0 0' },
        ], {
            duration: updated ? 400 : 520,
            delay,
            easing: 'cubic-bezier(.25, .8, .25, 1)',
            fill: 'backwards',
        });
        running.add(animation);
        animation.finished.catch(() => {}).finally(() => running.delete(animation));
    };
    const observer = new IntersectionObserver(entries => {
        const entering = entries.filter(entry => entry.isIntersecting)
            .sort((a, b) => a.target.compareDocumentPosition(b.target) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1);
        entering.forEach(({ target }) => {
            observer.unobserve(target);
            play(target);
        });
    }, { threshold: 0, rootMargin: '0px 0px 64px 0px' });

    const register = (root, updated = false) => {
        const candidates = [
            ...(root.matches(candidateSelector) ? [root] : []),
            ...root.querySelectorAll(candidateSelector),
        ];
        const visible = [];
        candidates.forEach(item => {
            if (registered.has(item) || topbar?.contains(item)
                || item.closest('[data-owner-navigation], [data-owner-dialog], [role="dialog"]')) return;
            // Layout groups yield to the individual cards they contain.
            if (!item.matches(surfaceSelector) && item.querySelector(`${surfaceSelector}, section`)) return;
            const updatedRegion = updated && item === root && item.hasAttribute('aria-live');
            if (!updatedRegion) {
                if (item.parentElement?.closest(surfaceSelector)) return;
                for (let parent = item.parentElement; parent && parent !== main; parent = parent.parentElement) {
                    if (registered.has(parent)) return;
                }
            }
            registered.add(item);
            const bounds = item.getBoundingClientRect();
            if (bounds.height > 0 && bounds.bottom > 0 && bounds.top < window.innerHeight) {
                visible.push(item);
            } else {
                observer.observe(item);
            }
        });
        // Reveal each visible section in document order with its own start time.
        // A refreshed table remains a single, immediate fade in place.
        visible.forEach(item => play(item, updated));
    };

    register(main);
    const mutations = new MutationObserver(records => {
        records.forEach(record => {
            record.removedNodes.forEach(node => {
                if (!(node instanceof Element)) return;
                observer.unobserve(node);
                node.querySelectorAll(candidateSelector).forEach(item => observer.unobserve(item));
            });
            record.addedNodes.forEach(node => {
                if (node instanceof Element) register(node, true);
            });
        });
    });
    mutations.observe(main, { childList: true, subtree: true });
    const cancelMotion = () => {
        running.forEach(animation => animation.cancel());
        nextRevealAt = 0;
    };
    motionPreference.addEventListener('change', () => {
        if (motionPreference.matches) cancelMotion();
    });
    main.addEventListener('focusin', event => {
        running.forEach(animation => {
            if (animation.effect?.target?.contains(event.target)) animation.cancel();
        });
    });
    window.addEventListener('pagehide', cancelMotion);
}
