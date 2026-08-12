import JSONLogicEditor from 'jsonlogic-editor-core';
import 'jsonlogic-editor-core/dist/jsonlogic-editor.css';

/**
 * Admin "Campi condizionali" form (see
 * admin/entities/conditions/form.blade.php): mounts a JSONLogicEditor
 * for the rule (variables = the entity's own fields, keyed by their
 * physical column name — matches what
 * resources/js/entity-field-conditions.js reads off the record form at
 * runtime) and wires each field row's "Gestione" checkbox to
 * enable/disable its Visibile/Readonly/Obbligatorio checkboxes —
 * disabled checkboxes aren't submitted, which is exactly "this
 * condition doesn't touch this field" server-side (see
 * Admin\EntityFieldConditionController::syncTargets()).
 */
document.addEventListener('DOMContentLoaded', function () {
    var mount = document.getElementById('entity-condition-rule-editor');

    if (!mount || !window.ENTITY_CONDITION_BUILDER) {
        return;
    }

    var DATA = window.ENTITY_CONDITION_BUILDER;
    var ruleInput = document.getElementById('entity-condition-rule-input');

    JSONLogicEditor.init('#entity-condition-rule-editor', {
        value: DATA.initialRule || null,
        variables: DATA.variables,
        theme: 'tabler',
        locale: 'it',
        onChange: function (value) {
            ruleInput.value = value ? JSON.stringify(value) : '';
        },
    });

    document.querySelectorAll('[data-condition-field-row]').forEach(function (row) {
        var managedCheckbox = row.querySelector('[data-condition-managed]');
        var flagCheckboxes = row.querySelectorAll('[data-condition-flag]');

        managedCheckbox.addEventListener('change', function () {
            flagCheckboxes.forEach(function (checkbox) {
                checkbox.disabled = !managedCheckbox.checked;
            });
        });
    });
});
