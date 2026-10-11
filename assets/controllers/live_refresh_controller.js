import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';

/*
 * Odświeża komponent Live (np. pulpit „Wymaga uwagi”) od razu po powrocie do karty przeglądarki,
 * nie czekając na kolejne odpytanie.
 */
export default class extends Controller {
    connect() {
        this.onVisible = () => {
            if (document.visibilityState === 'visible') {
                this.refresh();
            }
        };
        document.addEventListener('visibilitychange', this.onVisible);
        window.addEventListener('focus', this.onVisible);
    }

    disconnect() {
        document.removeEventListener('visibilitychange', this.onVisible);
        window.removeEventListener('focus', this.onVisible);
    }

    async refresh() {
        const element = this.element.querySelector('[data-controller~="live"]');
        if (!element || (this.last && Date.now() - this.last < 5000)) {
            return;
        }
        this.last = Date.now();
        const component = await getComponent(element);
        component.render();
    }
}
