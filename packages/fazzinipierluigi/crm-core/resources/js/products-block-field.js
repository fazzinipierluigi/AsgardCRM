/**
 * Runtime widget for a "Blocco Prodotti" field (see resources/views/
 * entities/_field_input.blade.php, @case('products_block')): a
 * products/services line-item grid. Each row's first cell stacks a
 * catalog product select with editable name/description inputs —
 * selecting a product fills name/description from the catalog, but
 * they stay editable per row afterward, which also allows a row with
 * no product selected (a custom line item or a purely descriptive
 * row). Quantity and unit price (auto-filled from the catalog entity's
 * own price field) drive a live-computed subtotal per row, and a
 * document total that — when the field was configured with a
 * `total_target_column` — is kept in sync with a Decimal field
 * elsewhere on the same form (see
 * EntityRecordController::productsBlockOptionsForEntity() for where the
 * catalog options this reads come from). Sibling of table-field.js, kept
 * separate because of the fixed product/price/subtotal columns and the
 * total-sync behaviour a generic Table column never needed.
 */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-products-block]').forEach(initProductsBlock);
});

function initProductsBlock(container) {
    var products = JSON.parse(container.dataset.products || '[]');
    var extraColumns = JSON.parse(container.dataset.extraColumns || '[]');
    var totalTarget = container.dataset.totalTarget || '';
    var productNamePlaceholder = container.dataset.namePlaceholder || '';
    var productDescriptionPlaceholder = container.dataset.descriptionPlaceholder || '';
    var productsById = {};
    products.forEach(function (p) {
        productsById[p.id] = p;
    });

    var hiddenInput = container.querySelector('[data-products-block-input]');
    var tbody = container.querySelector('tbody');
    var totalCell = container.querySelector('[data-products-block-total]');
    var addBtn = container.querySelector('[data-products-block-add]');
    var initialRows = [];

    try {
        initialRows = JSON.parse(hiddenInput.value || '[]');
    } catch (e) {
        initialRows = [];
    }

    function formatMoney(value) {
        return (Math.round((value + Number.EPSILON) * 100) / 100).toFixed(2);
    }

    function buildProductCell(row) {
        var select = document.createElement('select');
        select.className = 'form-select form-select-sm';
        select.dataset.col = 'product_id';

        var placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = '';
        select.appendChild(placeholder);

        products.forEach(function (p) {
            var option = document.createElement('option');
            option.value = p.id;
            option.textContent = p.label;
            select.appendChild(option);
        });

        var name = document.createElement('input');
        name.type = 'text';
        name.className = 'form-control form-control-sm mt-1';
        name.dataset.col = 'name';
        name.placeholder = productNamePlaceholder;
        name.value = row.name !== undefined && row.name !== null ? row.name : (productsById[row.product_id] ? productsById[row.product_id].label : '');

        var description = document.createElement('textarea');
        description.rows = 1;
        description.className = 'form-control form-control-sm mt-1';
        description.dataset.col = 'description';
        description.placeholder = productDescriptionPlaceholder;
        description.value = row.description !== undefined && row.description !== null ? row.description : (productsById[row.product_id] ? productsById[row.product_id].description : '');

        var td = document.createElement('td');
        td.appendChild(select);
        td.appendChild(name);
        td.appendChild(description);

        // allowEmptyOption:false (overriding the app-wide default) keeps the
        // blank placeholder <option> out of Tom Select's item list — with it
        // on, an unselected .form-select-sm gets a "has-items" wrapper class
        // whose CSS zeroes the control's bottom padding (meant for tag pills
        // in a multi-select), collapsing this single-select to ~6px tall.
        if (window.tomSelect) {
            window.tomSelect(select, { allowEmptyOption: false });
            if (row.product_id) {
                window.setSelectValue(select, String(row.product_id));
            }
        } else if (row.product_id) {
            select.value = row.product_id;
        }

        select.addEventListener('change', function () {
            var selected = productsById[select.value];

            if (selected) {
                name.value = selected.label;
                description.value = selected.description || '';
            }
        });

        return { td: td, select: select, name: name, description: description };
    }

    function buildNumberCell(colName, value, step) {
        var input = document.createElement('input');
        input.type = 'number';
        input.step = step || 'any';
        input.className = 'form-control form-control-sm';
        input.dataset.col = colName;
        input.value = value === undefined || value === null ? '' : value;

        var td = document.createElement('td');
        td.appendChild(input);

        return { td: td, input: input };
    }

    function buildExtraCell(column, value) {
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
        rowData = rowData || {};

        var tr = document.createElement('tr');

        var product = buildProductCell(rowData);
        tr.appendChild(product.td);

        var quantity = buildNumberCell('quantity', rowData.quantity !== undefined ? rowData.quantity : 1);
        tr.appendChild(quantity.td);

        var unitPrice = buildNumberCell('unit_price', rowData.unit_price !== undefined ? rowData.unit_price : (productsById[rowData.product_id] ? productsById[rowData.product_id].price : ''));
        tr.appendChild(unitPrice.td);

        extraColumns.forEach(function (column) {
            tr.appendChild(buildExtraCell(column, rowData[column.name]));
        });

        var subtotalCell = document.createElement('td');
        subtotalCell.className = 'fw-medium';
        subtotalCell.dataset.subtotalCell = '1';
        subtotalCell.textContent = formatMoney(Number(rowData.subtotal) || 0);
        tr.appendChild(subtotalCell);

        var removeTd = document.createElement('td');
        var removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn btn-sm btn-outline-danger';
        removeBtn.textContent = '✕';
        removeBtn.dataset.productsBlockRemove = '1';
        removeTd.appendChild(removeBtn);
        tr.appendChild(removeTd);

        product.select.addEventListener('change', function () {
            var selected = productsById[product.select.value];

            if (selected) {
                unitPrice.input.value = selected.price;
            }

            recalculate();
        });

        tbody.appendChild(tr);
    }

    function rowSubtotal(tr) {
        var quantity = parseFloat(tr.querySelector('[data-col="quantity"]').value) || 0;
        var unitPrice = parseFloat(tr.querySelector('[data-col="unit_price"]').value) || 0;

        return quantity * unitPrice;
    }

    function recalculate() {
        var total = 0;

        Array.prototype.forEach.call(tbody.querySelectorAll('tr'), function (tr) {
            var subtotal = rowSubtotal(tr);
            tr.querySelector('[data-subtotal-cell]').textContent = formatMoney(subtotal);
            total += subtotal;
        });

        totalCell.textContent = formatMoney(total);
        serialize(total);
    }

    function serialize(total) {
        var rows = Array.prototype.map.call(tbody.querySelectorAll('tr'), function (tr) {
            var row = {
                product_id: tr.querySelector('[data-col="product_id"]').value || null,
                name: tr.querySelector('[data-col="name"]').value,
                description: tr.querySelector('[data-col="description"]').value,
                quantity: tr.querySelector('[data-col="quantity"]').value,
                unit_price: tr.querySelector('[data-col="unit_price"]').value,
                subtotal: rowSubtotal(tr),
            };

            extraColumns.forEach(function (column) {
                var input = tr.querySelector('[data-col="' + column.name + '"]');
                row[column.name] = column.type === 'checkbox' ? input.checked : input.value;
            });

            return row;
        });

        hiddenInput.value = JSON.stringify(rows);

        if (totalTarget) {
            var targetInput = document.querySelector('[name="' + totalTarget + '"]');

            if (targetInput) {
                targetInput.value = formatMoney(total);
                targetInput.readOnly = true;
            }
        }
    }

    initialRows.forEach(addRow);
    recalculate();

    addBtn.addEventListener('click', function () {
        addRow({});
        recalculate();
    });

    tbody.addEventListener('click', function (event) {
        var removeBtn = event.target.closest('[data-products-block-remove]');

        if (!removeBtn) {
            return;
        }

        removeBtn.closest('tr').remove();
        recalculate();
    });

    tbody.addEventListener('input', recalculate);
    tbody.addEventListener('change', recalculate);
}
