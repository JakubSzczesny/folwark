import { Controller } from '@hotwired/stimulus';

/*
 * Okno dialogowe z ramką Turbo "modal". Link z data-turbo-frame="modal" ładuje treść,
 * a okno otwiera się automatycznie. Odpowiedź z elementem [data-modal-close] zamyka okno.
 */
export default class extends Controller {
    static targets = ['dialog', 'frame'];

    connect() {
        this.onLoad = (event) => {
            if (event.target !== this.frameTarget) {
                return;
            }
            if (this.frameTarget.querySelector('[data-modal-close]')) {
                this.close();
            } else if (!this.dialogTarget.open) {
                this.dialogTarget.showModal();
                this.frameTarget.querySelector('input:not([type=hidden]), select, textarea')?.focus();
            }
        };
        document.addEventListener('turbo:frame-load', this.onLoad);
    }

    disconnect() {
        document.removeEventListener('turbo:frame-load', this.onLoad);
    }

    close() {
        this.dialogTarget.close();
    }

    closeOnBackdrop(event) {
        if (event.target === this.dialogTarget) {
            this.close();
        }
    }
}
