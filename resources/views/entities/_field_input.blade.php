@php
    $column = $field->type->value === 'relation' ? "{$field->column_name}_id" : $field->column_name;
    $value = old($column, $record?->{$column});
@endphp

<div class="col-md-{{ $field->width }} mb-3">
    <label class="form-label">
        {{ $field->name }}
        @if ($field->required)
            <span class="text-danger">*</span>
        @endif
    </label>

    @switch($field->type->value)
        @case('checkbox')
            <div>
                <label class="form-check">
                    <input type="hidden" name="{{ $column }}" value="0">
                    <input type="checkbox" class="form-check-input" name="{{ $column }}" value="1" @checked((bool) $value)>
                </label>
            </div>
            @break

        @case('string')
            <input type="text" name="{{ $column }}" value="{{ $value }}" class="form-control @error($column) is-invalid @enderror">
            @break

        @case('select')
            <select name="{{ $column }}" class="form-select @error($column) is-invalid @enderror">
                <option value="">{{ t('Seleziona...') }}</option>
                @foreach ($field->options ?? [] as $optionValue => $optionLabel)
                    <option value="{{ $optionValue }}" @selected($value === $optionValue)>{{ $optionLabel }}</option>
                @endforeach
            </select>
            @break

        @case('integer')
            <input type="number" step="1" name="{{ $column }}" value="{{ $value }}" class="form-control @error($column) is-invalid @enderror">
            @break

        @case('decimal')
            <input type="number" step="any" name="{{ $column }}" value="{{ $value }}" class="form-control @error($column) is-invalid @enderror">
            @break

        @case('textarea')
            <textarea name="{{ $column }}" class="form-control @error($column) is-invalid @enderror" rows="3">{{ $value }}</textarea>
            @break

        @case('richtext')
            <input type="hidden" name="{{ $column }}" class="rich-text-input" value="{{ $value }}">
            <div class="btn-list mb-1">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-command="bold"><b>B</b></button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-command="italic"><i>I</i></button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-command="underline"><u>U</u></button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-command="insertUnorderedList">{{ t('Elenco') }}</button>
            </div>
            <div class="form-control rich-text-editor @error($column) is-invalid @enderror" contenteditable="true" style="min-height: 120px;">{!! $value !!}</div>
            @break

        @case('relation')
            <select name="{{ $column }}" class="form-select @error($column) is-invalid @enderror">
                <option value="">{{ t('Seleziona...') }}</option>
                @foreach ($relationOptions[$field->column_name] ?? [] as $optionValue => $optionLabel)
                    <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
                @endforeach
            </select>
            @break

        @case('date')
            <input type="date" name="{{ $column }}" value="{{ $value }}" class="form-control @error($column) is-invalid @enderror">
            @break

        @case('time')
            <input type="time" name="{{ $column }}" value="{{ $value }}" class="form-control @error($column) is-invalid @enderror">
            @break

        @case('datetime')
            <input type="datetime-local" name="{{ $column }}" value="{{ $value }}" class="form-control @error($column) is-invalid @enderror">
            @break

        @case('color')
            <input type="color" name="{{ $column }}" value="{{ $value ?: '#000000' }}" class="form-control form-control-color @error($column) is-invalid @enderror">
            @break

        @case('code')
            @if ($record)
                <input type="text" value="{{ $value }}" class="form-control" readonly>
            @else
                <div class="form-control-plaintext text-muted fst-italic">{{ t('Verrà generato automaticamente al salvataggio.') }}</div>
            @endif
            @break
    @endswitch

    @error($column)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
