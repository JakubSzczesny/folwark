import { Controller } from '@hotwired/stimulus';
import * as Turbo from '@hotwired/turbo';

/*
 * Wyszukiwarka globalna: Ctrl+K lub "/" otwiera okno, strzałki wybierają wynik, Enter przechodzi.
 */
export default class extends Controller {
    static targets = ['dialog', 'input', 'results'];
    static values = { url: String };

    connect() {
        this.timer = null;
        this.abort = null;
        this.index = -1;
    }

    shortcut(event) {
        const typing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)
            || document.activeElement?.isContentEditable;

        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            this.open();
        } else if (event.key === '/' && !typing) {
            event.preventDefault();
            this.open();
        }
    }

    open() {
        if (!this.dialogTarget.open) {
            this.dialogTarget.showModal();
        }
        this.inputTarget.focus();
        this.inputTarget.select();
    }

    close() {
        this.dialogTarget.close();
    }

    closeOnBackdrop(event) {
        if (event.target === this.dialogTarget) {
            this.close();
        }
    }

    query() {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.fetchResults(), 150);
    }

    async fetchResults() {
        const q = this.inputTarget.value.trim();
        this.abort?.abort();
        this.abort = new AbortController();

        const url = new URL(this.urlValue, window.location.origin);
        url.searchParams.set('q', q);

        const abort = this.abort;
        this.dialogTarget.classList.add('is-loading');
        try {
            const response = await fetch(url, { signal: abort.signal, headers: { 'X-Requested-With': 'fetch' } });
            this.resultsTarget.innerHTML = await response.text();
            this.index = -1;
            this.highlight(0);
        } catch (e) {
            if (e.name !== 'AbortError') {
                throw e;
            }
        } finally {
            if (this.abort === abort) {
                this.dialogTarget.classList.remove('is-loading');
            }
        }
    }

    navigate(event) {
        const items = this.items();
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            this.highlight(Math.min(this.index + 1, items.length - 1));
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            this.highlight(Math.max(this.index - 1, 0));
        } else if (event.key === 'Enter') {
            event.preventDefault();
            const item = items[this.index];
            if (item) {
                this.close();
                Turbo.visit(item.getAttribute('href'));
            }
        }
    }

    items() {
        return Array.from(this.resultsTarget.querySelectorAll('[data-search-item]'));
    }

    highlight(index) {
        const items = this.items();
        items.forEach((el, i) => {
            const active = i === index;
            el.classList.toggle('bg-szary-100', active);
            el.setAttribute('aria-selected', active ? 'true' : 'false');
            if (active) {
                el.scrollIntoView({ block: 'nearest' });
            }
        });
        this.index = items.length ? index : -1;
    }

    choose() {
        this.close();
    }
}
