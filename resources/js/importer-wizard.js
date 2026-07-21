document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('importer-form');

    if (!form) {
        return;
    }

    var steps = Array.prototype.slice.call(document.querySelectorAll('.importer-step'));
    var stepIndicators = Array.prototype.slice.call(document.querySelectorAll('[data-step-indicator]'));
    var prevBtn = document.getElementById('importer-wizard-prev');
    var nextBtn = document.getElementById('importer-wizard-next');
    var submitBtn = document.getElementById('importer-wizard-submit');
    var totalSteps = steps.length;
    var currentStep = 1;

    var channelSelect = document.getElementById('channel');
    var channelFieldsets = Array.prototype.slice.call(document.querySelectorAll('[data-importer-channel]'));
    var authTypeSelect = document.getElementById('auth_type');
    var authFieldsets = Array.prototype.slice.call(document.querySelectorAll('[data-auth-fields]'));
    var scheduleTypeSelect = document.getElementById('schedule_type');
    var scheduleCronBlock = document.querySelector('[data-schedule-cron]');
    var cronPresetSelect = document.getElementById('cron_preset');
    var cronExpressionInput = document.getElementById('cron_expression');

    var previewBtn = document.getElementById('importer-preview-btn');
    var previewError = document.getElementById('importer-preview-error');
    var mappingEmpty = document.getElementById('importer-mapping-empty');
    var mappingWrapper = document.getElementById('importer-mapping-wrapper');
    var mappingBody = document.getElementById('importer-mapping-body');
    var mappingRowTemplate = document.getElementById('importer-mapping-row-template');
    var fieldMappingInput = document.getElementById('field_mapping_json');
    var uniqueKeyInput = document.getElementById('unique_key_field');
    var entityFieldsData = JSON.parse(document.getElementById('importer-entity-fields-data').textContent || '{}');
    var entitySelect = document.getElementById('entity_id');

    var previewLoaded = false;

    function setDisabledWithin(container, disabled) {
        container.querySelectorAll('input, select, textarea').forEach(function (el) {
            el.disabled = disabled;
        });
    }

    function syncChannelVisibility() {
        var channel = channelSelect.value;

        channelFieldsets.forEach(function (fieldset) {
            var matches = fieldset.getAttribute('data-importer-channel') === channel;
            fieldset.style.display = matches ? '' : 'none';
            setDisabledWithin(fieldset, !matches);
        });

        if (channel === 'rest_api') {
            syncAuthVisibility();
        }
    }

    function syncAuthVisibility() {
        if (!authTypeSelect) {
            return;
        }

        var authType = authTypeSelect.value;

        authFieldsets.forEach(function (fieldset) {
            var matches = fieldset.getAttribute('data-auth-fields') === authType;
            fieldset.style.display = matches ? '' : 'none';
            setDisabledWithin(fieldset, !matches);
        });
    }

    function syncScheduleVisibility() {
        if (!scheduleCronBlock) {
            return;
        }

        var needsCron = scheduleTypeSelect.value === 'cron' || scheduleTypeSelect.value === 'both';
        scheduleCronBlock.style.display = needsCron ? '' : 'none';
        setDisabledWithin(scheduleCronBlock, !needsCron);
    }

    function showStep(step) {
        currentStep = Math.max(1, Math.min(totalSteps, step));

        steps.forEach(function (el) {
            el.classList.toggle('d-none', parseInt(el.getAttribute('data-step'), 10) !== currentStep);
        });

        stepIndicators.forEach(function (el) {
            el.classList.toggle('active', parseInt(el.getAttribute('data-step-indicator'), 10) === currentStep);
        });

        prevBtn.classList.toggle('d-none', currentStep === 1);
        nextBtn.classList.toggle('d-none', currentStep === totalSteps);
        submitBtn.classList.toggle('d-none', currentStep !== totalSteps);

        if (currentStep === 4 && !previewLoaded) {
            doPreview();
        }
    }

    function currentStepIsValid() {
        var stepEl = steps[currentStep - 1];
        var invalid = stepEl.querySelector(':invalid');

        if (invalid) {
            invalid.reportValidity();

            return false;
        }

        return true;
    }

    prevBtn.addEventListener('click', function () {
        showStep(currentStep - 1);
    });

    nextBtn.addEventListener('click', function () {
        if (!currentStepIsValid()) {
            return;
        }

        showStep(currentStep + 1);
    });

    stepIndicators.forEach(function (el) {
        el.classList.add('cursor-pointer');
        el.addEventListener('click', function () {
            var target = parseInt(el.getAttribute('data-step-indicator'), 10);

            if (target < currentStep) {
                showStep(target);
            }
        });
    });

    channelSelect.addEventListener('change', function () {
        previewLoaded = false;
        syncChannelVisibility();
    });

    if (authTypeSelect) {
        authTypeSelect.addEventListener('change', syncAuthVisibility);
    }

    if (scheduleTypeSelect) {
        scheduleTypeSelect.addEventListener('change', syncScheduleVisibility);
    }

    if (cronPresetSelect) {
        cronPresetSelect.addEventListener('change', function () {
            if (cronPresetSelect.value !== '') {
                cronExpressionInput.value = cronPresetSelect.value;
            }
        });
    }

    if (previewBtn) {
        previewBtn.addEventListener('click', function () {
            doPreview();
        });
    }

    function entityFieldsFor(entityId) {
        return entityFieldsData[entityId] || [];
    }

    function buildMappingRow(sourceField, sampleValue) {
        var fragment = mappingRowTemplate.content.cloneNode(true);
        var row = fragment.querySelector('tr');
        row.setAttribute('data-source-field', sourceField);
        row.querySelector('.source-field').textContent = sourceField;
        row.querySelector('.sample-value').textContent = sampleValue === null || sampleValue === undefined ? '' : String(sampleValue);

        var select = row.querySelector('.mapping-target');
        var emptyOption = document.createElement('option');
        emptyOption.value = '';
        emptyOption.textContent = window.IMPORTER_NOT_MAPPED_LABEL || 'Non mappato';
        select.appendChild(emptyOption);

        entityFieldsFor(entitySelect.value).forEach(function (field) {
            var option = document.createElement('option');
            option.value = field.column_name;
            option.textContent = field.name;
            select.appendChild(option);
        });

        // The row (and its select) doesn't exist yet when tomSelectAll()
        // runs on DOMContentLoaded, so — per its own data-tom-select-manual
        // opt-out — it's wired up here instead, once its options are set.
        if (window.tomSelect) {
            window.tomSelect(select);
        }

        return row;
    }

    function restoreMappingSelections() {
        var mapping = {};
        var uniqueKey = uniqueKeyInput.value;

        try {
            mapping = JSON.parse(fieldMappingInput.value || '{}');
        } catch (e) {
            mapping = {};
        }

        mappingBody.querySelectorAll('tr').forEach(function (row) {
            var sourceField = row.getAttribute('data-source-field');
            var select = row.querySelector('.mapping-target');
            var radio = row.querySelector('.unique-key-radio');

            if (mapping[sourceField]) {
                window.setSelectValue ? window.setSelectValue(select, mapping[sourceField]) : (select.value = mapping[sourceField]);
            }

            if (uniqueKey && mapping[sourceField] === uniqueKey) {
                radio.checked = true;
            }
        });
    }

    function renderMapping(columns, sample) {
        mappingBody.innerHTML = '';

        columns.forEach(function (column) {
            var row = buildMappingRow(column, sample ? sample[column] : null);
            mappingBody.appendChild(row);
        });

        restoreMappingSelections();

        mappingEmpty.classList.add('d-none');
        mappingWrapper.classList.remove('d-none');
        previewLoaded = true;
    }

    function doPreview() {
        previewError.classList.add('d-none');
        previewError.textContent = '';

        var formData = new FormData(form);

        fetch(form.dataset.previewUrl, {
            method: 'POST',
            headers: { Accept: 'application/json' },
            body: formData,
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (result) {
                if (!result.ok) {
                    previewError.textContent = result.message || window.IMPORTER_PREVIEW_ERROR_LABEL || 'Anteprima non disponibile.';
                    previewError.classList.remove('d-none');

                    return;
                }

                renderMapping(result.columns || [], result.sample || {});
            })
            .catch(function () {
                previewError.textContent = window.IMPORTER_PREVIEW_ERROR_LABEL || 'Anteprima non disponibile.';
                previewError.classList.remove('d-none');
            });
    }

    form.addEventListener('submit', function () {
        var mapping = {};

        mappingBody.querySelectorAll('tr').forEach(function (row) {
            var sourceField = row.getAttribute('data-source-field');
            var select = row.querySelector('.mapping-target');

            if (select.value !== '') {
                mapping[sourceField] = select.value;
            }
        });

        var checkedRadio = mappingBody.querySelector('.unique-key-radio:checked');
        var uniqueKeyRow = checkedRadio ? checkedRadio.closest('tr') : null;
        var uniqueKeySelect = uniqueKeyRow ? uniqueKeyRow.querySelector('.mapping-target') : null;

        fieldMappingInput.value = JSON.stringify(mapping);
        uniqueKeyInput.value = uniqueKeySelect && uniqueKeySelect.value !== '' ? uniqueKeySelect.value : '';
    });

    syncChannelVisibility();
    syncScheduleVisibility();

    // Land on the first step containing a server-side validation error
    // (redisplay after a failed submit), otherwise start at step 1.
    var firstInvalidStep = null;

    steps.forEach(function (stepEl) {
        if (firstInvalidStep === null && stepEl.querySelector('.is-invalid')) {
            firstInvalidStep = parseInt(stepEl.getAttribute('data-step'), 10);
        }
    });

    showStep(firstInvalidStep || 1);
});
