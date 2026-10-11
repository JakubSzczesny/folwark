import { Controller } from '@hotwired/stimulus';

/*
 * Lista faktur we wpisie serwisowym: po zmianie wykonawcy albo daty pobiera faktury
 * tego wykonawcy z ±14 dni.
 */
export default class extends Controller {
    static targets = ['contractor', 'date', 'select'];
    static values = { url: String };

    async refresh() {
        if (!this.hasSelectTarget) {
            return;
        }

        const params = new URLSearchParams({
            kontrahent: this.hasContractorTarget ? this.contractorTarget.value : '',
            data: this.hasDateTarget ? this.dateTarget.value : '',
            wybrana: this.selectTarget.value,
        });
        const response = await fetch(`${this.urlValue}?${params}`, { headers: { Accept: 'text/html' } });
        if (response.ok) {
            this.selectTarget.innerHTML = await response.text();
        }
    }
}
