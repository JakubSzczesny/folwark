import { StreamActions, visit } from '@hotwired/turbo';
import './stimulus_bootstrap.js';
import './loading.js';
import './autocomplete_in_dialog.js';
import './styles/app.css';

// Po zapisie formularza w oknie dialogowym serwer może wskazać stronę, na którą przejść.
StreamActions.visit = function () {
    visit(this.getAttribute('url'));
};
