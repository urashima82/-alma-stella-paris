import { Controller } from '@hotwired/stimulus';

/**
 * Reservation countdown timer with automatic extension on user activity.
 *
 * Displays a mm:ss countdown. When it reaches the warning threshold (2 min),
 * if user activity was detected recently, an automatic extension request is sent.
 * A manual "Extend" button is shown as a fallback. When all extensions are
 * exhausted and the timer reaches 0, the "expired" block is shown and the page
 * reloads after a short delay so the server can clean up the cart.
 */
export default class extends Controller {
    static targets = ['countdown', 'container', 'expired', 'extendButton', 'extendNotice'];
    static values = {
        seconds: Number,
        expiredText: String,
        extensionsLeft: Number,
        extendUrl: String,
    };

    /** @type {number} Seconds remaining on the countdown */
    remaining = 0;

    /** @type {number|null} Interval ID for the 1-second tick */
    timer = null;

    /** @type {number} Timestamp of last detected user activity */
    lastActivity = 0;

    /** @type {boolean} Whether an extension request is currently in flight */
    extending = false;

    /** @type {boolean} Whether auto-extend already fired for this countdown cycle */
    autoExtendFired = false;

    /** @type {number} Threshold (in seconds) at which to trigger warning / auto-extend */
    static WARNING_THRESHOLD = 120;

    /** @type {number} Max inactivity (ms) before activity is considered stale */
    static ACTIVITY_WINDOW = 60_000;

    connect() {
        this.remaining = this.secondsValue;
        this.lastActivity = Date.now();
        this.autoExtendFired = false;
        this.render();
        this.timer = setInterval(() => this.tick(), 1000);
        this.activityHandler = () => this.onActivity();
        this.activityEvents = ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'];
        this.activityEvents.forEach((event) =>
            document.addEventListener(event, this.activityHandler, { passive: true }),
        );
    }

    disconnect() {
        if (this.timer) {
            clearInterval(this.timer);
        }
        if (this.activityHandler) {
            this.activityEvents.forEach((event) =>
                document.removeEventListener(event, this.activityHandler),
            );
        }
    }

    onActivity() {
        this.lastActivity = Date.now();
    }

    tick() {
        this.remaining -= 1;

        if (this.remaining <= 0) {
            this.remaining = 0;
            clearInterval(this.timer);
            this.onExpired();
        }

        // Auto-extend when under threshold with recent activity
        if (
            this.remaining > 0 &&
            this.remaining <= this.constructor.WARNING_THRESHOLD &&
            !this.autoExtendFired &&
            this.extensionsLeftValue > 0 &&
            this.isRecentlyActive()
        ) {
            this.autoExtendFired = true;
            this.requestExtension();
        }

        // Show manual extend button when under threshold and extensions remain
        if (
            this.remaining > 0 &&
            this.remaining <= this.constructor.WARNING_THRESHOLD &&
            this.extensionsLeftValue > 0 &&
            this.hasExtendButtonTarget
        ) {
            this.extendButtonTarget.classList.remove('hidden');
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

    isRecentlyActive() {
        return Date.now() - this.lastActivity < this.constructor.ACTIVITY_WINDOW;
    }

    async requestExtension() {
        if (this.extending || this.extensionsLeftValue <= 0) {
            return;
        }

        this.extending = true;

        try {
            const response = await fetch(this.extendUrlValue, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json',
                },
            });

            const data = await response.json();

            if (data.extended) {
                this.remaining = data.remainingSeconds;
                this.extensionsLeftValue = data.extensionsLeft;
                this.autoExtendFired = false;

                // Reset visual state
                if (this.hasCountdownTarget) {
                    this.countdownTarget.classList.remove('text-red-500');
                    this.countdownTarget.classList.add('text-alma-gold');
                }

                // Show confirmation notice
                if (this.hasExtendNoticeTarget) {
                    this.extendNoticeTarget.classList.remove('hidden');
                    setTimeout(() => this.extendNoticeTarget.classList.add('hidden'), 4000);
                }

                // Hide extend button if no extensions left
                if (this.extensionsLeftValue <= 0 && this.hasExtendButtonTarget) {
                    this.extendButtonTarget.classList.add('hidden');
                }

                this.render();
            }
        } catch (error) {
            // Silently fail — the timer continues and the user can retry manually
        } finally {
            this.extending = false;
        }
    }

    extend(event) {
        event.preventDefault();
        this.requestExtension();
    }

    onExpired() {
        if (this.hasContainerTarget) {
            this.containerTarget.classList.add('hidden');
        }
        if (this.hasExtendButtonTarget) {
            this.extendButtonTarget.classList.add('hidden');
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
