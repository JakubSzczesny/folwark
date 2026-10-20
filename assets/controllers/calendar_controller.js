import { Controller } from '@hotwired/stimulus';
import * as Turbo from '@hotwired/turbo';

/*
 * Kalendarz: przeciągnięcie wydarzenia na inny dzień przenosi je (powtarzane – całą serię)
 * o tyle samo dni. Po zapisie strona odświeża się w miejscu.
 */
export default class extends Controller {
    static targets = ['day', 'event'];
    static values = { url: String, token: String };

    dragStart(event) {
        const chip = event.currentTarget;
        this.dragged = { id: chip.dataset.eventId, from: chip.dataset.day };
        chip.classList.add('is-dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', chip.dataset.eventId);
    }

    dragEnd(event) {
        event.currentTarget.classList.remove('is-dragging');
        this.dayTargets.forEach((day) => day.classList.remove('is-drop'));
        this.dragged = null;
    }

    dragOver(event) {
        if (!this.dragged) {
            return;
        }
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        event.currentTarget.classList.add('is-drop');
    }

    dragLeave(event) {
        if (!event.currentTarget.contains(event.relatedTarget)) {
            event.currentTarget.classList.remove('is-drop');
        }
    }

    async drop(event) {
        event.preventDefault();
        const day = event.currentTarget;
        day.classList.remove('is-drop');
        const dragged = this.dragged;
        this.dragged = null;
        if (!dragged || dragged.from === day.dataset.date) {
            return;
        }

        const response = await fetch(this.urlValue.replace('/0/', `/${dragged.id}/`), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.tokenValue },
            body: JSON.stringify({ from: dragged.from, to: day.dataset.date }),
        });
        if (!response.ok) {
            window.alert('Nie udało się przenieść wydarzenia.');
        }
        Turbo.visit(window.location.href, { action: 'replace' });
    }
}
