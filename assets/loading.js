/*
 * Kręcące się kółko na przycisku, który właśnie coś wysyła albo otwiera.
 * Turbo wysyła formularze i przechodzi po linkach w tle, więc bez tego nie widać, że coś się dzieje.
 */
const LOADING = 'is-loading';

function start(button) {
    if (button instanceof HTMLElement && button.classList.contains('btn')) {
        button.classList.add(LOADING);
    }
}

function stopAll() {
    document.querySelectorAll(`.btn.${LOADING}`).forEach((button) => button.classList.remove(LOADING));
}

document.addEventListener('turbo:submit-start', (event) => {
    const { formSubmission } = event.detail;
    start(formSubmission.submitter ?? formSubmission.formElement.querySelector('[type=submit]'));
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

['turbo:load', 'turbo:frame-load', 'turbo:before-cache', 'turbo:fetch-request-error'].forEach((name) => {
    document.addEventListener(name, stopAll);
});
window.addEventListener('pageshow', stopAll);
