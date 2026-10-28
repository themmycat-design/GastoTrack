const POLL_INTERVAL_MS = 6000;

const isProtectedNode = node => node.matches('form, input, textarea, select, canvas, script, style, [data-owner-dialog], [data-owner-live-preserve], [x-data]')
    || (node === document.activeElement && node.closest('[contenteditable]'));

const patchNode = (current, fresh) => {
    if (current.isEqualNode(fresh)) return;
    if (current.nodeType !== fresh.nodeType
        || (current.nodeType === Node.ELEMENT_NODE && current.tagName !== fresh.tagName)) {
        current.replaceWith(fresh.cloneNode(true));
        return;
    }
    if (current.nodeType === Node.ELEMENT_NODE && isProtectedNode(current)) return;
    if (current.nodeType === Node.TEXT_NODE) {
        const value = current.parentElement;
        if (value?.matches('[data-owner-count-up]')
            && value.animate
            && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            value.animate([{opacity: .6}, {opacity: 1}], {
                duration: 160,
                easing: 'ease-out',
            });
        }
        current.textContent = fresh.textContent;
        return;
    }
    if (current.nodeType !== Node.ELEMENT_NODE) return;

    const keepScrollAccess = current.matches('.overflow-x-auto') && current.hasAttribute('tabindex');
    [...current.attributes].forEach(attribute => {
        if (!fresh.hasAttribute(attribute.name)
            && !(keepScrollAccess && ['tabindex', 'role', 'aria-label'].includes(attribute.name))
            && !(attribute.name === 'open' && current.matches('details'))) {
            current.removeAttribute(attribute.name);
        }
    });
    [...fresh.attributes].forEach(attribute => {
        if (current.getAttribute(attribute.name) !== attribute.value) current.setAttribute(attribute.name, attribute.value);
    });

    const currentChildren = [...current.childNodes];
    const freshChildren = [...fresh.childNodes];
    for (let index = 0; index < Math.max(currentChildren.length, freshChildren.length); index += 1) {
        if (!freshChildren[index]) {
            currentChildren[index].remove();
        } else if (!currentChildren[index]) {
            current.appendChild(freshChildren[index].cloneNode(true));
        } else {
            patchNode(currentChildren[index], freshChildren[index]);
        }
    }
};

const refreshNavigation = freshDocument => {
    const currentLinks = document.querySelectorAll('[data-owner-inventory-link]');
    const freshLinks = freshDocument.querySelectorAll('[data-owner-inventory-link]');
    currentLinks.forEach((link, index) => {
        if (freshLinks[index]) patchNode(link, freshLinks[index]);
    });

    const currentTrigger = document.querySelector('[data-owner-notification-trigger]');
    const freshTrigger = freshDocument.querySelector('[data-owner-notification-trigger]');
    if (currentTrigger && freshTrigger) patchNode(currentTrigger, freshTrigger);

    const currentLabel = document.querySelector('[data-notification-unread-label]');
    const freshLabel = freshDocument.querySelector('[data-notification-unread-label]');
    if (currentLabel && freshLabel) patchNode(currentLabel, freshLabel);

    const currentList = document.querySelector('[data-owner-notification-list]');
    const freshList = freshDocument.querySelector('[data-owner-notification-list]');
    if (currentList && freshList && currentList.innerHTML !== freshList.innerHTML) {
        patchNode(currentList, freshList);
    }
};

export function initOwnerLive() {
    const liveHeader = document.querySelector('[data-owner-live-url]');
    const liveUrl = liveHeader?.dataset.ownerLiveUrl;
    const content = document.querySelector('.gt-owner-page main > div');
    if (!liveUrl || !content) return;

    let revision = null;
    let currentDay = new Date().toDateString();
    let busy = false;
    let stopped = false;
    const controller = new AbortController();

    const poll = async () => {
        if (stopped || busy || document.hidden || !navigator.onLine) return;
        busy = true;
        try {
            const stateResponse = await fetch(liveUrl, {
                credentials: 'same-origin', cache: 'no-store',
                headers: {'Accept': 'application/json'}, signal: controller.signal,
            });
            if (stateResponse.redirected) {
                window.location.assign(stateResponse.url);
                return;
            }
            if (stateResponse.status === 401) {
                window.location.assign(liveHeader.dataset.ownerLoginUrl);
                return;
            }
            if (!stateResponse.ok) return;
            const state = await stateResponse.json();
            if (typeof state.revision !== 'string') return;
            const today = new Date().toDateString();
            if (state.revision === revision && today === currentDay) return;
            if (document.querySelector('[data-owner-dialog]:not(.hidden)')) return;

            const pageUrl = window.location.href;
            const pageResponse = await fetch(pageUrl, {
                credentials: 'same-origin', cache: 'no-store',
                headers: {'Accept': 'text/html', 'X-GastoTrack-Live': '1'},
                signal: controller.signal,
            });
            if (pageResponse.redirected) {
                window.location.assign(pageResponse.url);
                return;
            }
            if (!pageResponse.ok || !pageResponse.headers.get('content-type')?.includes('text/html')) return;
            const freshDocument = new DOMParser().parseFromString(await pageResponse.text(), 'text/html');
            const freshContent = freshDocument.querySelector('.gt-owner-page main > div');
            if (!freshContent || window.location.href !== pageUrl || pageResponse.url !== pageUrl) return;

            refreshNavigation(freshDocument);
            // Keep focused fields, forms, dialogs, charts, and existing event listeners.
            // The surrounding Blade data updates in place, including new rows.
            patchNode(content, freshContent);
            window.dispatchEvent(new CustomEvent('gastotrack:owner-live', {detail: {freshDocument}}));
            revision = state.revision;
            currentDay = today;
        } catch (error) {
            if (error.name !== 'AbortError') console.debug('Owner live update will retry.');
        } finally {
            busy = false;
        }
    };

    const timer = window.setInterval(poll, POLL_INTERVAL_MS);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
    window.addEventListener('online', poll);
    window.addEventListener('pagehide', () => {
        stopped = true;
        window.clearInterval(timer);
        controller.abort();
    }, {once: true});
    poll();
}
