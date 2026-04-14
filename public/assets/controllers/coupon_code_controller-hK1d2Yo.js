import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'hiddenInput', 'error', 'success', 'form', 'applied'];
    static values = { validateUrl: String, removeUrl: String };

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
                this.hiddenInputTarget.value = code;
                window.location.reload();
            } else {
                this.showError(data.message);
                this.hiddenInputTarget.value = '';
            }
        } catch {
            this.showError('An error occurred. Please try again.');
        }
    }

    async remove() {
        try {
            await fetch(this.removeUrlValue, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
        } catch {
            // Proceed with reload even if the request fails
        }

        this.hiddenInputTarget.value = '';
        window.location.reload();
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
