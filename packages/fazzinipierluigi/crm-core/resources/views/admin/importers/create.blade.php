@extends('layouts.admin')

@section('title', t('Nuovo importatore'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.importers.index') }}">{{ t('Importatori') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.importers.create') }}">{{ t('Nuovo importatore') }}</a>
    </li>
@endsection

@section('buttons')
    <button type="button" id="importer-wizard-prev" class="btn btn-outline-secondary" data-testid="importer-wizard-prev">{{ t('Indietro') }}</button>
    <button type="button" id="importer-wizard-next" class="btn btn-primary" data-testid="importer-wizard-next">{{ t('Avanti') }}</button>
    <button type="submit" form="importer-form" id="importer-wizard-submit" class="btn btn-primary d-none" data-testid="importer-submit">{{ t('Crea importatore') }}</button>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.importers.store') }}" method="POST" id="importer-form" data-preview-url="{{ route('admin.importers.preview') }}">
                @csrf
                @include('crm::admin.importers._form', ['importer' => null])
            </form>
        </div>
    </div>
@endsection

@vite('resources/js/importer-wizard.js')
