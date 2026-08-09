import flatpickr from 'flatpickr';
import { Italian } from 'flatpickr/dist/l10n/it.js';

/**
 * Standing rule: entity Date/DateTime fields render via flatpickr rather
 * than the browser's native <input type="date">/<input type="datetime-local">
 * — see CLAUDE.md and resources/views/entities/_field_input.blade.php,
 * which emits a plain text input carrying `data-flatpickr-field` (plus
 * `data-flatpickr-time` for DateTime) instead of a native date input.
 * flatpickr's own value format (`Y-m-d` / `Y-m-d H:i`) is what actually
 * gets submitted, matching the `date`/`date_format` validation rules in
 * BuildsEntityFieldRules — the calendar-picked value never needs
 * reformatting server-side.
 */
export function initFlatpickrFields(root = document) {
    root.querySelectorAll('[data-flatpickr-field]').forEach((el) => {
        if (el._flatpickr) {
            return;
        }

        const isDateTime = el.dataset.flatpickrField === 'datetime';

        flatpickr(el, {
            locale: Italian,
            enableTime: isDateTime,
            time_24hr: true,
            dateFormat: isDateTime ? 'Y-m-d H:i' : 'Y-m-d',
            altInput: true,
            altFormat: isDateTime ? 'd/m/Y H:i' : 'd/m/Y',
            allowInput: true,
        });
    });
}

document.addEventListener('DOMContentLoaded', () => initFlatpickrFields());

window.initFlatpickrFields = initFlatpickrFields;
