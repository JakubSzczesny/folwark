import { Controller } from '@hotwired/stimulus';

/*
 * Status skryptu „Folwark – BS Pay” w ustawieniach: skrypt zgłasza się atrybutem data-bspay-extension na <html>.
 */
export default class extends Controller {
    static targets = ['status'];

    connect() {
        this.showStatus();
        this.observer = new MutationObserver(() => this.showStatus());
        this.observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-bspay-extension'] });
    }

    disconnect() {
        this.observer?.disconnect();
    }

    showStatus() {
        const version = document.documentElement.dataset.bspayExtension;
        this.statusTargets.forEach((element) => {
            element.textContent = version
                ? `Skrypt BS Pay połączony (wersja ${version}) – „Uzupełnij w BS” wypełni przelew w karcie banku.`
                : 'Skrypt BS Pay nie jest zainstalowany – „Uzupełnij w BS” tylko otworzy bank, pola skopiujesz z „Dane do przelewu”.';
            element.classList.toggle('text-zielen-700', Boolean(version));
        });
    }
}
