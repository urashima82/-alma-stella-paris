/**
 * Admin photo tray — the one source-photo picker, shared by the AI creation
 * wizard and the "Visuels IA" tab of the product edit page.
 *
 * Both modes follow the same flow: pick several photos at once, look at them,
 * set an angle on each, then validate.
 *
 *   - `staged` (wizard) — the product does not exist yet, so the files stay in
 *     the browser. Each card owns a hidden <input type="file"> carrying the
 *     File (assigned through DataTransfer) plus its angle <select>, both named
 *     for the Symfony CollectionType, and the whole set rides the form POST.
 *   - `live` (edit page) — picks are staged exactly the same way, then sent as
 *     one batch request. The server answers with the re-rendered tray, so the
 *     surrounding product form never reloads and never loses unsaved edits.
 *
 * Every picked image is decoded and re-encoded to WebP under MAX_DIMENSION
 * before it goes anywhere. A phone photo drops from several MB to a few
 * hundred KB, which is what makes the wizard's single POST viable on a mobile
 * connection, keeps the whole set far below post_max_size, and shrinks the
 * payload later base64-encoded into every Gemini call.
 *
 * Markup contract: templates/admin/product/_source_photo_tray.html.twig.
 * Also exposes window.AdminConfirm(message, confirmLabel) → Promise<boolean>,
 * a touch-friendly replacement for window.confirm().
 */
