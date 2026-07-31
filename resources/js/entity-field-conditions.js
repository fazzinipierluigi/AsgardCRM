/**
 * Applies an entity's conditional-field rules (configured in
 * admin/entities/conditions/form.blade.php via JSONLogicEditor, see
 * App\Models\EntityFieldCondition) live on the create/edit record
 * form: on every input/change, re-evaluates each rule against the
 * form's current values and toggles visible/readonly/required on the
 * fields it manages.
 *
 * jsonlogic-editor-core (the admin rule builder) ships an editor UI
 * only, no runtime evaluator — jsonLogicApply() below is a small,
 * self-contained implementation of the operator subset the editor can
 * actually produce (see getBuiltinOperators() in
 * node_modules/jsonlogic-editor-core), not a general-purpose JsonLogic
 * library. This is a client-side UX affordance only: nothing here is
 * re-validated server-side (see EntityFieldCondition's own docblock).
 */
function jsonLogicVar(data, path, defaultValue) {
    if (path === '' || path === undefined || path === null) {
        return data;
    }

    var value = String(path).split('.').reduce(function (acc, key) {
        return acc === null || acc === undefined ? undefined : acc[key];
    }, data);

    return value === undefined ? (defaultValue === undefined ? null : defaultValue) : value;
}

function jsonLogicApply(rule, data) {
    if (rule === null || typeof rule !== 'object') {
        return rule;
    }

    if (Array.isArray(rule)) {
        return rule.map(function (item) { return jsonLogicApply(item, data); });
    }

    var op = Object.keys(rule)[0];
    var rawArgs = rule[op];
    var args = Array.isArray(rawArgs) ? rawArgs : [rawArgs];

    if (op === 'var') {
        return jsonLogicVar(data, jsonLogicApply(args[0], data), args[1]);
    }

    if (op === 'and') {
        var andResult = true;
        for (var i = 0; i < args.length; i++) {
            andResult = jsonLogicApply(args[i], data);
            if (!andResult) {
                return andResult;
            }
        }

        return andResult;
    }

    if (op === 'or') {
        var orResult = false;
        for (var j = 0; j < args.length; j++) {
            orResult = jsonLogicApply(args[j], data);
            if (orResult) {
                return orResult;
            }
        }

        return orResult;
    }

    if (op === 'if') {
        var evaluated = args.map(function (item) { return jsonLogicApply(item, data); });
        for (var k = 0; k + 1 < evaluated.length; k += 2) {
            if (evaluated[k]) {
                return evaluated[k + 1];
            }
        }

        return evaluated.length % 2 === 1 ? evaluated[evaluated.length - 1] : null;
    }

    var values = args.map(function (item) { return jsonLogicApply(item, data); });

    switch (op) {
        case '!':
            return !values[0];
        case '!!':
            return !!values[0];
        case '==':
            return values[0] == values[1]; // eslint-disable-line eqeqeq
        case '===':
            return values[0] === values[1];
        case '!=':
            return values[0] != values[1]; // eslint-disable-line eqeqeq
        case '!==':
            return values[0] !== values[1];
        case '>':
            return values[0] > values[1];
        case '>=':
            return values[0] >= values[1];
        case '<':
            return values.length === 3 ? (values[0] < values[1] && values[1] < values[2]) : values[0] < values[1];
        case '<=':
            return values.length === 3 ? (values[0] <= values[1] && values[1] <= values[2]) : values[0] <= values[1];
        case '+':
            return values.reduce(function (a, b) { return Number(a) + Number(b); }, 0);
        case '-':
            return values.length === 1 ? -Number(values[0]) : values.reduce(function (a, b) { return Number(a) - Number(b); });
        case '*':
            return values.reduce(function (a, b) { return Number(a) * Number(b); }, 1);
        case '/':
            return Number(values[0]) / Number(values[1]);
        case '%':
            return Number(values[0]) % Number(values[1]);
        case 'min':
            return Math.min.apply(Math, values.map(Number));
        case 'max':
            return Math.max.apply(Math, values.map(Number));
        case 'in':
            return values[1] === null || values[1] === undefined ? false : values[1].indexOf(values[0]) !== -1;
        case 'cat':
            return values.join('');
        case 'substr': {
            var str = String(values[0]);
            return values.length > 2 ? str.substr(values[1], values[2]) : str.substr(values[1]);
        }
        case 'merge':
            return values.reduce(function (a, b) { return a.concat(b); }, []);
        case 'missing':
            return values.filter(function (key) { return jsonLogicVar(data, key) === null; });
        case 'missing_some':
            return jsonLogicApply({ missing: values[1] }, data);
        default:
            return null;
    }
}

function jsonLogicTruthy(value) {
    if (Array.isArray(value)) {
        return value.length > 0;
    }

    return !!value;
}

document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('entity-record-form');
    var conditions = window.ENTITY_FIELD_CONDITIONS;

    if (!form || !conditions || !conditions.length) {
        return;
    }

    var wrappers = {};
    form.querySelectorAll('[data-field-wrapper]').forEach(function (wrapper) {
        wrappers[wrapper.dataset.column] = wrapper;
    });

    function valueFor(column) {
        var checkbox = form.querySelector('input[type="checkbox"][name="' + column + '"]');

        if (checkbox) {
            return checkbox.checked;
        }

        var el = form.querySelector('[name="' + column + '"]');

        if (!el) {
            return null;
        }

        if (el.type === 'number') {
            return el.value === '' ? null : parseFloat(el.value);
        }

        return el.value;
    }

    function collectData() {
        var data = {};
        Object.keys(wrappers).forEach(function (column) {
            data[column] = valueFor(column);
        });

        return data;
    }

    function setControlsDisabled(wrapper, disabled) {
        wrapper.querySelectorAll('input, select, textarea, button').forEach(function (control) {
            control.disabled = disabled;
        });
    }

    function setReadonly(wrapper, readonly) {
        wrapper.querySelectorAll('input, textarea').forEach(function (control) {
            if (control.type === 'checkbox' || control.type === 'hidden') {
                return;
            }

            control.readOnly = readonly;
        });

        wrapper.querySelectorAll('select').forEach(function (select) {
            // <select> has no native readonly — block interaction instead
            // while still submitting its current value.
            select.style.pointerEvents = readonly ? 'none' : '';
            select.tabIndex = readonly ? -1 : 0;
        });

        wrapper.querySelectorAll('.rich-text-editor').forEach(function (editor) {
            editor.contentEditable = readonly ? 'false' : 'true';
        });

        wrapper.classList.toggle('bg-secondary-lt', readonly);
    }

    function setRequired(wrapper, required) {
        wrapper.querySelectorAll('input, select, textarea').forEach(function (control) {
            if (control.type === 'hidden' || control.type === 'checkbox') {
                return;
            }

            control.required = required;
        });

        var marker = wrapper.querySelector('[data-required-marker]');

        if (marker) {
            marker.classList.toggle('d-none', !required);
        }
    }

    function applyConditions() {
        var data = collectData();

        conditions.forEach(function (condition) {
            var active = jsonLogicTruthy(jsonLogicApply(condition.rule, data));

            condition.targets.forEach(function (target) {
                var wrapper = wrappers[target.column];

                if (!wrapper) {
                    return;
                }

                var visible = !active || target.visible;
                wrapper.classList.toggle('d-none', !visible);
                setControlsDisabled(wrapper, !visible);

                if (visible) {
                    setReadonly(wrapper, active && target.readonly);
                    setRequired(wrapper, active && target.required);
                }
            });
        });
    }

    form.addEventListener('input', applyConditions);
    form.addEventListener('change', applyConditions);
    applyConditions();
});
