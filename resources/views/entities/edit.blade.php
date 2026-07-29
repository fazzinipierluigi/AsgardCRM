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

    @vite('resources/js/entity-record-form.js')
@endsection
