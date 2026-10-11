/*
 * Listy z wyszukiwaniem (Tom Select) w oknie dialogowym: środek okna się przewija, więc rozwinięta
 * lista byłaby w nim ucięta. Podpinamy ją bezpośrednio pod <dialog> i ustawiamy pod polem.
 */
document.addEventListener('autocomplete:pre-connect', (event) => {
    const dialog = event.target.closest('dialog');
    if (dialog) {
        event.detail.options.dropdownParent = dialog;
    }
});

document.addEventListener('autocomplete:connect', (event) => {
    const dialog = event.target.closest('dialog');
    if (!dialog) {
        return;
    }

    const { tomSelect } = event.detail;
    tomSelect.positionDropdown = function () {
        const field = this.control.getBoundingClientRect();
        const box = dialog.getBoundingClientRect();
        Object.assign(this.dropdown.style, {
            position: 'absolute',
            width: `${field.width}px`,
            top: `${field.bottom - box.top - dialog.clientTop + 4}px`,
            left: `${field.left - box.left - dialog.clientLeft}px`,
        });
    };

    // Przewinięcie środka okna przesuwa pole – lista idzie za nim.
    dialog.addEventListener('scroll', () => tomSelect.isOpen && tomSelect.positionDropdown(), { capture: true });
});
