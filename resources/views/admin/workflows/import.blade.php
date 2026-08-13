@extends('layouts.admin')

@section('title', t('Importa workflow'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.workflows.index') }}">{{ t('Workflows') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.workflows.import.form') }}">{{ t('Importa workflow') }}</a>
    </li>
@endsection

@section('buttons')
    <button type="submit" form="workflow-import-form" class="btn btn-primary" data-testid="workflow-import-submit">{{ t('Importa') }}</button>
@endsection

@section('content')
    @if (session('error'))
        <div class="alert alert-danger" data-testid="workflows-error">{{ session('error') }}</div>
    @endif

    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.workflows.import') }}" method="POST" enctype="multipart/form-data" id="workflow-import-form">
                @csrf

                <div class="mb-3">
                    <label for="file" class="form-label">{{ t('File workflow (JSON)') }}</label>
                    <input type="file" id="file" name="file" accept="application/json" class="form-control @error('file') is-invalid @enderror" data-testid="workflow-import-file">
                    @error('file')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </form>
        </div>
    </div>
@endsection
