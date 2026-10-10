import { Controller } from '@hotwired/stimulus';

/*
 * Formularz filtrów wysyła się sam: od razu po zmianie pola wyboru, z opóźnieniem przy pisaniu.
 */
export default class extends Controller {
    static values = { delay: { type: Number, default: 400 } };

    submit() {
        clearTimeout(this.timer);
        this.element.requestSubmit();
    }

    debounced() {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.element.requestSubmit(), this.delayValue);
    }
}
