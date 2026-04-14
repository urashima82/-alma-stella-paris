import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['star'];

    connect() {
        this._update();
    }

    select(event) {
        const radio = event.currentTarget.querySelector('input[type="radio"]');
        if (radio) {
            radio.checked = true;
        }
        this._update();
    }

    hover(event) {
        const value = parseInt(event.currentTarget.dataset.starValue, 10);
        this._highlight(value);
    }

    leave() {
        this._update();
    }

    _update() {
        const checked = this.element.querySelector('input[type="radio"]:checked');
        const value = checked ? parseInt(checked.value, 10) : 0;
        this._highlight(value);
    }

    _highlight(value) {
        this.starTargets.forEach((star) => {
            const starValue = parseInt(star.dataset.starValue, 10);
            const span = star.querySelector('span');
            if (starValue <= value) {
                span.classList.add('bg-alma-gold', 'text-white', 'border-alma-gold');
                span.classList.remove('border-alma-hover');
            } else {
                span.classList.remove('bg-alma-gold', 'text-white', 'border-alma-gold');
                span.classList.add('border-alma-hover');
            }
        });
    }
}
