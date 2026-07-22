@extends('layouts.admin')

@section('title', $workflow->name)

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.workflows.index') }}">{{ t('Workflows') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.workflows.builder.edit', $workflow) }}">{{ $workflow->name }}</a>
    </li>
@endsection

@section('buttons')
    @if ($graph['version'] ?? null)
        <span class="badge bg-secondary-lt me-2" data-testid="workflow-version-badge">{{ t('Versione :n', ['n' => $graph['version']]) }}</span>
    @endif
    <span id="workflow-builder-status" class="text-secondary me-2" data-testid="workflow-builder-status"></span>
    <button type="button" class="btn btn-outline-secondary" id="workflow-variables-btn" data-testid="workflow-variables-btn">
        {!! icon('variable') !!} {{ t('Variabili') }}
    </button>
    <button type="button" class="btn btn-primary" id="workflow-save-btn" data-testid="workflow-save-btn">
        {{ t('Salva (nuova versione)') }}
    </button>
@endsection

@section('content')
    <style>
        .wf-tool-btn { width: 1.6rem; height: 1.6rem; padding: 0; }
        .wf-tool-btn svg { width: 14px; height: 14px; }
        .wf-tool-btn.active { background: var(--tblr-primary-lt); border-color: var(--tblr-primary); color: var(--tblr-primary); }
        .workflow-palette-item { cursor: grab; text-align: center; margin-bottom: .75rem; }
        .wf-palette-preview-wrap { display: flex; align-items: center; justify-content: center; height: 44px; }
        .wf-palette-preview { width: 36px; height: 36px; border-style: solid; box-sizing: border-box; display: flex; align-items: center; justify-content: center; }
        .wf-shape-circle { border-radius: 50%; }
        .wf-shape-rect { border-radius: 6px; position: relative; }
        .wf-shape-diamond { width: 26px; height: 26px; transform: rotate(45deg); }
        .wf-palette-icon svg { width: 16px; height: 16px; display: block; }
        .wf-palette-badge-icon svg { width: 12px; height: 12px; display: block; }
        .wf-palette-badge-icon { position: absolute; top: 2px; left: 2px; }
        .wf-field-resize-handle {
            position: absolute; top: 0; right: 0; bottom: 0; width: 14px; cursor: ew-resize;
            background: linear-gradient(to right, transparent, rgba(98, 105, 118, .12));
            border-top-right-radius: var(--tblr-border-radius, .25rem);
            border-bottom-right-radius: var(--tblr-border-radius, .25rem);
        }
        .wf-field-resize-handle::after {
            content: ""; position: absolute; top: 50%; right: 5px; width: 2px; height: 22px;
            transform: translateY(-50%); background: rgba(98, 105, 118, .5); border-radius: 1px;
        }
        .wf-field-resize-handle:hover, .wf-field-resize-handle.is-resizing { background: rgba(32, 107, 196, .25); }
        .wf-field-resize-handle:hover::after, .wf-field-resize-handle.is-resizing::after { background: #206bc4; }
        body.is-resizing-field { cursor: ew-resize !important; user-select: none !important; }
    </style>

    <div class="card mb-0">
        <div class="card-header d-flex align-items-center gap-1 py-1 flex-wrap" data-testid="workflow-toolbar">
            <div class="btn-group me-2" role="group" data-tool-group>
                <button type="button" class="btn btn-icon wf-tool-btn active" data-tool="select" title="{{ t('Seleziona e sposta') }}">{!! icon('pointer') !!}</button>
                <button type="button" class="btn btn-icon wf-tool-btn" data-tool="connect" title="{{ t('Crea collegamento') }}">{!! icon('line') !!}</button>
                <button type="button" class="btn btn-icon wf-tool-btn" data-tool="pan" title="{{ t('Sposta la vista') }}">{!! icon('hand-move') !!}</button>
            </div>
            <button type="button" class="btn btn-icon wf-tool-btn" id="wf-zoom-out" title="{{ t('Riduci zoom') }}">{!! icon('zoom-out') !!}</button>
            <button type="button" class="btn btn-icon wf-tool-btn" id="wf-zoom-reset" title="{{ t('Reimposta zoom') }}">{!! icon('zoom-reset') !!}</button>
            <button type="button" class="btn btn-icon wf-tool-btn" id="wf-zoom-in" title="{{ t('Aumenta zoom') }}">{!! icon('zoom-in') !!}</button>
            <div class="vr mx-1"></div>
            <button type="button" class="btn btn-icon wf-tool-btn" id="wf-undo" title="{{ t('Annulla') }}">{!! icon('arrow-back-up') !!}</button>
            <button type="button" class="btn btn-icon wf-tool-btn" id="wf-redo" title="{{ t('Ripeti') }}">{!! icon('arrow-forward-up') !!}</button>
            <div class="vr mx-1"></div>
            <button type="button" class="btn btn-icon wf-tool-btn text-danger" id="wf-delete" title="{{ t('Elimina selezione') }}">{!! icon('trash') !!}</button>
            <span class="text-secondary small ms-auto">{{ t('Doppio click su un nodo o un arco per modificarlo.') }}</span>
        </div>
        <div class="d-flex" style="height: calc(100vh - 260px); min-height: 460px;">
            <div class="border-end p-1" style="width: 95px; overflow-y: auto; flex-shrink: 0;" data-testid="workflow-palette">
                <div class="text-secondary text-uppercase small mb-2">{{ t('Elementi') }}</div>
                @php
                    $palettePreviews = [
                        'start' => ['shape' => 'circle', 'bg' => '#2fb344', 'border' => '#1a7431', 'borderWidth' => 2],
                        'end' => ['shape' => 'circle', 'bg' => '#9aa0a6', 'border' => '#495057', 'borderWidth' => 5],
                        'user_task' => ['shape' => 'rect', 'bg' => '#4263eb', 'border' => '#28408f', 'badge' => 'user'],
                        'service_task' => ['shape' => 'rect', 'bg' => '#206bc4', 'border' => '#164b8a', 'badge' => 'settings'],
                        'exclusive_gateway' => ['shape' => 'diamond', 'bg' => '#f59f00', 'border' => '#a66a00'],
                        'parallel_gateway' => ['shape' => 'diamond', 'bg' => '#f76707', 'border' => '#a34600'],
                        'timer' => ['shape' => 'circle', 'bg' => '#ae3ec9', 'border' => '#6e2680'],
                        'semaphore' => ['shape' => 'circle', 'bg' => '#f8f9fa', 'border' => '#2fb344', 'borderWidth' => 3, 'icon' => 'traffic-lights', 'iconColor' => '#2fb344'],
                        'subworkflow' => ['shape' => 'rect', 'bg' => '#495057', 'border' => '#212529', 'dashed' => true],
                    ];
                @endphp
                @foreach ($nodeTypes as $value => $label)
                    @php($shape = $palettePreviews[$value] ?? ['shape' => 'rect', 'bg' => '#6c757d', 'border' => '#495057'])
                    <div class="workflow-palette-item" draggable="true" data-node-type="{{ $value }}" data-testid="palette-{{ $value }}">
                        <div class="wf-palette-preview-wrap">
                            <div class="wf-palette-preview wf-shape-{{ $shape['shape'] }}" style="background: {{ $shape['bg'] }}; border-color: {{ $shape['border'] }}; border-width: {{ $shape['borderWidth'] ?? 2 }}px; {{ ($shape['dashed'] ?? false) ? 'border-style: dashed;' : '' }}">
                                @if (! empty($shape['icon']))
                                    <span class="wf-palette-icon" style="color: {{ $shape['iconColor'] ?? '#fff' }}">{!! icon($shape['icon']) !!}</span>
                                @endif
                                @if (! empty($shape['badge']))
                                    <span class="wf-palette-badge-icon text-white">{!! icon($shape['badge'], 'filled') !!}</span>
                                @endif
                            </div>
                        </div>
                        <div class="small text-center">{{ $label }}</div>
                    </div>
                @endforeach
            </div>

            <div id="workflow-canvas" class="flex-grow-1" style="position: relative; overflow: hidden; background-image: radial-gradient(circle, var(--tblr-border-color) 1px, transparent 1px); background-size: 16px 16px;" data-testid="workflow-canvas"></div>
        </div>
    </div>

    {{-- Floating, draggable, non-blocking editor window — opened on double-click of a node or edge --}}
    <div id="workflow-float-window" class="card shadow-lg" style="position: fixed; display: none; width: min(75vw, 1500px); min-width: 480px; max-height: 85vh; z-index: 1050; top: 90px; right: 30px;" data-testid="workflow-float-window">
        <div class="card-header py-2" id="workflow-float-window-header" style="cursor: move; user-select: none;">
            <h3 class="card-title mb-0" id="workflow-float-window-title"></h3>
            <div class="card-actions">
                <button type="button" class="btn-close" id="workflow-float-window-close" aria-label="{{ t('Chiudi') }}"></button>
            </div>
        </div>
        <div class="card-body" id="workflow-float-window-body" style="overflow-y: auto; max-height: calc(85vh - 112px);"></div>
        <div class="card-footer d-flex justify-content-between">
            <button type="button" class="btn btn-outline-danger" id="workflow-float-window-delete" data-testid="workflow-float-delete"></button>
            <button type="button" class="btn btn-primary" id="workflow-float-window-save">{{ t('Chiudi') }}</button>
        </div>
    </div>

    {{-- Templates used by workflow-builder.js to render the floating editor window and action rows without inline string-building --}}
    <template id="tpl-node-inspector">
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="mb-3">
                    <label class="form-label">{{ t('Nome') }}</label>
                    <input type="text" class="form-control" data-field="name">
                </div>
                <div data-node-config></div>
            </div>
            <div class="col-lg-6">
                <div data-actions-before></div>
                <hr>
                <div data-actions-after></div>
            </div>
        </div>
    </template>

    <template id="tpl-edge-inspector">
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="mb-3">
                    <label class="form-label">{{ t('Etichetta') }}</label>
                    <input type="text" class="form-control" data-field="label">
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ t('Condizione (gate esclusivo)') }}</label>
                    <div data-condition-editor></div>
                    <small class="form-hint">{{ t('Valutata in sequenza dal Gate esclusivo di partenza; vuota = ramo di default.') }}</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ t('Ordine') }}</label>
                    <input type="number" class="form-control" data-field="sequence" value="0">
                </div>
            </div>
            <div class="col-lg-6">
                <div data-actions-before></div>
                <hr>
                <div data-actions-after></div>
            </div>
        </div>
    </template>

    <template id="tpl-actions-block">
        <div>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <strong class="small text-uppercase" data-actions-title></strong>
                <button type="button" class="btn btn-sm btn-outline-primary" data-add-action>+ {{ t('Azione') }}</button>
            </div>
            <div data-actions-list></div>
        </div>
    </template>

    <template id="tpl-action-row">
        <div class="card card-sm mb-2" data-action-row>
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="cursor-move text-secondary me-1" data-action-drag-handle style="cursor: grab;">{!! icon('grip-vertical') !!}</span>
                    <select class="form-select form-select-sm" data-action-type style="max-width: 200px;">
                        @foreach ($actionTypes as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="btn btn-sm btn-link text-danger" data-remove-action>{{ t('Rimuovi') }}</button>
                </div>
                <div data-action-config></div>
            </div>
        </div>
    </template>

    {{-- Variables modal --}}
    <div class="modal" id="workflow-variables-modal" tabindex="-1" data-testid="workflow-variables-modal">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ t('Variabili del workflow') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-sm" id="workflow-variables-table">
                        <thead>
                            <tr>
                                <th>{{ t('Nome') }}</th>
                                <th>{{ t('Tipo') }}</th>
                                <th>{{ t('Valore predefinito') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="workflow-variables-body"></tbody>
                    </table>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="workflow-variable-add">+ {{ t('Variabile') }}</button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">{{ t('Chiudi') }}</button>
                </div>
            </div>
        </div>
    </div>

    <template id="tpl-variable-row">
        <tr data-variable-row>
            <td><input type="text" class="form-control form-control-sm" data-variable-name></td>
            <td>
                <select class="form-select form-select-sm" data-variable-type>
                    @foreach ($variableTypes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </td>
            <td><input type="text" class="form-control form-control-sm" data-variable-default></td>
            <td><button type="button" class="btn btn-sm btn-link text-danger" data-variable-remove>{{ t('Rimuovi') }}</button></td>
        </tr>
    </template>

    <script>
        window.WORKFLOW_BUILDER = {
            graph: @json($graph),
            saveUrl: @json(route('admin.workflows.builder.update', $workflow)),
            iconBaseUrl: @json(url('/tabler-icons')),
            options: {
                nodeTypes: @json($nodeTypes),
                variableTypes: @json($variableTypes),
                actionTypes: @json($actionTypes),
                actionPhases: @json($actionPhases),
                triggerTypes: @json($triggerTypes),
                occurrenceOptions: @json($occurrenceOptions),
                timerUnits: @json($timerUnits),
                entities: @json($entities),
                roles: @json($roles),
                otherWorkflows: @json($otherWorkflows),
            },
            labels: {
                phaseBefore: @json(t('Azioni in ingresso')),
                phaseAfter: @json(t('Azioni in uscita')),
                saving: @json(t('Salvataggio...')),
                saved: @json(t('Salvato.')),
                saveError: @json(t('Errore nel salvataggio')),
                confirmDelete: @json(t('Confermi l\'eliminazione?')),
                nodeWindowTitle: @json(t('Nodo')),
                edgeWindowTitle: @json(t('Arco')),
                deleteNode: @json(t('Elimina nodo')),
                deleteEdge: @json(t('Elimina arco')),
            },
            i18n: {
                onlyOneStart: @json(t('Il workflow può avere un solo nodo di avvio.')),
                trigger: @json(t('Trigger')),
                cronExpression: @json(t('Espressione cron')),
                entity: @json(t('Entità')),
                fixedDate: @json(t('Data fissa')),
                variableRef: @json(t('Variabile')),
                reference: @json(t('Riferimento')),
                variableNameDate: @json(t('Nome variabile (data)')),
                date: @json(t('Data')),
                before: @json(t('Prima')),
                after: @json(t('Dopo')),
                direction: @json(t('Direzione')),
                amount: @json(t('Quantità')),
                unit: @json(t('Unità')),
                none: @json(t('— nessuno —')),
                assignedRole: @json(t('Ruolo assegnato')),
                showInEntityDetail: @json(t('Mostra nel dettaglio entità')),
                formFields: @json(t('Campi form')),
                field: @json(t('Campo')),
                fieldNamePlaceholder: @json(t('nome_campo')),
                label: @json(t('Etichetta')),
                typeString: @json(t('Testo')),
                typeText: @json(t('Testo lungo')),
                typeNumber: @json(t('Numero')),
                typeBoolean: @json(t('Booleano')),
                bindVariable: @json(t('Variabile a cui assegnare la risposta')),
                removeField: @json(t('Rimuovi campo')),
                subworkflow: @json(t('Sotto-workflow')),
                waitForCompletion: @json(t('Attendi il completamento')),
                expression: @json(t('Espressione')),
                idExpression: @json(t('Espressione ID record')),
                to: @json(t('Destinatario/i')),
                subject: @json(t('Oggetto')),
                body: @json(t('Corpo')),
                assignToVariable: @json(t('Assegna nuovo record a variabile')),
                column: @json(t('colonna')),
                expressionShort: @json(t('espressione')),
                occurrence: @json(t('Ripetizione')),
                startCondition: @json(t('Condizione di avvio')),
                startConditionHint: @json(t('Il flusso si avvia solo se questa condizione è vera (vuota = nessun vincolo).')),
                exclusiveBranches: @json(t('Rami in uscita')),
                exclusiveBranchesEmpty: @json(t('Collega questo gate ad altri nodi per configurarne i rami.')),
                doubleClickToEdit: @json(t('Doppio click per modificare')),
                newField: @json(t('Nuovo campo')),
                noVariable: @json(t('— nessuna —')),
            },
        };
    </script>
@endsection

@vite('resources/js/workflow-builder.js')
