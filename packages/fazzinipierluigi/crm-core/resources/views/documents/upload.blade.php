@php
    $isEdit = $record !== null;
@endphp
@extends('layouts.base')

@section('title', $isEdit ? t('Modifica documento') : t('Carica documento'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('documents.index') }}">{{ t('Documenti') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        {{ $isEdit ? t('Modifica documento') : t('Carica documento') }}
    </li>
@endsection

@section('buttons')
    <button type="submit" form="document-form" class="btn btn-primary" data-testid="document-form-submit">{{ t('Salva') }}</button>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form
                action="{{ $isEdit ? route('documents.update', $record) : route('documents.store') }}"
                method="POST"
                enctype="multipart/form-data"
                id="document-form"
            >
                @csrf
                @if ($isEdit)
                    @method('PUT')
                @endif

                <div class="mb-3">
                    <label class="form-label">
                        {{ t('File') }}
                        @unless ($isEdit)
                            <span class="text-danger">*</span>
                        @endunless
                    </label>
                    <input type="file" name="file" class="form-control @error('file') is-invalid @enderror" data-testid="document-file-input" @if (! $isEdit) required @endif>
                    @if ($isEdit)
                        <small class="form-hint">{{ t('Lascia vuoto per mantenere il file attuale (:name).', ['name' => $record->original_filename]) }}</small>
                    @endif
                    @error('file')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">
                        {{ t('Nome') }}
                        <span class="text-danger">*</span>
                    </label>
                    <input type="text" name="nome" value="{{ old('nome', $record?->nome) }}" class="form-control @error('nome') is-invalid @enderror" data-testid="document-nome-input">
                    @error('nome')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">{{ t('Descrizione') }}</label>
                    <textarea name="descrizione" class="form-control @error('descrizione') is-invalid @enderror" rows="3">{{ old('descrizione', $record?->descrizione) }}</textarea>
                    @error('descrizione')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">{{ t('Cartella') }}</label>
                    <select name="folder_id" class="form-select @error('folder_id') is-invalid @enderror" data-testid="document-folder-select">
                        <option value="">{{ t('Radice') }}</option>
                        @foreach ($folderOptions as $optionId => $optionLabel)
                            <option value="{{ $optionId }}" @selected((string) old('folder_id', $folderId) === (string) $optionId)>{{ $optionLabel }}</option>
                        @endforeach
                    </select>
                    @error('folder_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var fileInput = document.querySelector('[data-testid="document-file-input"]');
            var nomeInput = document.querySelector('[data-testid="document-nome-input"]');

            fileInput.addEventListener('change', function () {
                if (!nomeInput.value && fileInput.files.length) {
                    nomeInput.value = fileInput.files[0].name.replace(/\.[^.]+$/, '');
                }
            });
        });
    </script>
@endsection
