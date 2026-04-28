import { Controller } from '@hotwired/stimulus';

/**
 * Inline category drawer panel for the shop toolbar.
 * Shares the expansion pattern of stone_drawer (max-height transition,
 * mutual exclusion via shop-toolbar:open) but is single-select and
 * relies on regular <a> links for navigation — no apply button.
 *
 * Includes a client-side search that filters parent tiles and their
 * subcategory rows based on the typed term.
 */
export default class extends Controller {
    static targets = ['panel', 'button', 'tile', 'subcategory', 'search', 'emptyState'];

    connect() {
        this._onKeydown = this._onKeydown.bind(this);
        this._onSiblingOpen = this._onSiblingOpen.bind(this);
        document.addEventListener('keydown', this._onKeydown);
        document.addEventListener('shop-toolbar:open', this._onSiblingOpen);
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
        document.dispatchEvent(new CustomEvent('shop-toolbar:open', { detail: { source: 'category-panel' } }));

        this._isClosing = false;

        this.panelTarget.classList.remove('hidden');
        void this.panelTarget.offsetHeight;

        const fullHeight = this.panelTarget.scrollHeight;
        this.panelTarget.style.maxHeight = `${fullHeight}px`;
        this.panelTarget.style.opacity = '1';

        if (this.hasButtonTarget) {
            this.buttonTarget.setAttribute('aria-expanded', 'true');
        }

        setTimeout(() => {
            if (this._isOpen()) {
                this.panelTarget.style.maxHeight = 'none';
            }
        }, 320);

        if (this.hasSearchTarget) {
            setTimeout(() => this.searchTarget.focus(), 320);
        }
    }

    close() {
        if (!this._isOpen()) {
            return;
        }
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

    /**
     * Filter visible tiles based on the typed term. A tile is shown when
     * its own name matches OR any of its subcategory rows match. Subcategory
     * rows themselves are hidden when they do not match the term.
     */
    filter(event) {
        const term = (event.currentTarget.value || '').trim().toLowerCase();
        let visibleTiles = 0;

        this.tileTargets.forEach((tile) => {
            const tileName = (tile.dataset.categoryName || '').toLowerCase();
            const subs = tile.querySelectorAll('[data-category-panel-target="subcategory"]');
            let subVisible = 0;

            subs.forEach((sub) => {
                const subName = (sub.dataset.categoryName || '').toLowerCase();
                const match = term === '' || subName.includes(term);
                sub.classList.toggle('hidden', !match);
                if (match) {
                    subVisible += 1;
                }
            });

            const tileMatches = term === '' || tileName.includes(term) || subVisible > 0;
            tile.classList.toggle('hidden', !tileMatches);
            if (tileMatches) {
                visibleTiles += 1;
            }
        });

        if (this.hasEmptyStateTarget) {
            this.emptyStateTarget.classList.toggle('hidden', visibleTiles !== 0);
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
        if (event.detail?.source === 'category-panel') {
            return;
        }
        this.close();
    }
}
