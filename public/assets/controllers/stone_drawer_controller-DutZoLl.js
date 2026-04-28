import { Controller } from '@hotwired/stimulus';

/**
 * Immersive drawer for selecting stones from the shop toolbar.
 * Manages: open/close (smooth max-height), multi-select state, client-side
 * search filtering, "no stone" toggle (mutually exclusive with stones), and
 * navigation to ?stones=slug1,slug2 (or ?stones=none) on apply.
 */
export default class extends Controller {
    static targets = [
        'panel',
        'button',
        'tile',
        'noStoneTile',
        'search',
        'apply',
        'applyCount',
        'badge',
        'avatar',
        'avatarStack',
        'emptyState',
        'clearButton',
    ];

    static values = {
        baseUrl: String,
        applyLabelMany: String,
        applyLabelOne: String,
        applyLabelNone: String,
    };

    connect() {
        this._onKeydown = this._onKeydown.bind(this);
        this._onSiblingOpen = this._onSiblingOpen.bind(this);
        document.addEventListener('keydown', this._onKeydown);
        document.addEventListener('shop-toolbar:open', this._onSiblingOpen);

        this._refreshState();
    }

    disconnect() {
        document.removeEventListener('keydown', this._onKeydown);
        document.removeEventListener('shop-toolbar:open', this._onSiblingOpen);
    }

    toggle(event) {
        event.preventDefault();
        event.stopPropagation();

        if (this._isOpen()) {
            this.close();
        } else {
            this.open();
        }
    }

    open() {
        document.dispatchEvent(new CustomEvent('shop-toolbar:open', { detail: { source: 'stone-drawer' } }));

        // Cancel any pending close so a fast re-open survives the transition.
        this._isClosing = false;

        this.panelTarget.classList.remove('hidden');
        // Force reflow so the height transition runs from 0.
        void this.panelTarget.offsetHeight;

        const fullHeight = this.panelTarget.scrollHeight;
        this.panelTarget.style.maxHeight = `${fullHeight}px`;
        this.panelTarget.style.opacity = '1';

        if (this.hasButtonTarget) {
            this.buttonTarget.setAttribute('aria-expanded', 'true');
        }

        // After the transition ends, lift the cap so internal content can grow
        // (e.g. when the user types in the search field and the layout shifts).
        setTimeout(() => {
            if (this._isOpen()) {
                this.panelTarget.style.maxHeight = 'none';
            }
        }, 320);

        if (this.hasSearchTarget) {
            // Defer focus so it does not jump the page during the slide-in.
            setTimeout(() => this.searchTarget.focus(), 320);
        }
    }

    close() {
        if (!this._isOpen()) {
            return;
        }
        // Snap back to the current height so the closing transition runs.
        this.panelTarget.style.maxHeight = `${this.panelTarget.scrollHeight}px`;
        void this.panelTarget.offsetHeight;
        this.panelTarget.style.maxHeight = '0px';
        this.panelTarget.style.opacity = '0';

        if (this.hasButtonTarget) {
            this.buttonTarget.setAttribute('aria-expanded', 'false');
        }

        this._isClosing = true;
        setTimeout(() => {
            if (this._isClosing) {
                this.panelTarget.classList.add('hidden');
                this._isClosing = false;
            }
        }, 320);
    }

    toggleStone(event) {
        event.preventDefault();
        const tile = event.currentTarget;
        const pressed = tile.getAttribute('aria-pressed') === 'true';
        tile.setAttribute('aria-pressed', String(!pressed));

        // A stone selection cancels the "no stone" filter.
        if (!pressed && this.hasNoStoneTileTarget) {
            this.noStoneTileTarget.setAttribute('aria-pressed', 'false');
        }

        this._refreshState();
    }

    toggleNoStone(event) {
        event.preventDefault();
        const tile = event.currentTarget;
        const pressed = tile.getAttribute('aria-pressed') === 'true';
        tile.setAttribute('aria-pressed', String(!pressed));

        // "No stone" cancels any individual stone selection.
        if (!pressed) {
            this.tileTargets.forEach((t) => t.setAttribute('aria-pressed', 'false'));
        }

        this._refreshState();
    }

