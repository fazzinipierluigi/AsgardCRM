import Offcanvas from 'bootstrap/js/dist/offcanvas';

/**
 * Wires the "Relazioni" sidebar card on a record's detail page (see
 * resources/views/entities/edit.blade.php): clicking a relation opens
 * a shared full-page offcanvas sheet showing the target-entity records
 * currently linked to this record, with a Tom Select (remote search
 * via EntityRelationLinkController::options()) to attach a new one and
 * a "Rimuovi" button per row to detach.
 */
document.addEventListener('DOMContentLoaded', function () {
    var offcanvasEl = document.getElementById('entity-relations-offcanvas');

    if (!offcanvasEl) {
        return;
    }

    var offcanvas = new Offcanvas(offcanvasEl);
    var title = document.getElementById('entity-relations-offcanvas-title');
    var tbody = document.getElementById('entity-relations-tbody');
    var attachBtn = document.getElementById('entity-relations-attach-btn');
    var attachSelect = document.getElementById('entity-relations-attach-select');
    var current = null;

    function csrfHeaders(extra) {
        return Object.assign({ 'X-CSRF-TOKEN': window.CSRF_TOKEN, Accept: 'application/json' }, extra || {});
    }

    function mountAttachSelect() {
        if (attachSelect.tomselect) {
            attachSelect.tomselect.destroy();
        }

        return window.tomSelect(attachSelect, {
            valueField: 'id',
            labelField: 'text',
            searchField: 'text',
            options: [],
            load: function (query, callback) {
                fetch(current.optionsUrl + '?q=' + encodeURIComponent(query), { headers: csrfHeaders() })
                    .then(function (response) { return response.json(); })
                    .then(function (data) { callback(data); })
                    .catch(function () { callback(); });
            },
        });
    }

    function renderRows(links) {
        tbody.innerHTML = '';

        if (!links.length) {
            var emptyRow = document.createElement('tr');
            emptyRow.innerHTML = '<td colspan="2" class="text-secondary" data-testid="entity-relations-empty"></td>';
            emptyRow.querySelector('td').textContent = window.ENTITY_RELATIONS_I18N.empty;
            tbody.appendChild(emptyRow);

            return;
        }

        links.forEach(function (link) {
            var row = document.createElement('tr');

            var labelCell = document.createElement('td');
            var labelLink = document.createElement('a');
            labelLink.href = link.url;
            labelLink.textContent = link.label;
            labelCell.appendChild(labelLink);
            row.appendChild(labelCell);

            var actionCell = document.createElement('td');
            actionCell.className = 'text-end';
            var removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn btn-sm btn-outline-danger';
            removeBtn.textContent = window.ENTITY_RELATIONS_I18N.remove;
            removeBtn.dataset.linkId = link.link_id;
            removeBtn.setAttribute('data-testid', 'entity-relation-detach-btn');
            actionCell.appendChild(removeBtn);
            row.appendChild(actionCell);

            tbody.appendChild(row);
        });
    }

    function reloadData() {
        return fetch(current.dataUrl, { headers: csrfHeaders() })
            .then(function (response) { return response.json(); })
            .then(function (links) {
                renderRows(links);

                var badge = document.querySelector('[data-data-url="' + current.dataUrl + '"] [data-entity-relation-count]');

                if (badge) {
                    badge.textContent = links.length;
                }
            });
    }

    document.querySelectorAll('[data-entity-relation-open]').forEach(function (button) {
        button.addEventListener('click', function () {
            current = {
                dataUrl: button.dataset.dataUrl,
                optionsUrl: button.dataset.optionsUrl,
                attachUrl: button.dataset.attachUrl,
                detachUrlBase: button.dataset.detachUrlBase,
            };

            title.textContent = button.dataset.relationName;
            mountAttachSelect();
            reloadData();
            offcanvas.show();
        });
    });

    attachBtn.addEventListener('click', function () {
        var targetId = attachSelect.tomselect ? attachSelect.tomselect.getValue() : attachSelect.value;

        if (!current || !targetId) {
            return;
        }

        attachBtn.disabled = true;

        fetch(current.attachUrl, {
            method: 'POST',
            headers: csrfHeaders({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({ target_record_id: targetId }),
        })
            .then(function () {
                if (attachSelect.tomselect) {
                    attachSelect.tomselect.clear();
                    attachSelect.tomselect.clearOptions();
                }

                return reloadData();
            })
            .finally(function () {
                attachBtn.disabled = false;
            });
    });

    tbody.addEventListener('click', function (event) {
        var button = event.target.closest('[data-link-id]');

        if (!button || !current) {
            return;
        }

        var detachUrl = current.detachUrlBase.replace(/\/0$/, '/' + button.dataset.linkId);

        button.disabled = true;

        fetch(detachUrl, {
            method: 'DELETE',
            headers: csrfHeaders(),
        })
            .then(function () { return reloadData(); })
            .finally(function () {
                button.disabled = false;
            });
    });
});
