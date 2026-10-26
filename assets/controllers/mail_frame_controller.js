import { Controller } from '@hotwired/stimulus';

/*
 * Treść maila w ramce iframe (srcdoc, sandbox bez skryptów). Ramka ma tę samą domenę
 * (allow-same-origin), więc możemy odczytać wysokość treści i dopasować do niej ramkę,
 * żeby przewijała się cała strona, a nie okienko w środku.
 */
export default class extends Controller {
    connect() {
        this.resize = this.resize.bind(this);
        this.element.addEventListener('load', this.resize);
        window.addEventListener('resize', this.resize);
        this.resize();
    }

    disconnect() {
        this.element.removeEventListener('load', this.resize);
        window.removeEventListener('resize', this.resize);
    }

    resize() {
        const body = this.element.contentDocument?.body;
        if (!body) {
            return;
        }
        this.element.style.height = `${Math.max(120, body.scrollHeight + 8)}px`;
        // Obrazki doczytują się po zdarzeniu load – dopasowujemy wysokość jeszcze raz.
        body.querySelectorAll('img').forEach((img) => {
            if (!img.complete) {
                img.addEventListener('load', this.resize, { once: true });
            }
        });
    }
}
