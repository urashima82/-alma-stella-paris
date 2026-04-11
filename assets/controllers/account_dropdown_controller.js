import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['dropdown'];

    toggle() {
        this.dropdownTarget.classList.toggle('hidden');
    }

    close(event) {
        if (!this.element.contains(event.target)) {
            this.dropdownTarget.classList.add('hidden');
        }
    }

    connect() {
        this._closeHandler = this.close.bind(this);
        document.addEventListener('click', this._closeHandler);
    }

    disconnect() {
        document.removeEventListener('click', this._closeHandler);
    }
}
