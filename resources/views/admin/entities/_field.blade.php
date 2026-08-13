@php
    $namePrefix = "tabs[{$tabToken}][cards][{$cardToken}][fields][{$fieldToken}]";
    $type = $field?->type?->value ?? 'string';
    $optionsText = $field && $field->options && $type === 'select' ? collect($field->options)->map(fn ($label, $key) => "{$key}:{$label}")->implode("\n") : '';
    $relationValue = $field && $field->relation_target_type ? "{$field->relation_target_type->value}:{$field->relation_target}" : '';
    $codePrefix = $field && $field->options && $type === 'code' ? ($field->options['prefix'] ?? '') : '';
    $buttonAction = $field && $field->options && $type === 'button' ? ($field->options['button_action'] ?? '') : '';
    $buttonWorkflowId = $field && $field->options && $type === 'button' ? ($field->options['button_workflow_id'] ?? '') : '';
    $buttonImporterIds = $field && $field->options && $type === 'button' ? implode(',', $field->options['button_importer_ids'] ?? []) : '';
    $buttonJavascript = $field && $field->options && $type === 'button' ? ($field->options['button_javascript'] ?? '') : '';
    $tableColumnsText = $field && $field->options && $type === 'table'
        ? collect($field->options['columns'] ?? [])->map(fn ($c) => "{$c['name']}:{$c['label']}:{$c['type']}:".($c['required'] ? 'si' : 'no'))->implode("\n")
        : '';
    $productsCatalog = $field && $field->options && $type === 'products_block' ? ($field->options['catalog_entity_slug'] ?? '') : '';
    $productsPriceColumn = $field && $field->options && $type === 'products_block' ? ($field->options['price_column'] ?? '') : '';
    $productsExtraColumnsText = $field && $field->options && $type === 'products_block'
        ? collect($field->options['extra_columns'] ?? [])->map(fn ($c) => "{$c['name']}:{$c['label']}:{$c['type']}:".($c['required'] ? 'si' : 'no'))->implode("\n")
        : '';
    $productsTotalTarget = $field && $field->options && $type === 'products_block' ? ($field->options['total_target_column'] ?? '') : '';
    $width = $field?->width ?? 12;
@endphp
<div class="field-item repeatable-row col-md-{{ $width }}" data-width="{{ $width }}" data-tab-token="{{ $tabToken }}" data-card-token="{{ $cardToken }}" data-field-token="{{ $fieldToken }}">
    <input type="hidden" name="{{ $namePrefix }}[name]" class="field-name-input" value="{{ $field?->name }}">
    <input type="hidden" name="{{ $namePrefix }}[column_name]" class="field-column-input" value="{{ $field?->column_name }}">
    <input type="hidden" name="{{ $namePrefix }}[type]" class="field-type-input" value="{{ $type }}">
    <input type="hidden" name="{{ $namePrefix }}[required]" class="field-required-input" value="{{ $field?->required ? 1 : 0 }}">
    <input type="hidden" name="{{ $namePrefix }}[options]" class="field-options-input" value="{{ $optionsText }}">
    <input type="hidden" name="{{ $namePrefix }}[code_prefix]" class="field-codeprefix-input" value="{{ $codePrefix }}">
    <input type="hidden" name="{{ $namePrefix }}[relation_target]" class="field-relationtarget-input" value="{{ $relationValue }}">
    <input type="hidden" name="{{ $namePrefix }}[button_action]" class="field-buttonaction-input" value="{{ $buttonAction }}">
    <input type="hidden" name="{{ $namePrefix }}[button_workflow_id]" class="field-buttonworkflowid-input" value="{{ $buttonWorkflowId }}">
    <input type="hidden" name="{{ $namePrefix }}[button_importer_ids]" class="field-buttonimporterids-input" value="{{ $buttonImporterIds }}">
    <input type="hidden" name="{{ $namePrefix }}[button_javascript]" class="field-buttonjavascript-input" value="{{ $buttonJavascript }}">
    <input type="hidden" name="{{ $namePrefix }}[table_columns]" class="field-tablecolumns-input" value="{{ $tableColumnsText }}">
    <input type="hidden" name="{{ $namePrefix }}[products_catalog]" class="field-productscatalog-input" value="{{ $productsCatalog }}">
    <input type="hidden" name="{{ $namePrefix }}[products_price_column]" class="field-productspricecolumn-input" value="{{ $productsPriceColumn }}">
    <input type="hidden" name="{{ $namePrefix }}[products_extra_columns]" class="field-productsextracolumns-input" value="{{ $productsExtraColumnsText }}">
    <input type="hidden" name="{{ $namePrefix }}[products_total_target]" class="field-productstotaltarget-input" value="{{ $productsTotalTarget }}">
    <input type="hidden" name="{{ $namePrefix }}[default_value]" class="field-defaultvalue-input" value="{{ $field?->default_value }}">
    <input type="hidden" name="{{ $namePrefix }}[width]" class="field-width-input" value="{{ $width }}">

    <div class="field-preview border rounded mb-2 position-relative d-flex align-items-center justify-content-center text-center" style="cursor: pointer; min-height: 34px; padding: 4px 22px;" title="{{ t('Doppio click per modificare') }}" data-testid="field-preview">
        <span class="field-drag-handle position-absolute" style="top: 2px; left: 4px; cursor: move; font-size: .75rem; color: var(--tblr-secondary-color, #6c7a91);" title="{{ t('Trascina per riordinare') }}" data-testid="field-drag-handle">⠿</span>
        @unless ($field?->is_locked)
            <span class="remove-row-btn position-absolute text-danger" style="top: 2px; right: 16px; cursor: pointer; font-size: .75rem;" title="{{ t('Rimuovi campo') }}" data-testid="field-remove-btn">✕</span>
        @endunless

        <div class="lh-1">
            <div class="fw-bold small field-preview-name" data-testid="field-preview-name">
                {{ $field?->name ?: t('Nuovo campo') }}
                @if ($field?->is_locked)
                    <span title="{{ t('Campo bloccato') }}" data-testid="field-locked-badge">🔒</span>
                @endif
            </div>
            <div class="text-muted field-preview-type" style="font-size: .7rem;">{{ $fieldTypes[$type] ?? '' }}</div>
        </div>

        <div class="field-resize-handle" title="{{ t('Trascina per ridimensionare') }}" data-testid="field-resize-handle"></div>
    </div>
</div>
