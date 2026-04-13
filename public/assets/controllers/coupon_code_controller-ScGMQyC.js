import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'hiddenInput', 'error', 'success', 'form', 'applied'];
    static values = { validateUrl: String };

    async validate() {
        const code = this.inputTarget.value.trim().toUpperCase();

        if (!code) {
            this.showError('');
            return;
        }

        this.hideMessages();

        try {
            const response = await fetch(this.validateUrlValue, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ code }),
            });

            const data = await response.json();

            if (data.valid) {
                this.showSuccess(data.message);
                this.hiddenInputTarget.value = code;
                this.inputTarget.value = code;
            } else {
                this.showError(data.message);
                this.hiddenInputTarget.value = '';
            }
        } catch {
            this.showError('An error occurred. Please try again.');
        }
    }

    remove() {
        this.hiddenInputTarget.value = '';
        // Reload page without coupon
        const url = new URL(window.location.href);
        url.searchParams.delete('promo');
        window.location.href = url.toString();
    }

    showError(message) {
        if (this.hasErrorTarget) {
            this.errorTarget.textContent = message;
            this.errorTarget.classList.toggle('hidden', !message);
        }
        if (this.hasSuccessTarget) {
            this.successTarget.classList.add('hidden');
        }
    }

    showSuccess(message) {
        if (this.hasSuccessTarget) {
            this.successTarget.textContent = message;
            this.successTarget.classList.remove('hidden');
        }
        if (this.hasErrorTarget) {
            this.errorTarget.classList.add('hidden');
        }
    }

    hideMessages() {
        if (this.hasErrorTarget) this.errorTarget.classList.add('hidden');
        if (this.hasSuccessTarget) this.successTarget.classList.add('hidden');
    }
}
