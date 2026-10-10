import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['content'];

    toggle() {
        this.contentTargets.forEach((el) => {
            el.hidden = !el.hidden;
        });
    }
}
