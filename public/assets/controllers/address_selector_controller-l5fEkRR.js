import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['select', 'addressLine1', 'addressLine2', 'city', 'state', 'postalCode', 'country'];
    static values = { addresses: Array };

    fill() {
        const selectedId = parseInt(this.selectTarget.value, 10);
        const address = this.addressesValue.find(a => a.id === selectedId);

        if (address) {
            this.addressLine1Target.value = address.addressLine1;
            this.addressLine2Target.value = address.addressLine2;
            this.cityTarget.value = address.city;
            this.stateTarget.value = address.state;
            this.postalCodeTarget.value = address.postalCode;
            this.countryTarget.value = address.country;
        } else {
            this.addressLine1Target.value = '';
            this.addressLine2Target.value = '';
            this.cityTarget.value = '';
            this.stateTarget.value = '';
            this.postalCodeTarget.value = '';
            this.countryTarget.value = '';
        }
    }
}
