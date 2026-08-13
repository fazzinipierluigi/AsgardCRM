@php
    $column = $field->type->value === 'relation' ? "{$field->column_name}_id" : $field->column_name;
    $value = old($column, $record?->{$column});
    $readonly = $readonly ?? false;
@endphp

@unless ($field->is_hidden)
    @if ($readonly && $field->type->value === 'button')
        {{-- Button fields trigger an action; there's nothing to display in read-only mode. --}}
    @else
        <div class="col-md-{{ $field->width }} mb-3" data-field-wrapper data-column="{{ $column }}">
            @unless ($field->type->value === 'button')
                <label class="form-label">
                    {{ $field->name }}
                    @unless ($readonly)
                        <span class="text-danger @unless ($field->required) d-none @endunless" data-required-marker>*</span>
                    @endunless
                </label>
            @endunless

            @if ($readonly)
                @switch($field->type->value)
                    @case('checkbox')
                        <div>
                            <span class="badge {{ $value ? 'bg-green-lt' : 'bg-red-lt' }}">{{ $value ? t('Sì') : t('No') }}</span>
                        </div>
                        @break

                    @case('select')
                        <div class="form-control-plaintext">{{ $field->options[$value] ?? $value ?: '—' }}</div>
                        @break

                    @case('richtext')
                        <div class="form-control-plaintext">{!! $value !!}</div>
                        @break

                    @case('relation')
                        <div class="form-control-plaintext">{{ $relationOptions[$field->column_name][$value] ?? ($value ? "#{$value}" : '—') }}</div>
                        @break

                    @case('date')
                        <div class="form-control-plaintext">{{ $value ? \Illuminate\Support\Carbon::parse($value)->format('d/m/Y') : '—' }}</div>
                        @break

                    @case('datetime')
                        <div class="form-control-plaintext">{{ $value ? \Illuminate\Support\Carbon::parse($value)->format('d/m/Y H:i') : '—' }}</div>
                        @break

                    @case('color')
                        <div class="d-flex align-items-center gap-2">
                            <span style="display:inline-block;width:1.25rem;height:1.25rem;border-radius:4px;background:{{ $value ?: '#000000' }};border:1px solid var(--tblr-border-color);"></span>
                            <span class="form-control-plaintext">{{ $value ?: '—' }}</span>
                        </div>
                        @break

                    @case('table')
                        <div class="form-control-plaintext">{{ count(json_decode((string) $value, true) ?: []) }} {{ t('righe') }}</div>
                        @break

                    @case('products_block')
                        <div class="form-control-plaintext">{{ count(json_decode((string) $value, true) ?: []) }} {{ t('prodotti') }}</div>
                        @break

                    @default
                        <div class="form-control-plaintext">{{ $value ?: '—' }}</div>
                @endswitch
            @else
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
                        <input type="text" name="{{ $column }}" value="{{ $value }}" class="form-control @error($column) is-invalid @enderror" data-flatpickr-field="date" autocomplete="off">
                        @break

                    @case('time')
                        <input type="time" name="{{ $column }}" value="{{ $value }}" class="form-control @error($column) is-invalid @enderror">
                        @break

                    @case('datetime')
                        <input type="text" name="{{ $column }}" value="{{ $value }}" class="form-control @error($column) is-invalid @enderror" data-flatpickr-field="datetime" autocomplete="off">
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

                    @case('button')
                        @if ($record)
                            <button
                                type="button"
                                class="btn btn-primary"
                                data-entity-button
                                data-mode="{{ $field->options['button_action'] ?? '' }}"
                                data-url="{{ route('entities.fields.trigger', [$entity, $record, $field]) }}"
                                data-js="{{ $field->options['button_javascript'] ?? '' }}"
                                data-testid="entity-button-field-{{ $field->id }}"
                            >{{ $field->name }}</button>
                        @else
                            <button type="button" class="btn btn-primary" disabled title="{{ t('Disponibile dopo il salvataggio del record.') }}">{{ $field->name }}</button>
                        @endif
                        @break

                    @case('table')
                        <div data-table-field data-columns="{{ json_encode($field->options['columns'] ?? []) }}">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-2">
                                    <thead></thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-table-field-add>{{ t('Aggiungi riga') }}</button>
                            <input type="hidden" name="{{ $column }}" data-table-field-input value="{{ $value ?: '[]' }}" class="@error($column) is-invalid @enderror">
                        </div>
                        @break

                    @case('products_block')
                        @php
                            $productOptions = $productsBlockOptions[$field->column_name] ?? [];
                            $extraColumns = $field->options['extra_columns'] ?? [];
                            $totalTarget = $field->options['total_target_column'] ?? null;
                        @endphp
                        <div data-products-block data-products="{{ json_encode($productOptions) }}" data-extra-columns="{{ json_encode($extraColumns) }}" data-total-target="{{ $totalTarget }}" data-name-placeholder="{{ t('Nome prodotto') }}" data-description-placeholder="{{ t('Descrizione') }}">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-2">
                                    <thead>
                                        <tr>
                                            <th style="min-width: 240px">{{ t('Prodotto/Servizio') }}</th>
                                            <th style="width: 100px">{{ t('Quantità') }}</th>
                                            <th style="width: 130px">{{ t('Prezzo unitario') }}</th>
                                            @foreach ($extraColumns as $extraColumn)
                                                <th>{{ $extraColumn['label'] }}</th>
                                            @endforeach
                                            <th style="width: 130px">{{ t('Subtotale') }}</th>
                                            <th style="width: 40px"></th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="{{ 3 + count($extraColumns) }}" class="text-end fw-bold">{{ t('Totale') }}</td>
                                            <td class="fw-bold" data-products-block-total>0.00</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-products-block-add>{{ t('Aggiungi prodotto') }}</button>
                            <input type="hidden" name="{{ $column }}" data-products-block-input value="{{ $value ?: '[]' }}" class="@error($column) is-invalid @enderror">
                        </div>
                        @break
                @endswitch

                @error($column)
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            @endif
        </div>
    @endif
@endunless
