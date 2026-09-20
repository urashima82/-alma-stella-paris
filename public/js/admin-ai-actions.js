/**
 * Admin AI workspace actions — AJAX dispatcher for every state-changing
 * interaction that previously relied on nested <form> elements.
 *
 * Why AJAX: the workspace is injected inside EasyAdmin's main edit <form>,
 * and HTML disallows nested forms.
 *
 * Markup contract:
 *   <button data-ai-action="post" data-ai-url="..." [data-ai-confirm="..."]
 *           [data-ai-reload="true"]>
 *
 *   Generate buttons additionally carry:
 *     data-ai-generate-btn
 *     data-ai-generate-type="all|vignette|worn|lifestyle"
 *
 * Source photos are not handled here — admin-photo-tray.js owns that whole
 * flow, uploads included.
 *
 * Default flow after a successful POST:
 *   - dispatch a `ai-action-completed` CustomEvent (admin-ai-poll.js listens
 *     to it and triggers an immediate state refresh, no page reload).
 *
 * For actions that need a full page reload (approve copies image to disk,
 * upload/delete source modify the SSR'd source list), set `data-ai-reload="true"`.
 *
 * Generate clicks additionally trigger an *optimistic* UI update so the user
 * gets immediate feedback without waiting for the next /ai-status poll:
 *   - all generate buttons are disabled until the next poll re-evaluates state,
 *   - a "generating" placeholder tile is inserted in each impacted column.
 * The poll then replaces innerHTML with the authoritative server state, which
 * naturally cleans up the optimistic placeholder.
 */
(function () {
    'use strict';

    const GENERATING_BADGE_COLOR = '#6366f1';

    function buildOptimisticPlaceholder() {
        return (
            '<article class="ai-visual-tile ai-visual-tile--generating ai-visual-tile--optimistic">' +
            '<div class="ai-visual-tile__media">' +
            '<div class="ai-visual-tile__loader"><i class="fa fa-spinner fa-spin"></i></div>' +
            '<span class="ai-visual-tile__variant">v?</span>' +
            '<span class="ai-visual-tile__status-badge" style="background:' +
            GENERATING_BADGE_COLOR +
            ';color:#fff;">En génération</span>' +
            '</div>' +
            '</article>'
        );
    }

    function insertOptimisticPlaceholder(type) {
        const container = document.querySelector('[data-ai-visual-items="' + type + '"]');
        if (!container) {
            return;
        }
        const empty = container.querySelector('.ai-visual-empty');
        if (empty) {
            empty.remove();
        }
        container.insertAdjacentHTML('beforeend', buildOptimisticPlaceholder());

        const column = document.querySelector('[data-ai-visual-type="' + type + '"]');
        if (column) {
            const countEl = column.querySelector('[data-ai-visual-count]');
            if (countEl) {
                const current = parseInt((countEl.textContent || '').replace(/[^\d]/g, ''), 10) || 0;
                countEl.textContent = '(' + (current + 1) + ')';
            }
        }
    }

    function applyGenerateOptimisticUpdate(btn) {
        if (!btn.hasAttribute('data-ai-generate-btn')) {
            return;
        }

        document.querySelectorAll('[data-ai-generate-btn]').forEach((b) => {
            b.setAttribute('disabled', 'disabled');
        });

        const generateType = btn.dataset.aiGenerateType;
        if (!generateType) {
            return;
        }
        const types = generateType === 'all' ? ['vignette', 'worn', 'lifestyle'] : [generateType];
        types.forEach((t) => insertOptimisticPlaceholder(t));
    }

    function postAction(url, body) {
        const opts = {
            method: 'POST',
            credentials: 'same-origin',
            redirect: 'follow',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        };
        if (body) {
            opts.body = body;
        }
        return fetch(url, opts);
    }

    function notifyCompleted(needsReload) {
        if (needsReload) {
            window.location.reload();
            return;
        }
        document.dispatchEvent(new CustomEvent('ai-action-completed'));
    }

    function notifyFailure(message) {
        if (window.AdminToast && typeof window.AdminToast.show === 'function') {
            window.AdminToast.show('danger', message);
        } else {
            window.alert(message);
        }
    }

    function askConfirmation(message) {
        if (!message) {
            return Promise.resolve(true);
        }
        // admin-photo-tray.js exposes a styled <dialog>; window.confirm is the
        // fallback when that script has not loaded.
        if (typeof window.AdminConfirm === 'function') {
            return window.AdminConfirm(message, 'Confirmer');
        }

        return Promise.resolve(window.confirm(message));
    }

    function handleClick(event) {
        const btn = event.target.closest('[data-ai-action]');
        if (!btn) {
            return;
        }
        event.preventDefault();

        if (btn.disabled || btn.dataset.aiBusy === '1') {
            return;
        }

        askConfirmation(btn.dataset.aiConfirm).then((confirmed) => {
            if (confirmed) {
                runAction(btn);
            }
        });
    }

    function runAction(btn) {
        const url = btn.dataset.aiUrl;
        if (!url) {
            return;
        }

        const needsReload = btn.dataset.aiReload === 'true';

        btn.dataset.aiBusy = '1';
        btn.setAttribute('disabled', 'disabled');

        applyGenerateOptimisticUpdate(btn);

        const isGenerateBtn = btn.hasAttribute('data-ai-generate-btn');

        postAction(url)
            .then((response) => {
                if (!response.ok && response.status !== 302) {
                    throw new Error('HTTP ' + response.status);
                }
                btn.dataset.aiBusy = '';
                // For generate buttons, keep all of them disabled — the poll will
                // re-enable them once the backend reports activelyGenerating === false.
                if (!needsReload && !isGenerateBtn) {
                    btn.removeAttribute('disabled');
                }
                notifyCompleted(needsReload);
            })
            .catch((err) => {
                btn.dataset.aiBusy = '';
                btn.removeAttribute('disabled');
                notifyFailure('Action échouée : ' + err.message);
                // Force a poll so server state wipes any optimistic placeholder/disable we applied.
                if (isGenerateBtn) {
                    document.dispatchEvent(new CustomEvent('ai-action-completed'));
                }
            });
    }

    document.addEventListener('click', handleClick);
})();
