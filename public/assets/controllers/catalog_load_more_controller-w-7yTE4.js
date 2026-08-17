import { Controller } from '@hotwired/stimulus';

/**
 * Progressive "load more" for the catalog grid.
 *
 * - Auto-loads the next page (via IntersectionObserver) up to `autoLoadMax` times,
 *   then requires a click on the "load more" button.
 * - The button is a real <a href="?page=N"> so crawlers and no-JS visitors keep
 *   the classic pagination (which this controller hides when it connects).
 * - Back/forward navigation restores the previously loaded cards and scroll
 *   position from sessionStorage, so returning from a product page does not
 *   reset the visitor to the first 12 items.
 */
export default class extends Controller {
    static targets = ['grid', 'sentinel', 'loadMoreArea', 'button', 'spinner', 'progress', 'pagination'];
    static values = {
        page: Number,
        lastPage: Number,
        total: Number,
        autoLoadMax: { type: Number, default: 3 },
        progressLabel: String,
    };

    connect() {
        this.autoLoadCount = 0;
        this.loading = false;
        this.ajaxFailed = false;
        this.serverPage = this.pageValue;
        this.storageKey = `catalog-load-more:${window.location.pathname}${window.location.search}`;

        this._restoreIfBackNavigation();

        // JS is active: swap the classic pagination for the load-more UI.
        if (this.hasPaginationTarget) {
            this.paginationTarget.classList.add('hidden');
        }
        if (this.hasLoadMoreAreaTarget) {
            this.loadMoreAreaTarget.classList.remove('hidden');
        }
        this._refreshUi();

        this.saveState = this._saveState.bind(this);
        window.addEventListener('pagehide', this.saveState);

        if ('IntersectionObserver' in window) {
            this.observer = new IntersectionObserver(
                (entries) => this._onSentinelVisible(entries),
                { rootMargin: '400px 0px' },
            );
            if (this.hasSentinelTarget && this.pageValue < this.lastPageValue) {
                this.observer.observe(this.sentinelTarget);
            }
        } else {
            // No auto-load possible: show the manual button right away.
            this.autoLoadCount = this.autoLoadMaxValue;
            this._refreshUi();
        }
    }

    disconnect() {
        this.observer?.disconnect();
        window.removeEventListener('pagehide', this.saveState);
    }

    loadMoreFromClick(event) {
        if (this.ajaxFailed) {
            return; // Let the link do a full navigation as a fallback.
        }
        event.preventDefault();
        this._loadMore();
    }

    _onSentinelVisible(entries) {
        const isVisible = entries.some((entry) => entry.isIntersecting);

        if (!isVisible || this.loading || this.autoLoadCount >= this.autoLoadMaxValue) {
            return;
        }
        this.autoLoadCount++;
        this._loadMore();
    }

    async _loadMore() {
        if (this.loading || this.pageValue >= this.lastPageValue) {
            return;
        }
        this.loading = true;
        this._setSpinner(true);

        try {
            const response = await fetch(this._nextUrl(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            const html = await response.text();
            this.gridTarget.insertAdjacentHTML('beforeend', html);
            this.pageValue++;
        } catch (error) {
            // Next click on the button falls back to a full page load.
            this.ajaxFailed = true;
            this.autoLoadCount = this.autoLoadMaxValue;
        } finally {
            this.loading = false;
            this._setSpinner(false);
            this._refreshUi();
        }
    }

    _nextUrl() {
        const url = new URL(window.location.href);
        url.searchParams.set('page', String(this.pageValue + 1));

        return url.toString();
    }

    _refreshUi() {
        const done = this.pageValue >= this.lastPageValue;

        if (done) {
            this.observer?.disconnect();
            if (this.hasLoadMoreAreaTarget) {
                this.loadMoreAreaTarget.classList.add('hidden');
            }

            return;
        }

        if (this.hasButtonTarget) {
            this.buttonTarget.href = this._nextUrl();
            this.buttonTarget.classList.toggle('hidden', this.autoLoadCount < this.autoLoadMaxValue);
        }
        if (this.hasProgressTarget && this.progressLabelValue) {
            const shown = this.gridTarget.querySelectorAll('article').length;
            this.progressTarget.textContent = this.progressLabelValue
                .replace('%shown%', String(shown))
                .replace('%total%', String(this.totalValue));
        }
    }

    _setSpinner(visible) {
        if (this.hasSpinnerTarget) {
            this.spinnerTarget.classList.toggle('hidden', !visible);
        }
        if (this.hasButtonTarget && visible) {
            this.buttonTarget.classList.add('hidden');
        }
    }

    _saveState() {
        if (this.pageValue <= this.serverPage) {
            sessionStorage.removeItem(this.storageKey);

            return;
        }
        try {
            sessionStorage.setItem(this.storageKey, JSON.stringify({
                html: this.gridTarget.innerHTML,
                page: this.pageValue,
                autoLoadCount: this.autoLoadCount,
                scrollY: window.scrollY,
            }));
        } catch (error) {
            // Quota exceeded — restoration is a progressive enhancement, ignore.
        }
    }

    _restoreIfBackNavigation() {
        const navigation = performance.getEntriesByType?.('navigation')?.[0];

        if (navigation?.type !== 'back_forward') {
            // Fresh visit or reload: start clean from the server-rendered page.
            sessionStorage.removeItem(this.storageKey);

            return;
        }

        const raw = sessionStorage.getItem(this.storageKey);
        if (raw === null) {
            return;
        }

        let state;
        try {
            state = JSON.parse(raw);
        } catch (error) {
            sessionStorage.removeItem(this.storageKey);

            return;
        }

        if (typeof state.page !== 'number' || state.page <= this.pageValue || typeof state.html !== 'string') {
            return;
        }

        this.gridTarget.innerHTML = state.html;
        this.pageValue = state.page;
        this.autoLoadCount = typeof state.autoLoadCount === 'number' ? state.autoLoadCount : this.autoLoadMaxValue;

        const scrollY = typeof state.scrollY === 'number' ? state.scrollY : 0;
        requestAnimationFrame(() => window.scrollTo(0, scrollY));
    }
}
