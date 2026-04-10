import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        publicKey: String,
        clientSecret: String,
        confirmUrl: String,
        returnUrl: String,
        locale: { type: String, default: 'en' },
    };

    static targets = ['element', 'submit', 'error'];

    async connect() {
        // Wait for Stripe.js to load from CDN
        if (typeof Stripe === 'undefined') {
            this.showError('Stripe.js failed to load.');
            return;
        }

        this.stripe = Stripe(this.publicKeyValue);
        this.isProcessing = false;

        // Check if returning from a 3DS redirect
        const params = new URLSearchParams(window.location.search);
        if (params.has('payment_intent')) {
            await this.handleReturn(params.get('payment_intent'));
            return;
        }

        this.mountPaymentElement();
    }

    mountPaymentElement() {
        const appearance = {
            theme: 'stripe',
            variables: {
                colorPrimary: '#C9A84C',
                colorBackground: '#FFFFFF',
                colorText: '#2C2418',
                fontFamily: 'Inter, sans-serif',
                borderRadius: '0px',
            },
        };

        this.elements = this.stripe.elements({
            clientSecret: this.clientSecretValue,
            appearance,
            locale: this.localeValue,
        });

        this.paymentElement = this.elements.create('payment', {
            layout: 'tabs',
        });

        this.paymentElement.mount(this.elementTarget);

        this.paymentElement.on('ready', () => {
            this.submitTarget.disabled = false;
        });

        this.paymentElement.on('change', (event) => {
            if (event.error) {
                this.showError(event.error.message);
            } else {
                this.clearError();
            }
        });
    }

    async handleReturn(paymentIntentId) {
        this.submitTarget.disabled = true;
        this.elementTarget.innerHTML = '<p class="text-center text-sm text-alma-text-muted py-8">Verifying payment…</p>';

        const { paymentIntent } = await this.stripe.retrievePaymentIntent(this.clientSecretValue);

        if (paymentIntent && paymentIntent.status === 'succeeded') {
            await this.confirmOnServer();
        } else {
            this.showError('Payment was not completed. Please try again.');
            this.mountPaymentElement();
        }
    }

    async submit(event) {
        event.preventDefault();

        if (this.isProcessing) {
            return;
        }

        this.isProcessing = true;
        this.submitTarget.disabled = true;
        this.clearError();

        const { error } = await this.stripe.confirmPayment({
            elements: this.elements,
            confirmParams: {
                return_url: this.returnUrlValue,
            },
            redirect: 'if_required',
        });

        if (error) {
            this.showError(error.message);
            this.submitTarget.disabled = false;
            this.isProcessing = false;
            return;
        }

        // Payment succeeded without redirect — confirm on server side
        await this.confirmOnServer();
    }

    async confirmOnServer() {
        try {
            const response = await fetch(this.confirmUrlValue, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const data = await response.json();

            if (data.success && data.redirectUrl) {
                window.location.href = data.redirectUrl;
            } else {
                this.showError(data.error || 'Payment confirmation failed.');
                this.submitTarget.disabled = false;
                this.isProcessing = false;
            }
        } catch {
            this.showError('An unexpected error occurred.');
            this.submitTarget.disabled = false;
            this.isProcessing = false;
        }
    }

    showError(message) {
        if (this.hasErrorTarget) {
            this.errorTarget.textContent = message;
            this.errorTarget.classList.remove('hidden');
        }
    }

    clearError() {
        if (this.hasErrorTarget) {
            this.errorTarget.textContent = '';
            this.errorTarget.classList.add('hidden');
        }
    }
}
