/**
 * Admin AI workspace polling — polls the /ai-status endpoint every 2s while:
 *   (a) the workspace is in the DOM, AND
 *   (b) at least one visual is "generating" or product status is "pending_visuals".
 *
 * Each poll request also drives the Messenger queue (consumes 1 message inline),
 * so generation actually happens while the user is watching. A cron fallback
 * (messenger:consume) handles processing when the user is away.
 *
 * On state change, only the impacted DOM regions are updated (visual columns,
 * status badge, generate buttons). Scroll position, active tab and other form
 * fields are preserved.
 */
(function () {
    'use strict';

    const POLL_INTERVAL_MS = 2000;

    let pollTimeoutId = null;
    let isPolling = false;
    let url = null;
    let configEl = null;
    let lastSignature = null;

    function applyState(data) {
        // Visual columns: replace innerHTML of each [data-ai-visual-items="..."] container
        if (data.html && typeof data.html === 'object') {
            for (const [type, htmlString] of Object.entries(data.html)) {
                const container = document.querySelector(
                    '[data-ai-visual-items="' + type + '"]'
                );
                if (container) {
                    container.innerHTML = htmlString;
                }
            }
        }

        // Counts in column headers
        if (data.counts && typeof data.counts === 'object') {
            for (const [type, count] of Object.entries(data.counts)) {
                const column = document.querySelector(
                    '[data-ai-visual-type="' + type + '"]'
                );
                if (column) {
                    const countEl = column.querySelector('[data-ai-visual-count]');
                    if (countEl) {
                        countEl.textContent = '(' + count + ')';
                    }
                }
            }
        }

        // Workflow status badge (top right of workspace)
        const badge = document.querySelector('[data-ai-status-badge]');
        if (badge && data.visualStatusLabel) {
            badge.textContent = data.visualStatusLabel;
            if (data.visualStatusColor) {
                badge.style.background = data.visualStatusColor;
            }
        }

        // Generate buttons disabled state — only disabled while Gemini is *actively* running
        const generateButtons = document.querySelectorAll('[data-ai-generate-btn]');
        generateButtons.forEach((btn) => {
            if (data.activelyGenerating) {
                btn.setAttribute('disabled', 'disabled');
            } else if (data.sourcePhotosCount > 0) {
                btn.removeAttribute('disabled');
            }
        });

        // Update the pending flag on the config so future visibilitychange handlers know
        if (configEl) {
            configEl.dataset.pending = data.pending ? '1' : '0';
        }
    }

    function tick() {
        if (isPolling || !url) {
            return;
        }
        isPolling = true;

        fetch(url, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then((r) => (r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status))))
            .then((data) => {
                isPolling = false;

                const signature = data.signature || '';
                if (lastSignature !== null && lastSignature !== signature) {
                    applyState(data);
                }
                lastSignature = signature;

                if (data.pending) {
                    pollTimeoutId = setTimeout(tick, POLL_INTERVAL_MS);
                } else {
                    pollTimeoutId = null;
                }
            })
            .catch(() => {
                isPolling = false;
                pollTimeoutId = setTimeout(tick, POLL_INTERVAL_MS * 3);
            });
    }

    function bootstrap() {
        configEl = document.getElementById('ai-poll-config');
        if (!configEl || !configEl.dataset.url) {
            return;
        }
        url = configEl.dataset.url;
        // Seed lastSignature from SSR so the first poll detects real transitions
        lastSignature = configEl.dataset.signature || '';

        // Start polling only if something is pending at page load
        if (configEl.dataset.pending === '1') {
            pollTimeoutId = setTimeout(tick, POLL_INTERVAL_MS);
        }

        // Resume polling when the tab becomes visible again
        document.addEventListener('visibilitychange', () => {
            if (
                !document.hidden &&
                configEl.dataset.pending === '1' &&
                pollTimeoutId === null
            ) {
                pollTimeoutId = setTimeout(tick, POLL_INTERVAL_MS);
            }
        });

        // External actions (admin-ai-actions.js) fire this event after a
        // successful dispatch — kick off polling immediately to drive the worker.
        document.addEventListener('ai-action-completed', () => {
            if (pollTimeoutId !== null) {
                clearTimeout(pollTimeoutId);
            }
            // Mark workspace as pending so visibilitychange handler keeps it alive
            configEl.dataset.pending = '1';
            pollTimeoutId = setTimeout(tick, 100);
        });
    }

    // The workspace HTML is injected into the tab pane after DOMContentLoaded by edit.html.twig.
    // Bind once via MutationObserver to catch the moment the config element appears.
    function init() {
        if (document.getElementById('ai-poll-config')) {
            bootstrap();
            return;
        }
        const observer = new MutationObserver(() => {
            if (document.getElementById('ai-poll-config')) {
                observer.disconnect();
                bootstrap();
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
