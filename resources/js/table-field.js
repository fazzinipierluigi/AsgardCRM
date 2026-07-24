/**
 * Generic runtime widget for a "Tabella" field: unlimited add/remove
 * rows, one typed input per configured column. Shared verbatim by the
 * entity record form (resources/views/entities/_field_input.blade.php,
 * @case('table')) and the workflow "Task utente" form
 * (resources/views/workflow-tasks/edit.blade.php, @case('table')) —
 * the two systems don't otherwise share any field-type code, but the
 * runtime editor is identical either way. Every container just needs:
 *   <div data-table-field data-columns="[{name,label,type,required}]">
 *     <table>...<thead></thead><tbody></tbody></table>
 *     <button data-table-field-add></button>
 *     <input type="hidden" data-table-field-input value="[...]">
 *   </div>
 */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-table-field]').forEach(initTableField);
});

function initTableField(container) {
    var columns = JSON.parse(container.dataset.columns || '[]');
    var hiddenInput = container.querySelector('[data-table-field-input]');
    var thead = container.querySelector('thead');
    var tbody = container.querySelector('tbody');
    var addBtn = container.querySelector('[data-table-field-add]');
    var initialRows = [];

    try {
        initialRows = JSON.parse(hiddenInput.value || '[]');
    } catch (e) {
        initialRows = [];
    }

    function buildHeader() {
        var tr = document.createElement('tr');
        columns.forEach(function (column) {
            var th = document.createElement('th');
            th.textContent = column.label || column.name;
            tr.appendChild(th);
        });
        tr.appendChild(document.createElement('th'));
        thead.appendChild(tr);
    }

    function buildCell(column, value) {
        var td = document.createElement('td');
        var input;

        if (column.type === 'checkbox') {
            input = document.createElement('input');
            input.type = 'checkbox';
            input.className = 'form-check-input';
            input.checked = !!value;
        } else {
            input = document.createElement('input');
            input.className = 'form-control form-control-sm';
            input.type = column.type === 'date' ? 'date' : (column.type === 'integer' || column.type === 'decimal') ? 'number' : 'text';
            if (column.type === 'integer') input.step = '1';
            if (column.type === 'decimal') input.step = 'any';
            input.value = value === undefined || value === null ? '' : value;
        }

        input.dataset.col = column.name;
        td.appendChild(input);

        return td;
    }

    function addRow(rowData) {
        var tr = document.createElement('tr');

        columns.forEach(function (column) {
            tr.appendChild(buildCell(column, rowData[column.name]));
        });

        var removeTd = document.createElement('td');
        var removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn btn-sm btn-outline-danger';
        removeBtn.textContent = '✕';
        removeBtn.dataset.tableFieldRemove = '1';
        removeTd.appendChild(removeBtn);
        tr.appendChild(removeTd);

        tbody.appendChild(tr);
    }

    function serialize() {
        var rows = Array.prototype.map.call(tbody.querySelectorAll('tr'), function (tr) {
            var row = {};

            columns.forEach(function (column) {
                var input = tr.querySelector('[data-col="' + column.name + '"]');
                row[column.name] = column.type === 'checkbox' ? input.checked : input.value;
            });

            return row;
        });

        hiddenInput.value = JSON.stringify(rows);
    }

    buildHeader();
    initialRows.forEach(addRow);
    serialize();

    addBtn.addEventListener('click', function () {
        addRow({});
        serialize();
    });

    tbody.addEventListener('click', function (event) {
        var removeBtn = event.target.closest('[data-table-field-remove]');

        if (!removeBtn) {
            return;
        }

        removeBtn.closest('tr').remove();
        serialize();
    });

    tbody.addEventListener('input', serialize);
    tbody.addEventListener('change', serialize);
}
