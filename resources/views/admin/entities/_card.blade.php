@php
    $fields = $card?->fields ?? collect();
    $cardLocked = ($entity?->is_installed ?? false) && $card !== null;
@endphp
<div class="col-12 card-item repeatable-row" data-tab-token="{{ $tabToken }}" data-card-token="{{ $cardToken }}">
    <div class="card mb-3">
        <div class="card-header d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <span class="card-drag-handle px-1" style="cursor: move" title="{{ t('Trascina per riordinare') }}" data-testid="card-drag-handle">⠿</span>
                <h3 class="card-title mb-0 card-name-label">{{ $card?->name ?: t('Nuova card') }}</h3>
            </div>
            <div class="btn-list">
                <button type="button" class="btn btn-sm btn-outline-primary add-field-btn" data-testid="add-field-btn">{{ t('Aggiungi campo') }}</button>
                <button type="button" class="btn btn-icon card-rename-btn" title="{{ t('Rinomina') }}" data-testid="card-rename-btn">✎</button>
                @unless ($cardLocked)
                    <button type="button" class="btn btn-icon text-danger remove-row-btn" title="{{ t('Rimuovi card') }}" data-testid="card-remove-btn">✕</button>
                @endunless
            </div>
        </div>
        <div class="card-body">
            <input type="hidden" name="tabs[{{ $tabToken }}][cards][{{ $cardToken }}][name]" class="card-name-input" value="{{ $card?->name }}">

            <div class="fields-container row g-2">
                @foreach ($fields as $field)
                    @include('admin.entities._field', ['tabToken' => $tabToken, 'cardToken' => $cardToken, 'fieldToken' => $field->id, 'field' => $field, 'relationTargets' => $relationTargets, 'fieldTypes' => $fieldTypes, 'entity' => $entity])
                @endforeach
            </div>
        </div>
    </div>
</div>
