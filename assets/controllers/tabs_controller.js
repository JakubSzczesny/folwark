import { Controller } from '@hotwired/stimulus';
import { skeleton } from '../skeleton.js';

/*
 * Zakładki w ramce Turbo (karta maszyny, karta towaru). Kliknięta zakładka podświetla się od razu,
 * a pod paskiem pojawia się szkielet – stara treść nie wisi przygaszona do czasu przyjścia nowej.
 * Nowa treść ramki zastępuje szkielet; przy błędzie wraca poprzedni stan.
 */
export default class extends Controller {
    connect() {
        this.onFetch = (event) => this.fetching(event);
        this.onFail = () => this.restore();
        this.element.addEventListener('turbo:before-fetch-request', this.onFetch);
        this.element.addEventListener('turbo:fetch-request-error', this.onFail);
        this.element.addEventListener('turbo:frame-missing', this.onFail);
    }

    disconnect() {
        this.element.removeEventListener('turbo:before-fetch-request', this.onFetch);
        this.element.removeEventListener('turbo:fetch-request-error', this.onFail);
        this.element.removeEventListener('turbo:frame-missing', this.onFail);
    }

    // Ramka zaczyna wczytywać adres jednej z zakładek – przełączamy ją od razu.
    fetching(event) {
        if (event.target !== this.element) {
            return;
        }
        const url = new URL(event.detail.url, window.location.origin);
        const tab = Array.from(this.element.querySelectorAll(':scope > .tabs a.tab'))
            .find((link) => {
                const href = new URL(link.href, window.location.origin);
                return href.pathname === url.pathname && href.search === url.search;
            });
        if (!tab || tab.classList.contains('tab-active')) {
            return;
        }
        this.previous = this.element.querySelector('.tab-active');
        this.previous?.classList.remove('tab-active');
        tab.classList.add('tab-active');

        this.element.querySelector('[data-tab-skeleton]')?.remove();
        this.element.querySelector(':scope > .tabs').insertAdjacentHTML('afterend', skeleton('tab'));
        this.element.classList.add('is-switching');
    }

    restore() {
        if (!this.element.classList.contains('is-switching')) {
            return;
        }
        this.element.classList.remove('is-switching');
        this.element.querySelector('[data-tab-skeleton]')?.remove();
        this.element.querySelector('a.tab.tab-active')?.classList.remove('tab-active');
        this.previous?.classList.add('tab-active');
    }

    frameLoaded() {
        this.element.classList.remove('is-switching');
    }
}
