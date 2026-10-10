import { Controller } from '@hotwired/stimulus';

/*
 * Pola płatności dzierżawy widać tylko przy umowie rodzaju „Dzierżawa”.
 */
export default class extends Controller {
    static targets = ['kind', 'lease'];

    connect() {
        this.toggle();
    }

    toggle() {
        this.leaseTarget.hidden = this.kindTarget.value !== 'lease';
    }
}
