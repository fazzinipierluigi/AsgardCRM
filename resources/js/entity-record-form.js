document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('entity-record-form');

    if (!form) {
        return;
    }

    form.addEventListener('click', function (event) {
        var button = event.target.closest('[data-command]');

        if (!button) {
            return;
        }

        event.preventDefault();

        var editor = button.closest('.mb-3').querySelector('.rich-text-editor');
        editor.focus();
        document.execCommand(button.dataset.command, false, null);
    });

    form.addEventListener('submit', function () {
        form.querySelectorAll('.rich-text-editor').forEach(function (editor) {
            var hidden = editor.closest('.mb-3').querySelector('.rich-text-input');
            hidden.value = editor.innerHTML;
        });
    });
});
