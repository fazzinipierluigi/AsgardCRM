/**
 * Installation wizard's database step: hides the host/port/username/
 * password fields for the sqlite driver (which needs none of them) and
 * wires the "Test connection" button to the AJAX probe endpoint.
 */
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('install-database-form');

    if (!form) {
        return;
    }

    var driverSelect = document.getElementById('driver');
    var connectionFields = document.getElementById('install-db-connection-fields');
    var testButton = document.getElementById('install-test-connection-button');
    var resultBox = document.getElementById('install-test-connection-result');

    function toggleConnectionFields() {
        connectionFields.classList.toggle('d-none', driverSelect.value === 'sqlite');
    }

    driverSelect.addEventListener('change', toggleConnectionFields);
    toggleConnectionFields();

    testButton.addEventListener('click', function () {
        resultBox.classList.add('d-none');
        testButton.disabled = true;
        testButton.textContent = 'Testing...';

        fetch(form.dataset.testUrl, {
            method: 'POST',
            headers: { Accept: 'application/json' },
            body: new FormData(form),
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (result) {
                resultBox.classList.remove('d-none', 'alert-success', 'alert-danger');
                resultBox.classList.add(result.ok ? 'alert-success' : 'alert-danger');
                resultBox.textContent = result.ok ? 'Connection successful.' : (result.message || 'Connection failed.');
            })
            .catch(function () {
                resultBox.classList.remove('d-none', 'alert-success');
                resultBox.classList.add('alert-danger');
                resultBox.textContent = 'Connection failed.';
            })
            .finally(function () {
                testButton.disabled = false;
                testButton.textContent = 'Test connection';
            });
    });
});
