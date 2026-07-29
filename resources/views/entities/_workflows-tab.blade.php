@php
    $selectedInstanceId = $workflowInstances->first()?->id;
@endphp

<div class="mb-3" style="max-width: 480px;">
    <label class="form-label">{{ t('Flusso') }}</label>
    <select id="workflow-instance-select" class="form-select" data-testid="entity-workflow-instance-select">
        <option value="">{{ t('Seleziona un flusso') }}</option>
        @foreach ($workflowInstances as $instance)
            <option
                value="{{ $instance->id }}"
                data-url="{{ route('entities.workflow-instance-graph', [$entity, $record, $instance]) }}"
                @selected($instance->id === $selectedInstanceId)
            >{{ $instance->workflow->name }} &middot; {{ $instance->started_at?->format('d/m/Y H:i') }} &middot; {{ $instance->status->label() }}</option>
        @endforeach
    </select>
</div>

@if ($workflowInstances->isEmpty())
    <p class="text-secondary" data-testid="entity-workflow-instances-empty">{{ t('Nessun flusso avviato su questo record.') }}</p>
@endif

<p id="workflow-instance-empty-state" class="text-secondary {{ $workflowInstances->isEmpty() ? 'd-none' : '' }}">
    {{ t('Seleziona un flusso per vederne il dettaglio.') }}
</p>

<div class="row">
    <div class="col-lg-8">
        <div
            id="workflow-instance-canvas"
            class="d-none"
            style="height: 500px; border: 1px solid var(--tblr-border-color); border-radius: var(--tblr-border-radius); position: relative; overflow: hidden;"
            data-testid="entity-workflow-instance-canvas"
        ></div>
    </div>
    <div class="col-lg-4">
        <div id="workflow-instance-log-panel" class="card d-none" data-testid="entity-workflow-instance-log-panel" data-no-executions-text="{{ t('Nessuna esecuzione registrata.') }}">
            <div class="card-header">
                <h3 id="workflow-instance-log-panel-title" class="card-title"></h3>
            </div>
            <div id="workflow-instance-log-panel-body" class="card-body" style="max-height: 440px; overflow-y: auto;"></div>
        </div>
    </div>
</div>

@vite('resources/js/workflow-instance-viewer.js')
