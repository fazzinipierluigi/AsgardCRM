import hugerte from 'hugerte';

/**
 * Standing rule: HugeRTE (self-hosted, MIT-licensed TinyMCE fork — see
 * CLAUDE.md/memory) is the app's one rich-text/WYSIWYG editor, the
 * same idea as tom-select.js being the one place every <select>
 * upgrade goes through. Its runtime assets (skins/icons/plugins/themes)
 * aren't bundled by Vite — HugeRTE fetches them itself, at runtime,
 * from `base_url` below, over plain HTTP. That path is a symlink,
 * `public/hugerte` → `../node_modules/hugerte` (created by the
 * `composer setup` script, mirroring Laravel's own `storage:link`
 * idiom), so it always matches whatever version is installed without
 * a separate asset-copy build step.
 *
 * Unlike tomSelectAll(), there is no auto-sweep: not every textarea
 * should become a rich-text editor, so callers opt in explicitly by
 * calling initRichText() on the specific element they want upgraded.
 *
 * @param {string|HTMLElement} target
 * @param {object} [options] HugeRTE init options, merged over the defaults below.
 * @returns {Promise<object|null>} the editor instance, or null if the target doesn't exist.
 */
export function initRichText(target, options = {}) {
    const el = typeof target === 'string' ? document.querySelector(target) : target;

    if (!el) {
        return Promise.resolve(null);
    }

    return hugerte.init(Object.assign({
        target: el,
        base_url: '/hugerte',
        menubar: false,
        statusbar: false,
        branding: false,
        plugins: 'lists link autolink autoresize',
        toolbar: 'bold italic underline | bullist numlist | link | removeformat',
        autoresize_bottom_margin: 16,
        min_height: 240,
    }, options)).then((editors) => editors[0] || null);
}

/**
 * Every HugeRTE editor keeps its content in the DOM iframe it manages,
 * not in the original <textarea>/<div> it replaced — save() copies the
 * current HTML back onto that original element (mirroring
 * tinymce.triggerSave()), which is what lets existing "read the
 * form/hidden input value" code keep working unchanged right before a
 * form submit.
 *
 * @param {object} editor
 */
export function syncRichText(editor) {
    if (editor) {
        editor.save();
    }
}

window.initRichText = initRichText;
window.syncRichText = syncRichText;
