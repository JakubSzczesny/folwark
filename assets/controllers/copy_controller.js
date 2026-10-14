import { Controller } from '@hotwired/stimulus';

/*
 * Przycisk „Kopiuj”: kopiuje wartość do schowka i na chwilę pokazuje „Skopiowano”.
 */
export default class extends Controller {
    static values = { text: String };

    async copy() {
        try {
            await navigator.clipboard.writeText(this.textValue);
        } catch {
            const area = document.createElement('textarea');
            area.value = this.textValue;
            document.body.append(area);
            area.select();
            document.execCommand('copy');
            area.remove();
        }
        const label = this.element.getAttribute('aria-label');
        this.element.classList.add('is-copied');
        this.element.setAttribute('aria-label', 'Skopiowano');
        clearTimeout(this.timer);
        this.timer = setTimeout(() => {
            this.element.classList.remove('is-copied');
            this.element.setAttribute('aria-label', label);
        }, 1500);
    }
}
