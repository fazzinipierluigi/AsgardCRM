import Sortable from 'sortablejs';
import { Modal } from 'bootstrap';

document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('entity-builder-form');

    if (!form) {
        return;
    }

    // Installed entities allow a narrower set of changes than a brand
    // new one being designed (see EntityBuilderController::updateInstalled()
    // / UpdateEntityBuilderRequest): existing tabs/cards can't be renamed
    // or removed from here (their rename/remove buttons simply aren't
    // rendered, see _tab.blade.php/_card.blade.php), and an existing
    // field's column_name/type/relation target are locked in the field
    // modal — everything else (metadata, add tab/card, reorder, resize,
    // remove an existing field after confirming) stays fully wired.
    var installed = form.dataset.installed === '1';

    // A numeric token is a real database id (an existing tab/card/field);
    // nextToken() below only ever produces "<prefix>new<n>" tokens for
    // brand new ones — see admin.entities._field/_card/_tab, which pass
    // the model's own id as the token when one already exists.
    function isExistingToken(token) {
        return /^\d+$/.test(token || '');
    }

    var tabsNav = document.getElementById('tabs-nav');
    var tabsContent = document.getElementById('tabs-content');
    var addTabBtn = document.getElementById('add-tab-btn');

    var tabNavTemplate = document.getElementById('tab-nav-template');
    var tabPaneTemplate = document.getElementById('tab-pane-template');
    var cardTemplate = document.getElementById('card-template');
    var fieldTemplate = document.getElementById('field-template');

    var nameModalEl = document.getElementById('name-modal');
    var nameModal = new Modal(nameModalEl);
    var nameModalTitle = document.getElementById('name-modal-title');
    var nameModalInput = document.getElementById('name-modal-input');
    var nameModalSaveBtn = document.getElementById('name-modal-save');
    var nameModalSaveHandler = null;

    var fieldModalEl = document.getElementById('field-modal');
    var fieldModal = new Modal(fieldModalEl);
    var fieldTypeLabels = JSON.parse(fieldModalEl.dataset.fieldTypes || '{}');
    var currentFieldEl = null;

    var tableColumnRowTemplate = document.getElementById('table-column-row-template');
    var tableColumnsRowsEl = document.getElementById('field-modal-table-columns-rows');
    var tableColumnsAddBtn = document.getElementById('field-modal-table-columns-add');
    var tableColumnAllowedTypes = ['string', 'integer', 'decimal', 'date', 'checkbox'];

    var productsExtraColumnsRowsEl = document.getElementById('field-modal-products-extra-columns-rows');
    var productsExtraColumnsAddBtn = document.getElementById('field-modal-products-extra-columns-add');
    var decimalFieldsByEntity = JSON.parse(fieldModalEl.dataset.decimalFieldsByEntity || '{}');

    var counter = 0;

    function nextToken(prefix) {
        counter += 1;

        return prefix + 'new' + counter;
    }

    function renderTemplate(template, replacements) {
        var html = template.innerHTML;

        Object.keys(replacements).forEach(function (token) {
            html = html.split(token).join(replacements[token]);
        });

        var wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();

        return wrapper.firstElementChild;
    }

    // ── name modal (reused for creating/renaming both tabs and cards) ──────

    function openNameModal(title, initialValue, onSave) {
        nameModalTitle.textContent = title;
        nameModalInput.value = initialValue;
        nameModalSaveHandler = onSave;
        nameModal.show();
        setTimeout(function () {
            nameModalInput.focus();
        }, 300);
    }

    nameModalSaveBtn.addEventListener('click', function () {
        var value = nameModalInput.value.trim();

        if (!value || !nameModalSaveHandler) {
            return;
        }

        nameModalSaveHandler(value);
        nameModal.hide();
    });

    nameModalInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            nameModalSaveBtn.click();
        }
    });

    // ── drag-and-drop reordering (same parent only, see DOCUMENTATION.md) ──

    function makeSortable(container, handleSelector) {
        if (!container) {
            return;
        }

        Sortable.create(container, { handle: handleSelector, animation: 150 });
    }

    function wireCard(cardEl) {
        makeSortable(cardEl.querySelector('.fields-container'), '.field-drag-handle');
    }

    function wireTabPane(paneEl) {
        makeSortable(paneEl.querySelector('.cards-container'), '.card-drag-handle');
        paneEl.querySelectorAll('.card-item').forEach(wireCard);
    }

    makeSortable(tabsNav, '.tab-drag-handle');
    tabsContent.querySelectorAll('.tab-pane').forEach(wireTabPane);

    // ── tab switching ────────────────────────────────────────────────────

    function activateTab(token) {
        tabsNav.querySelectorAll('.tab-switch-btn').forEach(function (btn) {
            btn.classList.remove('active');
        });
        tabsContent.querySelectorAll('.tab-pane').forEach(function (pane) {
            pane.classList.remove('show', 'active');
        });

        var btn = tabsNav.querySelector('[data-bs-target="#tab-pane-' + token + '"]');
        var pane = document.getElementById('tab-pane-' + token);
        btn?.classList.add('active');
        pane?.classList.add('show', 'active');
    }

    // ── add tab/card/field ───────────────────────────────────────────────

    function addTab() {
        openNameModal(nameModalEl.dataset.tabLabel || 'Nome tab', '', function (name) {
            var token = nextToken('t');
            var navEl = renderTemplate(tabNavTemplate, { __TAB__: token });
            var paneEl = renderTemplate(tabPaneTemplate, { __TAB__: token });

            navEl.querySelector('.tab-name-input').value = name;
            navEl.querySelector('.tab-name-label').textContent = name;

            tabsNav.appendChild(navEl);
            tabsContent.appendChild(paneEl);
            wireTabPane(paneEl);
            activateTab(token);
        });
    }

    function addCard(paneEl) {
        openNameModal(nameModalEl.dataset.cardLabel || 'Nome card', '', function (name) {
            var cardEl = renderTemplate(cardTemplate, {
                __TAB__: paneEl.dataset.tabToken,
                __CARD__: nextToken('c'),
            });

            cardEl.querySelector('.card-name-input').value = name;
            cardEl.querySelector('.card-name-label').textContent = name;

            paneEl.querySelector('.cards-container').appendChild(cardEl);
            wireCard(cardEl);
        });
    }

    function addField(cardEl) {
        var fieldEl = renderTemplate(fieldTemplate, {
            __TAB__: cardEl.dataset.tabToken,
            __CARD__: cardEl.dataset.cardToken,
            __FIELD__: nextToken('f'),
        });

        cardEl.querySelector('.fields-container').appendChild(fieldEl);
        openFieldModalFor(fieldEl);
    }

    addTabBtn?.addEventListener('click', addTab);

    // ── field settings modal ────────────────────────────────────────────

    function syncFieldModalGroups(type) {
        document.getElementById('field-modal-options').closest('.field-modal-options-group').classList.toggle('d-none', type !== 'select');
        document.getElementById('field-modal-code-prefix').closest('.field-modal-code-group').classList.toggle('d-none', type !== 'code');
        document.getElementById('field-modal-relation').closest('.field-modal-relation-group').classList.toggle('d-none', type !== 'relation');
        document.getElementById('field-modal-button-action').closest('.field-modal-button-group').classList.toggle('d-none', type !== 'button');
        document.getElementById('field-modal-table-columns').closest('.field-modal-table-group').classList.toggle('d-none', type !== 'table');
        document.getElementById('field-modal-products-catalog').closest('.field-modal-products-group').classList.toggle('d-none', type !== 'products_block');
        syncFieldModalButtonGroups();
    }

    // ── Blocco Prodotti field configurator ──────────────────────────────

    function populateProductsPriceColumnOptions(catalogSlug, selected) {
        var select = document.getElementById('field-modal-products-price-column');
        var fields = decimalFieldsByEntity[catalogSlug] || {};

        select.innerHTML = '';

        var placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = catalogSlug ? 'Seleziona...' : "Seleziona prima l'entità catalogo...";
        select.appendChild(placeholder);

        Object.keys(fields).forEach(function (columnName) {
            var option = document.createElement('option');
            option.value = columnName;
            option.textContent = fields[columnName];
            select.appendChild(option);
        });

        select.value = selected || '';
    }

    document.getElementById('field-modal-products-catalog').addEventListener('change', function (event) {
        populateProductsPriceColumnOptions(event.target.value, '');
    });

    // Every Numero decimale field currently present anywhere in the form
    // (including ones added earlier in this same edit session, not yet
    // saved) — the candidate list for "which field receives the computed
    // total", scanned live rather than off the DB since a Decimal field
    // and its Blocco Prodotti sibling are often added in the same save.
    function populateProductsTotalTargetOptions(selected) {
        var select = document.getElementById('field-modal-products-total-target');
        select.innerHTML = '';

        var placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Nessuno';
        select.appendChild(placeholder);

        form.querySelectorAll('.field-item').forEach(function (fieldEl) {
            if (fieldEl.querySelector('.field-type-input').value !== 'decimal') {
                return;
            }

            var option = document.createElement('option');
            option.value = fieldEl.querySelector('.field-column-input').value;
            option.textContent = fieldEl.querySelector('.field-name-input').value || option.value;
            select.appendChild(option);
        });

        select.value = selected || '';
    }

    function parseExtraColumnsText(raw) {
        return parseTableColumnsText(raw);
    }

    function serializeProductsExtraColumns() {
        return Array.prototype.slice.call(productsExtraColumnsRowsEl.querySelectorAll('.table-column-row'))
            .map(function (row) {
                return {
                    name: row.querySelector('.table-column-name').value.trim(),
                    label: row.querySelector('.table-column-label').value.trim(),
                    type: row.querySelector('.table-column-type').value,
                    required: row.querySelector('.table-column-required').checked,
                };
            })
            .filter(function (column) {
                return column.name !== '';
            })
            .map(function (column) {
                return [column.name, column.label || column.name, column.type, column.required ? 'si' : 'no'].join(':');
            })
            .join('\n');
    }

    function addProductsExtraColumnRow(column) {
        column = column || { name: '', label: '', type: 'string', required: false };

        var row = renderTemplate(tableColumnRowTemplate, {});
        row.querySelector('.table-column-name').value = column.name;
        row.querySelector('.table-column-label').value = column.label;
        row.querySelector('.table-column-required').checked = column.required;

        productsExtraColumnsRowsEl.appendChild(row);

        var typeSelect = row.querySelector('.table-column-type');
        window.tomSelect(typeSelect);
        window.setSelectValue(typeSelect, column.type);

        row.querySelector('.table-column-remove-btn').addEventListener('click', function () {
            if (typeSelect.tomselect) {
                typeSelect.tomselect.destroy();
            }
            row.remove();
        });
    }

    function resetProductsExtraColumnRows(raw) {
        productsExtraColumnsRowsEl.querySelectorAll('.table-column-type').forEach(function (select) {
            if (select.tomselect) {
                select.tomselect.destroy();
            }
        });
        productsExtraColumnsRowsEl.innerHTML = '';
        parseExtraColumnsText(raw).forEach(addProductsExtraColumnRow);
    }

    productsExtraColumnsAddBtn?.addEventListener('click', function () {
        addProductsExtraColumnRow();
    });

    function syncFieldModalButtonGroups() {
        var action = document.getElementById('field-modal-button-action').value;
        document.getElementById('field-modal-button-workflow').closest('.field-modal-button-workflow-group').classList.toggle('d-none', action !== 'workflow');
        document.getElementById('field-modal-button-importer-ids').closest('.field-modal-button-importer-group').classList.toggle('d-none', action !== 'importer');
        document.getElementById('field-modal-button-javascript').closest('.field-modal-button-javascript-group').classList.toggle('d-none', action !== 'javascript');
    }

    // ── Table field column configurator (click-based, no free-text) ────────
    // Mirrors StoreEntityFieldRequest::parseTableColumns() client-side just
    // to populate/serialize the UI — the server re-parses and validates the
    // same "name:label:type:required" raw format authoritatively, this
    // never bypasses that.

    function parseTableColumnsText(raw) {
        var columns = [];

        (raw || '').split(/\r\n|\r|\n/).forEach(function (line) {
            line = line.trim();

            if (!line) {
                return;
            }

            var parts = line.split(':');
            var name = (parts[0] || '').trim();

            if (!name) {
                return;
            }

            var label = (parts[1] || '').trim() || name;
            var type = (parts[2] || '').trim();
            type = tableColumnAllowedTypes.indexOf(type) !== -1 ? type : 'string';
            var required = ['si', '1', 'true'].indexOf((parts[3] || '').trim().toLowerCase()) !== -1;

            columns.push({ name: name, label: label, type: type, required: required });
        });

        return columns;
    }

    function serializeTableColumns() {
        return Array.prototype.slice.call(tableColumnsRowsEl.querySelectorAll('.table-column-row'))
            .map(function (row) {
                return {
                    name: row.querySelector('.table-column-name').value.trim(),
                    label: row.querySelector('.table-column-label').value.trim(),
                    type: row.querySelector('.table-column-type').value,
                    required: row.querySelector('.table-column-required').checked,
                };
            })
            .filter(function (column) {
                return column.name !== '';
            })
            .map(function (column) {
                return [column.name, column.label || column.name, column.type, column.required ? 'si' : 'no'].join(':');
            })
            .join('\n');
    }

    function addTableColumnRow(column) {
        column = column || { name: '', label: '', type: 'string', required: false };

        var row = renderTemplate(tableColumnRowTemplate, {});
        row.querySelector('.table-column-name').value = column.name;
        row.querySelector('.table-column-label').value = column.label;
        row.querySelector('.table-column-required').checked = column.required;

        tableColumnsRowsEl.appendChild(row);

        var typeSelect = row.querySelector('.table-column-type');
        window.tomSelect(typeSelect);
        window.setSelectValue(typeSelect, column.type);

        row.querySelector('.table-column-remove-btn').addEventListener('click', function () {
            if (typeSelect.tomselect) {
                typeSelect.tomselect.destroy();
            }
            row.remove();
        });
    }

    function resetTableColumnRows(raw) {
        tableColumnsRowsEl.querySelectorAll('.table-column-type').forEach(function (select) {
            if (select.tomselect) {
                select.tomselect.destroy();
            }
        });
        tableColumnsRowsEl.innerHTML = '';
        parseTableColumnsText(raw).forEach(addTableColumnRow);
    }

    tableColumnsAddBtn?.addEventListener('click', function () {
        addTableColumnRow();
    });

    function openFieldModalFor(fieldEl) {
        currentFieldEl = fieldEl;

        var type = fieldEl.querySelector('.field-type-input').value;
        var locked = installed && isExistingToken(fieldEl.dataset.fieldToken);

        document.getElementById('field-modal-name').value = fieldEl.querySelector('.field-name-input').value;
        document.getElementById('field-modal-column').value = fieldEl.querySelector('.field-column-input').value;
        document.getElementById('field-modal-column').disabled = locked;
        document.getElementById('field-modal-column-locked-hint').classList.toggle('d-none', !locked);
        window.setSelectValue('#field-modal-type', type);
        document.getElementById('field-modal-type').disabled = locked;
        document.getElementById('field-modal-type-locked-hint').classList.toggle('d-none', !locked);
        document.getElementById('field-modal-relation').disabled = locked;
        document.getElementById('field-modal-required').checked = fieldEl.querySelector('.field-required-input').value === '1';
        document.getElementById('field-modal-options').value = fieldEl.querySelector('.field-options-input').value;
        document.getElementById('field-modal-code-prefix').value = fieldEl.querySelector('.field-codeprefix-input').value;
        window.setSelectValue('#field-modal-relation', fieldEl.querySelector('.field-relationtarget-input').value);
        document.getElementById('field-modal-default').value = fieldEl.querySelector('.field-defaultvalue-input').value;
        window.setSelectValue('#field-modal-button-action', fieldEl.querySelector('.field-buttonaction-input').value || 'workflow');
        window.setSelectValue('#field-modal-button-workflow', fieldEl.querySelector('.field-buttonworkflowid-input').value);
        document.getElementById('field-modal-button-importer-ids').value = fieldEl.querySelector('.field-buttonimporterids-input').value;
        document.getElementById('field-modal-button-javascript').value = fieldEl.querySelector('.field-buttonjavascript-input').value;
        resetTableColumnRows(fieldEl.querySelector('.field-tablecolumns-input').value);

        var savedCatalog = fieldEl.querySelector('.field-productscatalog-input').value;
        window.setSelectValue('#field-modal-products-catalog', savedCatalog);
        populateProductsPriceColumnOptions(savedCatalog, fieldEl.querySelector('.field-productspricecolumn-input').value);
        resetProductsExtraColumnRows(fieldEl.querySelector('.field-productsextracolumns-input').value);
        populateProductsTotalTargetOptions(fieldEl.querySelector('.field-productstotaltarget-input').value);

        syncFieldModalGroups(type);
        fieldModal.show();
    }

    document.getElementById('field-modal-type').addEventListener('change', function (event) {
        syncFieldModalGroups(event.target.value);
    });

    document.getElementById('field-modal-button-action').addEventListener('change', syncFieldModalButtonGroups);

    document.getElementById('field-modal-save').addEventListener('click', function () {
        if (!currentFieldEl) {
            return;
        }

        var type = document.getElementById('field-modal-type').value;
        var name = document.getElementById('field-modal-name').value.trim();

        currentFieldEl.querySelector('.field-name-input').value = name;
        currentFieldEl.querySelector('.field-column-input').value = document.getElementById('field-modal-column').value.trim();
        currentFieldEl.querySelector('.field-type-input').value = type;
        currentFieldEl.querySelector('.field-required-input').value = document.getElementById('field-modal-required').checked ? '1' : '0';
        currentFieldEl.querySelector('.field-options-input').value = document.getElementById('field-modal-options').value;
        currentFieldEl.querySelector('.field-codeprefix-input').value = document.getElementById('field-modal-code-prefix').value;
        currentFieldEl.querySelector('.field-relationtarget-input').value = document.getElementById('field-modal-relation').value;
        currentFieldEl.querySelector('.field-defaultvalue-input').value = document.getElementById('field-modal-default').value;
        currentFieldEl.querySelector('.field-buttonaction-input').value = document.getElementById('field-modal-button-action').value;
        currentFieldEl.querySelector('.field-buttonworkflowid-input').value = document.getElementById('field-modal-button-workflow').value;
        currentFieldEl.querySelector('.field-buttonimporterids-input').value = document.getElementById('field-modal-button-importer-ids').value;
        currentFieldEl.querySelector('.field-buttonjavascript-input').value = document.getElementById('field-modal-button-javascript').value;
        document.getElementById('field-modal-table-columns').value = serializeTableColumns();
        currentFieldEl.querySelector('.field-tablecolumns-input').value = document.getElementById('field-modal-table-columns').value;
        currentFieldEl.querySelector('.field-productscatalog-input').value = document.getElementById('field-modal-products-catalog').value;
        currentFieldEl.querySelector('.field-productspricecolumn-input').value = document.getElementById('field-modal-products-price-column').value;
        document.getElementById('field-modal-products-extra-columns').value = serializeProductsExtraColumns();
        currentFieldEl.querySelector('.field-productsextracolumns-input').value = document.getElementById('field-modal-products-extra-columns').value;
        currentFieldEl.querySelector('.field-productstotaltarget-input').value = document.getElementById('field-modal-products-total-target').value;

        currentFieldEl.querySelector('.field-preview-name').textContent = name || 'Nuovo campo';
        currentFieldEl.querySelector('.field-preview-type').textContent = fieldTypeLabels[type] || '';

        fieldModal.hide();
        currentFieldEl = null;
    });

    // ── click routing ────────────────────────────────────────────────────

    form.addEventListener('click', function (event) {
        var addCardBtn = event.target.closest('.add-card-btn');
        if (addCardBtn) {
            addCard(addCardBtn.closest('.tab-pane'));
            return;
        }

        var addFieldBtn = event.target.closest('.add-field-btn');
        if (addFieldBtn) {
            addField(addFieldBtn.closest('.card-item'));
            return;
        }

        var tabRenameBtn = event.target.closest('.tab-rename-btn');
        if (tabRenameBtn) {
            var navLi = tabRenameBtn.closest('.nav-item');
            var currentName = navLi.querySelector('.tab-name-input').value;
            openNameModal(nameModalEl.dataset.tabLabel || 'Nome tab', currentName, function (newName) {
                navLi.querySelector('.tab-name-input').value = newName;
                navLi.querySelector('.tab-name-label').textContent = newName;
            });
            return;
        }

        var tabRemoveBtn = event.target.closest('.tab-pane-remove-tab-btn');
        if (tabRemoveBtn) {
            var pane = tabRemoveBtn.closest('.tab-pane');
            var token = pane.dataset.tabToken;
            var wasActive = pane.classList.contains('active');
            var navItem = tabsNav.querySelector('[data-tab-token="' + token + '"]');

            navItem?.remove();
            pane.remove();

            if (wasActive) {
                var firstNavItem = tabsNav.querySelector('.nav-item');
                if (firstNavItem) {
                    activateTab(firstNavItem.dataset.tabToken);
                }
            }
            return;
        }

        var cardRenameBtn = event.target.closest('.card-rename-btn');
        if (cardRenameBtn) {
            var cardEl = cardRenameBtn.closest('.card-item');
            var currentCardName = cardEl.querySelector('.card-name-input').value;
            openNameModal(nameModalEl.dataset.cardLabel || 'Nome card', currentCardName, function (newName) {
                cardEl.querySelector('.card-name-input').value = newName;
                cardEl.querySelector('.card-name-label').textContent = newName;
            });
            return;
        }

        var removeBtn = event.target.closest('.remove-row-btn');
        if (removeBtn) {
            var row = removeBtn.closest('.repeatable-row');
            var isExistingField = installed && row.dataset.fieldToken !== undefined && isExistingToken(row.dataset.fieldToken);

            if (!isExistingField) {
                row.remove();
                return;
            }

            confirmExistingFieldRemoval(row.dataset.fieldToken).then(function (confirmed) {
                if (confirmed) {
                    row.remove();
                }
            });
        }
    });

    // Before letting the admin actually remove an existing field, check
    // (via AJAX) whether any workflow references it — see
    // WorkflowFieldReferenceScanner. Structured references get folded
    // into the confirmation itself (they'll be auto-cleaned the moment
    // the field is actually deleted); anything the cleaner can't touch
    // (published versions, free-form expressions) is listed so the admin
    // knows exactly where to go double-check by hand. If the check
    // itself fails for some reason, fall back to the plain data-loss
    // confirmation rather than blocking the deletion outright.
    var usageUrlTemplate = form.dataset.usageUrlTemplate || '';

    function confirmExistingFieldRemoval(fieldId) {
        var baseText = 'Eliminando questo campo perderai per sempre i valori già salvati su tutti i record esistenti.';

        if (!usageUrlTemplate) {
            return simpleRemovalConfirm(baseText);
        }

        return fetch(usageUrlTemplate.replace('__FIELD__', fieldId), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('usage check failed');
                }
                return response.json();
            })
            .then(function (usage) {
                return usageRemovalConfirm(baseText, usage.cleanable || [], usage.manual || []);
            })
            .catch(function () {
                return simpleRemovalConfirm(baseText);
            });
    }

    function simpleRemovalConfirm(baseText) {
        return window.Swal.fire({
            icon: 'warning',
            text: baseText + ' Continuare?',
            showCancelButton: true,
            confirmButtonText: 'Elimina',
            cancelButtonText: 'Annulla',
        }).then(function (result) {
            return result.isConfirmed;
        });
    }

    function usageRemovalConfirm(baseText, cleanable, manual) {
        if (cleanable.length === 0 && manual.length === 0) {
            return simpleRemovalConfirm(baseText);
        }

        var html = '<p>' + escapeHtml(baseText) + '</p>';

        if (cleanable.length > 0) {
            html += '<p class="text-start mb-1"><strong>Questi riferimenti nei flussi verranno ripuliti automaticamente:</strong></p>' + listHtml(cleanable);
        }

        if (manual.length > 0) {
            html += '<p class="text-start mb-1 text-danger"><strong>Questi riferimenti NON possono essere ripuliti automaticamente: controllali manualmente</strong></p>' + listHtml(manual);
        }

        return window.Swal.fire({
            icon: manual.length > 0 ? 'error' : 'warning',
            html: html,
            showCancelButton: true,
            confirmButtonText: 'Elimina comunque',
            cancelButtonText: 'Annulla',
        }).then(function (result) {
            return result.isConfirmed;
        });
    }

    function listHtml(items) {
        return '<ul class="text-start small">' + items.map(function (item) {
            return '<li>' + escapeHtml(item) + '</li>';
        }).join('') + '</ul>';
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // A single click/release on the field preview also fires after a
    // resize drag ends (mouseup lands inside the box), which would
    // otherwise pop the field modal open right after resizing — require
    // a double-click to edit a field instead.
    form.addEventListener('dblclick', function (event) {
        var fieldPreview = event.target.closest('.field-preview');

        if (fieldPreview && !event.target.closest('.field-drag-handle') && !event.target.closest('.remove-row-btn') && !event.target.closest('.field-resize-handle')) {
            openFieldModalFor(fieldPreview.closest('.field-item'));
        }
    });

    // ── field resizing (drag the right edge, snapped to 1/12 columns) ──────

    var resizing = null;

    function columnStep(fieldEl) {
        // Measure the row's parent (the card body), not the .row itself —
        // Bootstrap's .row has negative margins that make its own bounding
        // box wider than the visible column area, which would overestimate
        // the step size and make dragging feel unresponsive.
        var container = fieldEl.closest('.fields-container').parentElement;

        return container.getBoundingClientRect().width / 12;
    }

    function setFieldWidth(fieldEl, width) {
        width = Math.min(12, Math.max(1, width));

        for (var i = 1; i <= 12; i++) {
            fieldEl.classList.remove('col-md-' + i);
        }

        fieldEl.classList.add('col-md-' + width);
        fieldEl.dataset.width = width;
        fieldEl.querySelector('.field-width-input').value = width;

        return width;
    }

    form.addEventListener('mousedown', function (event) {
        var handle = event.target.closest('.field-resize-handle');

        if (!handle) {
            return;
        }

        var fieldEl = handle.closest('.field-item');

        resizing = {
            handle: handle,
            fieldEl: fieldEl,
            startX: event.clientX,
            startWidth: parseInt(fieldEl.dataset.width, 10) || 12,
            step: columnStep(fieldEl),
        };

        handle.classList.add('is-resizing');
        document.body.classList.add('is-resizing-field');

        event.preventDefault();
        event.stopPropagation();
    });

    document.addEventListener('mousemove', function (event) {
        if (!resizing) {
            return;
        }

        var deltaColumns = Math.round((event.clientX - resizing.startX) / resizing.step);
        setFieldWidth(resizing.fieldEl, resizing.startWidth + deltaColumns);
    });

    document.addEventListener('mouseup', function () {
        if (!resizing) {
            return;
        }

        resizing.handle.classList.remove('is-resizing');
        document.body.classList.remove('is-resizing-field');
        resizing = null;
    });

    // Exposed so automated tests (Dusk headless Chrome can't reliably
    // simulate a real mouse drag) can set a field's width directly.
    window.__entityBuilderSetFieldWidth = setFieldWidth;
});
