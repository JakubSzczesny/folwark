import { Controller } from '@hotwired/stimulus';

/*
 * Okno podglądu na środku ekranu z rozmytym tłem. Otwiera się od razu z kropkami ładowania, gdy ramka
 * "preview" zaczyna wczytywać treść; zamyka przyciskiem, klawiszem Esc albo kliknięciem w tło.
 */
export default class extends Controller {
    static targets = ['panel', 'frame'];

    connect() {
        this.onLoad = (event) => {
            if (event.target === this.frameTarget) {
                this.open();
            }
        };
        this.onFetch = (event) => {
            if (event.target === this.frameTarget && this.panelTarget.hidden) {
                this.frameTarget.innerHTML = '<div class="flex items-center justify-center p-12" role="status" aria-label="Wczytywanie"><span class="dots dots-lg" aria-hidden="true"><span></span><span></span><span></span></span></div>';
                this.open();
            }
        };
        document.addEventListener('turbo:frame-load', this.onLoad);
        this.frameTarget.addEventListener('turbo:before-fetch-request', this.onFetch);
    }

    disconnect() {
        document.removeEventListener('turbo:frame-load', this.onLoad);
        this.frameTarget.removeEventListener('turbo:before-fetch-request', this.onFetch);
        document.documentElement.classList.remove('has-overlay');
    }

    open() {
        if (!this.panelTarget.hidden) {
            return;
        }
        this.panelTarget.hidden = false;
        // wymuszenie przeliczenia układu, żeby okno pojawiło się z animacją
        void this.panelTarget.offsetWidth;
        this.panelTarget.classList.remove('is-closed');
        document.documentElement.classList.add('has-overlay');
    }

    close() {
        this.panelTarget.classList.add('is-closed');
        document.documentElement.classList.remove('has-overlay');
        setTimeout(() => {
            this.panelTarget.hidden = true;
        }, 180);
        this.dispatch('closed');
    }

    backdrop(event) {
        if (event.target === this.panelTarget) {
            this.close();
        }
    }

    keydown(event) {
        if (event.key === 'Escape' && !this.panelTarget.hidden && !document.querySelector('dialog[open]')) {
            this.close();
        }
    }
}
