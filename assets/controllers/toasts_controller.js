import { Controller } from '@hotwired/stimulus';

/*
 * Komunikaty po akcji: wysuwają się od góry nad treścią i same znikają (błędy wiszą dłużej).
 * Najechanie myszą albo fokus zatrzymuje odliczanie; krzyżyk zamyka od razu.
 */
const DURATION = { danger: 9000, warning: 7000 };
const DEFAULT_DURATION = 4500;

export default class extends Controller {
    static targets = ['toast'];

    connect() {
        this.timers = new Map();
        this.onBeforeCache = () => this.element.remove();
        document.addEventListener('turbo:before-cache', this.onBeforeCache);

        requestAnimationFrame(() => {
            this.toastTargets.forEach((toast, index) => {
                toast.style.transitionDelay = `${index * 60}ms`;
                toast.classList.add('is-visible');
                toast.style.setProperty('--toast-duration', `${this.duration(toast)}ms`);
                this.schedule(toast, this.duration(toast));
            });
        });
    }

    disconnect() {
        document.removeEventListener('turbo:before-cache', this.onBeforeCache);
        this.timers.forEach(({ timer }) => clearTimeout(timer));
    }

    duration(toast) {
        const tone = [...toast.classList].find((name) => name.startsWith('toast-'))?.slice(6);
        return DURATION[tone] ?? DEFAULT_DURATION;
    }

    schedule(toast, remaining) {
        toast.classList.remove('is-paused');
        this.timers.set(toast, { timer: setTimeout(() => this.hide(toast), remaining), started: Date.now(), remaining });
    }

    pause(event) {
        const toast = event.currentTarget;
        const entry = this.timers.get(toast);
        if (!entry) {
            return;
        }
        clearTimeout(entry.timer);
        entry.remaining = Math.max(1000, entry.remaining - (Date.now() - entry.started));
        entry.timer = null;
        toast.classList.add('is-paused');
    }

    resume(event) {
        const toast = event.currentTarget;
        const entry = this.timers.get(toast);
        if (entry && entry.timer === null && !toast.contains(document.activeElement)) {
            this.schedule(toast, entry.remaining);
        }
    }

    close(event) {
        this.hide(event.currentTarget.closest('.toast'));
    }

    hide(toast) {
        clearTimeout(this.timers.get(toast)?.timer);
        this.timers.delete(toast);
        toast.style.transitionDelay = '0ms';
        toast.classList.remove('is-visible');
        // Usuwamy po czasie animacji wysuwania (bez czekania na transitionend – przy ograniczonym ruchu go nie ma).
        setTimeout(() => {
            toast.remove();
            if (this.toastTargets.length === 0) {
                this.element.remove();
            }
        }, 300);
    }
}
