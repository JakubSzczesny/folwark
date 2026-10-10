import { Controller } from '@hotwired/stimulus';

/*
 * Na telefonie pasek filtrów jest zwinięty pod przyciskiem „Filtry”.
 */
export default class extends Controller {
    static targets = ['panel', 'button'];

    toggle() {
        const open = this.panelTarget.classList.toggle('is-open');
        this.buttonTarget.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
}
