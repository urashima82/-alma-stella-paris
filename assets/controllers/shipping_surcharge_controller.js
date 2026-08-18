import { Controller } from '@hotwired/stimulus';

/**
 * Shows the out-of-zone shipping surcharge line in the checkout summary when
 * the destination leaves the included zone (EU + UK + Switzerland, metropolitan
 * France only — a French DOM-TOM postal code counts as out-of-zone), and swaps
 * the displayed total accordingly. Purely presentational: the authoritative
 * surcharge and total are recomputed server-side when the form is submitted.
 */
export default class extends Controller {
    static targets = ['country', 'postal', 'line', 'total'];
    static values = {
        includedCountries: Array,
        totalIncluded: String,
        totalWithSurcharge: String,
    };

    connect() {
        this.update();
    }

    update() {
        const country = this.countryTarget.value;
        const postal = this.hasPostalTarget ? this.postalTarget.value : '';
        const domTom = country === 'FR' && /^\s*9[78]/.test(postal);
        const outOfZone = country !== '' && (!this.includedCountriesValue.includes(country) || domTom);

        if (this.hasLineTarget) {
            this.lineTarget.classList.toggle('hidden', !outOfZone);
            this.lineTarget.classList.toggle('flex', outOfZone);
        }
        if (this.hasTotalTarget) {
            this.totalTarget.textContent = outOfZone ? this.totalWithSurchargeValue : this.totalIncludedValue;
        }
    }
}
