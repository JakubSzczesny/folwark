import { Controller } from '@hotwired/stimulus';

/*
 * Wysuwany z prawej panel podglądu. Otwiera się, gdy ramka "preview" załaduje treść;
 * zamyka przyciskiem lub klawiszem Esc. Lista pod spodem pozostaje widoczna.
 */
export default class extends Controller {
    static targets = ['panel', 'frame'];

    connect() {
        this.onLoad = (event) => {
            if (event.target === this.frameTarget) {
                this.open();
            }
        };
        document.addEventListener('turbo:frame-load', this.onLoad);
    }

    disconnect() {
        document.removeEventListener('turbo:frame-load', this.onLoad);
    }

    open() {
        this.panelTarget.hidden = false;
        // wymuszenie przeliczenia układu, żeby przejście transform zadziałało od stanu ukrytego
        void this.panelTarget.offsetWidth;
        this.panelTarget.classList.remove('translate-x-full');
    }

    close() {
        this.panelTarget.classList.add('translate-x-full');
        setTimeout(() => {
            this.panelTarget.hidden = true;
        }, 150);
        this.dispatch('closed');
    }

    keydown(event) {
        if (event.key === 'Escape' && !this.panelTarget.hidden && !document.querySelector('dialog[open]')) {
            this.close();
        }
    }
}
