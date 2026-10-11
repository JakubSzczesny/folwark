import { StreamActions, visit } from '@hotwired/turbo';
import './stimulus_bootstrap.js';
import './loading.js';
import './autocomplete_in_dialog.js';
import './styles/app.css';

// Po zapisie formularza w oknie dialogowym serwer może wskazać stronę, na którą przejść.
StreamActions.visit = function () {
    visit(this.getAttribute('url'));
};

// Nowy towar dodany w okienku – trafia do wiersza przypisania, z którego go dodano.
StreamActions.product_created = function () {
    const row = document.querySelector(`[data-assign-row-item-value="${this.getAttribute('row')}"]`);
    row?.dispatchEvent(new CustomEvent('product-created', {
        detail: {
            id: this.getAttribute('product-id'),
            label: this.getAttribute('product-label'),
            unit: this.getAttribute('unit'),
            factor: this.getAttribute('factor') || null,
        },
    }));
};
