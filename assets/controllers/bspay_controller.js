import { Controller } from '@hotwired/stimulus';

/*
 * BS Pay: przekazuje dane przelewu skryptowi „Folwark – BS Pay” (Tampermonkey) zdarzeniem „bspay:transfer”
 * i atrybutem data-bspay-transfer na <html>. Skrypt zgłasza się atrybutem data-bspay-extension i sam
 * przechodzi do karty banku; bez skryptu otwieramy bankowość w nowej karcie.
 */
export default class extends Controller {
    static targets = ['data', 'status'];
    static values = { bankUrl: String };

    connect() {
        this.showStatus();
        this.observer = new MutationObserver(() => this.showStatus());
        this.observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-bspay-extension'] });
    }

    disconnect() {
        this.observer?.disconnect();
    }

    send() {
        const json = this.hasDataTarget ? this.dataTarget.textContent : '{}';
        document.documentElement.dataset.bspayTransfer = json;
        document.dispatchEvent(new CustomEvent('bspay:transfer', { detail: json }));
        if (!document.documentElement.dataset.bspayExtension && this.bankUrlValue) {
            window.open(this.bankUrlValue, '_blank', 'noopener');
        }
    }

    showStatus() {
        const version = document.documentElement.dataset.bspayExtension;
        this.statusTargets.forEach((element) => {
            element.textContent = version
                ? `Skrypt BS Pay połączony (wersja ${version}) – „Uzupełnij w BS” wypełni przelew w karcie banku.`
                : 'Skrypt BS Pay nie jest zainstalowany – przycisk tylko otworzy bank, pola skopiuj ręcznie.';
            element.classList.toggle('text-zielen-700', Boolean(version));
        });
    }
}