    filter(event) {
        const term = (event.currentTarget.value || '').trim().toLowerCase();
        let visible = 0;
        this.tileTargets.forEach((tile) => {
            const name = (tile.dataset.stoneName || '').toLowerCase();
            const match = term === '' || name.includes(term);
            tile.classList.toggle('hidden', !match);
            if (match) {
                visible += 1;
            }
        });

        if (this.hasEmptyStateTarget) {
            this.emptyStateTarget.classList.toggle('hidden', visible !== 0);
        }
    }

    clearAll(event) {
        if (event) {
            event.preventDefault();
        }
        this.tileTargets.forEach((tile) => tile.setAttribute('aria-pressed', 'false'));
        if (this.hasNoStoneTileTarget) {
            this.noStoneTileTarget.setAttribute('aria-pressed', 'false');
        }
        this._refreshState();
    }

    apply(event) {
        if (event) {
            event.preventDefault();
        }
        const url = new URL(this.baseUrlValue, window.location.origin);

        if (this.hasNoStoneTileTarget && this.noStoneTileTarget.getAttribute('aria-pressed') === 'true') {
            url.searchParams.set('stones', 'none');
        } else {
            const selected = this.tileTargets
                .filter((tile) => tile.getAttribute('aria-pressed') === 'true')
                .map((tile) => tile.dataset.stoneSlug);
            if (selected.length > 0) {
                url.searchParams.set('stones', selected.join(','));
            } else {
                url.searchParams.delete('stones');
            }
        }

        url.searchParams.delete('page');
        window.location.assign(url.toString());
    }

    _refreshState() {
        const selectedTiles = this.tileTargets.filter((t) => t.getAttribute('aria-pressed') === 'true');
        const count = selectedTiles.length;
        const noStoneActive = this.hasNoStoneTileTarget && this.noStoneTileTarget.getAttribute('aria-pressed') === 'true';

        if (this.hasBadgeTarget) {
            const total = noStoneActive ? 1 : count;
            if (total === 0) {
                this.badgeTarget.classList.add('hidden');
                this.badgeTarget.textContent = '';
            } else {
                this.badgeTarget.classList.remove('hidden');
                this.badgeTarget.textContent = String(total);
            }
        }

        if (this.hasAvatarStackTarget) {
            const stack = this.avatarStackTarget;
            stack.innerHTML = '';
            if (!noStoneActive) {
                selectedTiles.slice(0, 3).forEach((tile) => {
                    const src = tile.dataset.stoneImage;
                    if (!src) {
                        return;
                    }
                    const img = document.createElement('img');
                    img.src = src;
                    img.alt = '';
                    img.loading = 'lazy';
                    img.className = 'w-5 h-5 rounded-full object-cover ring-2 ring-alma-text -ml-1.5 first:ml-0';
                    stack.appendChild(img);
                });
                stack.classList.toggle('hidden', selectedTiles.length === 0);
            } else {
                stack.classList.add('hidden');
            }
        }

        if (this.hasApplyTarget) {
            const labelEl = this.hasApplyCountTarget ? this.applyCountTarget : this.applyTarget;
            let label;
            if (noStoneActive) {
                label = this.applyLabelNoneValue;
            } else if (count === 0) {
                label = this.applyLabelManyValue.replace('%count%', '0');
            } else if (count === 1) {
                label = this.applyLabelOneValue;
            } else {
                label = this.applyLabelManyValue.replace('%count%', String(count));
            }
            labelEl.textContent = label;
        }

        if (this.hasClearButtonTarget) {
            this.clearButtonTarget.classList.toggle('hidden', count === 0 && !noStoneActive);
        }
    }

    _isOpen() {
        return !this.panelTarget.classList.contains('hidden');
    }

    _onKeydown(event) {
        if (event.key === 'Escape' && this._isOpen()) {
            this.close();
        }
    }

    _onSiblingOpen(event) {
        if (event.detail?.source === 'stone-drawer') {
            return;
        }
        this.close();
    }
}
