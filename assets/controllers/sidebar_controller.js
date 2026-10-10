import { Controller } from '@hotwired/stimulus';

/*
 * Menu boczne: zwijanie do samych ikon na desktopie (zapamiętane w ciasteczku,
 * żeby serwer od razu renderował właściwy stan) i wysuwanie na węższych ekranach.
 */
export default class extends Controller {
    static targets = ['drawer', 'backdrop'];

    toggleCollapsed() {
        const collapsed = !this.element.classList.contains('is-collapsed');
        this.element.classList.toggle('is-collapsed', collapsed);
        document.cookie = `sidebar=${collapsed ? 'collapsed' : 'expanded'}; path=/; max-age=31536000; samesite=lax`;
    }

    open() {
        this.drawerTarget.classList.add('is-open');
        this.backdropTarget.hidden = false;
        document.body.classList.add('overflow-hidden');
    }

    close() {
        this.drawerTarget.classList.remove('is-open');
        this.backdropTarget.hidden = true;
        document.body.classList.remove('overflow-hidden');
    }

    closeOnEscape(event) {
        if (event.key === 'Escape' && !this.backdropTarget.hidden) {
            this.close();
        }
    }
}
