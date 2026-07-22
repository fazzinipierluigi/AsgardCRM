import '@maxgraph/core/css/common.css';
import { Graph, InternalEvent, getDefaultPlugins, Point, ImageBox, CellOverlay, UndoManager } from '@maxgraph/core';
import { Modal } from 'bootstrap';
import Sortable from 'sortablejs';
import JSONLogicEditor from 'jsonlogic-editor-core';
import 'jsonlogic-editor-core/dist/jsonlogic-editor.css';

document.addEventListener('DOMContentLoaded', function () {
    var container = document.getElementById('workflow-canvas');

    if (!container || !window.WORKFLOW_BUILDER) {
        return;
    }

    var DATA = window.WORKFLOW_BUILDER;
    var LABELS = DATA.labels;
    var I18N = DATA.i18n;
    var OPTIONS = DATA.options;

    var NODE_STYLES = {
        start: { shape: 'ellipse', fillColor: '#2fb344', strokeColor: '#1a7431', fontColor: '#2fb344', verticalLabelPosition: 'bottom', verticalAlign: 'top' },
        end: { shape: 'ellipse', fillColor: '#9aa0a6', strokeColor: '#495057', strokeWidth: 5, fontColor: '#495057', verticalLabelPosition: 'bottom', verticalAlign: 'top' },
        user_task: { shape: 'rectangle', rounded: true, fillColor: '#4263eb', strokeColor: '#28408f', fontColor: '#ffffff' },
        service_task: { shape: 'rectangle', rounded: true, fillColor: '#206bc4', strokeColor: '#164b8a', fontColor: '#ffffff' },
        exclusive_gateway: { shape: 'rhombus', fillColor: '#f59f00', strokeColor: '#a66a00', fontColor: '#ffffff' },
        parallel_gateway: { shape: 'rhombus', fillColor: '#f76707', strokeColor: '#a34600', fontColor: '#ffffff' },
        timer: { shape: 'ellipse', fillColor: '#ae3ec9', strokeColor: '#6e2680', fontColor: '#ffffff' },
        semaphore: { shape: 'ellipse', fillColor: '#f8f9fa', strokeColor: '#2fb344', strokeWidth: 3, fontColor: '#2fb344', verticalLabelPosition: 'bottom', verticalAlign: 'top' },
        subworkflow: { shape: 'rectangle', rounded: true, dashed: true, fillColor: '#495057', strokeColor: '#212529', fontColor: '#ffffff' },
    };

    // Small badge icon in the top-left corner for the two task types, a
    // bigger centered icon standing in for the semaphore's own shape.
    // CellOverlay centers the image on its align/valign anchor point, so
    // a 'left'/'top' overlay needs a positive offset of roughly half its
    // own size to land fully inside the shape instead of straddling the
    // corner.
    var NODE_ICONS = {
        user_task: { name: 'user', variant: 'filled', align: 'left', valign: 'top', size: 22, offset: [17, 17] },
        service_task: { name: 'settings', variant: 'filled', align: 'left', valign: 'top', size: 22, offset: [17, 17] },
        semaphore: { name: 'traffic-lights', variant: 'outline', align: 'center', valign: 'middle', size: 30, offset: [0, -6] },
    };

    function iconUrl(name, variant) {
        return DATA.iconBaseUrl + '/' + (variant || 'outline') + '/' + name;
    }

    function attachNodeIcon(cell, type) {
        var spec = NODE_ICONS[type];

        if (!spec) {
            return;
        }

        var image = new ImageBox(iconUrl(spec.name, spec.variant), spec.size, spec.size);
        var overlay = new CellOverlay(image, '', spec.align, spec.valign, new Point(spec.offset[0], spec.offset[1]));
        overlay.cursor = 'move';
        graph.addCellOverlay(cell, overlay);
    }

    var NODE_SIZE = { w: 140, h: 60 };
    var GATEWAY_SIZE = { w: 70, h: 70 };
    var ROUND_SIZE = { w: 70, h: 70 };
    var START_END_SIZE = { w: 35, h: 35 };

    // ---------------------------------------------------------------
    // Graph setup
    // ---------------------------------------------------------------
    var graph = new Graph(container, undefined, getDefaultPlugins());
    graph.setConnectable(true);
    graph.setCellsEditable(false);
    graph.setAllowDanglingEdges(false);
    graph.setDisconnectOnMove(false);
    graph.setCellsBendable(true);

    // Every edge routes with right-angle segments and draggable bend
    // points by default, instead of a single straight line.
    Object.assign(graph.getStylesheet().getDefaultEdgeStyle(), {
        edgeStyle: 'orthogonalEdgeStyle',
        rounded: true,
        strokeColor: '#6c757d',
    });

    var parent = graph.getDefaultParent();
    var keyCounter = 0;
    var cellById = {}; // workflow node key -> Cell

    // GraphDataModel has no "get every cell" method — .cells is the raw
    // id => Cell map it keeps internally.
    function allCells() {
        return Object.values(graph.getDataModel().cells || {});
    }

    function newKey() {
        keyCounter += 1;
        return 'new-' + Date.now() + '-' + keyCounter;
    }

    function sizeFor(type) {
        if (type === 'exclusive_gateway' || type === 'parallel_gateway') {
            return GATEWAY_SIZE;
        }
        if (type === 'start' || type === 'end') {
            return START_END_SIZE;
        }
        if (type === 'timer' || type === 'semaphore') {
            return ROUND_SIZE;
        }
        return NODE_SIZE;
    }

    function addNodeCell(node) {
        var size = sizeFor(node.type);
        var style = Object.assign({}, NODE_STYLES[node.type] || {}, { fontSize: 12, whiteSpace: 'wrap' });

        var cell = graph.insertVertex({
            parent: parent,
            value: node.name,
            position: [node.pos_x || 0, node.pos_y || 0],
            size: [size.w, size.h],
            style: style,
        });

        attachNodeIcon(cell, node.type);

        cell.workflowData = {
            key: node.key,
            type: node.type,
            config: node.config || {},
            actions: node.actions || { before: [], after: [] },
        };

        cellById[node.key] = cell;

        return cell;
    }

    function addEdgeCell(edge) {
        var source = cellById[edge.source_key];
        var target = cellById[edge.target_key];

        if (!source || !target) {
            return;
        }

        var cell = graph.insertEdge({
            parent: parent,
            source: source,
            target: target,
            value: edge.label || '',
        });

        cell.workflowData = {
            label: edge.label || '',
            sequence: edge.sequence || 0,
            condition_logic: edge.condition_logic || null,
            actions: edge.actions || { before: [], after: [] },
        };

        return cell;
    }

    graph.batchUpdate(function () {
        (DATA.graph.nodes || []).forEach(addNodeCell);
        (DATA.graph.edges || []).forEach(addEdgeCell);
    });

    // ---------------------------------------------------------------
    // Palette: drag & drop onto the canvas, or click to drop it at a
    // default position (native HTML5 drag events don't fire from
    // simulated/scripted clicks, so the click fallback is also what
    // makes this reachable without a mouse).
    // ---------------------------------------------------------------
    var paletteDropSeq = 0;

    function createNodeAt(type, x, y) {
        if (type === 'start' && hasStartNode()) {
            alert(I18N.onlyOneStart);
            return null;
        }

        var size = sizeFor(type);
        var cell;
        graph.batchUpdate(function () {
            cell = addNodeCell({
                key: newKey(),
                type: type,
                name: OPTIONS.nodeTypes[type] || type,
                pos_x: Math.round(x - size.w / 2),
                pos_y: Math.round(y - size.h / 2),
                config: {},
                actions: { before: [], after: [] },
            });
        });

        selectCell(cell);

        return cell;
    }

    document.querySelectorAll('.workflow-palette-item').forEach(function (item) {
        var type = item.getAttribute('data-node-type');

        item.addEventListener('dragstart', function (evt) {
            evt.dataTransfer.setData('text/workflow-node-type', type);
        });

        item.addEventListener('click', function () {
            paletteDropSeq += 1;
            var offset = (paletteDropSeq % 6) * 24;
            createNodeAt(type, 120 + offset, 100 + offset);
        });
    });

    container.addEventListener('dragover', function (evt) {
        evt.preventDefault();
    });

    container.addEventListener('drop', function (evt) {
        evt.preventDefault();
        var type = evt.dataTransfer.getData('text/workflow-node-type');
        if (!type) {
            return;
        }

        var pt = graph.getPointForEvent(evt, true);
        createNodeAt(type, pt.x, pt.y);
    });

    function hasStartNode() {
        return allCells().filter(function (cell) {
            return cell.vertex && cell.workflowData && cell.workflowData.type === 'start';
        }).length > 0;
    }

    // ---------------------------------------------------------------
    // Floating, draggable, non-blocking editor window — opened on
    // double-click of a node or edge (single click just selects, as
    // usual, so the cell can still be moved/deleted/connected).
    // ---------------------------------------------------------------
    var floatWindow = document.getElementById('workflow-float-window');
    var floatTitle = document.getElementById('workflow-float-window-title');
    var floatBody = document.getElementById('workflow-float-window-body');
    var inspector = floatBody; // kept as `inspector` below: same render target, new host

    function selectCell(cell) {
        graph.setSelectionCell(cell);
    }

    graph.addListener(InternalEvent.DOUBLE_CLICK, function (sender, evt) {
        var cell = evt.getProperty('cell');

        if (cell) {
            openFloatWindow(cell);
            evt.consume();
        }
    });

    var floatDeleteBtn = document.getElementById('workflow-float-window-delete');
    var floatSaveBtn = document.getElementById('workflow-float-window-save');

    function openFloatWindow(cell) {
        renderInspector(cell);

        floatDeleteBtn.textContent = cell.vertex ? LABELS.deleteNode : LABELS.deleteEdge;
        floatDeleteBtn.onclick = function () {
            if (confirm(LABELS.confirmDelete)) {
                graph.removeCells([cell]);
                closeFloatWindow();
            }
        };

        floatWindow.style.display = 'block';
    }

    function closeFloatWindow() {
        destroyConditionEditors();
        floatBody.innerHTML = '';
        floatDeleteBtn.onclick = null;
        floatWindow.style.display = 'none';
    }

    document.getElementById('workflow-float-window-close').addEventListener('click', closeFloatWindow);
    floatSaveBtn.addEventListener('click', closeFloatWindow);

    // Drag the window by its header — plain mouse events, not HTML5
    // drag-and-drop (which doesn't suit a "grab anywhere and move a
    // panel around the screen" interaction).
    (function makeFloatWindowDraggable() {
        var header = document.getElementById('workflow-float-window-header');
        var dragging = false;
        var startX = 0;
        var startY = 0;
        var startLeft = 0;
        var startTop = 0;

        header.addEventListener('mousedown', function (evt) {
            if (evt.target.closest('.btn-close')) {
                return;
            }

            dragging = true;
            var rect = floatWindow.getBoundingClientRect();
            startX = evt.clientX;
            startY = evt.clientY;
            startLeft = rect.left;
            startTop = rect.top;
            floatWindow.style.right = 'auto';
            evt.preventDefault();
        });

        document.addEventListener('mousemove', function (evt) {
            if (!dragging) {
                return;
            }

            floatWindow.style.left = (startLeft + evt.clientX - startX) + 'px';
            floatWindow.style.top = Math.max(0, startTop + evt.clientY - startY) + 'px';
        });

        document.addEventListener('mouseup', function () {
            dragging = false;
        });
    })();

    // JSONLogicEditor instances mounted in the currently-rendered
    // inspector panel — torn down before every re-render/clear so a
    // long editing session doesn't pile up orphaned instances.
    var activeConditionEditors = [];
    var jsonLogicEditorSeq = 0;

    // Drag-to-resize state for a user task's form fields (see the
    // 'user_task' branch of renderNodeConfig below) — mousemove/mouseup
    // are registered once here rather than per-render, since the field
    // grid itself is torn down and rebuilt on every render.
    var fieldResizing = null;

    document.addEventListener('mousemove', function (evt) {
        if (!fieldResizing) {
            return;
        }

        var deltaColumns = Math.round((evt.clientX - fieldResizing.startX) / fieldResizing.step);
        var newWidth = fieldResizing.col.dataset.width;
        var colEl = fieldResizing.col;

        for (var i = 1; i <= 12; i++) {
            colEl.classList.remove('col-md-' + i);
        }
        newWidth = Math.min(12, Math.max(2, fieldResizing.startWidth + deltaColumns));
        colEl.classList.add('col-md-' + newWidth);
        colEl.dataset.width = newWidth;

        if (colEl._formField) {
            colEl._formField.width = newWidth;
        }
    });

    document.addEventListener('mouseup', function () {
        if (!fieldResizing) {
            return;
        }

        var handle = fieldResizing.col.querySelector('.wf-field-resize-handle');
        if (handle) {
            handle.classList.remove('is-resizing');
        }
        document.body.classList.remove('is-resizing-field');
        fieldResizing = null;
    });

    function destroyConditionEditors() {
        activeConditionEditors.forEach(function (instance) { instance.destroy(); });
        activeConditionEditors = [];
    }

    function mountConditionEditor(container, value, variableDefs, onChange) {
        jsonLogicEditorSeq += 1;
        var mountId = 'jle-' + jsonLogicEditorSeq;
        var mount = document.createElement('div');
        mount.id = mountId;
        container.appendChild(mount);

        var instance = JSONLogicEditor.init('#' + mountId, {
            value: value || null,
            variables: variableDefs,
            theme: 'tabler',
            locale: 'it',
            onChange: onChange,
        });
        activeConditionEditors.push(instance);

        return instance;
    }

    function workflowVariableDefs() {
        var typeMap = { integer: 'number', float: 'number', boolean: 'boolean', date: 'date', datetime: 'date' };

        return variables.filter(function (v) { return v.name; }).map(function (v) {
            return { name: v.name, label: v.name, type: typeMap[v.type] || 'string' };
        });
    }

    function entityFieldDefs(entitySlug) {
        var entity = OPTIONS.entities.find(function (e) { return e.slug === entitySlug; });

        if (!entity) {
            return [];
        }

        return entity.fields.map(function (f) {
            return { name: 'entity.' + f.column_name, label: f.name, type: 'string' };
        });
    }

    function currentStartNodeConfig() {
        var starts = allCells().filter(function (cell) {
            return cell.vertex && cell.workflowData && cell.workflowData.type === 'start';
        });

        return starts.length ? (starts[0].workflowData.config || {}) : {};
    }

    function clearInspector() {
        closeFloatWindow();
    }

    function renderInspector(cell) {
        if (!cell) {
            clearInspector();
            return;
        }

        destroyConditionEditors();
        inspector.innerHTML = '';
        floatTitle.textContent = (cell.vertex ? LABELS.nodeWindowTitle : LABELS.edgeWindowTitle) + ': ' +
            (cell.vertex ? (graph.convertValueToString(cell) || '') : (cell.workflowData && cell.workflowData.label ? cell.workflowData.label : ('#' + cell.id)));

        if (cell.vertex) {
            renderNodeInspector(cell);
        } else if (cell.edge) {
            renderEdgeInspector(cell);
        }
    }

    function renderNodeInspector(cell) {
        var tpl = document.getElementById('tpl-node-inspector').content.cloneNode(true);
        var root = tpl.firstElementChild;

        root.querySelector('[data-field="name"]').value = graph.convertValueToString(cell) || '';
        root.querySelector('[data-field="name"]').addEventListener('input', function (evt) {
            graph.getDataModel().setValue(cell, evt.target.value);
        });

        // JSONLogicEditor.init() looks up its mount point with
        // document.querySelector(), so `root` must already be attached
        // to the live DOM before any editor is mounted inside it.
        inspector.appendChild(root);

        renderNodeConfig(root.querySelector('[data-node-config]'), cell);
        renderActionsBlock(root.querySelector('[data-actions-before]'), cell, 'before');
        renderActionsBlock(root.querySelector('[data-actions-after]'), cell, 'after');

        window.tomSelectAll(inspector);
    }

    function renderEdgeInspector(cell) {
        var tpl = document.getElementById('tpl-edge-inspector').content.cloneNode(true);
        var root = tpl.firstElementChild;
        var data = cell.workflowData || { label: '', sequence: 0, condition_logic: null, actions: { before: [], after: [] } };
        cell.workflowData = data;

        root.querySelector('[data-field="label"]').value = data.label || '';
        root.querySelector('[data-field="label"]').addEventListener('input', function (evt) {
            data.label = evt.target.value;
            graph.getDataModel().setValue(cell, evt.target.value);
        });

        root.querySelector('[data-field="sequence"]').value = data.sequence || 0;
        root.querySelector('[data-field="sequence"]').addEventListener('input', function (evt) {
            data.sequence = parseInt(evt.target.value, 10) || 0;
        });

        inspector.appendChild(root);

        var startConfig = currentStartNodeConfig();
        var edgeVariableDefs = workflowVariableDefs().concat(startConfig.entity_slug ? entityFieldDefs(startConfig.entity_slug) : []);
        mountConditionEditor(root.querySelector('[data-condition-editor]'), data.condition_logic, edgeVariableDefs, function (value) {
            data.condition_logic = value;
        });

        renderActionsBlock(root.querySelector('[data-actions-before]'), cell, 'before');
        renderActionsBlock(root.querySelector('[data-actions-after]'), cell, 'after');

        window.tomSelectAll(inspector);
    }

    // ---------------------------------------------------------------
    // Node-type-specific config fields
    // ---------------------------------------------------------------
    function renderNodeConfig(container, cell) {
        var data = cell.workflowData;
        var config = data.config || {};
        data.config = config;
        container.innerHTML = '';

        function field(labelText, el) {
            var wrap = document.createElement('div');
            wrap.className = 'mb-3';
            var label = document.createElement('label');
            label.className = 'form-label';
            label.textContent = labelText;
            wrap.appendChild(label);
            wrap.appendChild(el);
            container.appendChild(wrap);
            return wrap;
        }

        function select(options, value, onChange) {
            var el = document.createElement('select');
            el.className = 'form-select';
            Object.keys(options).forEach(function (key) {
                var opt = document.createElement('option');
                opt.value = key;
                opt.textContent = options[key];
                if (key === value) {
                    opt.selected = true;
                }
                el.appendChild(opt);
            });
            el.addEventListener('change', function () {
                onChange(el.value);
            });
            return el;
        }

        function text(value, onInput, placeholder) {
            var el = document.createElement('input');
            el.type = 'text';
            el.className = 'form-control';
            el.value = value || '';
            if (placeholder) el.placeholder = placeholder;
            el.addEventListener('input', function () {
                onInput(el.value);
            });
            return el;
        }

        function checkbox(checked, labelText, onChange) {
            var wrap = document.createElement('label');
            wrap.className = 'form-check form-switch';
            var el = document.createElement('input');
            el.type = 'checkbox';
            el.className = 'form-check-input';
            el.checked = !!checked;
            el.addEventListener('change', function () {
                onChange(el.checked);
            });
            wrap.appendChild(el);
            var span = document.createElement('span');
            span.className = 'form-check-label';
            span.textContent = labelText;
            wrap.appendChild(span);
            return wrap;
        }

        if (data.type === 'start') {
            container.appendChild(field(I18N.trigger, select(OPTIONS.triggerTypes, config.trigger_type || 'manual', function (v) {
                config.trigger_type = v;
                renderNodeConfig(container, cell);
            })));

            if (config.trigger_type === 'cron') {
                container.appendChild(field(I18N.cronExpression, text(config.cron_expression, function (v) { config.cron_expression = v; }, '*/15 * * * *')));
            }

            var isEntityBound = ['entity_created', 'entity_updated', 'entity_created_or_updated'].indexOf(config.trigger_type) !== -1;

            if (isEntityBound) {
                var entityOptions = {};
                OPTIONS.entities.forEach(function (e) { entityOptions[e.slug] = e.name; });
                container.appendChild(field(I18N.entity, select(entityOptions, config.entity_slug || '', function (v) {
                    config.entity_slug = v;
                    renderNodeConfig(container, cell);
                })));

                container.appendChild(field(I18N.occurrence, select(OPTIONS.occurrenceOptions, config.occurrence || 'every_time', function (v) { config.occurrence = v; })));
            }

            var startConditionWrap = field(I18N.startCondition, document.createElement('div'));
            startConditionWrap.querySelector('div').remove();
            var startVariableDefs = workflowVariableDefs().concat(isEntityBound && config.entity_slug ? entityFieldDefs(config.entity_slug) : []);
            mountConditionEditor(startConditionWrap, config.start_condition, startVariableDefs, function (value) {
                config.start_condition = value;
            });

            var hint = document.createElement('small');
            hint.className = 'form-hint';
            hint.textContent = I18N.startConditionHint;
            startConditionWrap.appendChild(hint);
        }

        if (data.type === 'timer') {
            var refOptions = { fixed: I18N.fixedDate, variable: I18N.variableRef };
            container.appendChild(field(I18N.reference, select(refOptions, config.reference || 'fixed', function (v) {
                config.reference = v;
                renderNodeConfig(container, cell);
            })));

            if ((config.reference || 'fixed') === 'variable') {
                container.appendChild(field(I18N.variableNameDate, text(config.variable_name, function (v) { config.variable_name = v; })));
            } else {
                container.appendChild(field(I18N.date, text(config.date, function (v) { config.date = v; }, 'YYYY-MM-DD HH:mm:ss')));
            }

            var dirOptions = { before: I18N.before, after: I18N.after };
            container.appendChild(field(I18N.direction, select(dirOptions, config.direction || 'after', function (v) { config.direction = v; })));
            container.appendChild(field(I18N.amount, text(config.amount != null ? String(config.amount) : '0', function (v) { config.amount = parseInt(v, 10) || 0; })));
            container.appendChild(field(I18N.unit, select(OPTIONS.timerUnits, config.unit || 'minutes', function (v) { config.unit = v; })));
        }

        if (data.type === 'user_task') {
            var roleOptions = { '': I18N.none };
            OPTIONS.roles.forEach(function (r) { roleOptions[r.id] = r.name; });
            container.appendChild(field(I18N.assignedRole, select(roleOptions, config.assigned_role_id || '', function (v) { config.assigned_role_id = v || null; })));

            container.appendChild(checkbox(config.show_in_entity_detail, I18N.showInEntityDetail, function (v) { config.show_in_entity_detail = v; }));

            var fieldsWrap = document.createElement('div');
            fieldsWrap.className = 'mt-3';
            var fieldsTitle = document.createElement('div');
            fieldsTitle.className = 'd-flex justify-content-between align-items-center mb-2';
            var fieldsTitleStrong = document.createElement('strong');
            fieldsTitleStrong.className = 'small text-uppercase';
            fieldsTitleStrong.textContent = I18N.formFields;
            fieldsTitle.appendChild(fieldsTitleStrong);
            var addFieldBtn = document.createElement('button');
            addFieldBtn.type = 'button';
            addFieldBtn.className = 'btn btn-sm btn-outline-primary';
            addFieldBtn.textContent = '+ ' + I18N.field;
            fieldsTitle.appendChild(addFieldBtn);
            fieldsWrap.appendChild(fieldsTitle);

            // Same column-grid + drag-to-resize pattern as the entity
            // builder (see resources/js/entity-builder.js) — a field's
            // width is a Bootstrap col-md-N (1-12), dragged from a
            // handle on the preview's right edge.
            var fieldsRow = document.createElement('div');
            fieldsRow.className = 'row g-2';
            fieldsWrap.appendChild(fieldsRow);
            container.appendChild(fieldsWrap);

            config.form_fields = config.form_fields || [];

            var FIELD_TYPE_VARIABLE_TYPES = {
                string: ['string'],
                text: ['string'],
                number: ['integer', 'float'],
                boolean: ['boolean'],
                date: ['date', 'datetime'],
            };
            var FIELD_TYPE_LABELS = { string: I18N.typeString, text: I18N.typeText, number: I18N.typeNumber, boolean: I18N.typeBoolean, date: I18N.date };

            function compatibleVariableOptions(fieldType) {
                var allowed = FIELD_TYPE_VARIABLE_TYPES[fieldType] || ['string'];
                var opts = { '': I18N.noVariable };
                variables.forEach(function (v) {
                    if (v.name && allowed.indexOf(v.type) !== -1) {
                        opts[v.name] = v.name;
                    }
                });

                return opts;
            }

            function fieldColumnStep() {
                return fieldsRow.getBoundingClientRect().width / 12;
            }

            function renderFormFields() {
                fieldsRow.innerHTML = '';

                config.form_fields.forEach(function (f) {
                    f.width = f.width || 12;

                    var col = document.createElement('div');
                    col.className = 'col-md-' + f.width;
                    col.dataset.width = f.width;
                    col._formField = f;

                    var preview = document.createElement('div');
                    preview.className = 'border rounded position-relative d-flex align-items-center justify-content-center text-center';
                    preview.style.cssText = 'cursor: pointer; min-height: 34px; padding: 4px 22px;';
                    preview.title = I18N.doubleClickToEdit;

                    var dragHandle = document.createElement('span');
                    dragHandle.className = 'wf-field-drag-handle position-absolute';
                    dragHandle.style.cssText = 'top: 2px; left: 4px; cursor: move; font-size: .75rem; color: var(--tblr-secondary-color, #6c7a91);';
                    dragHandle.textContent = '⠿';
                    preview.appendChild(dragHandle);

                    var removeBtn = document.createElement('span');
                    removeBtn.className = 'position-absolute text-danger';
                    removeBtn.style.cssText = 'top: 2px; right: 16px; cursor: pointer; font-size: .75rem;';
                    removeBtn.textContent = '✕';
                    removeBtn.title = I18N.removeField;
                    removeBtn.addEventListener('click', function (evt) {
                        evt.stopPropagation();
                        config.form_fields.splice(config.form_fields.indexOf(f), 1);
                        renderFormFields();
                    });
                    preview.appendChild(removeBtn);

                    var textWrap = document.createElement('div');
                    textWrap.className = 'lh-1';
                    var nameEl = document.createElement('div');
                    nameEl.className = 'fw-bold small';
                    nameEl.textContent = f.name || I18N.newField;
                    var typeEl = document.createElement('div');
                    typeEl.className = 'text-muted';
                    typeEl.style.fontSize = '.7rem';
                    typeEl.textContent = FIELD_TYPE_LABELS[f.type || 'string'];
                    textWrap.appendChild(nameEl);
                    textWrap.appendChild(typeEl);
                    preview.appendChild(textWrap);

                    var resizeHandle = document.createElement('div');
                    resizeHandle.className = 'wf-field-resize-handle';
                    preview.appendChild(resizeHandle);

                    col.appendChild(preview);

                    var details = document.createElement('div');
                    details.className = 'card card-sm mt-1 d-none';
                    var detailsBody = document.createElement('div');
                    detailsBody.className = 'card-body py-2';
                    detailsBody.appendChild(text(f.name, function (v) { f.name = v; nameEl.textContent = v || I18N.newField; }, I18N.fieldNamePlaceholder));
                    detailsBody.appendChild(text(f.label, function (v) { f.label = v; }, I18N.label));
                    detailsBody.appendChild(select(FIELD_TYPE_LABELS, f.type || 'string', function (v) {
                        f.type = v;
                        f.bind_variable = '';
                        renderFormFields();
                    }));
                    detailsBody.appendChild(select(compatibleVariableOptions(f.type || 'string'), f.bind_variable || '', function (v) { f.bind_variable = v; }));
                    details.appendChild(detailsBody);
                    col.appendChild(details);

                    preview.addEventListener('click', function (evt) {
                        if (evt.target.closest('.wf-field-resize-handle') || evt.target === removeBtn) {
                            return;
                        }
                        details.classList.toggle('d-none');
                    });

                    fieldsRow.appendChild(col);
                });

                window.tomSelectAll(fieldsRow);

                Sortable.create(fieldsRow, {
                    handle: '.wf-field-drag-handle',
                    animation: 150,
                    onEnd: function () {
                        config.form_fields = Array.prototype.map.call(fieldsRow.children, function (el) {
                            return el._formField;
                        });
                    },
                });
            }

            fieldsRow.addEventListener('mousedown', function (evt) {
                var handle = evt.target.closest('.wf-field-resize-handle');

                if (!handle) {
                    return;
                }

                var col = handle.closest('[data-width]');
                fieldResizing = {
                    col: col,
                    startX: evt.clientX,
                    startWidth: parseInt(col.dataset.width, 10) || 12,
                    step: fieldColumnStep(),
                };
                handle.classList.add('is-resizing');
                document.body.classList.add('is-resizing-field');
                evt.preventDefault();
                evt.stopPropagation();
            });

            addFieldBtn.addEventListener('click', function () {
                config.form_fields.push({ name: '', label: '', type: 'string', bind_variable: '', width: 12 });
                renderFormFields();
            });

            renderFormFields();
        }

        if (data.type === 'exclusive_gateway') {
            var branchesWrap = document.createElement('div');
            branchesWrap.className = 'mt-3';
            var branchesTitle = document.createElement('strong');
            branchesTitle.className = 'small text-uppercase d-block mb-2';
            branchesTitle.textContent = I18N.exclusiveBranches;
            branchesWrap.appendChild(branchesTitle);

            var startCfg = currentStartNodeConfig();
            var branchVariableDefs = workflowVariableDefs().concat(startCfg.entity_slug ? entityFieldDefs(startCfg.entity_slug) : []);

            var outEdges = allCells().filter(function (c) {
                return c.edge && c.workflowData && c.getTerminal(true) === cell;
            }).sort(function (a, b) {
                return (a.workflowData.sequence || 0) - (b.workflowData.sequence || 0);
            });

            if (!outEdges.length) {
                var branchesHint = document.createElement('p');
                branchesHint.className = 'text-secondary small';
                branchesHint.textContent = I18N.exclusiveBranchesEmpty;
                branchesWrap.appendChild(branchesHint);
            }

            outEdges.forEach(function (edgeCell, idx) {
                var targetCell = edgeCell.getTerminal(false);
                var targetName = targetCell ? (graph.convertValueToString(targetCell) || ('#' + targetCell.id)) : '?';

                var card = document.createElement('div');
                card.className = 'card card-sm mb-2';
                var body = document.createElement('div');
                body.className = 'card-body py-2';

                var branchLabel = document.createElement('div');
                branchLabel.className = 'small text-secondary mb-1';
                branchLabel.textContent = (idx + 1) + '. → ' + targetName;
                body.appendChild(branchLabel);

                var edgeData = edgeCell.workflowData;
                mountConditionEditor(body, edgeData.condition_logic, branchVariableDefs, function (value) {
                    edgeData.condition_logic = value;
                });

                card.appendChild(body);
                branchesWrap.appendChild(card);
            });

            container.appendChild(branchesWrap);
        }

        if (data.type === 'subworkflow') {
            var wfOptions = {};
            OPTIONS.otherWorkflows.forEach(function (w) { wfOptions[w.id] = w.name; });
            container.appendChild(field(I18N.subworkflow, select(wfOptions, config.workflow_id || '', function (v) { config.workflow_id = v ? parseInt(v, 10) : null; })));
            container.appendChild(checkbox(config.wait_for_completion !== false, I18N.waitForCompletion, function (v) { config.wait_for_completion = v; }));
        }
    }

    // ---------------------------------------------------------------
    // Actions editor (before/after), shared by node & edge inspectors
    // ---------------------------------------------------------------
    function renderActionsBlock(container, cell, phase) {
        var data = cell.workflowData;
        data.actions = data.actions || { before: [], after: [] };
        data.actions[phase] = data.actions[phase] || [];

        var tpl = document.getElementById('tpl-actions-block').content.cloneNode(true);
        var root = tpl.firstElementChild;
        root.querySelector('[data-actions-title]').textContent = phase === 'before' ? LABELS.phaseBefore : LABELS.phaseAfter;
        var list = root.querySelector('[data-actions-list]');

        function renderList() {
            list.innerHTML = '';
            data.actions[phase].forEach(function (action, idx) {
                var row = renderActionRow(action, function () {
                    data.actions[phase].splice(idx, 1);
                    renderList();
                });
                row._workflowAction = action;
                list.appendChild(row);
            });
        }

        root.querySelector('[data-add-action]').addEventListener('click', function () {
            data.actions[phase].push({ type: 'set_variable', config: {} });
            renderList();
        });

        renderList();

        // Reorderable (see CLAUDE.md: prefer the app's existing JS
        // plugins) — reordering the DOM alone wouldn't persist, so
        // resync the underlying actions array to the new DOM order.
        Sortable.create(list, {
            handle: '[data-action-drag-handle]',
            animation: 150,
            onEnd: function () {
                data.actions[phase] = Array.prototype.map.call(list.children, function (el) {
                    return el._workflowAction;
                });
            },
        });

        container.appendChild(root);
    }

    function renderActionRow(action, onRemove) {
        var tpl = document.getElementById('tpl-action-row').content.cloneNode(true);
        var root = tpl.firstElementChild;
        var typeSelect = root.querySelector('[data-action-type]');
        typeSelect.value = action.type;
        var configContainer = root.querySelector('[data-action-config]');

        function renderConfig() {
            configContainer.innerHTML = '';
            renderActionConfigFields(configContainer, action);
        }

        typeSelect.addEventListener('change', function () {
            action.type = typeSelect.value;
            action.config = {};
            renderConfig();
        });

        root.querySelector('[data-remove-action]').addEventListener('click', onRemove);

        renderConfig();

        return root;
    }

    function renderActionConfigFields(container, action) {
        var config = action.config || {};
        action.config = config;

        function row(labelText, el) {
            var wrap = document.createElement('div');
            wrap.className = 'mb-2';
            var label = document.createElement('label');
            label.className = 'form-label small mb-1';
            label.textContent = labelText;
            wrap.appendChild(label);
            wrap.appendChild(el);
            container.appendChild(wrap);
        }

        function textInput(value, onInput, placeholder) {
            var el = document.createElement('input');
            el.type = 'text';
            el.className = 'form-control form-control-sm font-monospace';
            el.value = value || '';
            if (placeholder) el.placeholder = placeholder;
            el.addEventListener('input', function () { onInput(el.value); });
            return el;
        }

        function entitySelect(value, onChange) {
            var el = document.createElement('select');
            el.className = 'form-select form-select-sm';
            var blank = document.createElement('option');
            blank.value = '';
            blank.textContent = '—';
            el.appendChild(blank);
            OPTIONS.entities.forEach(function (entity) {
                var opt = document.createElement('option');
                opt.value = entity.slug;
                opt.textContent = entity.name;
                if (entity.slug === value) opt.selected = true;
                el.appendChild(opt);
            });
            el.addEventListener('change', function () { onChange(el.value); });
            return el;
        }

        if (action.type === 'set_variable') {
            row(I18N.variableRef, textInput(config.variable, function (v) { config.variable = v; }));
            row(I18N.expression, textInput(config.expression, function (v) { config.expression = v; }, 'quantita * prezzo'));
        }

        if (action.type === 'assign_entity_to_variable') {
            row(I18N.variableRef, textInput(config.variable, function (v) { config.variable = v; }));
            row(I18N.entity, entitySelect(config.entity_slug, function (v) { config.entity_slug = v; }));
            row(I18N.idExpression, textInput(config.id_expression, function (v) { config.id_expression = v; }));
        }

        if (action.type === 'send_email') {
            row(I18N.to, textInput(config.to, function (v) { config.to = v; }, '{{ entity.email }}'));
            row(I18N.subject, textInput(config.subject, function (v) { config.subject = v; }));
            var bodyEl = document.createElement('textarea');
            bodyEl.className = 'form-control form-control-sm';
            bodyEl.rows = 3;
            bodyEl.value = config.body || '';
            bodyEl.addEventListener('input', function () { config.body = bodyEl.value; });
            row(I18N.body, bodyEl);
        }

        if (action.type === 'update_entity' || action.type === 'create_entity') {
            row(I18N.entity, entitySelect(config.entity_slug, function (v) { config.entity_slug = v; }));

            if (action.type === 'update_entity') {
                row(I18N.idExpression, textInput(config.id_expression, function (v) { config.id_expression = v; }));
            } else {
                row(I18N.assignToVariable, textInput(config.assign_to_variable, function (v) { config.assign_to_variable = v; }));
            }

            config.fields = config.fields || [];
            var fieldsList = document.createElement('div');
            container.appendChild(fieldsList);

            function renderFields() {
                fieldsList.innerHTML = '';
                config.fields.forEach(function (f, idx) {
                    var line = document.createElement('div');
                    line.className = 'input-group input-group-sm mb-1';
                    var colInput = textInput(f.column, function (v) { f.column = v; }, I18N.column);
                    colInput.style.maxWidth = '35%';
                    var exprInput = textInput(f.expression, function (v) { f.expression = v; }, I18N.expressionShort);
                    var removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.className = 'btn btn-outline-danger';
                    removeBtn.textContent = '×';
                    removeBtn.addEventListener('click', function () {
                        config.fields.splice(idx, 1);
                        renderFields();
                    });
                    line.appendChild(colInput);
                    line.appendChild(exprInput);
                    line.appendChild(removeBtn);
                    fieldsList.appendChild(line);
                });
            }

            var addFieldBtn = document.createElement('button');
            addFieldBtn.type = 'button';
            addFieldBtn.className = 'btn btn-sm btn-outline-primary';
            addFieldBtn.textContent = '+ ' + I18N.field;
            addFieldBtn.addEventListener('click', function () {
                config.fields.push({ column: '', expression: '' });
                renderFields();
            });
            container.appendChild(addFieldBtn);

            renderFields();
        }
    }

    // ---------------------------------------------------------------
    // Variables modal
    // ---------------------------------------------------------------
    var variables = (DATA.graph.variables || []).map(function (v) { return Object.assign({}, v); });
    var variablesBody = document.getElementById('workflow-variables-body');

    function renderVariables() {
        variablesBody.innerHTML = '';
        variables.forEach(function (variable, idx) {
            var tpl = document.getElementById('tpl-variable-row').content.cloneNode(true);
            var row = tpl.firstElementChild;
            row.querySelector('[data-variable-name]').value = variable.name || '';
            row.querySelector('[data-variable-name]').addEventListener('input', function (evt) { variable.name = evt.target.value; });
            row.querySelector('[data-variable-default]').value = variable.default_value || '';
            row.querySelector('[data-variable-default]').addEventListener('input', function (evt) { variable.default_value = evt.target.value; });
            row.querySelector('[data-variable-remove]').addEventListener('click', function () {
                variables.splice(idx, 1);
                renderVariables();
            });
            variablesBody.appendChild(row);

            var typeSelect = row.querySelector('[data-variable-type]');
            typeSelect.value = variable.type || 'string';
            typeSelect.addEventListener('change', function (evt) { variable.type = evt.target.value; });
        });
        window.tomSelectAll(variablesBody);
    }

    document.getElementById('workflow-variable-add').addEventListener('click', function () {
        variables.push({ name: '', type: 'string', default_value: '' });
        renderVariables();
    });

    renderVariables();

    // ---------------------------------------------------------------
    // Save
    // ---------------------------------------------------------------
    function serializeGraph() {
        var nodes = [];
        var edges = [];

        allCells().filter(function (cell) {
            return cell.vertex && cell.workflowData;
        }).forEach(function (cell) {
            var geo = cell.getGeometry();
            nodes.push({
                key: cell.workflowData.key,
                type: cell.workflowData.type,
                name: graph.convertValueToString(cell) || cell.workflowData.type,
                pos_x: Math.round(geo.x),
                pos_y: Math.round(geo.y),
                config: cell.workflowData.config || {},
                actions: cell.workflowData.actions || { before: [], after: [] },
            });
        });

        allCells().filter(function (cell) {
            return cell.edge && cell.getTerminal(true) && cell.getTerminal(false);
        }).forEach(function (cell) {
            var data = cell.workflowData || {};
            edges.push({
                source_key: cell.getTerminal(true).workflowData.key,
                target_key: cell.getTerminal(false).workflowData.key,
                label: data.label || '',
                sequence: data.sequence || 0,
                condition_logic: data.condition_logic || null,
                actions: data.actions || { before: [], after: [] },
            });
        });

        return {
            name: DATA.graph.name,
            description: DATA.graph.description,
            is_active: DATA.graph.is_active,
            variables: variables.filter(function (v) { return v.name; }),
            nodes: nodes,
            edges: edges,
        };
    }

    var statusEl = document.getElementById('workflow-builder-status');

    document.getElementById('workflow-save-btn').addEventListener('click', function () {
        statusEl.textContent = LABELS.saving;

        fetch(DATA.saveUrl, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.CSRF_TOKEN,
                Accept: 'application/json',
            },
            body: JSON.stringify(serializeGraph()),
        })
            .then(function (response) {
                return response.json().then(function (body) {
                    if (!response.ok) {
                        throw new Error(body.message || LABELS.saveError);
                    }
                    return body;
                });
            })
            .then(function () {
                statusEl.textContent = LABELS.saved;
                setTimeout(function () { statusEl.textContent = ''; }, 2500);
            })
            .catch(function (err) {
                statusEl.textContent = '';
                alert(err.message || LABELS.saveError);
            });
    });

    document.getElementById('workflow-variables-btn').addEventListener('click', function () {
        var modalEl = document.getElementById('workflow-variables-modal');
        new Modal(modalEl).show();
    });

    // ---------------------------------------------------------------
    // Toolbar: zoom, undo/redo, delete selection
    // ---------------------------------------------------------------
    document.getElementById('wf-zoom-in').addEventListener('click', function () { graph.zoomIn(); });
    document.getElementById('wf-zoom-out').addEventListener('click', function () { graph.zoomOut(); });
    document.getElementById('wf-zoom-reset').addEventListener('click', function () { graph.zoomActual(); });

    var undoManager = new UndoManager();
    var undoListener = function (sender, evt) { undoManager.undoableEditHappened(evt.getProperty('edit')); };
    graph.getDataModel().addListener(InternalEvent.UNDO, undoListener);
    graph.getView().addListener(InternalEvent.UNDO, undoListener);

    document.getElementById('wf-undo').addEventListener('click', function () { undoManager.undo(); });
    document.getElementById('wf-redo').addEventListener('click', function () { undoManager.redo(); });

    document.getElementById('wf-delete').addEventListener('click', function () {
        var cells = graph.getSelectionCells();

        if (cells.length && confirm(LABELS.confirmDelete)) {
            graph.removeCells(cells);
            closeFloatWindow();
        }
    });

    // ---------------------------------------------------------------
    // Tool modes: select (default — move/rubber-band), connect (click
    // one cell then another to link them, on top of the always-available
    // drag-from-border connect), pan (hand — drag the canvas itself).
    // ---------------------------------------------------------------
    var panningHandler = graph.getPlugin('PanningHandler');
    var toolMode = 'select';
    var connectPendingSource = null;

    function setToolMode(mode) {
        toolMode = mode;
        connectPendingSource = null;

        document.querySelectorAll('[data-tool-group] [data-tool]').forEach(function (btn) {
            btn.classList.toggle('active', btn.getAttribute('data-tool') === mode);
        });

        if (mode === 'pan') {
            panningHandler.useLeftButtonForPanning = true;
            panningHandler.ignoreCell = true;
            graph.setPanning(true);
        } else {
            panningHandler.useLeftButtonForPanning = false;
            panningHandler.ignoreCell = false;
            graph.setPanning(false);
        }

        container.style.cursor = mode === 'pan' ? 'grab' : (mode === 'connect' ? 'crosshair' : 'default');
    }

    document.querySelectorAll('[data-tool-group] [data-tool]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setToolMode(btn.getAttribute('data-tool'));
        });
    });

    graph.addListener(InternalEvent.CLICK, function (sender, evt) {
        if (toolMode !== 'connect') {
            return;
        }

        var cell = evt.getProperty('cell');

        if (!cell || !cell.vertex) {
            connectPendingSource = null;

            return;
        }

        if (!connectPendingSource) {
            connectPendingSource = cell;
            selectCell(cell);
        } else if (connectPendingSource !== cell) {
            var edge;
            graph.batchUpdate(function () {
                edge = graph.insertEdge({ parent: parent, source: connectPendingSource, target: cell, value: '' });
                edge.workflowData = { label: '', sequence: 0, condition_logic: null, actions: { before: [], after: [] } };
            });
            connectPendingSource = null;
            selectCell(edge);
        }

        evt.consume();
    });
});
