import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['content', 'icon'];
    static values = { open: { type: Boolean, default: false } };

    connect() {
        const content = this.contentTarget;
        content.style.transition = 'max-height 0.25s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.2s ease';
        content.style.overflow = 'hidden';

        if (this.openValue) {
            this._setOpen(false);
        } else {
            this._setClosed(false);
        }
    }

    toggle(event) {
        event.preventDefault();
        event.stopPropagation();
        this.openValue ? this._setClosed(true) : this._setOpen(true);
    }

    _setOpen(animate) {
        this.openValue = true;
        const content = this.contentTarget;

        if (animate) {
            // Temporarily remove max-height to measure scrollHeight
            content.style.maxHeight = content.scrollHeight + 'px';
            content.style.opacity = '1';
            // After transition, allow dynamic content
            const onEnd = () => {
                if (this.openValue) content.style.maxHeight = 'none';
                content.removeEventListener('transitionend', onEnd);
            };
            content.addEventListener('transitionend', onEnd);
        } else {
            content.style.maxHeight = 'none';
            content.style.opacity = '1';
        }

        if (this.hasIconTarget) {
            this.iconTarget.style.transition = 'transform 0.25s cubic-bezier(0.4, 0, 0.2, 1)';
            this.iconTarget.style.transform = 'rotate(180deg)';
        }
    }

    _setClosed(animate) {
        this.openValue = false;
        const content = this.contentTarget;

        if (animate) {
            // Lock current height first, then animate to 0
            content.style.maxHeight = content.scrollHeight + 'px';
            // Force reflow
            content.offsetHeight; // eslint-disable-line no-unused-expressions
            requestAnimationFrame(() => {
                content.style.maxHeight = '0px';
                content.style.opacity = '0';
            });
        } else {
            content.style.maxHeight = '0px';
            content.style.opacity = '0';
        }

        if (this.hasIconTarget) {
            this.iconTarget.style.transition = 'transform 0.25s cubic-bezier(0.4, 0, 0.2, 1)';
            this.iconTarget.style.transform = 'rotate(0deg)';
        }
    }
}
