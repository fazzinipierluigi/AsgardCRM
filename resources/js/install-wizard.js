/**
 * Install wizard's database step (resources/views/install/database.blade.php):
 * hides the host/port/username/password fieldset for `sqlite` (it needs
 * none of them — see StoreDatabaseRequest's `required_unless:driver,sqlite`
 * rules) and wires the "Test connection" button to the wizard's own
 * `database/test-connection` JSON endpoint before the form is actually
 * submitted.
 */
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('install-database-form');

    if (!form) {
        return;
    }

    const driverSelect = document.getElementById('driver');
    const connectionFields = document.getElementById('install-db-connection-fields');
    const testButton = document.getElementById('install-test-connection-button');
    const resultBox = document.getElementById('install-test-connection-result');
    const csrfToken = form.querySelector('input[name="_token"]').value;

    function applyDriverVisibility() {
        connectionFields.classList.toggle('d-none', driverSelect.value === 'sqlite');
    }

    function showResult(ok, message) {
        resultBox.textContent = message;
        resultBox.classList.remove('d-none', 'alert-success', 'alert-danger');
        resultBox.classList.add(ok ? 'alert-success' : 'alert-danger');
    }

    driverSelect.addEventListener('change', applyDriverVisibility);
    applyDriverVisibility();

    testButton.addEventListener('click', function () {
        testButton.disabled = true;

        const payload = {
            driver: driverSelect.value,
            host: form.host.value,
            port: form.port.value,
            database: form.database.value,
            username: form.username.value,
            password: form.password.value,
        };

        fetch(form.dataset.testUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(payload),
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                showResult(data.ok, data.ok ? 'Connection successful.' : data.message);
            })
            .catch(function () {
                showResult(false, 'Could not reach the server.');
            })
            .finally(function () {
                testButton.disabled = false;
            });
    });
});
