import { Controller } from '@hotwired/stimulus';

/*
 * Lista wierszy formularza (CollectionType): dodawanie z prototypu i usuwanie.
 * Z „autofill” pusta lista dostaje od razu jeden wiersz.
 */
export default class extends Controller {
    static targets = ['list', 'template'];
    static values = { index: Number, autofill: Boolean };

    connect() {
        if (this.autofillValue && !this.listTarget.querySelector('[data-collection-item]')) {
            this.append();
        }
    }

    add() {
        this.append()?.querySelector('input:not([type="hidden"]), select')?.focus();
    }

    remove(event) {
        event.target.closest('[data-collection-item]')?.remove();
    }

    append() {
        const html = this.templateTarget.innerHTML.replace(/__name__/g, this.indexValue++);
        this.listTarget.insertAdjacentHTML('beforeend', html);

        return this.listTarget.lastElementChild;
    }
}
