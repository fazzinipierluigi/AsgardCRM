@php
    $config = $connection?->config ?? [];
    $value = fn (string $key, mixed $default = null) => old($key, $config[$key] ?? $default);
    $isEdit = $connection !== null;
@endphp

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="name" class="form-label">{{ t('Nome') }}</label>
        <input type="text" id="name" name="name" value="{{ old('name', $connection?->name) }}" class="form-control @error('name') is-invalid @enderror">
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="workflow_id" class="form-label">{{ t('Workflow (vuoto = globale, usabile da tutti)') }}</label>
        <select id="workflow_id" name="workflow_id" class="form-select @error('workflow_id') is-invalid @enderror">
            <option value="">{{ t('— Globale —') }}</option>
            @foreach ($workflows as $workflow)
                <option value="{{ $workflow->id }}" @selected((string) old('workflow_id', $connection?->workflow_id) === (string) $workflow->id)>{{ $workflow->name }}</option>
            @endforeach
        </select>
        @error('workflow_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="row">
    <div class="col-md-3 mb-3">
        <label for="driver" class="form-label">{{ t('Driver') }}</label>
        <select id="driver" name="driver" class="form-select @error('driver') is-invalid @enderror">
            @foreach (['mysql' => 'MySQL/MariaDB', 'pgsql' => 'PostgreSQL', 'sqlsrv' => 'SQL Server', 'sqlite' => 'SQLite'] as $key => $label)
                <option value="{{ $key }}" @selected($value('driver', 'mysql') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @error('driver') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="database" class="form-label">{{ t('Database (o percorso file per SQLite)') }}</label>
        <input type="text" id="database" name="database" value="{{ $value('database') }}" class="form-control @error('database') is-invalid @enderror">
        @error('database') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3 mb-3">
        <label for="port" class="form-label">{{ t('Porta') }}</label>
        <input type="number" id="port" name="port" value="{{ $value('port') }}" class="form-control @error('port') is-invalid @enderror">
        @error('port') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="host" class="form-label">{{ t('Host') }}</label>
        <input type="text" id="host" name="host" value="{{ $value('host') }}" class="form-control @error('host') is-invalid @enderror">
        @error('host') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3 mb-3">
        <label for="username" class="form-label">{{ t('Username') }}</label>
        <input type="text" id="username" name="username" value="{{ $value('username') }}" class="form-control @error('username') is-invalid @enderror">
        @error('username') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3 mb-3">
        <label for="password" class="form-label">{{ t('Password') }}</label>
        <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="{{ $isEdit ? t('Lascia vuoto per non modificarla') : '' }}">
        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>
