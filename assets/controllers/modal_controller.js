import { Controller } from '@hotwired/stimulus';

/*
 * Okno dialogowe z ramką Turbo "modal". Link z data-turbo-frame="modal" ładuje treść
 * i otwiera okno; udany zapis formularza w oknie (odpowiedź 2xx/3xx) je zamyka.
 */
export default class extends Controller {
    static targets = ['dialog', 'frame'];

    connect() {
        this.onLoad = (event) => {
            if (event.target !== this.frameTarget) {
                return;
            }
            if (!this.dialogTarget.open) {
                this.dialogTarget.showModal();
            }
            this.frameTarget.querySelector('[autofocus], input:not([type=hidden]), select, textarea')?.focus();
        };
        this.onSubmitEnd = (event) => {
            if (this.frameTarget.contains(event.target) && event.detail.success) {
                this.close();
            }
        };
        document.addEventListener('turbo:frame-load', this.onLoad);
        document.addEventListener('turbo:submit-end', this.onSubmitEnd);
    }

    disconnect() {
        document.removeEventListener('turbo:frame-load', this.onLoad);
        document.removeEventListener('turbo:submit-end', this.onSubmitEnd);
    }

    close() {
        this.dialogTarget.close();
        this.frameTarget.innerHTML = '';
        this.frameTarget.removeAttribute('src');
    }

    closeOnBackdrop(event) {
        if (event.target === this.dialogTarget) {
            this.close();
        }
    }
}
