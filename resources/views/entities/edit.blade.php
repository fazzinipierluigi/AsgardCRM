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

    @vite('resources/js/entity-record-form.js')
@endsection
