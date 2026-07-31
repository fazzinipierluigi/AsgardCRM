@extends('layouts.base')

@section('title', t('Modifica record'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('entities.index', $entity) }}">{{ $entity->name }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('entities.edit', [$entity, $record]) }}">{{ t('Modifica record') }}</a>
    </li>
@endsection

@section('buttons')
    <button type="submit" form="entity-record-form" class="btn btn-primary" data-testid="entity-record-submit">{{ t('Salva modifiche') }}</button>
@endsection

@section('content')
    @php
        $entityRelations = $entityRelations ?? collect();
    @endphp

    <div class="row">
        <div class="{{ $entityRelations->isNotEmpty() ? 'col-lg-9' : 'col-12' }}">
            @if ($canViewWorkflows ?? false)
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" type="button" data-bs-toggle="tab" data-bs-target="#entity-record-tab-data" role="tab">{{ t('Dati') }}</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#entity-record-tab-workflows" role="tab" data-testid="entity-record-workflows-tab">{{ t('Flussi') }}</button>
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

                    <form action="{{ route('entities.update', [$entity, $record]) }}" method="POST" id="entity-record-form">
                        @csrf
                        @method('PUT')
                        @include('entities._form', ['entity' => $entity, 'record' => $record, 'relationOptions' => $relationOptions])
                    </form>

                    @if (($changeTransactions ?? collect())->isNotEmpty())
                        <div class="card mt-3" data-testid="entity-change-log">
                            <div class="card-header">
                                <h3 class="card-title">{{ t('Storico modifiche') }}</h3>
                            </div>
                            <div class="list-group list-group-flush">
                                @foreach ($changeTransactions as $changes)
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between text-secondary small mb-1">
                                            <span>{{ $changes->first()->changedByUser?->name ?? $changes->first()->changed_by_label ?? t('Sconosciuto') }}</span>
                                            <span>{{ $changes->first()->created_at->format('d/m/Y H:i') }}</span>
                                        </div>
                                        <ul class="mb-0 ps-3">
                                            @foreach ($changes as $change)
                                                <li>
                                                    <strong>{{ $change->field_label }}</strong>:
                                                    {{ $change->old_value ?? t('(vuoto)') }} → {{ $change->new_value ?? t('(vuoto)') }}
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                @if ($canViewWorkflows ?? false)
                    <div class="tab-pane" id="entity-record-tab-workflows" role="tabpanel" data-testid="entity-record-workflows-panel">
                        @include('entities._workflows-tab', ['entity' => $entity, 'record' => $record, 'workflowInstances' => $workflowInstances])
                    </div>
                @endif
            </div>
        </div>

        @if ($entityRelations->isNotEmpty())
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
    </script>

    @vite(['resources/js/entity-record-form.js', 'resources/js/entity-relations.js'])
@endsection
