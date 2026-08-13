@extends('layouts.admin')

@section('title', t('Nuovo workflow'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.workflows.index') }}">{{ t('Workflows') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.workflows.create') }}">{{ t('Nuovo workflow') }}</a>
    </li>
@endsection

@section('buttons')
    <button type="submit" form="workflow-form" class="btn btn-primary" data-testid="workflow-submit">
        {{ t('Crea e apri editor') }}
    </button>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.workflows.store') }}" method="POST" id="workflow-form">
                @csrf

                <div class="mb-3">
                    <label for="name" class="form-label">{{ t('Nome') }}</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" data-testid="workflow-name" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">{{ t('Descrizione') }}</label>
                    <textarea id="description" name="description" class="form-control @error('description') is-invalid @enderror" data-testid="workflow-description" rows="3">{{ old('description') }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-check form-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" data-testid="workflow-is-active" {{ old('is_active', true) ? 'checked' : '' }}>
                        <span class="form-check-label">{{ t('Attivo') }}</span>
                    </label>
                </div>
            </form>
        </div>
    </div>
@endsection
