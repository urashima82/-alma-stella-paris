/**
 * Admin lightbox — vanilla JS fullscreen image viewer.
 * Activates on any element with class `js-admin-lightbox-trigger` containing an <img>.
 */
(function () {
    'use strict';

    let overlay = null;
    let keyHandler = null;

    function open(src, alt) {
        if (overlay) {
            return;
        }
        overlay = document.createElement('div');
        overlay.className = 'admin-lightbox';
        overlay.innerHTML = `
            <div class="admin-lightbox__backdrop"></div>
            <button class="admin-lightbox__close" type="button" aria-label="Fermer">
                <svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
            <div class="admin-lightbox__stage">
                <img class="admin-lightbox__img" src="${src}" alt="${alt || ''}">
            </div>
        `;
        document.body.appendChild(overlay);
        document.body.style.overflow = 'hidden';
        requestAnimationFrame(() => overlay.classList.add('admin-lightbox--visible'));

        overlay.querySelector('.admin-lightbox__close').addEventListener('click', close);
        overlay.querySelector('.admin-lightbox__backdrop').addEventListener('click', close);

        keyHandler = (e) => { if (e.key === 'Escape') close(); };
        document.addEventListener('keydown', keyHandler);
    }

    function close() {
        if (!overlay) return;
        overlay.classList.remove('admin-lightbox--visible');
        document.removeEventListener('keydown', keyHandler);
        const o = overlay;
        overlay = null;
        keyHandler = null;
        setTimeout(() => {
            o.remove();
            document.body.style.overflow = '';
        }, 200);
    }

    function bind(root) {
        root.querySelectorAll('.js-admin-lightbox-trigger').forEach((el) => {
            if (el.dataset.lightboxBound === '1') return;
            el.dataset.lightboxBound = '1';
            el.addEventListener('click', (e) => {
                e.preventDefault();
                const img = el.tagName === 'IMG' ? el : el.querySelector('img');
                if (img) open(img.src, img.alt);
            });
        });
    }

    function init() {
        bind(document);
        // Re-bind when the workspace is injected into the tab pane
        const observer = new MutationObserver((mutations) => {
            for (const m of mutations) {
                m.addedNodes.forEach((node) => {
                    if (node.nodeType === 1) bind(node);
                });
            }
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
