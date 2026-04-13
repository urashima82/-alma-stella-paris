import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['drawer', 'overlay', 'itemList', 'subtotal', 'badge', 'emptyState', 'footer', 'checkoutLink'];
    static values = {
        contentUrl: String,
        removeUrl: String,
        locale: String,
    };

    connect() {
        this._closeHandler = this._onKeydown.bind(this);
        document.addEventListener('keydown', this._closeHandler);
        this.refreshCount();
    }

    disconnect() {
        document.removeEventListener('keydown', this._closeHandler);
    }

    open() {
        this.drawerTarget.classList.remove('translate-x-full');
        this.overlayTarget.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        this.loadContent();
    }

    close() {
        this.drawerTarget.classList.add('translate-x-full');
        this.overlayTarget.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    async addToCart(event) {
        event.preventDefault();

        const button = event.currentTarget;
        const url = button.dataset.cartAddUrl;

        if (!url) return;

        button.disabled = true;

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const data = await response.json();

            if (data.success) {
                this.updateBadge(data.count);
                this._switchButtonToInCart(button);
                this.open();
            }
        } finally {
            button.disabled = false;
        }
    }

    async removeItem(event) {
        event.preventDefault();

        const button = event.currentTarget;
        const url = button.dataset.removeUrl;

        if (!url) return;

        // Extract product ID from URL: /{locale}/cart/remove/{id}
        const productId = url.split('/').pop();

        const item = button.closest('[data-cart-item]');
        if (item) {
            item.style.opacity = '0.5';
        }

        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const data = await response.json();

        if (data.success) {
            this.updateBadge(data.count);
            this._switchButtonToAddToCart(productId);
            this.loadContent();
        }
    }

    async loadContent() {
        const response = await fetch(this.contentUrlValue, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const data = await response.json();

        this.renderItems(data);
    }

    async refreshCount() {
        try {
            const response = await fetch(this.contentUrlValue, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const data = await response.json();
            this.updateBadge(data.count);
        } catch {
            // Silently fail on initial load
        }
    }

    renderItems(data) {
        if (data.count === 0) {
            this.itemListTarget.innerHTML = '';
            this.emptyStateTarget.classList.remove('hidden');
            this.footerTarget.classList.add('hidden');
            return;
        }

        this.emptyStateTarget.classList.add('hidden');
        this.footerTarget.classList.remove('hidden');

        const locale = this.localeValue;
        const productRoute = locale === 'fr' ? '/fr/produit/' : '/en/product/';

        let html = '';
        data.items.forEach(item => {
            html += `
                <div class="flex gap-4 py-4 border-b border-alma-hover" data-cart-item>
                    <a href="${productRoute}${item.slug}" class="shrink-0 w-20 h-20 bg-alma-surface overflow-hidden">
                        ${item.image
                            ? `<img src="${item.image}" alt="${item.name}" class="w-full h-full object-cover">`
                            : `<div class="w-full h-full flex items-center justify-center text-alma-gold text-2xl">✦</div>`
                        }
                    </a>
                    <div class="flex-1 min-w-0">
                        <a href="${productRoute}${item.slug}" class="font-serif text-sm text-alma-text hover:text-alma-gold transition-colors line-clamp-2">
                            ${item.name}
                        </a>
                        <p class="font-sans text-sm font-semibold text-alma-text mt-1">
                            ${item.hasDiscount
                                ? `<span class="text-alma-text-muted line-through text-xs font-normal mr-1">${item.originalPriceFormatted}</span>`
                                : ''
                            }${item.priceFormatted}
                        </p>
                    </div>
                    <button type="button"
                            class="shrink-0 self-start text-alma-text-muted hover:text-alma-text transition-colors p-1"
                            data-action="click->cart-drawer#removeItem"
                            data-remove-url="/${locale}/cart/remove/${item.id}"
                            aria-label="Remove">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            `;
        });

        this.itemListTarget.innerHTML = html;
        this.subtotalTarget.textContent = data.subtotalFormatted;
    }

    updateBadge(count) {
        if (this.hasBadgeTarget) {
            if (count > 0) {
                this.badgeTarget.textContent = count;
                this.badgeTarget.classList.remove('hidden');
            } else {
                this.badgeTarget.classList.add('hidden');
            }
        }
    }

    _switchButtonToInCart(button) {
        const label = button.dataset.cartLabelInCart;
        if (!label) return;

        button.classList.remove('bg-alma-gold', 'text-white', 'hover:bg-alma-gold/90');
        button.classList.add('bg-alma-surface', 'text-alma-text', 'border', 'border-alma-hover', 'hover:border-alma-gold');

        button.innerHTML = `
            <span class="flex items-center justify-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-alma-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
                ${label}
            </span>
        `;

        button.dataset.action = 'click->cart-drawer#open';
    }

    _switchButtonToAddToCart(productId) {
        const button = this.element.querySelector(`[data-cart-button][data-cart-product-id="${productId}"]`);
        if (!button) return;

        const label = button.dataset.cartLabelAdd;
        if (!label) return;

        button.classList.remove('bg-alma-surface', 'text-alma-text', 'border', 'border-alma-hover', 'hover:border-alma-gold');
        button.classList.add('bg-alma-gold', 'text-white', 'hover:bg-alma-gold/90');

        button.textContent = label;
        button.dataset.action = 'click->cart-drawer#addToCart';
    }

    _onKeydown(event) {
        if (event.key === 'Escape') {
            this.close();
        }
    }
}
