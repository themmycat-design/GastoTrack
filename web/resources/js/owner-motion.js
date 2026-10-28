// Animate owner page surfaces, form panels, table/list rows, and filter replacements.
// Content stays readable if animation APIs are unavailable or motion is reduced.
export function initOwnerMotion(motionPreference) {
    const main = document.querySelector('.gt-owner-page main');
    if (!main || !('IntersectionObserver' in window) || !Element.prototype.animate) return;

    // Sections own their entrance. Only explicitly marked children get another reveal.
    const nestedMotionSelector = '[data-owner-motion-child]';
    const countUpSelector = '[data-owner-count-up]';
    const surfaceSelector = `.gt-card, article, form, section, [data-owner-motion], .gt-enter, ${nestedMotionSelector}, ${countUpSelector}, .gt-snapshot-donut`;
    const overviewMode = document.body.classList.contains('gt-owner-overview');
    const candidateSelector = overviewMode ? `${countUpSelector}, .gt-snapshot-donut` : surfaceSelector;
    const topbar = main.querySelector(':scope > header');
    const registered = new WeakSet();
    const counted = new WeakSet();
    const running = new Set();
    let nextRevealAt = 0;
    const animateCountUp = (target, delay, updated) => {
        if (!target.matches(countUpSelector) || counted.has(target)) return;

        const finalValue = Number(target.dataset.ownerCountUpValue);
        if (!Number.isFinite(finalValue)) return;

        counted.add(target);
        const decimals = Math.max(0, Math.min(4, Number.parseInt(target.dataset.ownerCountUpDecimals || '2', 10) || 0));
        const prefix = target.dataset.ownerCountUpPrefix || '';
        const suffix = target.dataset.ownerCountUpSuffix || '';
        const formatter = new Intl.NumberFormat('en-PH', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        });
        const render = value => {
            target.textContent = `${prefix}${formatter.format(value)}${suffix}`;
        };

        if (motionPreference.matches) {
            render(finalValue);
            return;
        }
        render(0);
        window.setTimeout(() => {
            const startedAt = performance.now();
            const duration = updated ? 180 : 340;
            const tick = now => {
                if (motionPreference.matches) {
                    render(finalValue);
                    return;
                }

                const progress = Math.min(1, (now - startedAt) / duration);
                const easedProgress = 1 - Math.pow(1 - progress, 3);
                render(finalValue * easedProgress);

                if (progress < 1) window.requestAnimationFrame(tick);
                else render(finalValue);
            };

            window.requestAnimationFrame(tick);
        }, Math.max(0, delay));
    };
    const animateSnapshotDonut = (target, delay, updated) => {
        if (!target.matches('.gt-snapshot-donut')) return;

        const arc = target.querySelector('.gt-snapshot-donut-value');
        const finalDasharray = arc?.getAttribute('stroke-dasharray');
        if (!arc || !finalDasharray) return;

        if (motionPreference.matches) return;
        arc.style.strokeDashoffset = '100';
        window.setTimeout(() => {
            if (motionPreference.matches) {
                arc.style.strokeDashoffset = '0';
                return;
            }

            const animation = arc.animate([
                { strokeDashoffset: '100' },
                { strokeDashoffset: '0' },
            ], {
                duration: updated ? 180 : 340,
                easing: 'cubic-bezier(.2, .75, .25, 1)',
                fill: 'none',
            });
            animation.onfinish = () => { arc.style.strokeDashoffset = '0'; };
            animation.oncancel = () => { arc.style.strokeDashoffset = '0'; };
            running.add(animation);
            animation.finished.catch(() => {}).finally(() => running.delete(animation));
        }, Math.max(0, delay + 70));
    };
    const play = (target, updated = false) => {
        if (motionPreference.matches || target.contains(document.activeElement)) return;
        if (overviewMode) {
            const step = Math.min(6, Math.max(0, Number(target.closest('[data-overview-step]')?.dataset.overviewStep) || 0));
            const figureDelay = updated ? 0 : step * 30 + 90;
            animateCountUp(target, figureDelay, updated);
            animateSnapshotDonut(target, figureDelay, updated);
            return;
        }
        // Share a sequence across initial content and scroll observer batches so
        // sections near the viewport edge do not start alongside the first card.
        const now = performance.now();
        const pageEntrance = target.matches('.gt-enter');
        const nestedTarget = target.matches(nestedMotionSelector);
        const stagger = pageEntrance ? 36 : nestedTarget ? 25 : 32;
        const delay = updated ? 0 : Math.min(120, Math.max(0, nextRevealAt - now));
        if (!updated) nextRevealAt = now + delay + stagger;
        const startOffset = pageEntrance ? 10 : nestedTarget ? 6 : 8;
        const cssEntrance = target.matches('.gt-enter, .gt-owner-page main > div > :is(section, article, form, .gt-card)');
        if (cssEntrance || target.matches(countUpSelector)) {
            // CSS owns page/card entrances as a reliable fallback; JS still animates
            // count-up values and charts without competing for the same opacity.
            animateCountUp(target, delay, updated);
            animateSnapshotDonut(target, delay, updated);
            return;
        }
        const animation = target.animate([
            {
                opacity: updated ? .85 : 0,
                transform: updated ? 'translateY(0)' : `translateY(${startOffset}px)`,
            },
            { opacity: 1, transform: 'translateY(0)' },
        ], {
            duration: updated ? 160 : 240,
            delay,
            easing: 'cubic-bezier(.25, .8, .25, 1)',
            fill: 'none',
        });
        animateCountUp(target, delay, updated);
        animateSnapshotDonut(target, delay, updated);
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
                || item.closest('[data-owner-navigation], [data-owner-dialog], [role="dialog"]')
                || (overviewMode && item.closest('[data-overview-static-value]'))) return;
            // Animate the page wrapper as an entrance layer, then stagger its content.
            // Other layout groups yield to the individual cards they contain.
            const pageEntrance = item.matches('.gt-enter');
            if (!item.matches(surfaceSelector) && item.querySelector(`${surfaceSelector}, section`) && !pageEntrance) return;
            const updatedRegion = updated && item === root && item.hasAttribute('aria-live');
            // Explicit child markers, forms, and rows reveal inside their parent surface.
            // Unmarked content keeps the one-animation-per-surface behavior.
            if (!updatedRegion && !item.matches(`${nestedMotionSelector}, ${countUpSelector}, .gt-snapshot-donut`) && !pageEntrance) {
                if (item.parentElement?.closest(surfaceSelector)) return;
                for (let parent = item.parentElement; parent && parent !== main; parent = parent.parentElement) {
                    if (registered.has(parent) && !parent.matches('.gt-enter')) return;
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

    const overviewIsWaiting = () => overviewMode && document.documentElement.classList.contains('gt-owner-overview-waiting');
    if (overviewIsWaiting()) {
        window.addEventListener('gastotrack:overview-ready', () => register(main), {once: true});
    } else {
        register(main);
    }
    const mutations = new MutationObserver(records => {
        records.forEach(record => {
            record.removedNodes.forEach(node => {
                if (!(node instanceof Element)) return;
                observer.unobserve(node);
                node.querySelectorAll(candidateSelector).forEach(item => observer.unobserve(item));
            });
            record.addedNodes.forEach(node => {
                if (node instanceof Element && !overviewIsWaiting()) register(node, true);
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
