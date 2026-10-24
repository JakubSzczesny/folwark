/*
 * Potwierdzenie akcji w oknie aplikacji zamiast systemowego confirm() przeglądarki.
 * Działa dla data-turbo-confirm na formularzach i linkach oraz z kodu: confirmAction({message, confirmLabel, danger}).
 * Przycisk potwierdzenia bierze napis z przycisku, który wysłał formularz („Usuń maszynę”), a usuwanie
 * i inne nieodwracalne akcje dostają czerwony przycisk.
 */
import * as Turbo from '@hotwired/turbo';

const DANGER = /^(na pewno )?(usun|usuń|wyczyś|odebra|anulow)/i;

function dialog() {
    return document.getElementById('confirm-dialog');
}

export function confirmAction({ message, confirmLabel = 'Potwierdź', danger = DANGER.test(message ?? '') }) {
    const element = dialog();
    if (!element) {
        return Promise.resolve(window.confirm(message));
    }

    element.querySelector('[data-confirm-message]').textContent = message;
    const accept = element.querySelector('[data-confirm-accept]');
    accept.textContent = confirmLabel;
    accept.classList.toggle('btn-danger-solid', danger);
    accept.classList.toggle('btn-primary', !danger);
    element.querySelector('[data-confirm-icon]').hidden = !danger;

    return new Promise((resolve) => {
        const finish = () => {
            element.removeEventListener('close', finish);
            resolve(element.returnValue === 'ok');
        };
        element.returnValue = '';
        element.addEventListener('close', finish);
        element.showModal();
        // Przy usuwaniu domyślnie wybrane jest „Anuluj”, żeby Enter niczego przypadkiem nie skasował.
        element.querySelector(danger ? '[data-confirm-cancel]' : '[data-confirm-accept]').focus();
    });
}

function labelOf(submitter) {
    const text = submitter?.textContent?.replace(/\s+/g, ' ').trim();
    return text && text.length <= 40 ? text.replace(/…$/, '') : 'Potwierdź';
}

const turboConfirm = (message, element, submitter) => confirmAction({
    message,
    confirmLabel: labelOf(submitter ?? (element instanceof HTMLAnchorElement ? element : null)),
    danger: DANGER.test(message) || Boolean(submitter?.classList.contains('btn-danger') || submitter?.classList.contains('text-danger')),
});

if (Turbo.config?.forms) {
    Turbo.config.forms.confirm = turboConfirm;
} else {
    Turbo.setConfirmMethod(turboConfirm);
}

document.addEventListener('click', (event) => {
    const element = dialog();
    // Kliknięcie w tło zamyka okno jak „Anuluj”.
    if (element?.open && event.target === element) {
        element.close('');
    }
});
