@php
    $condition = $condition ?? null;
    $isEdit = $condition !== null;
    $targetsByFieldId = $isEdit ? $condition->targets->keyBy('entity_field_id') : collect();

    $variableTypeFor = function ($field) {
        return match ($field->type->value) {
            'checkbox' => 'boolean',
            'integer', 'decimal' => 'number',
            'date', 'datetime' => 'date',
            default => 'string',
        };
    };

    $variableDefs = $fields
        ->reject(fn ($field) => in_array($field->type->value, ['button', 'table'], true))
        ->map(fn ($field) => [
            'name' => $field->type->value === 'relation' ? "{$field->column_name}_id" : $field->column_name,
            'label' => $field->name,
            'type' => $variableTypeFor($field),
        ])
        ->values();
@endphp
@extends('layouts.admin')

@section('title', $isEdit ? t('Modifica condizione') : t('Nuova condizione'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.entities.index') }}">{{ t('Entità') }}</a>
    </li>
    <li class="breadcrumb-item">
        <a href="{{ route('admin.entities.conditions.index', $entity) }}">{{ t('Campi condizionali') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        {{ $isEdit ? t('Modifica condizione') : t('Nuova condizione') }}
    </li>
@endsection

@section('buttons')
    <button type="submit" form="entity-condition-form" class="btn btn-primary" data-testid="entity-condition-submit">{{ t('Salva') }}</button>
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <form
                action="{{ $isEdit ? route('admin.entities.conditions.update', [$entity, $condition]) : route('admin.entities.conditions.store', $entity) }}"
                method="POST"
                id="entity-condition-form"
            >
                @csrf
                @if ($isEdit)
                    @method('PUT')
                @endif

                <div class="mb-3">
                    <label for="name" class="form-label">{{ t('Nome condizione') }}</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $condition?->name) }}" class="form-control @error('name') is-invalid @enderror" data-testid="entity-condition-name">
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">{{ t('Regola') }}</label>
                    <div id="entity-condition-rule-editor" data-testid="entity-condition-rule-editor"></div>
                    <input type="hidden" id="entity-condition-rule-input" name="rule" value="{{ old('rule', $condition?->rule ? json_encode($condition->rule) : '') }}">
                    @error('rule')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table" data-testid="entity-condition-fields-table">
                <thead>
                    <tr>
                        <th>{{ t('Campo') }}</th>
                        <th class="text-center">{{ t('Gestione') }}</th>
                        <th class="text-center">{{ t('Visibile') }}</th>
                        <th class="text-center">{{ t('Readonly') }}</th>
                        <th class="text-center">{{ t('Obbligatorio') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($fields as $field)
                        @php
                            $target = $targetsByFieldId->get($field->id);
                            $managed = $target !== null;
                        @endphp
                        <tr data-condition-field-row>
                            <td>{{ $field->name }}</td>
                            <td class="text-center">
                                <input
                                    type="checkbox"
                                    class="form-check-input"
                                    data-condition-managed
                                    form="entity-condition-form"
                                    name="fields[{{ $field->id }}][managed]"
                                    value="1"
                                    @checked($managed)
                                    data-testid="entity-condition-managed-{{ $field->id }}"
                                >
                            </td>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input" data-condition-flag form="entity-condition-form" name="fields[{{ $field->id }}][visible]" value="1" @checked($target?->visible ?? true) @disabled(! $managed)>
                            </td>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input" data-condition-flag form="entity-condition-form" name="fields[{{ $field->id }}][readonly]" value="1" @checked($target?->readonly ?? false) @disabled(! $managed)>
                            </td>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input" data-condition-flag form="entity-condition-form" name="fields[{{ $field->id }}][required]" value="1" @checked($target?->required ?? false) @disabled(! $managed)>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <script>
        window.ENTITY_CONDITION_BUILDER = {
            variables: @json($variableDefs),
            initialRule: @json($condition?->rule),
        };
    </script>

    @vite('resources/js/entity-condition-builder.js')
@endsection
