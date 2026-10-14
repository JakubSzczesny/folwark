import { Controller } from '@hotwired/stimulus';

/*
 * Menu pod prawym przyciskiem myszy na wierszach list. Zawsze: Podgląd (jeśli jest), Otwórz,
 * Otwórz w nowej karcie; dalej akcje wiersza z <template data-context-menu>.
 * Klawiatura: klawisz menu albo Shift+F10 na wybranym wierszu, strzałki, Enter, Esc.
 * Shift + prawy przycisk zostawia zwykłe menu przeglądarki.
 */
export default class extends Controller {
    static values = { frame: { type: String, default: 'preview' } };

    connect() {
        this.onContextMenu = (event) => this.open(event);
        this.onKeydown = (event) => this.keydown(event);
        this.onDocumentDown = (event) => {
            if (this.menu && !this.menu.contains(event.target)) {
                this.close();
            }
        };
        this.onClose = () => this.close();

        this.element.addEventListener('contextmenu', this.onContextMenu);
        window.addEventListener('keydown', this.onKeydown);
        document.addEventListener('mousedown', this.onDocumentDown);
        window.addEventListener('resize', this.onClose);
        window.addEventListener('scroll', this.onClose, true);
        document.addEventListener('turbo:before-visit', this.onClose);
    }

    disconnect() {
        this.close();
        this.element.removeEventListener('contextmenu', this.onContextMenu);
        window.removeEventListener('keydown', this.onKeydown);
        document.removeEventListener('mousedown', this.onDocumentDown);
        window.removeEventListener('resize', this.onClose);
        window.removeEventListener('scroll', this.onClose, true);
        document.removeEventListener('turbo:before-visit', this.onClose);
    }

    open(event) {
        const row = event.target.closest('[data-table-nav-target="row"]');
        if (!row || event.shiftKey || event.target.closest('input, textarea, select')) {
            return;
        }
        event.preventDefault();
        this.show(row, event.clientX, event.clientY);
    }

    keydown(event) {
        if (this.menu) {
            this.navigateMenu(event);
            return;
        }
        if (event.key !== 'ContextMenu' && !(event.shiftKey && event.key === 'F10')) {
            return;
        }
        const row = this.element.querySelector('[data-table-nav-target="row"].is-selected');
        if (row) {
            event.preventDefault();
            const rect = row.getBoundingClientRect();
            this.show(row, rect.left + 24, rect.bottom - 4);
        }
    }

    show(row, x, y) {
        this.close();

        const menu = document.createElement('div');
        menu.className = 'dropdown context-menu';
        menu.setAttribute('role', 'menu');

        if (row.dataset.previewUrl) {
            menu.append(this.button('Podgląd', () => this.preview(row)));
        }
        if (row.dataset.href) {
            menu.append(this.link('Otwórz', row.dataset.href));
            const newTab = this.link('Otwórz w nowej karcie', row.dataset.href);
            newTab.target = '_blank';
            newTab.rel = 'noopener';
            menu.append(newTab);
        }

        const template = row.querySelector('template[data-context-menu]');
        if (template && template.content.childElementCount > 0) {
            if (menu.childElementCount > 0) {
                menu.append(document.createElement('hr'));
            }
            menu.append(template.content.cloneNode(true));
        }
        if (menu.childElementCount === 0) {
            return;
        }

        menu.addEventListener('click', (event) => {
            if (event.target.closest('a, button')) {
                setTimeout(() => this.close(), 0);
            }
        });
        menu.querySelectorAll('form').forEach((form) => form.addEventListener('submit', () => this.close()));

        document.body.append(menu);
        const { innerWidth, innerHeight } = window;
        const { width, height } = menu.getBoundingClientRect();
        menu.style.left = `${Math.max(8, Math.min(x, innerWidth - width - 8))}px`;
        menu.style.top = `${Math.max(8, y + height > innerHeight - 8 ? y - height : y)}px`;

        this.menu = menu;
        this.row = row;
        row.classList.add('is-context');
        this.items()[0]?.focus();
    }

    close() {
        this.menu?.remove();
        this.menu = null;
        this.row?.classList.remove('is-context');
        this.row = null;
    }

    navigateMenu(event) {
        const items = this.items();
        const index = items.indexOf(document.activeElement);
        if (event.key === 'Escape' || event.key === 'Tab') {
            event.preventDefault();
            this.close();
        } else if (event.key === 'ArrowDown') {
            event.preventDefault();
            items[(index + 1) % items.length]?.focus();
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            items[(index - 1 + items.length) % items.length]?.focus();
        }
    }

    items() {
        return this.menu ? [...this.menu.querySelectorAll('a.dropdown-item, button.dropdown-item')] : [];
    }

    preview(row) {
        const frame = document.getElementById(this.frameValue);
        if (!frame) {
            return;
        }
        row.classList.remove('is-unread');
        // Ramka trzyma adres pełny, wiersz względny – porównujemy po rozwinięciu.
        if (frame.src && new URL(frame.src, window.location.href).href === new URL(row.dataset.previewUrl, window.location.href).href) {
            frame.reload();
        } else {
            frame.src = row.dataset.previewUrl;
        }
    }

    link(label, href) {
        const a = document.createElement('a');
        a.className = 'dropdown-item';
        a.href = href;
        a.textContent = label;
        a.setAttribute('role', 'menuitem');
        return a;
    }

    button(label, onClick) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'dropdown-item';
        button.textContent = label;
        button.setAttribute('role', 'menuitem');
        button.addEventListener('click', onClick);
        return button;
    }
}
