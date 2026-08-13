@extends('layouts.base')

@section('title', $task->node->name)

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('workflow-tasks.index') }}">{{ t('I miei task') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('workflow-tasks.edit', $task) }}">{{ $task->node->name }}</a>
    </li>
@endsection

@section('buttons')
    <button type="submit" form="workflow-task-form" class="btn btn-primary" data-testid="workflow-task-submit">
        {{ t('Completa task') }}
    </button>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('workflow-tasks.update', $task) }}" method="POST" id="workflow-task-form">
                @csrf
                @method('PUT')

                @forelse ($fields as $field)
                    <div class="mb-3">
                        <label for="field-{{ $field['name'] }}" class="form-label">{{ $field['label'] ?? $field['name'] }}</label>

                        @switch($field['type'] ?? 'string')
                            @case('boolean')
                                <label class="form-check form-switch">
                                    <input type="checkbox" name="{{ $field['name'] }}" value="1" class="form-check-input" id="field-{{ $field['name'] }}">
                                </label>
                                @break
                            @case('text')
                                <textarea name="{{ $field['name'] }}" id="field-{{ $field['name'] }}" class="form-control" rows="4"></textarea>
                                @break
                            @case('number')
                                <input type="number" name="{{ $field['name'] }}" id="field-{{ $field['name'] }}" class="form-control" step="any">
                                @break
                            @case('date')
                                <input type="date" name="{{ $field['name'] }}" id="field-{{ $field['name'] }}" class="form-control">
                                @break
                            @case('select')
                                <select name="{{ $field['name'] }}" id="field-{{ $field['name'] }}" class="form-select">
                                    @foreach (($field['options'] ?? []) as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @break
                            @case('table')
                                <div data-table-field data-columns="{{ json_encode($field['columns'] ?? []) }}">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered mb-2">
                                            <thead></thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-table-field-add>{{ t('Aggiungi riga') }}</button>
                                    <input type="hidden" name="{{ $field['name'] }}" id="field-{{ $field['name'] }}" data-table-field-input value="[]">
                                </div>
                                @break

                            @default
                                <input type="text" name="{{ $field['name'] }}" id="field-{{ $field['name'] }}" class="form-control">
                        @endswitch
                    </div>
                @empty
                    <p class="text-secondary">{{ t('Questo task non richiede dati aggiuntivi: completalo per proseguire il workflow.') }}</p>
                @endforelse
            </form>
        </div>
    </div>
@endsection
