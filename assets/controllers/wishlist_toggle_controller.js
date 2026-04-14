import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        url: String,
        authenticated: Boolean,
        loginUrl: String,
    };

    async toggle(event) {
        event.preventDefault();
        event.stopPropagation();

        if (!this.authenticatedValue) {
            window.location.href = this.loginUrlValue;
            return;
        }

        const button = this.element;
        button.disabled = true;

        try {
            const response = await fetch(this.urlValue, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const data = await response.json();

            if (data.success) {
                this._updateIcon(data.isWishlisted);
                this._updateNavCount(data.count);
            }
        } finally {
            button.disabled = false;
        }
    }

    _updateIcon(isWishlisted) {
        const filled = this.element.querySelector('[data-wishlist-filled]');
        const outline = this.element.querySelector('[data-wishlist-outline]');

        if (filled && outline) {
            filled.classList.toggle('hidden', !isWishlisted);
            outline.classList.toggle('hidden', isWishlisted);
        }
    }

    _updateNavCount(count) {
        const badge = document.querySelector('[data-wishlist-nav-count]');
        if (badge) {
            badge.textContent = count;
            badge.classList.toggle('hidden', count === 0);
        }
    }
}
