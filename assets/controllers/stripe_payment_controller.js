import { Controller } from '@hotwired/stimulus';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

export default class extends Controller {
    static values = {
        publicKey: String,
        clientSecret: String,
        confirmUrl: String,
        returnUrl: String,
        locale: { type: String, default: 'en' },
    };

    static targets = ['element', 'submit', 'error', 'terms'];

    async connect() {
        // Wait for Stripe.js to load from CDN (deferred script may not be ready yet)
        try {
            await this.waitForStripe();
        } catch {
            this.showError('Stripe.js failed to load.');
            return;
        }

        this.stripe = Stripe(this.publicKeyValue);
        this.isProcessing = false;
        this.hasReturnError = false;
        this.stripeReady = false;

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
            this.stripeReady = true;
            this.updateSubmitState();

            if (this.hasReturnError) {
                const msg = this.localeValue === 'fr'
                    ? 'Le paiement n\u2019a pas abouti. Veuillez réessayer.'
                    : 'Payment was not completed. Please try again.';
                this.showError(msg);
            }
        });

        this.paymentElement.on('change', (event) => {
            if (event.error) {
                this.showError(event.error.message);
                this.hasReturnError = false;
            } else if (!this.hasReturnError) {
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
            this.hasReturnError = true;
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
        this.hasReturnError = false;
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
            this.isProcessing = false;
            this.updateSubmitState();
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
                    'X-CSRF-Token': csrfToken(),
                },
            });

            const data = await response.json();

            if (data.success && data.redirectUrl) {
                window.location.href = data.redirectUrl;
            } else {
                this.showError(data.error || 'Payment confirmation failed.');
                this.isProcessing = false;
                this.updateSubmitState();
            }
        } catch {
            this.showError('An unexpected error occurred.');
            this.isProcessing = false;
            this.updateSubmitState();
        }
    }

    toggleTerms() {
        this.updateSubmitState();
    }

    updateSubmitState() {
        const termsAccepted = this.hasTermsTarget && this.termsTarget.checked;
        this.submitTarget.disabled = !this.stripeReady || !termsAccepted || this.isProcessing;
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

    waitForStripe(timeout = 10000) {
        return new Promise((resolve, reject) => {
            if (typeof Stripe !== 'undefined') {
                resolve();
                return;
            }

            const interval = setInterval(() => {
                if (typeof Stripe !== 'undefined') {
                    clearInterval(interval);
                    resolve();
                }
            }, 100);

            setTimeout(() => {
                clearInterval(interval);
                reject(new Error('Stripe.js timeout'));
            }, timeout);
        });
    }
}
