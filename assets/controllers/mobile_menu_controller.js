import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['panel', 'overlay'];

    open() {
        this.panelTarget.classList.remove('-translate-x-full');
        this.overlayTarget.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    close() {
        this.panelTarget.classList.add('-translate-x-full');
        this.overlayTarget.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    closeOnKeydown(event) {
        if (event.key === 'Escape') {
            this.close();
        }
    }
}
