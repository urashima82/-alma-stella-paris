import { Controller } from '@hotwired/stimulus';

/**
 * Shows first N stones with a "show more" toggle.
 * When expanded, the list becomes scrollable with a max-height.
 */
export default class extends Controller {
    static targets = ['overflow', 'toggle', 'list'];
    static values = {
        expanded: { type: Boolean, default: false },
        limit: { type: Number, default: 5 },
    };

    connect() {
        if (this.expandedValue) {
            this._expand(false);
        }
    }

    toggle(event) {
        event.preventDefault();
        this.expandedValue ? this._collapse() : this._expand(true);
    }

    _expand(animate) {
        this.expandedValue = true;
        const overflow = this.overflowTarget;

        if (animate) {
            overflow.style.transition = 'max-height 0.3s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.25s ease';
            overflow.style.maxHeight = overflow.scrollHeight + 'px';
            overflow.style.opacity = '1';

            const onEnd = () => {
                overflow.style.maxHeight = 'none';
                overflow.removeEventListener('transitionend', onEnd);
            };
            overflow.addEventListener('transitionend', onEnd);
        } else {
            overflow.style.maxHeight = 'none';
            overflow.style.opacity = '1';
        }

        overflow.classList.remove('hidden');

        if (this.hasListTarget) {
            this.listTarget.classList.add('max-h-52', 'overflow-y-auto');
        }

        this._updateToggleText();
    }

    _collapse() {
        this.expandedValue = false;
        const overflow = this.overflowTarget;

        overflow.style.transition = 'max-height 0.25s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.2s ease';
        overflow.style.maxHeight = overflow.scrollHeight + 'px';
        // Force reflow
        overflow.offsetHeight; // eslint-disable-line no-unused-expressions
        requestAnimationFrame(() => {
            overflow.style.maxHeight = '0px';
            overflow.style.opacity = '0';
        });

        const onEnd = () => {
            overflow.classList.add('hidden');
            overflow.removeEventListener('transitionend', onEnd);
        };
        overflow.addEventListener('transitionend', onEnd);

        if (this.hasListTarget) {
            this.listTarget.classList.remove('max-h-52', 'overflow-y-auto');
        }

        this._updateToggleText();
    }

    _updateToggleText() {
        if (!this.hasToggleTarget) return;

        const label = this.toggleTarget.querySelector('[data-label]');
        if (!label) return;

        label.textContent = this.expandedValue
            ? label.dataset.lessLabel
            : label.dataset.moreLabel;
    }
}
