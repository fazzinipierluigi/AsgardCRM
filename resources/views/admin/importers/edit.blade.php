@extends('layouts.admin')

@section('title', t('Modifica importatore'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.importers.index') }}">{{ t('Importatori') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.importers.edit', $importer) }}">{{ $importer->title }}</a>
    </li>
@endsection

@section('buttons')
    <button type="button" id="importer-wizard-prev" class="btn btn-outline-secondary" data-testid="importer-wizard-prev">{{ t('Indietro') }}</button>
    <button type="button" id="importer-wizard-next" class="btn btn-primary" data-testid="importer-wizard-next">{{ t('Avanti') }}</button>
    <button type="submit" form="importer-form" id="importer-wizard-submit" class="btn btn-primary d-none" data-testid="importer-submit">{{ t('Salva modifiche') }}</button>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.importers.update', $importer) }}" method="POST" id="importer-form" data-preview-url="{{ route('admin.importers.preview') }}">
                @csrf
                @method('PUT')
                @include('admin.importers._form', ['importer' => $importer])
            </form>
        </div>
    </div>
@endsection

@vite('resources/js/importer-wizard.js')
