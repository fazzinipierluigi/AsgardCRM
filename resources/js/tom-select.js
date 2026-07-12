import TomSelect from '@tabler/core/dist/libs/tom-select/dist/js/tom-select.complete.js';

/**
 * Standing rule: prefer the JS plugins the Tabler template ships over
 * plain vanilla form fields. Every real <select> in the app's own Blade
 * views goes through this — see CLAUDE.md. Not applied to markup we
 * don't control (e.g. raccoon-tables' own grid-internal filter dropdowns).
 *
 * Wraps Tabler's vendored Tom Select (node_modules/@tabler/core/dist/libs/tom-select)
 * with the app's defaults. Tom Select keeps the original <select> element
 * in the DOM (hidden) and mirrors it — reading/writing `.value` and
 * listening for native 'change' events on the original element keeps
 * working unchanged. Code that needs to *set* the value or mutate the
 * option list programmatically must go through the returned instance
 * (`instance.setValue(...)`, `.addOption(...)`, `.removeOption(...)`,
 * `.clearOptions()`, `.refreshOptions(false)`) — writing `select.value`
 * or calling native DOM methods on the hidden original element does not
 * update Tom Select's own rendered UI.
 *
 * @param {string|HTMLSelectElement} target
 * @param {object} [options] TomSelect options, merged over the defaults below.
 * @returns {TomSelect|null} null if the target doesn't exist.
 */
export function tomSelect(target, options = {}) {
    const el = typeof target === 'string' ? document.querySelector(target) : target;

    if (!el || el.tomselect) {
        return el ? el.tomselect : null;
    }

    return new TomSelect(el, Object.assign({ create: false, allowEmptyOption: true, maxOptions: null }, options));
}

/**
 * Initializes every plain <select> present in the DOM at call time, except
 * ones carrying `data-tom-select-manual` — an explicit opt-out for a select
 * that needs custom options (e.g. a custom `render.option`) and so
 * initializes itself by calling tomSelect() directly instead. Run once on
 * DOMContentLoaded for the initial page, and call again after injecting
 * new <select> markup at runtime (e.g. a modal body swapped in via fetch)
 * — already-initialized selects are skipped (see the el.tomselect guard
 * above), so calling this repeatedly is safe.
 */
export function tomSelectAll(root = document) {
    root.querySelectorAll('select:not([data-tom-select-manual])').forEach((select) => tomSelect(select));
}

/**
 * Sets a <select>'s value whether or not it's Tom-Select-wrapped —
 * `el.value = x` alone only updates the hidden original element, not
 * Tom Select's own rendered UI, so callers that programmatically set a
 * select's value (as opposed to reading it, or reacting to the user's
 * own 'change' event) must go through this instead of `el.value = x`.
 *
 * Silent by default (no 'change' event), matching plain `el.value = x`
 * semantics — pass `silent: false` to fire one deliberately.
 *
 * @param {string|HTMLSelectElement} target
 * @param {string} value
 * @param {boolean} [silent]
 */
export function setSelectValue(target, value, silent = true) {
    const el = typeof target === 'string' ? document.querySelector(target) : target;

    if (!el) {
        return;
    }

    if (el.tomselect) {
        el.tomselect.setValue(value, silent);
    } else {
        el.value = value;
    }
}

document.addEventListener('DOMContentLoaded', () => tomSelectAll());

window.tomSelect = tomSelect;
window.tomSelectAll = tomSelectAll;
window.setSelectValue = setSelectValue;
