import { Controller } from '@hotwired/stimulus';

/*
 * Wiersz przypisania pozycji faktury do towaru: po wyborze towaru podpowiada przelicznik
 * jednostek, pokazuje ilość w jednostce towaru, „pomiń” wyszarza pola.
 */
export default class extends Controller {
    static targets = ['product', 'factor', 'unit', 'result', 'skip', 'fields'];
    static values = { item: Number, quantity: String, factorUrl: String };

    connect() {
        this.toggleSkip();
    }

    async productChanged() {
        const product = this.productTarget.value;
        if (!product) {
            this.unitTarget.textContent = '';
            this.recompute();
            return;
        }

        const params = new URLSearchParams({ pozycja: this.itemValue, towar: product });
        const response = await fetch(`${this.factorUrlValue}?${params}`, { headers: { Accept: 'application/json' } });
        if (!response.ok) {
            return;
        }
        const data = await response.json();
        this.apply(data.factor, data.unit);
    }

    // Towar dodany w okienku „Nowy towar” – wybieramy go od razu w tym wierszu.
    created(event) {
        const { id, label, unit, factor } = event.detail;
        const select = this.productTarget.tomselect;
        if (select) {
            select.addOption({ value: id, text: label });
            select.setValue(id, true);
        } else {
            this.productTarget.add(new Option(label, id, true, true));
        }
        this.apply(factor, unit);
    }

    apply(factor, unit) {
        this.unitTarget.textContent = unit ?? '';
        this.factorTarget.value = factor === null || factor === undefined || factor === '' ? '' : String(Math.round(Number(factor) * 1e6) / 1e6).replace('.', ',');
        this.recompute();
        if (this.factorTarget.value === '') {
            this.factorTarget.focus();
        }
    }

    recompute() {
        const quantity = parseFloat(this.quantityValue);
        const factor = parseFloat(this.factorTarget.value.replace(/\s/g, '').replace(',', '.'));
        if (!this.productTarget.value || Number.isNaN(quantity) || Number.isNaN(factor)) {
            this.resultTarget.textContent = '';
            return;
        }
        const total = (quantity * factor).toLocaleString('pl-PL', { maximumFractionDigits: 3 });
        this.resultTarget.textContent = `= ${total} ${this.unitTarget.textContent}`;
    }

    toggleSkip() {
        const skip = this.skipTarget.checked;
        this.fieldsTargets.forEach((field) => {
            field.classList.toggle('opacity-40', skip);
            field.querySelectorAll('input, select').forEach((input) => {
                input.toggleAttribute('data-skipped', skip);
            });
        });
        this.productTarget.tomselect?.[skip ? 'disable' : 'enable']();
    }
}
