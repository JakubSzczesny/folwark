import { Controller } from '@hotwired/stimulus';

/*
 * BS Pay: przekazuje dane przelewu dodatkowi przeglądarki (zdarzenie „bspay:transfer” i atrybut
 * na <html>) i otwiera bankowość w nowej karcie. Dodatek zgłasza się atrybutem data-bspay-extension na <html>.
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
        window.dispatchEvent(new CustomEvent('bspay:transfer', { detail: json }));
        if (this.bankUrlValue) {
            window.open(this.bankUrlValue, '_blank', 'noopener');
        }
    }

    showStatus() {
        const version = document.documentElement.dataset.bspayExtension;
        this.statusTargets.forEach((element) => {
            element.textContent = version ? `Dodatek BS Pay wykryty (wersja ${version}).` : 'Dodatek BS Pay nie jest zainstalowany – skopiuj pola ręcznie.';
            element.classList.toggle('text-zielen-700', Boolean(version));
        });
    }
}
