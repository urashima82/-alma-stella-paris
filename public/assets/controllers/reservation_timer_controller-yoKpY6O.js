import { Controller } from '@hotwired/stimulus';

/**
 * Reservation countdown timer.
 *
 * Displays a mm:ss countdown. When it reaches 0 the "expired" block is shown
 * and the page reloads after a short delay so the server can clean up the cart.
 */
export default class extends Controller {
    static targets = ['countdown', 'container', 'expired'];
    static values = {
        seconds: Number,
        expiredText: String,
    };

    connect() {
        this.remaining = this.secondsValue;
        this.render();
        this.timer = setInterval(() => this.tick(), 1000);
    }

    disconnect() {
        if (this.timer) {
            clearInterval(this.timer);
        }
    }

    tick() {
        this.remaining -= 1;

        if (this.remaining <= 0) {
            this.remaining = 0;
            clearInterval(this.timer);
            this.onExpired();
        }

        this.render();
    }

    render() {
        const minutes = Math.floor(this.remaining / 60);
        const seconds = this.remaining % 60;
        const display = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;

        if (this.hasCountdownTarget) {
            this.countdownTarget.textContent = display;
        }

        // Visual urgency when under 2 minutes
        if (this.hasCountdownTarget && this.remaining <= 120 && this.remaining > 0) {
            this.countdownTarget.classList.add('text-red-500');
            this.countdownTarget.classList.remove('text-alma-gold');
        }
    }

    onExpired() {
        if (this.hasContainerTarget) {
            this.containerTarget.classList.add('hidden');
        }
        if (this.hasExpiredTarget) {
            this.expiredTarget.classList.remove('hidden');
        }

        // Reload after 3 seconds so the server cleans up the cart
        setTimeout(() => {
            window.location.reload();
        }, 3000);
    }
}
