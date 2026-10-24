/*
 * Szkielet wczytywanej treści z <template id="skeleton-…"> w układzie strony (templates/_partials/skeletons.html.twig).
 * Gdy szablonu nie ma (strona bez gospodarstwa), zostają kropki.
 */
const DOTS = '<div class="flex items-center justify-center p-12" role="status" aria-label="Wczytywanie"><span class="dots dots-lg" aria-hidden="true"><span></span><span></span><span></span></span></div>';

export function skeleton(name) {
    return document.getElementById(`skeleton-${name}`)?.innerHTML ?? DOTS;
}
