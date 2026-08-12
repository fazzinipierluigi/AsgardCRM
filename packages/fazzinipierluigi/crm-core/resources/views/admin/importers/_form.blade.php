@php
    $isEdit = $importer !== null;
    $config = $importer?->config ?? [];
    $value = fn (string $key, mixed $default = null) => old($key, $config[$key] ?? $default);
    $fieldMappingJson = old('field_mapping_json', $importer !== null ? json_encode($importer->field_mapping ?? []) : '{}');
    $uniqueKeyField = old('unique_key_field', $importer?->unique_key_field);
@endphp

<script type="application/json" id="importer-entity-fields-data">{!! json_encode($entityFields) !!}</script>
<script type="application/json" id="importer-cron-presets-data">{!! json_encode($cronPresets) !!}</script>
<script>
    window.IMPORTER_NOT_MAPPED_LABEL = @json(t('Non mappato'));
    window.IMPORTER_PREVIEW_ERROR_LABEL = @json(t('Anteprima non disponibile.'));
</script>

<div class="steps mb-4" id="importer-wizard-steps">
    <span class="step-item" data-step-indicator="1">{{ t('Anagrafica') }}</span>
    <span class="step-item" data-step-indicator="2">{{ t('Canale') }}</span>
    <span class="step-item" data-step-indicator="3">{{ t('Connessione') }}</span>
    <span class="step-item" data-step-indicator="4">{{ t('Mappatura campi') }}</span>
    <span class="step-item" data-step-indicator="5">{{ t('Programmazione') }}</span>
</div>

