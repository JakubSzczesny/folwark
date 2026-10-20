import { Controller } from '@hotwired/stimulus';

const WINDOW_NAME = 'folwark-bs';

/*
 * „Uzupełnij w BS”: przekazuje dane przelewu skryptowi „Folwark – BS Pay”. Gdy karta banku jest otwarta,
 * skrypt sam wysuwa ją na wierzch; gdy nie – otwieramy bankowość w nowej karcie.
 */
export default class extends Controller {
    static values = { url: String, bankUrl: String };

    async send(event) {
        event.preventDefault();
        const extension = Boolean(document.documentElement.dataset.bspayExtension);
        const bankOpen = document.documentElement.dataset.bspayBankOpen === '1';

        /* Nową kartę trzeba otworzyć od razu w kliknięciu – po pobraniu danych przeglądarka by ją zablokowała. */
        if (!extension || !bankOpen) {
            window.open(this.bankUrlValue, WINDOW_NAME);
        }
        if (!extension) {
            return;
        }

        const button = event.currentTarget;
        button.classList.add('is-loading');
        let json;
        try {
            const response = await fetch(this.urlValue, { headers: { Accept: 'application/json' } });
            json = await response.text();
        } finally {
            button.classList.remove('is-loading');
        }
        document.documentElement.dataset.bspayTransfer = json;
        document.dispatchEvent(new CustomEvent('bspay:transfer', { detail: json }));
    }
}
