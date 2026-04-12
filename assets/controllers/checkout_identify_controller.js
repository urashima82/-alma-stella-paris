import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['emailInput', 'emailStep', 'loginStep', 'choiceStep', 'loginEmail', 'guestEmail', 'errorMessage'];
    static values = { checkUrl: String };

    async continue() {
        const email = this.emailInputTarget.value.trim();

        if (!email || !this._isValidEmail(email)) {
            this.errorMessageTarget.classList.remove('hidden');
            return;
        }

        this.errorMessageTarget.classList.add('hidden');

        try {
            const response = await fetch(this.checkUrlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email }),
            });

            const data = await response.json();

            this.emailStepTarget.classList.add('hidden');

            if (data.exists) {
                this.loginEmailTarget.value = email;
                this.loginStepTarget.classList.remove('hidden');
            } else {
                this.guestEmailTarget.value = email;
                this.choiceStepTarget.classList.remove('hidden');
            }
        } catch {
            // On error, let them continue as guest
            this.guestEmailTarget.value = email;
            this.emailStepTarget.classList.add('hidden');
            this.choiceStepTarget.classList.remove('hidden');
        }
    }

    back() {
        this.loginStepTarget.classList.add('hidden');
        this.choiceStepTarget.classList.add('hidden');
        this.emailStepTarget.classList.remove('hidden');
        this.emailInputTarget.focus();
    }

    _isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }
}
