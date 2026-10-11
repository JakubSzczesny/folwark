import { Controller } from '@hotwired/stimulus';
import * as Turbo from '@hotwired/turbo';

/*
 * Obsługa klawiatury w tabelach: ↑/↓ wybór wiersza, Spacja podgląd, Enter pełny widok.
 * Kliknięcie wiersza otwiera podgląd (jeśli jest), w przeciwnym razie kartę obiektu.
 * Ctrl/Cmd + klik, środkowy przycisk albo Ctrl + Enter otwierają kartę obiektu w nowej karcie przeglądarki.
 */
export default class extends Controller {
    static targets = ['row'];
    static values = { frame: { type: String, default: 'preview' } };

    connect() {
        this.selected = this.rowTargets.findIndex((row) => row.classList.contains('is-selected'));
        this.onAuxClick = (event) => {
            const row = event.target.closest('[data-table-nav-target="row"]');
            if (event.button === 1 && row?.dataset.href && !event.target.closest('a, button')) {
                event.preventDefault();
                this.openInNewTab(row.dataset.href);
            }
        };
        this.element.addEventListener('auxclick', this.onAuxClick);
    }

    disconnect() {
        this.element.removeEventListener('auxclick', this.onAuxClick);
    }

    keydown(event) {
        if (this.isTyping() || document.querySelector('dialog[open]') || event.altKey) {
            return;
        }

        if (event.key === 'Enter' && (event.ctrlKey || event.metaKey) && this.rowTargets[this.selected]?.dataset.href) {
            event.preventDefault();
            this.openInNewTab(this.rowTargets[this.selected].dataset.href);
            return;
        }
        if (event.ctrlKey || event.metaKey) {
            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const delta = event.key === 'ArrowDown' ? 1 : -1;
            const next = Math.max(0, Math.min(this.rowTargets.length - 1, this.selected + delta));
            this.select(next);
        } else if (event.key === ' ' && this.selected >= 0) {
            event.preventDefault();
            this.preview(this.rowTargets[this.selected]);
        } else if (event.key === 'Enter' && this.selected >= 0) {
            event.preventDefault();
            const href = this.rowTargets[this.selected].dataset.href;
            if (href) {
                Turbo.visit(href);
            }
        }
    }

    click(event) {
        if (event.target.closest('a, button, input, select, label, form')) {
            return;
        }
        const row = event.currentTarget;
        if ((event.ctrlKey || event.metaKey) && row.dataset.href) {
            this.openInNewTab(row.dataset.href);
            return;
        }
        this.select(this.rowTargets.indexOf(row));
        if (row.dataset.previewUrl) {
            this.preview(row);
        } else if (row.dataset.href) {
            Turbo.visit(row.dataset.href);
        }
    }

    select(index) {
        this.rowTargets.forEach((row, i) => row.classList.toggle('is-selected', i === index));
        this.selected = index;
        this.rowTargets[index]?.scrollIntoView({ block: 'nearest' });
    }

    preview(row) {
        const frame = document.getElementById(this.frameValue);
        if (!row.dataset.previewUrl || !frame) {
            if (row.dataset.href) {
                Turbo.visit(row.dataset.href);
            }
            return;
        }
        row.classList.remove('is-unread');
        if (frame.getAttribute('src') === row.dataset.previewUrl) {
            frame.reload();
        } else {
            frame.src = row.dataset.previewUrl;
        }
    }

    openInNewTab(href) {
        window.open(href, '_blank', 'noopener');
    }

    isTyping() {
        const el = document.activeElement;
        return el && (['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName) || el.isContentEditable);
    }
}
