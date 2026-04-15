import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['dropdown'];

    connect() {
        this._leaveTimeout = null;
    }

    show() {
        clearTimeout(this._leaveTimeout);
        this.dropdownTarget.classList.remove('hidden');
        this.dropdownTarget.classList.add('opacity-100');
    }

    hide() {
        this._leaveTimeout = setTimeout(() => {
            this.dropdownTarget.classList.add('hidden');
            this.dropdownTarget.classList.remove('opacity-100');
        }, 150);
    }

    keepOpen() {
        clearTimeout(this._leaveTimeout);
    }
}
