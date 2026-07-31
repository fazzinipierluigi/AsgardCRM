import Modal from 'bootstrap/js/dist/modal';

/**
 * "Nuova cartella" on documents/index.blade.php: driven entirely
 * through the Modal instance API (not `data-bs-toggle="modal"`), same
 * reasoning as entity-builder.js's own name/field modals — an
 * explicit instance avoids depending on Tabler's bundled Bootstrap
 * data-api being wired for every component.
 */
document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('new-folder-modal');
    var trigger = document.querySelector('[data-testid="document-new-folder-btn"]');

    if (!modalEl || !trigger) {
        return;
    }

    var modal = new Modal(modalEl);
    trigger.addEventListener('click', function () {
        modal.show();
    });
});
