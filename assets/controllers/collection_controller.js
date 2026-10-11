import { Controller } from '@hotwired/stimulus';

/*
 * Lista wierszy formularza (CollectionType): dodawanie z prototypu i usuwanie.
 */
export default class extends Controller {
    static targets = ['list', 'template'];
    static values = { index: Number };

    add() {
        const html = this.templateTarget.innerHTML.replace(/__name__/g, this.indexValue++);
        this.listTarget.insertAdjacentHTML('beforeend', html);
        this.listTarget.lastElementChild?.querySelector('input')?.focus();
    }

    remove(event) {
        event.target.closest('[data-collection-item]')?.remove();
    }
}
