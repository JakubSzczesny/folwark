import { Controller } from '@hotwired/stimulus';
import * as Turbo from '@hotwired/turbo';

/*
 * Nagłówek sortowanej kolumny: Shift + klik dokłada kolumnę jako drugie kryterium sortowania.
 * Zwykły klik zostaje zwykłym linkiem (sortowanie tylko po tej kolumnie).
 */
export default class extends Controller {
    static values = { add: String };

    click(event) {
        if (!event.shiftKey || event.ctrlKey || event.metaKey || event.altKey) {
            return;
        }
        event.preventDefault();
        window.getSelection()?.removeAllRanges();
        Turbo.visit(this.addValue);
    }
}