(function () {
    'use strict';

    const MAX_DIMENSION = 2048;
    const WEBP_QUALITY = 0.85;
    const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    const MAX_BYTES = 10 * 1024 * 1024;

    // ── Small helpers ───────────────────────────────────────────────────────

    function toast(type, message) {
        if (window.AdminToast && typeof window.AdminToast.show === 'function') {
            window.AdminToast.show(type, message);
        }
    }

    function cardsOf(tray) {
        return Array.from(tray.querySelectorAll('[data-tray-card]'));
    }

    function pendingCardsOf(tray) {
        return cardsOf(tray).filter((card) => card.hasAttribute('data-tray-pending'));
    }

    function intData(tray, key, fallback) {
        const parsed = parseInt(tray.dataset[key], 10);
        return isNaN(parsed) ? fallback : parsed;
    }

    // ── Confirmation dialog ─────────────────────────────────────────────────

    function confirmDialog(message, confirmLabel) {
        // window.confirm() renders as a cramped system sheet on mobile and, on
        // some Android browsers, steals focus from the page for good.
        if (typeof window.HTMLDialogElement === 'undefined') {
            return Promise.resolve(window.confirm(message));
        }

        return new Promise((resolve) => {
            const dialog = document.createElement('dialog');
            dialog.className = 'admin-confirm';
            dialog.innerHTML =
                '<p class="admin-confirm__message"></p>' +
                '<div class="admin-confirm__actions">' +
                '<button type="button" class="admin-confirm__btn" data-confirm-cancel>Annuler</button>' +
                '<button type="button" class="admin-confirm__btn admin-confirm__btn--danger" data-confirm-ok></button>' +
                '</div>';
            dialog.querySelector('.admin-confirm__message').textContent = message;
            dialog.querySelector('[data-confirm-ok]').textContent = confirmLabel || 'Supprimer';

            let settled = false;
            const close = (answer) => {
                if (settled) {
                    return;
                }
                settled = true;
                resolve(answer);
                dialog.close();
                dialog.remove();
            };

            dialog.querySelector('[data-confirm-cancel]').addEventListener('click', () => close(false));
            dialog.querySelector('[data-confirm-ok]').addEventListener('click', () => close(true));
            dialog.addEventListener('cancel', (event) => {
                event.preventDefault();
                close(false);
            });

            document.body.appendChild(dialog);
            dialog.showModal();
        });
    }

    // ── Image preparation ───────────────────────────────────────────────────

    function loadBitmap(file) {
        if (typeof window.createImageBitmap === 'function') {
            // `from-image` bakes in the EXIF orientation phones record instead
            // of rotating pixels — without it every portrait shot lands sideways.
            return window.createImageBitmap(file, { imageOrientation: 'from-image' });
        }

        // Fallback for browsers without createImageBitmap. A data URL, not a
        // blob: one — see showPreview() for why the CSP rules that out.
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onerror = () => reject(new Error('read failed'));
            reader.onload = () => {
                const image = new Image();
                image.onload = () => resolve(image);
                image.onerror = () => reject(new Error('decode failed'));
                image.src = reader.result;
            };
            reader.readAsDataURL(file);
        });
    }

    function canvasToBlob(canvas) {
        return new Promise((resolve) => {
            canvas.toBlob(resolve, 'image/webp', WEBP_QUALITY);
        });
    }

    function downscale(file) {
        return loadBitmap(file)
            .then((bitmap) => {
                const width = bitmap.width || bitmap.naturalWidth;
                const height = bitmap.height || bitmap.naturalHeight;
                if (!width || !height) {
                    return null;
                }

                const scale = Math.min(1, MAX_DIMENSION / Math.max(width, height));
                const canvas = document.createElement('canvas');
                canvas.width = Math.round(width * scale);
                canvas.height = Math.round(height * scale);
                canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
                if (typeof bitmap.close === 'function') {
                    bitmap.close();
                }

                return canvasToBlob(canvas).then((blob) => {
                    if (!blob) {
                        return null;
                    }
                    // Re-encoding a small, already-optimised file can make it
                    // heavier — keep the original when it is already acceptable.
                    if (blob.size >= file.size && ACCEPTED_TYPES.indexOf(file.type) !== -1) {
                        return file;
                    }
                    // Browsers without WebP encoding silently hand back a PNG.
                    const extension = blob.type === 'image/webp' ? 'webp' : 'png';
                    const base = file.name.replace(/\.[^.]+$/, '') || 'photo';

                    return new File([blob], base + '.' + extension, {
                        type: blob.type,
                        lastModified: Date.now(),
                    });
                });
            })
            .catch(() => null);
    }

    function prepare(file) {
        if (!file.type || file.type.indexOf('image/') !== 0) {
            return Promise.resolve(null);
        }

        return downscale(file).then((prepared) => {
            if (!prepared || prepared.size > MAX_BYTES) {
                return null;
            }

            return ACCEPTED_TYPES.indexOf(prepared.type) === -1 ? null : prepared;
        });
    }

    // ── Cards ───────────────────────────────────────────────────────────────

    function defaultAngleFor(tray, index) {
        const angles = (tray.dataset.trayDefaultAngles || '').split(',').filter(Boolean);
        if (angles.length === 0) {
            return null;
        }

        return angles[Math.min(index, angles.length - 1)];
    }

    function addCard(tray, file) {
        const template = tray.querySelector('[data-tray-card-template]');
        if (!template) {
            return null;
        }

        const defaultAngle = defaultAngleFor(tray, cardsOf(tray).length);
        const card = document.importNode(template.content.firstElementChild, true);

        // Insert before touching the <img>: a node cloned out of a <template>
        // still belongs to an inert document, where setting `src` loads
        // nothing and adoption alone does not retry it.
        tray.querySelector('[data-tray-grid]').appendChild(card);

        card.trayFile = file;
        showPreview(card.querySelector('[data-tray-thumb]'), file);

        const angleSelect = card.querySelector('[data-tray-angle]');
        if (angleSelect && defaultAngle) {
            angleSelect.value = defaultAngle;
        }

        const fileInput = card.querySelector('[data-tray-file]');
        if (fileInput) {
            const transfer = new DataTransfer();
            transfer.items.add(file);
            fileInput.files = transfer.files;
        }

        return card;
    }

    /**
     * The site-wide CSP allows `img-src 'self' data:` and nothing else, so a
     * blob: URL — the obvious choice here — renders as a broken image. Reading
     * the (already downscaled, few-hundred-KB) file as a data URL keeps the
     * preview working without loosening the header for the public shop.
     */
    function showPreview(image, file) {
        const reader = new FileReader();
        reader.onload = () => {
            image.src = reader.result;
        };
        reader.readAsDataURL(file);
    }

    function discardCard(card) {
        card.remove();
    }

    /**
     * Keeps the visible numbering and, in staged mode, the submitted field
     * names contiguous. Symfony's `allow_add` would accept gaps, but a stable
     * 0..n-1 run keeps the posted payload readable when something goes wrong.
     */
    function renumber(tray) {
        const staged = tray.dataset.trayMode === 'staged';
        const fieldName = tray.dataset.trayFieldName || '';

        cardsOf(tray).forEach((card, index) => {
            const label = card.querySelector('[data-tray-index]');
            if (label) {
                label.textContent = 'Photo ' + (index + 1);
            }
            if (!staged) {
                return;
            }

            const fileInput = card.querySelector('[data-tray-file]');
            const angleSelect = card.querySelector('[data-tray-angle]');
            if (fileInput) {
                fileInput.name = fieldName + '[' + index + '][file]';
            }
            if (angleSelect) {
                angleSelect.name = fieldName + '[' + index + '][angle]';
            }
        });
    }

    // ── Collapsing (narrow screens only) ────────────────────────────────────

    /**
     * Matches the breakpoint where `.ai-workspace__split` stops being two
     * columns: once the sources panel is full width it pushes the generated
     * visuals off screen, which is the whole reason to fold it. Above it the
     * CSS ignores the collapsed flag entirely.
     */
    const COLLAPSE_QUERY = '(max-width: 1100px)';

    function collapseApplies() {
        return window.matchMedia(COLLAPSE_QUERY).matches;
    }

    function syncCollapse(tray) {
        const toggle = tray.querySelector('[data-tray-toggle]');
        if (!toggle) {
            return;
        }
        // aria has to describe what the viewer actually sees, and above the
        // breakpoint the body is open whatever the flag says.
        const folded = collapseApplies() && tray.dataset.trayCollapsed === '1';
        toggle.setAttribute('aria-expanded', folded ? 'false' : 'true');
    }

    // ── Tray state ──────────────────────────────────────────────────────────

    function setBusy(tray, busy, message) {
        tray.dataset.trayBusy = busy ? '1' : '';
        tray.classList.toggle('photo-tray--busy', busy);

        const status = tray.querySelector('[data-tray-status]');
        if (status) {
            status.textContent = busy ? message || '' : '';
            status.hidden = !busy;
        }
        if (!busy) {
            setProgress(tray, null);
        }
    }

    function setProgress(tray, percent) {
        const bar = tray.querySelector('[data-tray-progress]');
        if (!bar) {
            return;
        }
        if (percent === null) {
            bar.hidden = true;
            bar.removeAttribute('value');

            return;
        }
        bar.hidden = false;
        bar.value = percent;
    }

    function refreshState(tray) {
        const total = cardsOf(tray).length;
        const pending = pendingCardsOf(tray).length;
        const max = intData(tray, 'trayMax', 4);
        const min = intData(tray, 'trayMin', 0);

        const counter = tray.querySelector('[data-tray-counter]');
        if (counter) {
            counter.textContent = total + ' / ' + max;
            counter.classList.toggle('is-valid', total >= min && total <= max);
            counter.classList.toggle('is-invalid', total < min);
        }

        const addZone = tray.querySelector('[data-tray-add]');
        if (addZone) {
            addZone.hidden = total >= max;
        }

        const emptyHint = tray.querySelector('[data-tray-empty]');
        if (emptyHint) {
            emptyHint.hidden = total > 0;
        }

        const footer = tray.querySelector('[data-tray-footer]');
        if (footer) {
            footer.hidden = pending === 0;
        }

        const confirmButton = tray.querySelector('[data-tray-confirm]');
        if (confirmButton) {
            confirmButton.textContent = pending === 1
                ? 'Téléverser la photo'
                : 'Téléverser les ' + pending + ' photos';
        }

        // Mirrored on the node so a listener that bound late (or a tray that
        // booted first) can still read the current state instead of waiting
        // for the next event.
        tray.dataset.trayTotal = String(total);
        tray.dataset.trayValid = total >= min && total <= max ? '1' : '0';

        tray.dispatchEvent(new CustomEvent('photo-tray:change', {
            bubbles: true,
            detail: {
                total: total,
                pending: pending,
                min: min,
                max: max,
                ready: total >= min && total <= max,
            },
        }));
    }

    /**
     * The workspace disables its generate buttons while the product has no
     * source photo. The poller owns that state, but it only runs while a
     * generation is in flight — so an upload has to sync it directly, without
     * stepping on a generation actually running right now.
     */
    function syncGenerateButtons(count) {
        const generating = document.querySelector('.ai-visual-tile--generating') !== null;
        document.querySelectorAll('[data-ai-generate-btn]').forEach((button) => {
            if (count > 0 && !generating) {
                button.removeAttribute('disabled');
            } else {
                button.setAttribute('disabled', 'disabled');
            }
        });
    }

    // ── Server round trips (live mode) ──────────────────────────────────────

    function replaceTray(oldTray, payload) {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = payload.html.trim();
        const newTray = wrapper.firstElementChild;
        if (!newTray) {
            window.location.reload();

            return;
        }

        // The server re-renders with its own default fold state; refolding the
        // panel under the viewer right after she uploaded into it would be rude.
        if (newTray.hasAttribute('data-tray-collapsible') && oldTray.dataset.trayCollapsed !== undefined) {
            newTray.dataset.trayCollapsed = oldTray.dataset.trayCollapsed;
        }

        oldTray.replaceWith(newTray);
        initTray(newTray);
        syncGenerateButtons(payload.count || 0);
    }

    function sendTray(tray, url, formData, busyMessage) {
        if (!url) {
            return;
        }

        setBusy(tray, true, busyMessage);
        setProgress(tray, 0);

        const request = new XMLHttpRequest();
        request.open('POST', url, true);
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        request.withCredentials = true;

        if (formData && request.upload) {
            request.upload.addEventListener('progress', (event) => {
                if (event.lengthComputable) {
                    setProgress(tray, Math.round((event.loaded / event.total) * 100));
                }
            });
        }

        request.addEventListener('load', () => {
            setBusy(tray, false);

            if (request.status < 200 || request.status >= 300) {
                toast('danger', 'Opération échouée (erreur ' + request.status + ').');

                return;
            }

            let payload = null;
            try {
                payload = JSON.parse(request.responseText);
            } catch (error) {
                payload = null;
            }

            if (!payload || typeof payload.html !== 'string') {
                window.location.reload();

                return;
            }

            replaceTray(tray, payload);
        });

        request.addEventListener('error', () => {
            setBusy(tray, false);
            toast('danger', 'Connexion perdue, réessayez.');
        });

        request.send(formData);
    }

    function uploadPending(tray) {
        const cards = pendingCardsOf(tray);
        if (cards.length === 0) {
            return;
        }

        const formData = new FormData();
        cards.forEach((card) => {
            formData.append('files[]', card.trayFile);
            const angleSelect = card.querySelector('[data-tray-angle]');
            formData.append('angles[]', angleSelect ? angleSelect.value : '');
        });

        sendTray(tray, tray.dataset.trayUploadUrl, formData, 'Envoi des photos…');
    }

    // ── Event handling (delegated: trays are injected into tabs after load) ──

    function handlePick(picker) {
        const tray = picker.closest('[data-photo-tray]');
        if (!tray || tray.dataset.trayBusy === '1') {
            return;
        }

        const files = Array.from(picker.files || []);
        // Reset now so re-picking the very same file still fires `change`.
        picker.value = '';
        if (files.length === 0) {
            return;
        }

        const max = intData(tray, 'trayMax', 4);
        let free = max - cardsOf(tray).length;
        if (free <= 0) {
            toast('warning', 'Maximum ' + max + ' photos sources. Supprimez-en une avant d\'en ajouter.');

            return;
        }

        setBusy(tray, true, files.length === 1 ? 'Préparation de la photo…' : 'Préparation des photos…');

        let rejected = 0;
        // Sequential on purpose: the arrival order decides the default angles
        // and the numbering the shop owner sees.
        const chain = files.reduce((previous, file) => previous.then(() => {
            if (free <= 0) {
                rejected += 1;

                return null;
            }

            return prepare(file).then((prepared) => {
                if (!prepared) {
                    rejected += 1;

                    return;
                }
                addCard(tray, prepared);
                free -= 1;
            });
        }), Promise.resolve());

        chain.then(() => {
            setBusy(tray, false);
            renumber(tray);
            refreshState(tray);

            if (rejected > 0) {
                toast('warning', rejected + ' photo(s) ignorée(s) — format illisible, ou maximum de ' + max + ' atteint.');
            }
        });
    }

    function handleRemove(button) {
        const card = button.closest('[data-tray-card]');
        const tray = button.closest('[data-photo-tray]');
        if (!card || !tray || tray.dataset.trayBusy === '1') {
            return;
        }

        if (card.hasAttribute('data-tray-pending')) {
            discardCard(card);
            renumber(tray);
            refreshState(tray);

            return;
        }

        confirmDialog('Supprimer cette photo source ?').then((confirmed) => {
            if (confirmed) {
                sendTray(tray, card.dataset.trayDeleteUrl, null, 'Suppression…');
            }
        });
    }

    function handleAngleChange(select) {
        const card = select.closest('[data-tray-card]');
        const tray = select.closest('[data-photo-tray]');
        if (!card || !tray || card.hasAttribute('data-tray-pending')) {
            // Staged cards carry their angle in the submitted form field, or in
            // the FormData built at upload — nothing to persist yet.
            return;
        }

        const url = card.dataset.trayAngleUrl;
        if (!url) {
            return;
        }

        const body = new FormData();
        body.append('angle', select.value);

        // Deliberately not swapping the tray back in: re-rendering under the
        // finger would close the native picker the shop owner just used.
        fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: body,
        }).catch(() => toast('danger', 'Angle non enregistré, réessayez.'));
    }

    document.addEventListener('change', (event) => {
        const picker = event.target.closest ? event.target.closest('[data-tray-picker]') : null;
        if (picker) {
            handlePick(picker);

            return;
        }

        const angleSelect = event.target.closest ? event.target.closest('[data-tray-angle]') : null;
        if (angleSelect) {
            handleAngleChange(angleSelect);
        }
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest) {
            return;
        }

        const toggle = event.target.closest('[data-tray-toggle]');
        if (toggle) {
            event.preventDefault();
            const tray = toggle.closest('[data-photo-tray]');
            // Above the breakpoint the head is a plain title: clicking it does
            // nothing rather than flipping a flag with no visible effect.
            if (tray && collapseApplies()) {
                tray.dataset.trayCollapsed = tray.dataset.trayCollapsed === '1' ? '0' : '1';
                syncCollapse(tray);
            }

            return;
        }

        const removeButton = event.target.closest('[data-tray-remove]');
        if (removeButton) {
            event.preventDefault();
            handleRemove(removeButton);

            return;
        }

        const confirmButton = event.target.closest('[data-tray-confirm]');
        if (confirmButton) {
            event.preventDefault();
            const tray = confirmButton.closest('[data-photo-tray]');
            if (tray && tray.dataset.trayBusy !== '1') {
                uploadPending(tray);
            }

            return;
        }

        const discardButton = event.target.closest('[data-tray-discard]');
        if (discardButton) {
            event.preventDefault();
            const tray = discardButton.closest('[data-photo-tray]');
            if (tray) {
                pendingCardsOf(tray).forEach(discardCard);
                renumber(tray);
                refreshState(tray);
            }
        }
    });

    // Drag & drop onto the add zone — desktop convenience, the picker stays the
    // only path on touch.
    document.addEventListener('dragover', (event) => {
        const zone = event.target.closest ? event.target.closest('[data-tray-add]') : null;
        if (zone) {
            event.preventDefault();
            zone.classList.add('is-dragover');
        }
    });

    document.addEventListener('dragleave', (event) => {
        const zone = event.target.closest ? event.target.closest('[data-tray-add]') : null;
        if (zone) {
            zone.classList.remove('is-dragover');
        }
    });

    document.addEventListener('drop', (event) => {
        const zone = event.target.closest ? event.target.closest('[data-tray-add]') : null;
        if (!zone) {
            return;
        }
        event.preventDefault();
        zone.classList.remove('is-dragover');

        const picker = zone.querySelector('[data-tray-picker]');
        if (!picker || !event.dataTransfer || event.dataTransfer.files.length === 0) {
            return;
        }
        picker.files = event.dataTransfer.files;
        handlePick(picker);
    });

    // ── Bootstrap ───────────────────────────────────────────────────────────

    function initTray(tray) {
        if (tray.dataset.trayReady === '1') {
            return;
        }
        tray.dataset.trayReady = '1';
        renumber(tray);
        refreshState(tray);
        syncCollapse(tray);
    }

    function initAll() {
        document.querySelectorAll('[data-photo-tray]').forEach(initTray);
    }

    function boot() {
        initAll();

        // Crossing the breakpoint (rotation, resize) changes what is on screen
        // without touching the flag, so aria has to be recomputed.
        const query = window.matchMedia(COLLAPSE_QUERY);
        const resync = () => document.querySelectorAll('[data-photo-tray]').forEach(syncCollapse);
        if (typeof query.addEventListener === 'function') {
            query.addEventListener('change', resync);
        }

        // The edit page moves the workspace into its tab pane after load, so a
        // tray can appear well after DOMContentLoaded.
        new MutationObserver(initAll).observe(document.body, { childList: true, subtree: true });
    }

    window.AdminConfirm = confirmDialog;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
