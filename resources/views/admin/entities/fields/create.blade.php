@extends('layouts.admin')

@section('title', t('Aggiungi campo a :entity', ['entity' => $entity->name]))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.entities.index') }}">{{ t('Entità') }}</a>
    </li>
    <li class="breadcrumb-item">
        <a href="{{ route('admin.entities.builder.edit', $entity) }}">{{ t('Progetta :entity', ['entity' => $entity->name]) }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        {{ t('Aggiungi campo') }}
    </li>
@endsection

@section('buttons')
    <button type="submit" form="entity-field-form" class="btn btn-primary" data-testid="entity-field-submit">{{ t('Aggiungi campo') }}</button>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.entities.fields.store', $entity) }}" method="POST" id="entity-field-form">
                @csrf

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label">{{ t('Nome campo') }}</label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror">
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="column_name" class="form-label">{{ t('Nome colonna') }}</label>
                        <input type="text" id="column_name" name="column_name" value="{{ old('column_name') }}" placeholder="es. cognome" class="form-control @error('column_name') is-invalid @enderror">
                        @error('column_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="entity_card_id" class="form-label">{{ t('Card') }}</label>
                        <select id="entity_card_id" name="entity_card_id" class="form-select @error('entity_card_id') is-invalid @enderror">
                            @foreach ($entity->tabs as $tab)
                                <optgroup label="{{ $tab->name }}">
                                    @foreach ($tab->cards as $card)
                                        <option value="{{ $card->id }}" @selected(old('entity_card_id') == $card->id)>{{ $card->name }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        @error('entity_card_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="type" class="form-label">{{ t('Tipo') }}</label>
                        <select id="type" name="type" class="form-select @error('type') is-invalid @enderror">
                            @foreach ($fieldTypes as $value => $label)
                                <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-check">
                        <input type="checkbox" class="form-check-input" name="required" value="1" @checked(old('required'))>
                        <span class="form-check-label">{{ t('Obbligatorio') }}</span>
                    </label>
                </div>

                <div class="mb-3 field-options-group d-none">
                    <label for="options" class="form-label">{{ t('Opzioni (una per riga, formato chiave:Etichetta)') }}</label>
                    <textarea id="options" name="options" rows="3" class="form-control @error('options') is-invalid @enderror">{{ old('options') }}</textarea>
                    @error('options')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3 field-code-group d-none">
                    <label for="code_prefix" class="form-label">{{ t('Prefisso') }}</label>
                    <input type="text" id="code_prefix" name="code_prefix" value="{{ old('code_prefix') }}" placeholder="es. INV-" class="form-control @error('code_prefix') is-invalid @enderror">
                    <small class="form-hint">{{ t('Il valore generato sarà "prefisso" + numero progressivo, es. INV-1, INV-2...') }}</small>
                    @error('code_prefix')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3 field-relation-group d-none">
                    <label for="relation_target" class="form-label">{{ t('Relazione verso') }}</label>
                    <select id="relation_target" name="relation_target" class="form-select @error('relation_target') is-invalid @enderror">
                        <option value="">{{ t('Seleziona...') }}</option>
                        @foreach ($relationTargets as $group => $targets)
                            <optgroup label="{{ $group }}">
                                @foreach ($targets as $value => $label)
                                    <option value="{{ $value }}" @selected(old('relation_target') === $value)>{{ $label }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    @error('relation_target')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label for="default_value" class="form-label">{{ t('Valore predefinito') }}</label>
                        <input type="text" id="default_value" name="default_value" value="{{ old('default_value') }}" class="form-control @error('default_value') is-invalid @enderror">
                        @error('default_value')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="width" class="form-label">{{ t('Larghezza (1-12)') }}</label>
                        <input type="number" id="width" name="width" min="1" max="12" value="{{ old('width', 12) }}" class="form-control @error('width') is-invalid @enderror">
                        @error('width')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const typeSelect = document.getElementById('type');
            const optionsGroup = document.querySelector('.field-options-group');
            const codeGroup = document.querySelector('.field-code-group');
            const relationGroup = document.querySelector('.field-relation-group');

            function syncGroups() {
                const type = typeSelect.value;
                optionsGroup.classList.toggle('d-none', type !== 'select');
                codeGroup.classList.toggle('d-none', type !== 'code');
                relationGroup.classList.toggle('d-none', type !== 'relation');
            }

            typeSelect.addEventListener('change', syncGroups);
            syncGroups();
        });
    </script>
@endsection
