/**
 * Admin AI content workspace — polling + modal-style edit interactions for the
 * Contenu IA tab. Independent from admin-ai-poll.js (which handles the visual
 * pipeline) so the two pipelines never share state.
 *
 * Markup contract:
 *   <div id="ai-content-poll-config" data-url="..." data-pending="0|1" data-signature="...">
 *   <div data-ai-content-card>...</div>
 *   <div data-ai-content-history>...</div>
 *
 *   Buttons:
 *     [data-ai-content-generate]              — handled by admin-ai-actions.js (data-ai-action="post")
 *     [data-ai-content-action]                — handled by admin-ai-actions.js (reject)
 *     [data-ai-content-regenerate]            — opens a prompt for additional context, posts here
 *     [data-ai-content-apply]                 — saves edits then applies suggestion to product
 *     [data-ai-content-field]                 — editable input/textarea inside a pending card
 */
(function () {
    'use strict';

    const POLL_INTERVAL_MS = 2000;

    let pollTimeoutId = null;
    let isPolling = false;
    let url = null;
    let configEl = null;
    let lastSignature = null;

    // ── Polling ──

    function applyState(data) {
        const cardSlot = document.querySelector('[data-ai-content-card]');
        if (cardSlot && typeof data.cardHtml === 'string') {
            cardSlot.innerHTML = data.cardHtml;
        }
        const historySlot = document.querySelector('[data-ai-content-history]');
        if (historySlot && typeof data.historyHtml === 'string') {
            historySlot.innerHTML = data.historyHtml;
        }
        const generateBtn = document.querySelector('[data-ai-content-generate]');
        if (generateBtn) {
            if (data.isGenerating || data.sourcePhotosCount === 0) {
                generateBtn.setAttribute('disabled', 'disabled');
            } else {
                generateBtn.removeAttribute('disabled');
            }
        }
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
                    // One last apply to render the final Pending state if the
                    // signature happened to match (rare race on first poll).
                    applyState(data);
                }
            })
            .catch(() => {
                isPolling = false;
                pollTimeoutId = setTimeout(tick, POLL_INTERVAL_MS * 3);
            });
    }

    function startPolling(immediate) {
        if (pollTimeoutId !== null) {
            clearTimeout(pollTimeoutId);
        }
        pollTimeoutId = setTimeout(tick, immediate ? 100 : POLL_INTERVAL_MS);
    }

    // ── Custom interactions: regenerate prompt + apply with edits ──

    function gatherFieldValues(card) {
        const data = new FormData();
        const fields = card.querySelectorAll('[data-ai-content-field]');
        fields.forEach((f) => {
            if (f.name) {
                data.append(f.name, f.value);
            }
        });
        return data;
    }

    function postFormData(targetUrl, formData) {
        return fetch(targetUrl, {
            method: 'POST',
            credentials: 'same-origin',
            redirect: 'follow',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData,
        });
    }

    function handleRegenerate(btn) {
        const additional = window.prompt(
            'Instruction additionnelle pour la régénération (optionnel) :\n\nEx: « plus poétique », « insiste sur la couleur bleue », « plus court »',
            ''
        );
        if (additional === null) {
            return; // user cancelled
        }
        const targetUrl = btn.dataset.aiContentRegenerateUrl;
        if (!targetUrl) {
            return;
        }
        btn.setAttribute('disabled', 'disabled');

        const formData = new FormData();
        if (additional.trim() !== '') {
            formData.append('additionalContext', additional.trim());
        }

        postFormData(targetUrl, formData)
            .then((r) => {
                if (!r.ok && r.status !== 302) {
                    throw new Error('HTTP ' + r.status);
                }
                if (configEl) {
                    configEl.dataset.pending = '1';
                }
                startPolling(true);
            })
            .catch((err) => {
                btn.removeAttribute('disabled');
                window.alert('Régénération échouée : ' + err.message);
            });
    }

    function handleApply(btn) {
        const card = btn.closest('[data-suggestion-id]');
        if (!card) {
            return;
        }
        const updateUrl = btn.dataset.aiContentUpdateUrl;
        const applyUrl = btn.dataset.aiContentApplyUrl;
        if (!updateUrl || !applyUrl) {
            return;
        }
        btn.setAttribute('disabled', 'disabled');
        btn.dataset.aiBusy = '1';

        const formData = gatherFieldValues(card);

        postFormData(updateUrl, formData)
            .then((r) => {
                if (!r.ok && r.status !== 302) {
                    throw new Error('Save failed: HTTP ' + r.status);
                }
                return postFormData(applyUrl, new FormData());
            })
            .then((r) => {
                if (!r.ok && r.status !== 302) {
                    throw new Error('Apply failed: HTTP ' + r.status);
                }
                // The Product fields just changed — full reload to refresh the form
                window.location.reload();
            })
            .catch((err) => {
                btn.dataset.aiBusy = '';
                btn.removeAttribute('disabled');
                window.alert('Application échouée : ' + err.message);
            });
    }

    function handleClick(event) {
        const regenBtn = event.target.closest('[data-ai-content-regenerate]');
        if (regenBtn) {
            event.preventDefault();
            handleRegenerate(regenBtn);
            return;
        }
        const applyBtn = event.target.closest('[data-ai-content-apply]');
        if (applyBtn) {
            event.preventDefault();
            handleApply(applyBtn);
        }
    }

    // ── Bootstrap ──

    function bootstrap() {
        configEl = document.getElementById('ai-content-poll-config');
        if (!configEl || !configEl.dataset.url) {
            return;
        }
        url = configEl.dataset.url;
        lastSignature = configEl.dataset.signature || '';

        if (configEl.dataset.pending === '1') {
            startPolling(false);
        }

        document.addEventListener('visibilitychange', () => {
            if (
                !document.hidden &&
                configEl.dataset.pending === '1' &&
                pollTimeoutId === null
            ) {
                startPolling(false);
            }
        });

        // The generic [data-ai-action="post"] handler in admin-ai-actions.js
        // dispatches `ai-action-completed` after a successful POST. We listen
        // for that event so a "Generate content" click triggers immediate polling.
        document.addEventListener('ai-action-completed', () => {
            if (configEl.dataset.pending !== '1') {
                configEl.dataset.pending = '1';
            }
            startPolling(true);
        });
    }

    function init() {
        if (document.getElementById('ai-content-poll-config')) {
            bootstrap();
        } else {
            const observer = new MutationObserver(() => {
                if (document.getElementById('ai-content-poll-config')) {
                    observer.disconnect();
                    bootstrap();
                }
            });
            observer.observe(document.body, { childList: true, subtree: true });
        }
        document.addEventListener('click', handleClick);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
