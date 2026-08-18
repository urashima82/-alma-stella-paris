import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['select', 'recipientName', 'addressLine1', 'addressLine2', 'city', 'state', 'postalCode', 'country'];
    static values = { addresses: Array };

    fill() {
        const selectedId = parseInt(this.selectTarget.value, 10);
        const address = this.addressesValue.find(a => a.id === selectedId);

        if (address) {
            if (this.hasRecipientNameTarget) {
                this.recipientNameTarget.value = address.recipientName || '';
            }
            this.addressLine1Target.value = address.addressLine1;
            this.addressLine2Target.value = address.addressLine2;
            this.cityTarget.value = address.city;
            this.stateTarget.value = address.state;
            this.postalCodeTarget.value = address.postalCode;
            this.countryTarget.value = address.country;
            // Programmatic value changes fire no 'change' event — notify
            // listeners (shipping surcharge display) explicitly.
            this.countryTarget.dispatchEvent(new Event('change', { bubbles: true }));
        } else {
            if (this.hasRecipientNameTarget) {
                this.recipientNameTarget.value = '';
            }
            this.addressLine1Target.value = '';
            this.addressLine2Target.value = '';
            this.cityTarget.value = '';
            this.stateTarget.value = '';
            this.postalCodeTarget.value = '';
            this.countryTarget.value = '';
            this.countryTarget.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }
}
