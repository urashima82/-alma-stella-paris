import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['checkbox', 'fields'];

    toggle() {
        const show = this.checkboxTarget.checked;
        this.fieldsTarget.classList.toggle('hidden', !show);

        // Toggle required attributes on billing fields
        this.fieldsTarget.querySelectorAll('[data-billing-required]').forEach(input => {
            input.required = show;
        });
    }
}
