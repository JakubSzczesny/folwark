import { Controller } from '@hotwired/stimulus';
import Sortable from 'sortablejs';

/*
 * Przeciąganie kolejności w drzewie. Każda lista <ul data-parent="…"> sortuje się osobno
 * (rodzeństwo w obrębie jednego rodzica); po upuszczeniu wysyłamy nową kolejność.
 */
export default class extends Controller {
    static values = { url: String, token: String };

    connect() {
        this.sortables = [...this.element.querySelectorAll('ul[data-parent]')].map((list) => Sortable.create(list, {
            handle: '.drag-handle',
            draggable: ':scope > li',
            animation: 120,
            onEnd: () => this.save(list),
        }));
    }

    disconnect() {
        this.sortables.forEach((sortable) => sortable.destroy());
    }

    save(list) {
        const ids = [...list.children].map((item) => item.dataset.id).filter(Boolean);

        fetch(this.urlValue, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.tokenValue },
            body: JSON.stringify({ parent: list.dataset.parent || null, ids }),
        });
    }
}
