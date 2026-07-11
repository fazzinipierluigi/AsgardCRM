@extends('layouts.admin')

@section('title', t('Progetta :entity', ['entity' => $entity->name]))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.entities.index') }}">{{ t('Entità') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.entities.builder.edit', $entity) }}">{{ t('Progetta :entity', ['entity' => $entity->name]) }}</a>
    </li>
@endsection

@unless ($entity->is_installed)
    @section('buttons')
        <button type="submit" form="entity-builder-form" class="btn btn-primary" data-testid="entity-builder-submit">{{ t('Salva struttura') }}</button>
    @endsection
@endunless

@section('content')
    <style>
        .field-resize-handle {
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            width: 14px;
            cursor: ew-resize;
            background: linear-gradient(to right, transparent, rgba(98, 105, 118, .12));
            border-top-right-radius: var(--tblr-border-radius, .25rem);
            border-bottom-right-radius: var(--tblr-border-radius, .25rem);
        }
        .field-resize-handle::after {
            content: "";
            position: absolute;
            top: 50%;
            right: 5px;
            width: 2px;
            height: 22px;
            transform: translateY(-50%);
            background: rgba(98, 105, 118, .5);
            border-radius: 1px;
        }
        .field-resize-handle:hover,
        .field-resize-handle.is-resizing {
            background: rgba(32, 107, 196, .25);
        }
        .field-resize-handle:hover::after,
        .field-resize-handle.is-resizing::after {
            background: #206bc4;
        }
        body.is-resizing-field {
            cursor: ew-resize !important;
            user-select: none !important;
        }
    </style>

    @if (session('status') === 'entity-structure-saved')
        <div class="alert alert-success" data-testid="entity-builder-status">
            {{ t('Struttura salvata correttamente.') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger" data-testid="entity-builder-errors">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($entity->is_installed)
        <div class="alert alert-info" data-testid="entity-builder-installed-notice">
            {{ t('Questa entità è installata: la struttura non è più modificabile da qui.') }}
        </div>
    @endif

    <form action="{{ route('admin.entities.builder.update', $entity) }}" method="POST" id="entity-builder-form" data-installed="{{ $entity->is_installed ? '1' : '0' }}">
        @csrf
        @method('PUT')

        <fieldset @if ($entity->is_installed) disabled @endif>
            <ul class="nav nav-tabs mb-2" id="tabs-nav" role="tablist">
                @foreach ($entity->tabs as $tab)
                    @include('admin.entities._tab_nav', ['tab' => $tab, 'tabToken' => $tab->id, 'active' => $loop->first])
                @endforeach
            </ul>
            <button type="button" class="btn btn-sm btn-outline-primary mb-3" id="add-tab-btn" data-testid="entity-builder-add-tab">{{ t('Aggiungi tab') }}</button>

            <div class="tab-content" id="tabs-content">
                @foreach ($entity->tabs as $tab)
                    @include('admin.entities._tab', ['tabToken' => $tab->id, 'tab' => $tab, 'active' => $loop->first, 'relationTargets' => $relationTargets, 'fieldTypes' => $fieldTypes])
                @endforeach
            </div>
        </fieldset>
    </form>

    <template id="tab-nav-template">
        @include('admin.entities._tab_nav', ['tab' => null, 'tabToken' => '__TAB__', 'active' => false])
    </template>
    <template id="tab-pane-template">
        @include('admin.entities._tab', ['tabToken' => '__TAB__', 'tab' => null, 'active' => false, 'relationTargets' => $relationTargets, 'fieldTypes' => $fieldTypes])
    </template>
    <template id="card-template">
        @include('admin.entities._card', ['tabToken' => '__TAB__', 'cardToken' => '__CARD__', 'card' => null, 'relationTargets' => $relationTargets, 'fieldTypes' => $fieldTypes])
    </template>
    <template id="field-template">
        @include('admin.entities._field', ['tabToken' => '__TAB__', 'cardToken' => '__CARD__', 'fieldToken' => '__FIELD__', 'field' => null, 'relationTargets' => $relationTargets, 'fieldTypes' => $fieldTypes])
    </template>

    {{-- Reused for naming/renaming both tabs and cards --}}
    <div class="modal" id="name-modal" tabindex="-1" data-testid="name-modal" data-tab-label="{{ t('Nome tab') }}" data-card-label="{{ t('Nome card') }}">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="name-modal-title"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="text" class="form-control" id="name-modal-input" data-testid="name-modal-input">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="name-modal-save" data-testid="name-modal-save">{{ t('Salva') }}</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="field-modal" tabindex="-1" data-testid="field-modal" data-field-types="{{ json_encode($fieldTypes) }}">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ t('Campo') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label">{{ t('Nome campo') }}</label>
                        <input type="text" class="form-control" id="field-modal-name" data-testid="field-modal-name">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">{{ t('Nome colonna') }}</label>
                        <input type="text" class="form-control" id="field-modal-column" placeholder="es. cognome" data-testid="field-modal-column">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">{{ t('Tipo') }}</label>
                        <select class="form-select" id="field-modal-type" data-testid="field-modal-type">
                            @foreach ($fieldTypes as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-check">
                            <input type="checkbox" class="form-check-input" id="field-modal-required">
                            <span class="form-check-label">{{ t('Obbligatorio') }}</span>
                        </label>
                    </div>
                    <div class="mb-2 field-modal-options-group d-none">
                        <label class="form-label">{{ t('Opzioni (una per riga, formato chiave:Etichetta)') }}</label>
                        <textarea class="form-control" id="field-modal-options" rows="3"></textarea>
                    </div>
                    <div class="mb-2 field-modal-code-group d-none">
                        <label class="form-label">{{ t('Prefisso') }}</label>
                        <input type="text" class="form-control" id="field-modal-code-prefix" placeholder="es. INV-">
                        <small class="form-hint">{{ t('Il valore generato sarà "prefisso" + numero progressivo, es. INV-1, INV-2...') }}</small>
                    </div>
                    <div class="mb-2 field-modal-relation-group d-none">
                        <label class="form-label">{{ t('Relazione verso') }}</label>
                        <select class="form-select" id="field-modal-relation">
                            <option value="">{{ t('Seleziona...') }}</option>
                            @foreach ($relationTargets as $group => $targets)
                                <optgroup label="{{ $group }}">
                                    @foreach ($targets as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">{{ t('Valore predefinito') }}</label>
                        <input type="text" class="form-control" id="field-modal-default">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="field-modal-save" data-testid="field-modal-save">{{ t('Salva campo') }}</button>
                </div>
            </div>
        </div>
    </div>

    @vite('resources/js/entity-builder.js')
@endsection
