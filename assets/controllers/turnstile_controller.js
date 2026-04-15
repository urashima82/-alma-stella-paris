import { Controller } from '@hotwired/stimulus';

/**
 * Loads and renders a Cloudflare Turnstile widget.
 *
 * Usage:
 *   <div data-controller="turnstile"
 *        data-turnstile-site-key-value="your-site-key">
 *   </div>
 *
 * When the site key is empty, the controller does nothing (dev mode).
 */
export default class extends Controller {
    static values = {
        siteKey: String,
    };

    connect() {
        if (!this.siteKeyValue) {
            return;
        }

        this._loadScript().then(() => {
            window.turnstile.render(this.element, {
                sitekey: this.siteKeyValue,
                theme: 'light',
            });
        });
    }

    disconnect() {
        if (window.turnstile && this.element.dataset.turnstileWidgetId) {
            window.turnstile.remove(this.element.dataset.turnstileWidgetId);
        }
    }

    _loadScript() {
        if (window.turnstile) {
            return Promise.resolve();
        }

        if (this.constructor._loadPromise) {
            return this.constructor._loadPromise;
        }

        this.constructor._loadPromise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
            script.async = true;
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });

        return this.constructor._loadPromise;
    }
}
