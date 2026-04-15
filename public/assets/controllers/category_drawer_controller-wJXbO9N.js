import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['overlay', 'panel'];

    connect() {
        // Set up transition styles on overlay for fade effect
        this.overlayTarget.style.transition = 'opacity 0.6s ease';
        this.overlayTarget.style.opacity = '0';
        this.overlayTarget.style.pointerEvents = 'none';
        this.overlayTarget.classList.remove('hidden');

        this._boundKeydown = this._onKeydown.bind(this);
    }

    disconnect() {
        document.removeEventListener('keydown', this._boundKeydown);
        document.body.style.overflow = '';
    }

    open() {
        // Show overlay with fade
        this.overlayTarget.style.opacity = '1';
        this.overlayTarget.style.pointerEvents = 'auto';

        // Slide panel in
        requestAnimationFrame(() => {
            this.panelTarget.classList.remove('-translate-x-full');
            this.panelTarget.classList.add('translate-x-0');
        });

        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', this._boundKeydown);
    }

    close() {
        // Slide panel out
        this.panelTarget.classList.remove('translate-x-0');
        this.panelTarget.classList.add('-translate-x-full');

        // Fade overlay out
        this.overlayTarget.style.opacity = '0';
        this.overlayTarget.style.pointerEvents = 'none';

        document.body.style.overflow = '';
        document.removeEventListener('keydown', this._boundKeydown);
    }

    _onKeydown(event) {
        if (event.key === 'Escape') this.close();
    }
}
