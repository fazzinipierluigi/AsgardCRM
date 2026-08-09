import { initRichText, syncRichText } from './hugerte.js';

/**
 * Admin "Firme e-mail" create/edit form (resources/views/admin/
 * mail-signatures/_form.blade.php) — a HugeRTE editor over the
 * signature's body_html, plus a handful of buttons that insert a
 * literal `{{user.*}}` placeholder token at the cursor (see
 * App\Models\MailSignature::render() for how those get resolved once
 * the signature is actually used in a message).
 */
document.addEventListener('DOMContentLoaded', function () {
    var root = document.getElementById('mail-signature-form-app');

    if (!root) {
        return;
    }

    var textarea = document.getElementById('body_html');
    var form = textarea.closest('form');
    var editor = null;

    initRichText(textarea, { min_height: 200 }).then(function (created) {
        editor = created;
    });

    document.querySelectorAll('[data-insert-placeholder]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (editor) {
                editor.insertContent(button.dataset.insertPlaceholder);
                editor.focus();
            }
        });
    });

    form.addEventListener('submit', function () {
        syncRichText(editor);
    });
});
