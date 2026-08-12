@php
    $entityRelations = $entityRelations ?? collect();
    $changeTransactions = $changeTransactions ?? collect();
    $canViewWorkflows = $canViewWorkflows ?? false;
    $canViewCalendarActivities = $canViewCalendarActivities ?? false;
    $calendarActivities = $calendarActivities ?? collect();
    $showNav = true;
    $canEditRelations = $mode !== 'view' || ($canEdit ?? false);
@endphp

<div class="row">
    <div class="{{ $entityRelations->isNotEmpty() ? 'col-lg-9' : 'col-12' }}">
        @if ($showNav)
            <ul class="nav nav-tabs mb-3" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" type="button" data-bs-toggle="tab" data-bs-target="#entity-record-tab-data" role="tab">{{ t('Dati') }}</button>
                </li>
                @if ($canViewWorkflows)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#entity-record-tab-workflows" role="tab" data-testid="entity-record-workflows-tab">{{ t('Flussi') }}</button>
                    </li>
                @endif
                @if ($canViewCalendarActivities)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#entity-record-tab-activities" role="tab" data-testid="entity-record-activities-tab">{{ t('Attività') }}</button>
                    </li>
                @endif
                <li class="nav-item" role="presentation">
                    <button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#entity-record-tab-changelog" role="tab" data-testid="entity-record-changelog-tab">{{ t('Storico modifiche') }}</button>
                </li>
            </ul>
        @endif

        <div class="tab-content">
            <div class="tab-pane show active" id="entity-record-tab-data" role="tabpanel">
                @if (($workflowTasks ?? collect())->isNotEmpty())
                    <div class="card mb-3" data-testid="entity-workflow-tasks">
                        <div class="card-header">
                            <h3 class="card-title">{{ t('Task da completare') }}</h3>
                        </div>
                        <div class="list-group list-group-flush">
                            @foreach ($workflowTasks as $task)
                                <a href="{{ route('workflow-tasks.edit', $task) }}" class="list-group-item list-group-item-action">
                                    {{ $task->node->name }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($mode === 'edit')
                    <form action="{{ route('entities.update', [$entity, $record]) }}" method="POST" id="entity-record-form">
                        @csrf
                        @method('PUT')
                        @include('crm::entities._form', ['entity' => $entity, 'record' => $record, 'relationOptions' => $relationOptions, 'productsBlockOptions' => $productsBlockOptions ?? []])
                    </form>
                @else
                    @include('crm::entities._form', ['entity' => $entity, 'record' => $record, 'relationOptions' => $relationOptions, 'productsBlockOptions' => $productsBlockOptions ?? [], 'readonly' => true])
                @endif
            </div>

            @if ($canViewWorkflows)
                <div class="tab-pane" id="entity-record-tab-workflows" role="tabpanel" data-testid="entity-record-workflows-panel">
                    @include('crm::entities._workflows-tab', ['entity' => $entity, 'record' => $record, 'workflowInstances' => $workflowInstances])
                </div>
            @endif

            @if ($canViewCalendarActivities)
                <div class="tab-pane" id="entity-record-tab-activities" role="tabpanel" data-testid="entity-record-activities-panel">
                    @if ($calendarActivities->isEmpty())
                        <p class="text-secondary" data-testid="entity-activities-empty">{{ t('Nessuna attività di calendario collegata a questo record.') }}</p>
                    @else
                        <div class="card">
                            <div class="list-group list-group-flush" data-testid="entity-activities-list">
                                @foreach ($calendarActivities as $activity)
                                    <a href="{{ route('entities.show', ['calendario', $activity]) }}" class="list-group-item list-group-item-action">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <strong>{{ $activity->title }}</strong>
                                            <span class="text-secondary small">
                                                {{ $activity->start_datetime ? \Carbon\Carbon::parse($activity->start_datetime)->format('d/m/Y H:i') : '' }} &ndash; {{ $activity->end_datetime ? \Carbon\Carbon::parse($activity->end_datetime)->format('d/m/Y H:i') : '' }}
                                            </span>
                                        </div>
                                        @if ($activity->description)
                                            <div class="text-secondary small mt-1">{{ \Illuminate\Support\Str::limit($activity->description, 140) }}</div>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            <div class="tab-pane" id="entity-record-tab-changelog" role="tabpanel" data-testid="entity-record-changelog-panel">
                <div class="card">
                    <div class="card-body">
                        <div class="timeline" data-testid="entity-change-log">
                            @foreach ($changeTransactions as $changes)
                                @php $isCreation = $loop->last; @endphp
                                <div class="timeline-event">
                                    <div class="timeline-event-icon {{ $isCreation ? 'bg-green-lt' : 'bg-blue-lt' }}">
                                        @if ($isCreation)
                                            <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                                        @else
                                            <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 20h4l10.5 -10.5a2.828 2.828 0 1 0 -4 -4l-10.5 10.5v4" /><path d="M13.5 6.5l4 4" /></svg>
                                        @endif
                                    </div>
                                    <div class="timeline-event-card card">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between text-secondary small mb-1">
                                                <span>{{ $changes->first()->changedByUser?->name ?? $changes->first()->changed_by_label ?? t('Sconosciuto') }}</span>
                                                <span>{{ $changes->first()->created_at->format('d/m/Y H:i') }}</span>
                                            </div>
                                            @if ($isCreation)
                                                <div class="fw-medium mb-1">{{ t('Record creato') }}</div>
                                            @endif
                                            <ul class="mb-0 ps-3">
                                                @foreach ($changes as $change)
                                                    <li>
                                                        <strong>{{ $change->field_label }}</strong>:
                                                        {{ $change->old_value ?? t('(vuoto)') }} → {{ $change->new_value ?? t('(vuoto)') }}
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            @endforeach

                            @if ($changeTransactions->isEmpty())
                                <div class="timeline-event">
                                    <div class="timeline-event-icon bg-green-lt">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                                    </div>
                                    <div class="timeline-event-card card">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between text-secondary small mb-1">
                                                <span>{{ $record->owner?->name ?? t('Sconosciuto') }}</span>
                                                <span>{{ $record->created_at->format('d/m/Y H:i') }}</span>
                                            </div>
                                            <div class="fw-medium mb-0">{{ t('Record creato') }}</div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($entityRelations->isNotEmpty() && $canEditRelations)
        <div class="col-lg-3">
            <div class="card" style="position: sticky; top: 1rem;" data-testid="entity-relations-card">
                <div class="card-header">
                    <h3 class="card-title">{{ t('Relazioni') }}</h3>
                </div>
                <div class="list-group list-group-flush">
                    @foreach ($entityRelations as $item)
                        <button
                            type="button"
                            class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                            data-entity-relation-open
                            data-relation-name="{{ $item['relation']->name }}"
                            data-data-url="{{ route('entities.relations.data', [$entity, $record, $item['relation']]) }}"
                            data-options-url="{{ route('entities.relations.options', [$entity, $record, $item['relation']]) }}"
                            data-attach-url="{{ route('entities.relations.attach', [$entity, $record, $item['relation']]) }}"
                            data-detach-url-base="{{ route('entities.relations.detach', [$entity, $record, $item['relation'], 0]) }}"
                            data-testid="entity-relation-item-{{ $item['relation']->id }}"
                        >
                            <span>{{ $item['relation']->name }}</span>
                            <span class="badge bg-blue-lt" data-entity-relation-count>{{ $item['count'] }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="entity-relations-offcanvas" style="--tblr-offcanvas-width: calc(100vw - 15rem);" data-testid="entity-relations-offcanvas">
    <div class="offcanvas-header border-bottom">
        <h2 class="offcanvas-title" id="entity-relations-offcanvas-title" data-testid="entity-relations-offcanvas-title"></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ t('Chiudi') }}"></button>
    </div>
    <div class="offcanvas-body">
        <div class="row g-2 mb-3">
            <div class="col-md-8">
                <select id="entity-relations-attach-select" data-tom-select-manual data-testid="entity-relations-attach-select"></select>
            </div>
            <div class="col-md-4">
                <button type="button" class="btn btn-primary w-100" id="entity-relations-attach-btn" data-testid="entity-relations-attach-btn">{{ t('Aggiungi') }}</button>
            </div>
        </div>
        <div class="card">
            <div class="table-responsive">
                <table class="table table-vcenter card-table" data-testid="entity-relations-table">
                    <thead>
                        <tr>
                            <th>{{ t('Record') }}</th>
                            <th class="w-1"></th>
                        </tr>
                    </thead>
                    <tbody id="entity-relations-tbody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    window.ENTITY_RELATIONS_I18N = {
        empty: @json(t('Nessuna relazione collegata.')),
        remove: @json(t('Rimuovi')),
    };
    window.ENTITY_FIELD_CONDITIONS = @json($mode === 'edit' ? ($fieldConditions ?? []) : []);
</script>

@vite(['resources/js/entity-record-form.js', 'resources/js/entity-relations.js', 'resources/js/entity-field-conditions.js'], 'vendor/crm')
