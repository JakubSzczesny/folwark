import { Controller } from '@hotwired/stimulus';
import { confirmAction } from '../confirm.js';

/*
 * Zaznaczanie wierszy listy do akcji masowych: pola wyboru w wierszach, „cała strona”
 * w nagłówku, Shift + klik zaznacza zakres, a „wszystkie pasujące” obejmuje też kolejne strony
 * (serwer wybiera wtedy faktury według filtrów). Pasek akcji pojawia się po zaznaczeniu.
 */
export default class extends Controller {
    static targets = ['item', 'page', 'bar', 'summary', 'pageButton', 'matchingButton', 'matching', 'form'];
    static values = { total: Number, costOnlyUrl: String };

    connect() {
        this.last = null;
        this.allMatching = false;
        this.update();
    }

    // Wiersz podmieniony strumieniem Turbo (np. po akcji z podglądu) – przeliczamy zaznaczenie.
    itemTargetConnected() {
        this.update();
    }

    toggle(event) {
        const box = event.currentTarget;
        const index = this.itemTargets.indexOf(box);
        if (event.shiftKey && this.last !== null && this.last !== index) {
            const [from, to] = [Math.min(this.last, index), Math.max(this.last, index)];
            this.itemTargets.slice(from, to + 1).forEach((item) => {
                item.checked = box.checked;
            });
        }
        this.last = index;
        this.allMatching = false;
        this.update();
    }

    togglePage(event) {
        this.setAll(event.currentTarget.checked);
    }

    selectPage() {
        this.setAll(true);
    }

    selectMatching() {
        this.setAll(true);
        this.allMatching = true;
        this.update();
    }

    clear() {
        this.setAll(false);
    }

    setAll(checked) {
        this.itemTargets.forEach((item) => {
            item.checked = checked;
        });
        this.allMatching = false;
        this.last = null;
        this.update();
    }

    update() {
        if (!this.hasBarTarget) {
            return;
        }
        const checked = this.itemTargets.filter((item) => item.checked).length;
        const all = this.itemTargets.length;
        const count = this.selectedCount();

        this.barTarget.hidden = checked === 0;
        this.summaryTarget.textContent = this.allMatching
            ? `Zaznaczono wszystkie pasujące do filtrów: ${count}`
            : `Zaznaczono: ${count}`;
        if (this.hasMatchingTarget) {
            this.matchingTarget.value = this.allMatching ? '1' : '0';
        }
        if (this.hasPageTarget) {
            this.pageTarget.checked = all > 0 && checked === all;
            this.pageTarget.indeterminate = checked > 0 && checked < all;
        }
        if (this.hasPageButtonTarget) {
            this.pageButtonTarget.hidden = checked === all;
        }
        if (this.hasMatchingButtonTarget) {
            this.matchingButtonTarget.hidden = this.allMatching;
        }
        this.itemTargets.forEach((item) => item.closest('tr')?.classList.toggle('is-checked', item.checked));
    }

    selectedCount() {
        return this.allMatching ? this.totalValue : this.itemTargets.filter((item) => item.checked).length;
    }

    submit(event) {
        const count = this.selectedCount();
        if (count === 0) {
            event.preventDefault();
            return;
        }
        if (this.allMatching && !this.confirmed) {
            event.preventDefault();
            const submitter = event.submitter;
            confirmAction({ message: `Zmienić ${count} ${plural(count)}?`, confirmLabel: submitter?.textContent.trim() || 'Zmień' })
                .then((ok) => {
                    if (ok) {
                        this.confirmed = true;
                        this.formTarget.requestSubmit(submitter);
                        this.confirmed = false;
                    }
                });
        }
    }

    // „Tylko koszt” wymaga wyboru ośrodka i kategorii – otwiera okno z zaznaczeniem w adresie.
    costOnly() {
        const url = new URL(this.costOnlyUrlValue, window.location.href);
        if (this.allMatching) {
            url.searchParams.set('wszystkie', '1');
        } else {
            this.itemTargets.filter((item) => item.checked).forEach((item) => url.searchParams.append('ids[]', item.value));
        }
        url.searchParams.set('filtr', this.formTarget.elements.filtr.value);
        document.getElementById('modal')?.setAttribute('src', url.pathname + url.search);
    }
}

function plural(n) {
    if (n === 1) {
        return 'fakturę';
    }
    const tens = n % 100;
    const units = n % 10;
    return units >= 2 && units <= 4 && (tens < 12 || tens > 14) ? 'faktury' : 'faktur';
}
