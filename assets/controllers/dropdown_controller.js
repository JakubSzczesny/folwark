import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['menu', 'button'];

    connect() {
        this.outside = (event) => {
            if (!this.element.contains(event.target)) {
                this.hide();
            }
        };
        document.addEventListener('click', this.outside);
    }

    disconnect() {
        document.removeEventListener('click', this.outside);
    }

    toggle(event) {
        event.preventDefault();
        this.menuTarget.hidden ? this.show() : this.hide();
    }

    show() {
        this.menuTarget.hidden = false;
        if (this.hasButtonTarget) {
            this.buttonTarget.setAttribute('aria-expanded', 'true');
        }
        this.dispatch('opened');
    }

    hide() {
        if (this.menuTarget.hidden) {
            return;
        }
        this.menuTarget.hidden = true;
        if (this.hasButtonTarget) {
            this.buttonTarget.setAttribute('aria-expanded', 'false');
        }
    }

    hideOnEscape(event) {
        if (event.key === 'Escape') {
            this.hide();
        }
    }
}
