/**
 * Wires up every "Bottone" entity field (see resources/views/entities/
 * _field_input.blade.php, @case('button')): javascript actions run
 * entirely client-side via `new Function`, workflow/importer actions
 * POST to EntityFieldButtonController and report the JSON response.
 * Feedback is always a SweetAlert2 toast (window.Swal is already
 * global, see app.js).
 */
window.showEntityButtonToast = function (message, icon) {
    window.Swal.fire({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        icon: icon || 'success',
        title: message,
    });
};

document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-entity-button]');
    if (!button) {
        return;
    }

    var mode = button.dataset.mode;

    if (mode === 'javascript') {
        try {
            new Function(button.dataset.js || '')();
        } catch (error) {
            window.showEntityButtonToast(error.message, 'error');
        }

        return;
    }

    button.disabled = true;

    fetch(button.dataset.url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': window.CSRF_TOKEN,
            Accept: 'application/json',
        },
    })
        .then(function (response) {
            return response.json().then(function (data) {
                return { ok: response.ok, data: data };
            });
        })
        .then(function (result) {
            window.showEntityButtonToast(result.data.message, result.ok ? 'success' : 'error');
        })
        .catch(function () {
            window.showEntityButtonToast('Errore imprevisto.', 'error');
        })
        .finally(function () {
            button.disabled = false;
        });
});
