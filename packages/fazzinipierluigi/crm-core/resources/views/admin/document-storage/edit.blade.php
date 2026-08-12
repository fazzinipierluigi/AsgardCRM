@extends('layouts.admin')

@section('title', t('Storage documenti'))

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.document-storage.edit') }}">{{ t('Storage documenti') }}</a>
    </li>
@endsection

@section('buttons')
    <button type="submit" form="document-storage-form" class="btn btn-primary" data-testid="document-storage-submit">{{ t('Salva impostazioni') }}</button>
@endsection

@section('content')
    @if (session('status') === 'document-storage-updated')
        <div class="alert alert-success" data-testid="document-storage-updated">{{ t('Impostazioni storage aggiornate.') }}</div>
    @endif

    @php
        // old('config', ...): 'config' is never an actual submitted field
        // name (form submits ftp_host/sftp_host/... individually), so this
        // always falls through to the given default — the real saved
        // config. Do NOT use old(null, ...): a null key makes Laravel's
        // old() ignore the default entirely and return the (empty, on a
        // fresh GET) flashed old-input bucket — every field silently unset.
        $config = old('config', $setting->config ?? []);
        // Form field names for FTP/SFTP are prefixed (ftp_host, sftp_host, ...)
        // to avoid id/name collisions between the two fieldsets on this same
        // page; the stored config itself uses the plain, unprefixed keys.
        $value = function (string $key, mixed $default = null) use ($config) {
            $configKey = preg_replace('/^(ftp|sftp)_/', '', $key);

            return old($key, $config[$configKey] ?? $default);
        };
    @endphp

    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.document-storage.update') }}" method="POST" id="document-storage-form">
                @csrf
                @method('PUT')

                <div class="col-md-4 mb-3">
                    <label for="type" class="form-label">{{ t('Tipo di storage') }}</label>
                    <select id="type" name="type" class="form-select @error('type') is-invalid @enderror" data-testid="document-storage-type-select">
                        @foreach (\App\Enums\DocumentStorageType::options() as $value2 => $label)
                            <option value="{{ $value2 }}" @selected(old('type', $setting->type->value) === $value2)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('type')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="form-hint">{{ t('Dove vengono salvati i file caricati nella sezione Documenti.') }}</small>
                </div>

                <fieldset data-storage-config="s3" class="mb-3">
                    <legend class="fs-4">{{ t('Bucket S3-compatibile') }}</legend>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="key" class="form-label">{{ t('Access key') }}</label>
                            <input type="text" id="key" name="key" value="{{ $value('key') }}" class="form-control @error('key') is-invalid @enderror">
                            @error('key') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="secret" class="form-label">{{ t('Secret key') }}</label>
                            <input type="password" id="secret" name="secret" class="form-control @error('secret') is-invalid @enderror" placeholder="{{ $setting->exists ? t('Lascia vuoto per non modificarla') : '' }}">
                            @error('secret') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="region" class="form-label">{{ t('Regione') }}</label>
                            <input type="text" id="region" name="region" value="{{ $value('region') }}" class="form-control @error('region') is-invalid @enderror" placeholder="eu-west-1">
                            @error('region') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="bucket" class="form-label">{{ t('Bucket') }}</label>
                            <input type="text" id="bucket" name="bucket" value="{{ $value('bucket') }}" class="form-control @error('bucket') is-invalid @enderror">
                            @error('bucket') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="endpoint" class="form-label">{{ t('Endpoint personalizzato') }}</label>
                            <input type="url" id="endpoint" name="endpoint" value="{{ $value('endpoint') }}" class="form-control @error('endpoint') is-invalid @enderror" placeholder="https://s3.esempio.it">
                            @error('endpoint') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <small class="form-hint">{{ t('Solo per provider compatibili S3 diversi da AWS (es. MinIO, Wasabi, R2).') }}</small>
                        </div>
                    </div>

                    <div class="mb-3 form-check form-switch">
                        <input type="checkbox" id="use_path_style_endpoint" name="use_path_style_endpoint" value="1" class="form-check-input" @checked($value('use_path_style_endpoint'))>
                        <label for="use_path_style_endpoint" class="form-check-label">{{ t('Usa path-style endpoint') }}</label>
                    </div>
                </fieldset>

                <fieldset data-storage-config="ftp" class="mb-3">
                    <legend class="fs-4">{{ t('Server FTP') }}</legend>
                    @include('crm::admin.document-storage._ftp_sftp_fields', ['value' => $value, 'isEdit' => $setting->exists, 'showSsl' => true, 'defaultPort' => 21, 'prefix' => 'ftp_'])
                </fieldset>

                <fieldset data-storage-config="sftp" class="mb-3">
                    <legend class="fs-4">{{ t('Server SFTP') }}</legend>
                    @include('crm::admin.document-storage._ftp_sftp_fields', ['value' => $value, 'isEdit' => $setting->exists, 'showSsl' => false, 'defaultPort' => 22, 'prefix' => 'sftp_'])
                </fieldset>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var typeSelect = document.getElementById('type');
            var fieldsets = document.querySelectorAll('[data-storage-config]');

            function syncVisibility() {
                var type = typeSelect.value;
                fieldsets.forEach(function (fieldset) {
                    fieldset.style.display = fieldset.getAttribute('data-storage-config') === type ? '' : 'none';
                });
            }

            typeSelect.addEventListener('change', syncVisibility);
            syncVisibility();
        });
    </script>
@endsection
