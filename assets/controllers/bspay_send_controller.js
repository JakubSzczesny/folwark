import { Controller } from '@hotwired/stimulus';

const WINDOW_NAME = 'folwark-bs';

/*
 * „Uzupełnij w BS”: przekazuje dane przelewu skryptowi „Folwark – BS Pay” i przełącza na kartę banku.
 * Kartę otwartą wcześniej przez Folwark tylko pokazujemy (bez przeładowania – bank wylogowuje przy
 * wejściu z zewnątrz); kartę otwartą ręcznie skrypt zasila sam, a my prosimy o przejście do niej.
 */
export default class extends Controller {
    static values = { url: String, bankUrl: String };

    async send(event) {
        event.preventDefault();
        const extension = Boolean(document.documentElement.dataset.bspayExtension);
        const bankOpen = document.documentElement.dataset.bspayBankOpen === '1';
        const ownWindow = this.ownWindow();

        let target = null;
        if (ownWindow || !bankOpen || !extension) {
            target = window.open('', WINDOW_NAME);
        }

        let reused = false;
        try {
            reused = Boolean(target) && target.location.href !== 'about:blank';
        } catch {
            reused = true;
        }

        if (extension) {
            const response = await fetch(this.urlValue, { headers: { Accept: 'application/json' } });
            const json = await response.text();
            document.documentElement.dataset.bspayTransfer = json;
            document.dispatchEvent(new CustomEvent('bspay:transfer', { detail: json }));
        }

        if (reused) {
            target.focus();
        } else if (target && (!bankOpen || !extension)) {
            target.location.href = this.bankUrlValue;
            sessionStorage.setItem('bspayWindow', '1');
        } else {
            target?.close();
            this.notice('Przelew czeka w otwartej karcie BS – przejdź do niej i kliknij „Uzupełnij przelew”.');
        }
    }

    ownWindow() {
        try {
            return sessionStorage.getItem('bspayWindow') === '1';
        } catch {
            return false;
        }
    }

    notice(text) {
        const box = document.createElement('div');
        box.className = 'alert alert-info fixed bottom-4 right-4 z-[60] max-w-sm shadow-lg';
        box.setAttribute('role', 'status');
        box.textContent = text;
        document.body.append(box);
        setTimeout(() => box.remove(), 6000);
    }
}
