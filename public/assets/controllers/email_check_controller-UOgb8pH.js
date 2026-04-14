import { Controller } from '@hotwired/stimulus';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

export default class extends Controller {
    static targets = ['input', 'banner'];
    static values = { url: String };

    async check() {
        const email = this.inputTarget.value.trim();

        if (!email || !email.includes('@')) {
            this.bannerTarget.classList.add('hidden');
            return;
        }

        try {
            const response = await fetch(this.urlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
                body: JSON.stringify({ email }),
            });

            const data = await response.json();
            this.bannerTarget.classList.toggle('hidden', !data.exists);
        } catch {
            this.bannerTarget.classList.add('hidden');
        }
    }
}
