/*
 * Wskaźniki ładowania. Turbo wysyła formularze i przechodzi po linkach w tle, więc bez nich nie widać,
 * że coś się dzieje.
 * - Kółko na przycisku, który właśnie coś wysyła albo otwiera.
 * - Okienko „Zapisywanie…” na dole ekranu, gdy zapis nie ma przycisku (menu pod prawym przyciskiem)
 *   albo dzieje się w tle – kontrolery zgłaszają je zdarzeniem app:busy {busy, label}.
 * - Kropki na pasku filtrów i przygaszona lista, gdy lista odświeża się po zmianie filtrów.
 */
const LOADING = 'is-loading';
const REFRESHING = 'is-refreshing';
const SAVE_DELAY = 150;

let busyCount = 0;
let saveTimer = null;

function start(button) {
    if (button instanceof HTMLElement && button.classList.contains('btn')) {
        button.classList.add(LOADING);
        return true;
    }
    return false;
}

function indicator() {
    return document.querySelector('[data-save-indicator]');
}

/* Krótkie zapisy nie migają – okienko pojawia się dopiero po chwili. */
function showSaving(label = 'Zapisywanie…') {
    busyCount += 1;
    clearTimeout(saveTimer);
    saveTimer = setTimeout(() => {
        const element = indicator();
        if (element && busyCount > 0) {
            element.querySelector('[data-save-indicator-label]').textContent = label;
            element.hidden = false;
        }
    }, SAVE_DELAY);
}

function hideSaving(all = false) {
    busyCount = all ? 0 : Math.max(0, busyCount - 1);
    if (busyCount === 0) {
        clearTimeout(saveTimer);
        const element = indicator();
        if (element) {
            element.hidden = true;
        }
    }
}

function stopAll() {
    document.querySelectorAll(`.btn.${LOADING}`).forEach((button) => button.classList.remove(LOADING));
    document.querySelectorAll(`.${REFRESHING}`).forEach((element) => element.classList.remove(REFRESHING));
    hideSaving(true);
}

document.addEventListener('turbo:submit-start', (event) => {
    const { formSubmission } = event.detail;
    const form = formSubmission.formElement;
    if (form.method.toLowerCase() === 'get') {
        // Filtry listy: lista zostaje na miejscu do czasu przyjścia nowej.
        if (form.classList.contains('filter-bar')) {
            form.classList.add(REFRESHING);
        }
        start(formSubmission.submitter);
        return;
    }
    if (!start(formSubmission.submitter ?? form.querySelector('[type=submit]'))) {
        showSaving();
    }
});

// Po udanym wysłaniu zwykle następuje przejście na inną stronę – kółko znika dopiero wtedy.
// Błąd walidacji albo odpowiedź strumieniowa (bez przejścia) kończą ładowanie od razu.
document.addEventListener('turbo:submit-end', (event) => {
    const contentType = event.detail.fetchResponse?.contentType ?? '';
    if (!event.detail.success || contentType.startsWith('text/vnd.turbo-stream.html')) {
        stopAll();
    }
});

document.addEventListener('turbo:click', (event) => {
    start(event.target.closest('a'));
});

// Formularze z wyłączonym Turbo (np. przełączanie gospodarstwa) przeładowują całą stronę.
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!event.defaultPrevented && form instanceof HTMLFormElement && form.closest('[data-turbo="false"]')) {
        start(event.submitter ?? form.querySelector('[type=submit]'));
    }
});

document.addEventListener('app:busy', (event) => {
    if (event.detail?.busy) {
        showSaving(event.detail.label);
    } else {
        hideSaving();
    }
});

['turbo:load', 'turbo:frame-load', 'turbo:before-cache', 'turbo:fetch-request-error'].forEach((name) => {
    document.addEventListener(name, stopAll);
});
window.addEventListener('pageshow', stopAll);
