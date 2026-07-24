@php
    $widget = $widget ?? null;
    $config = $widget?->config ?? [];
    $isEdit = $widget !== null;
@endphp
@extends('layouts.admin')

@section('title', $isEdit ? t('Modifica widget') : t('Nuovo widget'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.entities.index') }}">{{ t('Entità') }}</a>
    </li>
    <li class="breadcrumb-item">
        <a href="{{ route('admin.entities.widgets.index', $entity) }}">{{ t('Widget lista') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        {{ $isEdit ? t('Modifica widget') : t('Nuovo widget') }}
    </li>
@endsection

@section('buttons')
    <button type="submit" form="entity-widget-form" class="btn btn-primary" data-testid="entity-widget-submit">{{ t('Salva') }}</button>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form
                action="{{ $isEdit ? route('admin.entities.widgets.update', [$entity, $widget]) : route('admin.entities.widgets.store', $entity) }}"
                method="POST"
                id="entity-widget-form"
            >
                @csrf
                @if ($isEdit)
                    @method('PUT')
                @endif

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label">{{ t('Nome') }}</label>
                        <input type="text" id="name" name="name" value="{{ old('name', $widget?->name) }}" class="form-control @error('name') is-invalid @enderror">
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3 mb-3">
                        <label for="type" class="form-label">{{ t('Tipo') }}</label>
                        <select id="type" name="type" class="form-select @error('type') is-invalid @enderror" @disabled($isEdit)>
                            <option value="button" @selected(old('type', $widget?->type) === 'button')>{{ t('Bottone') }}</option>
                            <option value="counter" @selected(old('type', $widget?->type) === 'counter')>{{ t('Contatore') }}</option>
                            <option value="chart" @selected(old('type', $widget?->type) === 'chart')>{{ t('Grafico') }}</option>
                        </select>
                        @if ($isEdit)
                            <input type="hidden" name="type" value="{{ $widget->type }}">
                        @endif
                        @error('type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-2 mb-3">
                        <label for="position" class="form-label">{{ t('Posizione') }}</label>
                        <input type="number" id="position" name="position" value="{{ old('position', $widget?->position) }}" class="form-control @error('position') is-invalid @enderror">
                        @error('position')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-1 mb-3 d-flex align-items-end">
                        <label class="form-check">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" class="form-check-input" name="is_active" value="1" @checked(old('is_active', $widget?->is_active ?? true))>
                            <span class="form-check-label">{{ t('Attivo') }}</span>
                        </label>
                    </div>
                </div>

                <div class="widget-button-group d-none">
                    <div class="mb-3">
                        <label for="button_action" class="form-label">{{ t('Azione al click') }}</label>
                        <select id="button_action" name="button_action" class="form-select @error('button_action') is-invalid @enderror">
                            <option value="workflow" @selected(old('button_action', $config['button_action'] ?? null) === 'workflow')>{{ t('Avvia un flusso (workflow manuale)') }}</option>
                            <option value="importer" @selected(old('button_action', $config['button_action'] ?? null) === 'importer')>{{ t('Lancia uno o più importatori') }}</option>
                            <option value="javascript" @selected(old('button_action', $config['button_action'] ?? null) === 'javascript')>{{ t('Esegui codice JavaScript') }}</option>
                        </select>
                        @error('button_action')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3 widget-button-workflow-group d-none">
                        <label for="button_workflow_id" class="form-label">{{ t('Workflow da avviare') }}</label>
                        <select id="button_workflow_id" name="button_workflow_id" class="form-select @error('button_workflow_id') is-invalid @enderror">
                            <option value="">{{ t('Seleziona...') }}</option>
                            @foreach ($manualWorkflows as $workflow)
                                <option value="{{ $workflow->id }}" @selected((string) old('button_workflow_id', $config['button_workflow_id'] ?? null) === (string) $workflow->id)>{{ $workflow->name }}</option>
                            @endforeach
                        </select>
                        @error('button_workflow_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3 widget-button-importer-group d-none">
                        <label for="button_importer_ids" class="form-label">{{ t('Importatori da lanciare') }}</label>
                        <select id="button_importer_ids" name="button_importer_ids[]" multiple class="form-select @error('button_importer_ids') is-invalid @enderror">
                            @foreach ($importers as $importer)
                                <option value="{{ $importer->id }}" @selected(in_array((string) $importer->id, (array) old('button_importer_ids', $config['button_importer_ids'] ?? []), true))>{{ $importer->title }}</option>
                            @endforeach
                        </select>
                        @error('button_importer_ids')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3 widget-button-javascript-group d-none">
                        <label for="button_javascript" class="form-label">{{ t('Codice JavaScript') }}</label>
                        <textarea id="button_javascript" name="button_javascript" rows="6" class="form-control font-monospace @error('button_javascript') is-invalid @enderror">{{ old('button_javascript', $config['button_javascript'] ?? null) }}</textarea>
                        @error('button_javascript')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="widget-counter-group d-none">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="counter_color" class="form-label">{{ t('Colore') }}</label>
                            <select id="counter_color" name="counter_color" class="form-select">
                                @foreach (['primary', 'success', 'danger', 'warning', 'info', 'secondary'] as $color)
                                    <option value="{{ $color }}" @selected(old('counter_color', $config['color'] ?? 'primary') === $color)>{{ $color }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="counter_icon" class="form-label">{{ t('Icona (nome tabler-icons, opzionale)') }}</label>
                            <input type="text" id="counter_icon" name="counter_icon" value="{{ old('counter_icon', $config['icon'] ?? null) }}" placeholder="es. users" class="form-control">
                        </div>
                    </div>
                </div>

                <div class="widget-chart-group d-none">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="chart_type" class="form-label">{{ t('Tipo di grafico') }}</label>
                            <select id="chart_type" name="chart_type" class="form-select @error('chart_type') is-invalid @enderror">
                                <option value="bar" @selected(old('chart_type', $config['chart_type'] ?? null) === 'bar')>{{ t('Barre') }}</option>
                                <option value="line" @selected(old('chart_type', $config['chart_type'] ?? null) === 'line')>{{ t('Linee') }}</option>
                                <option value="pie" @selected(old('chart_type', $config['chart_type'] ?? null) === 'pie')>{{ t('Torta') }}</option>
                                <option value="doughnut" @selected(old('chart_type', $config['chart_type'] ?? null) === 'doughnut')>{{ t('Ciambella') }}</option>
                            </select>
                            @error('chart_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="chart_group_by" class="form-label">{{ t('Raggruppa per campo') }}</label>
                            <select id="chart_group_by" name="chart_group_by" class="form-select @error('chart_group_by') is-invalid @enderror">
                                <option value="">{{ t('Seleziona...') }}</option>
                                @foreach ($columns as $value => $label)
                                    <option value="{{ $value }}" @selected(old('chart_group_by', $config['group_by'] ?? null) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('chart_group_by')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="chart_aggregate" class="form-label">{{ t('Aggregazione') }}</label>
                            <select id="chart_aggregate" name="chart_aggregate" class="form-select @error('chart_aggregate') is-invalid @enderror">
                                <option value="count" @selected(old('chart_aggregate', $config['aggregate'] ?? null) === 'count')>{{ t('Conteggio righe') }}</option>
                                <option value="sum" @selected(old('chart_aggregate', $config['aggregate'] ?? null) === 'sum')>{{ t('Somma') }}</option>
                                <option value="avg" @selected(old('chart_aggregate', $config['aggregate'] ?? null) === 'avg')>{{ t('Media') }}</option>
                            </select>
                            @error('chart_aggregate')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3 widget-chart-value-column-group d-none">
                        <label for="chart_value_column" class="form-label">{{ t('Campo numerico da aggregare') }}</label>
                        <select id="chart_value_column" name="chart_value_column" class="form-select @error('chart_value_column') is-invalid @enderror">
                            <option value="">{{ t('Seleziona...') }}</option>
                            @foreach ($numericColumns as $value => $label)
                                <option value="{{ $value }}" @selected(old('chart_value_column', $config['value_column'] ?? null) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('chart_value_column')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="widget-filter-group d-none">
                    <label class="form-label">{{ t('Filtro (opzionale)') }}</label>
                    <div class="row">
                        <div class="col-md-5 mb-3">
                            <select id="filter_column" name="filter_column" class="form-select @error('filter_column') is-invalid @enderror">
                                <option value="">{{ t('— nessun filtro —') }}</option>
                                @foreach ($columns as $value => $label)
                                    <option value="{{ $value }}" @selected(old('filter_column', $config['filter']['column'] ?? null) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('filter_column')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3 mb-3">
                            <select id="filter_operator" name="filter_operator" class="form-select @error('filter_operator') is-invalid @enderror">
                                @foreach (['=', '!=', '>', '<', '>=', '<='] as $operator)
                                    <option value="{{ $operator }}" @selected(old('filter_operator', $config['filter']['operator'] ?? null) === $operator)>{{ $operator }}</option>
                                @endforeach
                            </select>
                            @error('filter_operator')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4 mb-3">
                            <input type="text" name="filter_value" value="{{ old('filter_value', $config['filter']['value'] ?? null) }}" class="form-control @error('filter_value') is-invalid @enderror">
                            @error('filter_value')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const typeSelect = document.getElementById('type');
            const buttonGroup = document.querySelector('.widget-button-group');
            const counterGroup = document.querySelector('.widget-counter-group');
            const chartGroup = document.querySelector('.widget-chart-group');
            const filterGroup = document.querySelector('.widget-filter-group');
            const buttonActionSelect = document.getElementById('button_action');
            const buttonWorkflowGroup = document.querySelector('.widget-button-workflow-group');
            const buttonImporterGroup = document.querySelector('.widget-button-importer-group');
            const buttonJavascriptGroup = document.querySelector('.widget-button-javascript-group');
            const chartAggregateSelect = document.getElementById('chart_aggregate');
            const chartValueColumnGroup = document.querySelector('.widget-chart-value-column-group');

            function syncButtonGroups() {
                const action = buttonActionSelect.value;
                buttonWorkflowGroup.classList.toggle('d-none', action !== 'workflow');
                buttonImporterGroup.classList.toggle('d-none', action !== 'importer');
                buttonJavascriptGroup.classList.toggle('d-none', action !== 'javascript');
            }

            function syncChartGroups() {
                chartValueColumnGroup.classList.toggle('d-none', chartAggregateSelect.value === 'count');
            }

            function syncGroups() {
                const type = typeSelect.value;
                buttonGroup.classList.toggle('d-none', type !== 'button');
                counterGroup.classList.toggle('d-none', type !== 'counter');
                chartGroup.classList.toggle('d-none', type !== 'chart');
                filterGroup.classList.toggle('d-none', type === 'button');
            }

            typeSelect.addEventListener('change', syncGroups);
            buttonActionSelect.addEventListener('change', syncButtonGroups);
            chartAggregateSelect.addEventListener('change', syncChartGroups);
            syncGroups();
            syncButtonGroups();
            syncChartGroups();
        });
    </script>
@endsection