{{-- Step 1: anagrafica --}}
<div class="importer-step" data-step="1">
    <div class="mb-3">
        <label for="title" class="form-label">{{ t('Titolo') }}</label>
        <input type="text" id="title" name="title" value="{{ old('title', $importer?->title) }}" class="form-control @error('title') is-invalid @enderror" required>
        @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="mb-3">
        <label for="description" class="form-label">{{ t('Descrizione') }}</label>
        <textarea id="description" name="description" rows="3" class="form-control @error('description') is-invalid @enderror">{{ old('description', $importer?->description) }}</textarea>
        @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="mb-3">
        <label for="entity_id" class="form-label">{{ t('Entità di destinazione') }}</label>
        <select id="entity_id" name="entity_id" class="form-select @error('entity_id') is-invalid @enderror" data-testid="importer-entity-select" @if ($isEdit) disabled @else required @endif>
            <option value=""></option>
            @foreach ($entities as $entity)
                <option value="{{ $entity->id }}" @selected(old('entity_id', $importer?->entity_id) == $entity->id)>{{ $entity->name }}</option>
            @endforeach
        </select>
        @if ($isEdit)
            <input type="hidden" name="entity_id" value="{{ $importer->entity_id }}">
            <small class="form-hint">{{ t("L'entità di destinazione non può essere modificata dopo la creazione.") }}</small>
        @endif
        @error('entity_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

{{-- Step 2: canale --}}
<div class="importer-step d-none" data-step="2">
    <div class="mb-3">
        <label for="channel" class="form-label">{{ t('Canale di importazione') }}</label>
        <select id="channel" name="channel" class="form-select @error('channel') is-invalid @enderror" data-testid="importer-channel-select" @if ($isEdit) disabled @else required @endif>
            @foreach ($channels as $channelValue => $channelLabel)
                <option value="{{ $channelValue }}" @selected(old('channel', $importer?->channel?->value) === $channelValue)>{{ $channelLabel }}</option>
            @endforeach
        </select>
        @if ($isEdit)
            <input type="hidden" name="channel" value="{{ $importer->channel->value }}">
            <small class="form-hint">{{ t('Il canale non può essere modificato dopo la creazione.') }}</small>
        @endif
        @error('channel') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

{{-- Step 3: configurazione connessione --}}
<div class="importer-step d-none" data-step="3">
    <fieldset data-importer-channel="database" class="mb-3">
        <legend class="fs-4">{{ t('Connessione Database') }}</legend>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label for="driver" class="form-label">{{ t('Driver') }}</label>
                <select id="driver" name="driver" class="form-select @error('driver') is-invalid @enderror">
                    <option value="mysql" @selected($value('driver', 'mysql') === 'mysql')>MySQL / MariaDB</option>
                    <option value="pgsql" @selected($value('driver') === 'pgsql')>PostgreSQL</option>
                    <option value="sqlsrv" @selected($value('driver') === 'sqlsrv')>SQL Server</option>
                </select>
                @error('driver') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-5 mb-3">
                <label for="host" class="form-label">{{ t('Host') }}</label>
                <input type="text" id="host" name="host" value="{{ $value('host') }}" class="form-control @error('host') is-invalid @enderror">
                @error('host') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3 mb-3">
                <label for="port" class="form-label">{{ t('Porta') }}</label>
                <input type="number" id="port" name="port" value="{{ $value('port') }}" class="form-control @error('port') is-invalid @enderror">
                @error('port') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label for="database" class="form-label">{{ t('Nome database') }}</label>
                <input type="text" id="database" name="database" value="{{ $value('database') }}" class="form-control @error('database') is-invalid @enderror">
                @error('database') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4 mb-3">
                <label for="username" class="form-label">{{ t('Username') }}</label>
                <input type="text" id="username" name="username" value="{{ $value('username') }}" class="form-control @error('username') is-invalid @enderror">
                @error('username') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4 mb-3">
                <label for="password" class="form-label">{{ t('Password') }}</label>
                <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="{{ $isEdit ? t('Lascia vuoto per non modificarla') : '' }}">
                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="mb-3">
            <label for="query" class="form-label">{{ t('Query da eseguire') }}</label>
            <textarea id="query" name="query" rows="4" class="form-control font-monospace @error('query') is-invalid @enderror">{{ $value('query') }}</textarea>
            @error('query') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </fieldset>

    <fieldset data-importer-channel="rest_api" class="mb-3">
        <legend class="fs-4">{{ t('Connessione API REST') }}</legend>

        <div class="row">
            <div class="col-md-3 mb-3">
                <label for="method" class="form-label">{{ t('Metodo') }}</label>
                <select id="method" name="method" class="form-select @error('method') is-invalid @enderror">
                    @foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $methodValue)
                        <option value="{{ $methodValue }}" @selected($value('method', 'GET') === $methodValue)>{{ $methodValue }}</option>
                    @endforeach
                </select>
                @error('method') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-9 mb-3">
                <label for="endpoint" class="form-label">{{ t('Endpoint') }}</label>
                <input type="url" id="endpoint" name="endpoint" value="{{ $value('endpoint') }}" class="form-control @error('endpoint') is-invalid @enderror" placeholder="https://api.example.com/records">
                @error('endpoint') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="mb-3">
            <label for="auth_type" class="form-label">{{ t('Autenticazione') }}</label>
            <select id="auth_type" name="auth_type" class="form-select @error('auth_type') is-invalid @enderror" data-testid="importer-auth-type-select">
                <option value="none" @selected($value('auth_type', 'none') === 'none')>{{ t('Nessuna') }}</option>
                <option value="basic" @selected($value('auth_type') === 'basic')>{{ t('Basic Auth') }}</option>
                <option value="bearer" @selected($value('auth_type') === 'bearer')>{{ t('Bearer token') }}</option>
                <option value="api_key" @selected($value('auth_type') === 'api_key')>{{ t('API key (header)') }}</option>
            </select>
            @error('auth_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div data-auth-fields="basic" class="row">
            <div class="col-md-6 mb-3">
                <label for="auth_username" class="form-label">{{ t('Username') }}</label>
                <input type="text" id="auth_username" name="auth_username" value="{{ $value('auth_username') }}" class="form-control @error('auth_username') is-invalid @enderror">
                @error('auth_username') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6 mb-3">
                <label for="auth_password" class="form-label">{{ t('Password') }}</label>
                <input type="password" id="auth_password" name="auth_password" class="form-control @error('auth_password') is-invalid @enderror" placeholder="{{ $isEdit ? t('Lascia vuoto per non modificarla') : '' }}">
                @error('auth_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <div data-auth-fields="bearer" class="mb-3">
            <label for="auth_token" class="form-label">{{ t('Token') }}</label>
            <input type="password" id="auth_token" name="auth_token" class="form-control @error('auth_token') is-invalid @enderror" placeholder="{{ $isEdit ? t('Lascia vuoto per non modificarlo') : '' }}">
            @error('auth_token') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div data-auth-fields="api_key" class="row">
            <div class="col-md-6 mb-3">
                <label for="auth_api_key_name" class="form-label">{{ t('Nome header') }}</label>
                <input type="text" id="auth_api_key_name" name="auth_api_key_name" value="{{ $value('auth_api_key_name', 'X-Api-Key') }}" class="form-control @error('auth_api_key_name') is-invalid @enderror">
                @error('auth_api_key_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6 mb-3">
                <label for="auth_api_key_value" class="form-label">{{ t('Valore') }}</label>
                <input type="password" id="auth_api_key_value" name="auth_api_key_value" class="form-control @error('auth_api_key_value') is-invalid @enderror" placeholder="{{ $isEdit ? t('Lascia vuoto per non modificarlo') : '' }}">
                @error('auth_api_key_value') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="mb-3">
            <label for="params_json" class="form-label">{{ t('Parametri della chiamata (JSON)') }}</label>
            <textarea id="params_json" name="params_json" rows="3" class="form-control font-monospace @error('params_json') is-invalid @enderror" placeholder='{"key": "value"}'>{{ $value('params_json') }}</textarea>
            <small class="form-hint">{{ t('Inviati come query string per GET/DELETE, come body JSON per gli altri metodi.') }}</small>
            @error('params_json') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </fieldset>

    <fieldset data-importer-channel="csv" class="mb-3">
        <legend class="fs-4">{{ t('Sorgente CSV') }}</legend>

        <div class="mb-3">
            <label for="path_or_url_csv" class="form-label">{{ t('Path assoluto o URL') }}</label>
            <input type="text" id="path_or_url_csv" data-path-or-url name="path_or_url" value="{{ $value('path_or_url') }}" class="form-control @error('path_or_url') is-invalid @enderror" placeholder="/var/data/import.csv oppure https://example.com/import.csv">
            @error('path_or_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label for="delimiter" class="form-label">{{ t('Delimitatore') }}</label>
                <select id="delimiter" name="delimiter" class="form-select @error('delimiter') is-invalid @enderror">
                    <option value="," @selected($value('delimiter', ',') === ',')>{{ t('Virgola (,)') }}</option>
                    <option value=";" @selected($value('delimiter') === ';')>{{ t('Punto e virgola (;)') }}</option>
                    <option value="{{ "\t" }}" @selected($value('delimiter') === "\t")>{{ t('Tabulazione') }}</option>
                </select>
                @error('delimiter') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-8 mb-3 d-flex align-items-end">
                <div class="form-check form-switch">
                    <input type="checkbox" id="has_header" name="has_header" value="1" class="form-check-input" @checked($value('has_header', true))>
                    <label for="has_header" class="form-check-label">{{ t('La prima riga contiene le intestazioni') }}</label>
                </div>
            </div>
        </div>
    </fieldset>

    <fieldset data-importer-channel="json" class="mb-3">
        <legend class="fs-4">{{ t('Sorgente JSON') }}</legend>

        <div class="mb-3">
            <label for="path_or_url_json" class="form-label">{{ t('Path assoluto o URL') }}</label>
            <input type="text" id="path_or_url_json" data-path-or-url name="path_or_url" value="{{ $value('path_or_url') }}" class="form-control @error('path_or_url') is-invalid @enderror" placeholder="/var/data/import.json oppure https://example.com/import.json">
            @error('path_or_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </fieldset>
</div>

{{-- Step 4: mappatura campi --}}
<div class="importer-step d-none" data-step="4">
    <div class="mb-3 d-flex justify-content-between align-items-center">
        <p class="text-secondary mb-0">{{ t("Recupera un'anteprima della sorgente e mappa ogni campo sul campo corrispondente dell'entità di destinazione. Puoi opzionalmente indicare un campo come chiave univoca: se lo fai, ogni riga aggiornerà il record esistente invece di crearne uno nuovo.") }}</p>
        <button type="button" id="importer-preview-btn" class="btn btn-outline-primary text-nowrap ms-3" data-testid="importer-preview-btn">{{ t('Recupera anteprima') }}</button>
    </div>

    <div id="importer-preview-error" class="alert alert-danger d-none" data-testid="importer-preview-error"></div>

    <div id="importer-mapping-empty" class="text-secondary">{{ t("Nessuna anteprima ancora recuperata.") }}</div>

    <div id="importer-mapping-wrapper" class="table-responsive d-none">
        <table class="table table-vcenter">
            <thead>
                <tr>
                    <th>{{ t('Campo sorgente') }}</th>
                    <th>{{ t('Valore di esempio') }}</th>
                    <th>{{ t('Campo entità') }}</th>
                    <th class="text-center">{{ t('Chiave univoca') }}</th>
                </tr>
            </thead>
            <tbody id="importer-mapping-body"></tbody>
        </table>
    </div>

    <input type="hidden" id="field_mapping_json" name="field_mapping_json" value="{{ $fieldMappingJson }}">
    <input type="hidden" id="unique_key_field" name="unique_key_field" value="{{ $uniqueKeyField }}">
    @error('field_mapping_json') <div class="text-danger small">{{ $message }}</div> @enderror
    @error('unique_key_field') <div class="text-danger small">{{ $message }}</div> @enderror
</div>

<template id="importer-mapping-row-template">
    <tr>
        <td class="source-field fw-medium"></td>
        <td class="sample-value text-secondary"></td>
        <td>
            <select class="form-select mapping-target" data-tom-select-manual></select>
        </td>
        <td class="text-center">
            <input type="radio" name="importer_unique_key_radio" class="form-check-input unique-key-radio">
        </td>
    </tr>
</template>

{{-- Step 5: programmazione e avvio --}}
<div class="importer-step d-none" data-step="5">
    <div class="mb-3">
        <label for="schedule_type" class="form-label">{{ t('Modalità di avvio') }}</label>
        <select id="schedule_type" name="schedule_type" class="form-select @error('schedule_type') is-invalid @enderror" data-testid="importer-schedule-type-select">
            @foreach ($scheduleTypes as $scheduleValue => $scheduleLabel)
                <option value="{{ $scheduleValue }}" @selected(old('schedule_type', $importer?->schedule_type?->value ?? 'manual') === $scheduleValue)>{{ $scheduleLabel }}</option>
            @endforeach
        </select>
        @error('schedule_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div data-schedule-cron class="row">
        <div class="col-md-6 mb-3">
            <label for="cron_preset" class="form-label">{{ t('Cadenza') }}</label>
            <select id="cron_preset" class="form-select">
                <option value="">{{ t('Personalizzata') }}</option>
                @foreach ($cronPresets as $presetLabel => $presetExpression)
                    <option value="{{ $presetExpression }}">{{ $presetLabel }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6 mb-3">
            <label for="cron_expression" class="form-label">{{ t('Espressione cron') }}</label>
            <input type="text" id="cron_expression" name="cron_expression" value="{{ old('cron_expression', $importer?->cron_expression) }}" class="form-control font-monospace @error('cron_expression') is-invalid @enderror" placeholder="* * * * *">
            @error('cron_expression') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="mb-3 form-check form-switch">
        <input type="checkbox" id="is_active" name="is_active" value="1" class="form-check-input" @checked(old('is_active', $importer?->is_active ?? true))>
        <label for="is_active" class="form-check-label">{{ t('Attivo') }}</label>
    </div>
</div>
