import { Controller } from '@hotwired/stimulus';

/*
 * Tryb dostosowywania pulpitu: pokazuje listę widżetów oraz przyciski przesuwania i usuwania.
 * Klasa siedzi poza komponentem Live, więc odświeżanie pulpitu jej nie gubi.
 */
export default class extends Controller {
    static targets = ['button', 'label'];
    static values = { editing: Boolean };

    toggle() {
        this.editingValue = !this.editingValue;
    }

    editingValueChanged() {
        this.element.classList.toggle('is-editing', this.editingValue);
        this.buttonTargets.forEach((button) => button.setAttribute('aria-pressed', String(this.editingValue)));
        this.labelTargets.forEach((label) => {
            label.textContent = this.editingValue ? 'Gotowe' : 'Dostosuj pulpit';
        });
    }
}
