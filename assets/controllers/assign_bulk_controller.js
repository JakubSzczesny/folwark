import { Controller } from '@hotwired/stimulus';

/*
 * Masowe przypisanie pozycji faktury (np. kilkanaście opłat na fakturze za prąd): zaznaczenie
 * pól wyboru, wszystkich, pozycji bez towaru albo zawierających wpisany tekst, a potem jednym
 * kliknięciem towar, ośrodek kosztów albo „pomiń” dla zaznaczonych. Wiersze zmieniamy przez ich
 * własne pola, więc przelicznik jednostek podpowiada się tak samo jak przy ręcznym wyborze.
 */
export default class extends Controller {
    static targets = ['row', 'check', 'all', 'search', 'panel', 'summary', 'product', 'center'];

    connect() {
        this.last = null;
        this.update();
    }

    toggle(event) {
        const index = this.checkTargets.indexOf(event.currentTarget);
        if (event.shiftKey && this.last !== null && this.last !== index) {
            const [from, to] = [Math.min(this.last, index), Math.max(this.last, index)];
            this.checkTargets.slice(from, to + 1).forEach((check) => {
                check.checked = event.currentTarget.checked;
            });
        }
        this.last = index;
        this.update();
    }

    toggleAll(event) {
        this.select(() => event.currentTarget.checked);
    }

    search() {
        const text = this.searchTarget.value.trim().toLocaleLowerCase('pl');
        this.select((row) => text !== '' && row.dataset.name.toLocaleLowerCase('pl').includes(text));
    }

    selectUnassigned() {
        this.select((row) => !this.productSelect(row).value && !this.skipBox(row).checked);
    }

    // Enter w polu wyszukiwania nie wysyła formularza przypisania.
    preventSubmit(event) {
        event.preventDefault();
    }

    select(predicate) {
        this.rowTargets.forEach((row, i) => {
            this.checkTargets[i].checked = predicate(row);
        });
        this.last = null;
        this.update();
    }

    applyProduct() {
        const select = this.productTarget;
        const value = select.value;
        if (!value) {
            select.tomselect?.focus();
            return;
        }
        const label = select.tomselect?.options[value]?.text ?? select.selectedOptions[0]?.text ?? value;

        this.selectedRows().forEach((row) => {
            this.setSkip(row, false);
            const target = this.productSelect(row);
            if (target.tomselect) {
                target.tomselect.addOption({ value, text: label });
                target.tomselect.setValue(value);
            } else {
                if (![...target.options].some((option) => option.value === value)) {
                    target.add(new Option(label, value));
                }
                target.value = value;
                target.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    }

    applyCenter() {
        const value = this.centerTarget.value;
        this.selectedRows().forEach((row) => {
            const select = row.querySelector('select[name$="[costCenter]"]');
            if (select) {
                select.value = value;
            }
        });
    }

    skip() {
        this.selectedRows().forEach((row) => this.setSkip(row, true));
    }

    unskip() {
        this.selectedRows().forEach((row) => this.setSkip(row, false));
    }

    setSkip(row, skip) {
        const box = this.skipBox(row);
        if (box.checked !== skip) {
            box.checked = skip;
            box.dispatchEvent(new Event('input', { bubbles: true }));
            box.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    update() {
        if (!this.hasPanelTarget) {
            return;
        }
        const count = this.selectedRows().length;
        const all = this.rowTargets.length;
        this.panelTarget.hidden = count === 0;
        this.summaryTarget.textContent = `Zaznaczono: ${count} z ${all}`;
        this.allTarget.checked = all > 0 && count === all;
        this.allTarget.indeterminate = count > 0 && count < all;
        this.rowTargets.forEach((row, i) => row.classList.toggle('is-checked', this.checkTargets[i].checked));
    }

    selectedRows() {
        return this.rowTargets.filter((row, i) => this.checkTargets[i]?.checked);
    }

    productSelect(row) {
        return row.querySelector('select[data-assign-row-target="product"]');
    }

    skipBox(row) {
        return row.querySelector('input[data-assign-row-target="skip"]');
    }
}
