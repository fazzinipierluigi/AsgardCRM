@extends('layouts.admin')

@section('title', t('Importa entità'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.entities.index') }}">{{ t('Entità') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.entities.import.form') }}">{{ t('Importa entità') }}</a>
    </li>
@endsection

@section('buttons')
    <button type="submit" form="entity-import-form" class="btn btn-primary" data-testid="entity-import-submit">{{ t('Importa') }}</button>
@endsection

@section('content')
    @if (session('error'))
        <div class="alert alert-danger" data-testid="entities-error">{{ session('error') }}</div>
    @endif

    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.entities.import') }}" method="POST" enctype="multipart/form-data" id="entity-import-form">
                @csrf

                <div class="mb-3">
                    <label for="file" class="form-label">{{ t('File schema (JSON)') }}</label>
                    <input type="file" id="file" name="file" accept="application/json" class="form-control @error('file') is-invalid @enderror" data-testid="entity-import-file">
                    @error('file')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </form>
        </div>
    </div>
@endsection
